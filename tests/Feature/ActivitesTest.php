<?php

namespace Tests\Feature;

use App\Models\Activite;
use App\Models\Departement;
use App\Models\Faculte;
use App\Models\Promotion;
use App\Models\User;
use App\Services\GestionDesActivites;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\ConstruitUnContexteAcademique;
use Tests\TestCase;

/**
 * Les activités et leur portée.
 *
 * La règle qui structure tout : c'est la portée, et non le créateur, qui
 * décide qui voit une activité — et chacun n'annonce qu'aussi loin que son
 * mandat le permet.
 */
class ActivitesTest extends TestCase
{
    use ConstruitUnContexteAcademique, RefreshDatabase;

    private GestionDesActivites $gestion;

    private User $vde;

    private User $doyen;

    protected function setUp(): void
    {
        parent::setUp();

        $this->construireContexte();
        $this->gestion = app(GestionDesActivites::class);

        $this->vde = $this->creerUtilisateur('VDE-ACT', User::ROLE_VDE);
        $this->doyen = $this->creerUtilisateur('DF-ACT', User::ROLE_DF);
        $this->doyen->update(['faculte_id' => $this->faculte->id]);
    }

    public function test_le_chef_de_promotion_annonce_pour_sa_promotion(): void
    {
        $activite = $this->gestion->creer($this->chef, $this->donnees([
            'portee' => Activite::PORTEE_PROMOTION,
        ]));

        $this->assertSame($this->promotion->id, $activite->promotion_id);
        $this->assertSame('planifiee', $activite->statut);
    }

    /**
     * Un chef de promotion ne choisit pas sa promotion : c'est la sienne.
     * Sans cette règle, il pourrait annoncer un examen à la promotion voisine.
     */
    public function test_le_chef_ne_peut_pas_viser_une_autre_promotion(): void
    {
        $voisine = Promotion::create([
            'departement_id' => $this->promotion->departement_id,
            'annee_academique_id' => $this->annee->id,
            'niveau' => 'L2',
            'intitule' => 'Deuxième année',
            'active' => true,
        ]);

        $activite = $this->gestion->creer($this->chef, $this->donnees([
            'portee' => Activite::PORTEE_PROMOTION,
            'promotion_id' => $voisine->id,
        ]));

        $this->assertSame($this->promotion->id, $activite->promotion_id);
    }

    public function test_le_chef_ne_peut_pas_annoncer_a_toute_la_faculte(): void
    {
        $this->expectException(ValidationException::class);

        $this->gestion->creer($this->chef, $this->donnees(['portee' => Activite::PORTEE_FACULTE]));
    }

    public function test_le_doyen_annonce_pour_sa_faculte_sans_la_choisir(): void
    {
        $activite = $this->gestion->creer($this->doyen, $this->donnees([
            'portee' => Activite::PORTEE_FACULTE,
        ]));

        $this->assertSame($this->faculte->id, $activite->faculte_id);
    }

    public function test_le_doyen_ne_vise_pas_une_promotion_d_une_autre_faculte(): void
    {
        $autreFaculte = Faculte::create(['nom' => 'Faculté de Médecine', 'sigle' => 'MED', 'slug' => 'med']);
        $autreDepartement = Departement::create([
            'faculte_id' => $autreFaculte->id, 'nom' => 'Biomédical', 'sigle' => 'BIO',
        ]);
        $etrangere = Promotion::create([
            'departement_id' => $autreDepartement->id,
            'annee_academique_id' => $this->annee->id,
            'niveau' => 'L1',
            'intitule' => 'Première année',
            'active' => true,
        ]);

        $this->expectException(ValidationException::class);

        $this->gestion->creer($this->doyen, $this->donnees([
            'portee' => Activite::PORTEE_PROMOTION,
            'promotion_id' => $etrangere->id,
        ]));
    }

    public function test_le_vice_recteur_annonce_a_toute_l_universite(): void
    {
        $activite = $this->gestion->creer($this->vde, $this->donnees([
            'portee' => Activite::PORTEE_UNIVERSITE,
        ]));

        $this->assertSame(Activite::PORTEE_UNIVERSITE, $activite->portee);
        $this->assertNull($activite->faculte_id);
    }

    public function test_un_etudiant_ne_peut_rien_annoncer(): void
    {
        $etudiant = $this->creerUtilisateur('ETU-ACT', User::ROLE_ETUDIANT);

        $this->assertSame([], $this->gestion->porteesAutorisees($etudiant));

        $this->expectException(ValidationException::class);

        $this->gestion->creer($etudiant, $this->donnees(['portee' => Activite::PORTEE_PROMOTION]));
    }

    /** C'est la portée qui décide, jamais le créateur. */
    public function test_chacun_voit_ce_que_la_portee_lui_destine(): void
    {
        $this->gestion->creer($this->vde, $this->donnees([
            'titre' => 'Rentrée solennelle',
            'portee' => Activite::PORTEE_UNIVERSITE,
        ]));
        $this->gestion->creer($this->doyen, $this->donnees([
            'titre' => 'Conseil de faculté',
            'portee' => Activite::PORTEE_FACULTE,
        ]));
        $this->gestion->creer($this->chef, $this->donnees([
            'titre' => 'Interrogation de droit civil',
            'portee' => Activite::PORTEE_PROMOTION,
        ]));

        $etudiant = $this->creerUtilisateur('ETU-VUE', User::ROLE_ETUDIANT);
        $etudiant->update([
            'promotion_id' => $this->promotion->id,
            'faculte_id' => $this->faculte->id,
        ]);

        $vues = Activite::visiblesPour($etudiant->fresh())->pluck('titre');

        $this->assertCount(3, $vues);

        // Un étudiant d'une autre faculté ne voit que ce qui est universitaire.
        $etranger = $this->creerUtilisateur('ETU-AILLEURS', User::ROLE_ETUDIANT);

        $this->assertSame(
            ['Rentrée solennelle'],
            Activite::visiblesPour($etranger)->pluck('titre')->all(),
        );
    }

    public function test_la_fin_doit_suivre_le_debut(): void
    {
        $this->expectException(ValidationException::class);

        $this->gestion->creer($this->chef, $this->donnees([
            'portee' => Activite::PORTEE_PROMOTION,
            'debut' => now()->addDays(3)->toDateTimeString(),
            'fin' => now()->addDay()->toDateTimeString(),
        ]));
    }

    public function test_l_auteur_cloture_son_activite(): void
    {
        $activite = $this->gestion->creer($this->chef, $this->donnees(['portee' => Activite::PORTEE_PROMOTION]));

        $this->gestion->cloturer($this->chef, $activite);

        $this->assertSame('cloturee', $activite->fresh()->statut);
    }

    public function test_une_activite_cloturee_ne_se_modifie_plus(): void
    {
        $activite = $this->gestion->creer($this->chef, $this->donnees(['portee' => Activite::PORTEE_PROMOTION]));
        $this->gestion->cloturer($this->chef, $activite);

        $this->expectException(ValidationException::class);

        $this->gestion->mettreAJour($this->chef, $activite->fresh(), ['titre' => 'Autre titre']);
    }

    public function test_un_tiers_ne_modifie_pas_l_activite_d_un_autre(): void
    {
        $activite = $this->gestion->creer($this->chef, $this->donnees(['portee' => Activite::PORTEE_PROMOTION]));

        $autreChef = $this->creerUtilisateur('CP-AUTRE-ACT', User::ROLE_CP);

        $this->expectException(ValidationException::class);

        $this->gestion->mettreAJour($autreChef, $activite, ['titre' => 'Détournement']);
    }

    public function test_le_doyen_peut_cloturer_une_activite_de_sa_faculte(): void
    {
        $activite = $this->gestion->creer($this->chef, $this->donnees(['portee' => Activite::PORTEE_PROMOTION]));

        $this->gestion->cloturer($this->doyen, $activite);

        $this->assertSame('cloturee', $activite->fresh()->statut);
    }

    /**
     * Le bouton ne doit pas s'afficher là où l'action serait refusée : un
     * chef de promotion voit la conférence du vice-recteur, mais n'a rien à
     * y faire.
     */
    public function test_le_chef_ne_peut_pas_agir_sur_une_activite_universitaire(): void
    {
        $conference = $this->gestion->creer($this->vde, $this->donnees([
            'titre' => 'Conférence inaugurale',
            'portee' => Activite::PORTEE_UNIVERSITE,
        ]));

        $sienne = $this->gestion->creer($this->chef, $this->donnees(['portee' => Activite::PORTEE_PROMOTION]));

        $this->assertFalse($this->gestion->peutAgirSur($this->chef, $conference));
        $this->assertTrue($this->gestion->peutAgirSur($this->chef, $sienne));
        $this->assertTrue($this->gestion->peutAgirSur($this->doyen, $sienne));
    }

    public function test_l_ecran_s_ouvre(): void
    {
        $this->actingAs($this->chef)->get('/activites')->assertOk()->assertSee('Activités');
    }

    /** @param  array<string, mixed>  $surcharges */
    private function donnees(array $surcharges = []): array
    {
        return array_merge([
            'titre' => 'Interrogation de droit civil',
            'description' => 'Sur les chapitres 1 à 4.',
            'type' => 'interrogation',
            'debut' => now()->addWeek()->setTime(8, 0)->toDateTimeString(),
            'fin' => now()->addWeek()->setTime(10, 0)->toDateTimeString(),
        ], $surcharges);
    }
}
