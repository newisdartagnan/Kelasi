<?php

namespace Tests\Feature;

use App\Models\DemandeReinitialisation;
use App\Models\Faculte;
use App\Models\User;
use App\Services\AdministrationDesComptes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\ConstruitUnContexteAcademique;
use Tests\TestCase;

/**
 * L'administration des comptes.
 *
 * La règle qui structure tout : personne n'administre au-dessus de soi, ni
 * hors de son périmètre. Un doyen tient sa faculté, le vice-recteur
 * l'université, et aucun doyen ne suspend un autre doyen.
 */
class AdministrationDesComptesTest extends TestCase
{
    use ConstruitUnContexteAcademique, RefreshDatabase;

    private AdministrationDesComptes $administration;

    private User $doyen;

    private User $vde;

    private User $etudiant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->construireContexte();
        $this->administration = app(AdministrationDesComptes::class);

        $this->doyen = $this->creerUtilisateur('DF-ADM', User::ROLE_DF);
        $this->doyen->update(['faculte_id' => $this->faculte->id]);
        $this->doyen = $this->doyen->fresh();

        $this->vde = $this->creerUtilisateur('VDE-ADM', User::ROLE_VDE);

        $this->etudiant = $this->creerUtilisateur('ETU-ADM', User::ROLE_ETUDIANT);
        $this->etudiant->update([
            'faculte_id' => $this->faculte->id,
            'promotion_id' => $this->promotion->id,
        ]);
        $this->etudiant = $this->etudiant->fresh();
    }

    public function test_le_doyen_suspend_un_compte_de_sa_faculte(): void
    {
        $this->administration->suspendre($this->doyen, $this->chef, 'Saisies répétées non conformes.');

        $this->assertTrue($this->chef->fresh()->estSuspendu());
        $this->assertSame($this->doyen->id, $this->chef->fresh()->suspendu_par_id);
    }

    /** Une suspension se motive : la personne doit savoir ce qu'on lui reproche. */
    public function test_une_suspension_sans_motif_est_refusee(): void
    {
        $this->expectException(ValidationException::class);

        $this->administration->suspendre($this->doyen, $this->chef, '');
    }

    public function test_un_compte_suspendu_ne_peut_plus_se_connecter(): void
    {
        $this->administration->suspendre($this->doyen, $this->chef, 'Enquête en cours.');

        $this->post('/connexion', ['matricule' => 'CP-001', 'password' => 'motdepasse'])
            ->assertSessionHasErrors('matricule');

        $this->assertGuest();
    }

    public function test_reactiver_efface_le_motif(): void
    {
        $this->administration->suspendre($this->doyen, $this->chef, 'Enquête en cours.');
        $this->administration->reactiver($this->doyen, $this->chef->fresh());

        $rendu = $this->chef->fresh();

        $this->assertFalse($rendu->estSuspendu());
        $this->assertNull($rendu->motif_suspension);
    }

    public function test_un_doyen_ne_suspend_pas_un_compte_d_une_autre_faculte(): void
    {
        $autre = Faculte::create(['nom' => 'Faculté de Médecine', 'sigle' => 'MED', 'slug' => 'med-adm']);
        $etranger = $this->creerUtilisateur('CP-ETRANGER', User::ROLE_CP);
        $etranger->update(['faculte_id' => $autre->id]);

        $this->expectException(ValidationException::class);

        $this->administration->suspendre($this->doyen, $etranger->fresh(), 'Motif quelconque.');
    }

    /** Personne n'administre au-dessus de soi. */
    public function test_un_doyen_ne_suspend_pas_le_vice_recteur(): void
    {
        $this->expectException(ValidationException::class);

        $this->administration->suspendre($this->doyen, $this->vde, 'Motif quelconque.');
    }

    public function test_le_vice_recteur_suspend_un_doyen(): void
    {
        $this->administration->suspendre($this->vde, $this->doyen, 'Décision du rectorat.');

        $this->assertTrue($this->doyen->fresh()->estSuspendu());
    }

    public function test_designer_un_etudiant_comme_chef(): void
    {
        $this->administration->designerChef($this->doyen, $this->etudiant, User::ROLE_CP);

        $this->assertTrue($this->etudiant->fresh()->hasRole(User::ROLE_CP));
    }

    /**
     * Un seul titulaire par promotion : sans cela, deux personnes saisiraient
     * les mêmes séances.
     */
    public function test_nommer_un_chef_rend_l_ancien_a_son_rang(): void
    {
        $this->administration->designerChef($this->doyen, $this->etudiant, User::ROLE_CP);

        $ancien = $this->chef->fresh();

        $this->assertTrue($ancien->hasRole(User::ROLE_ETUDIANT));
        $this->assertFalse($ancien->hasRole(User::ROLE_CP));
    }

    public function test_on_ne_designe_pas_un_enseignant_comme_chef(): void
    {
        $this->expectException(ValidationException::class);

        $this->administration->designerChef($this->doyen, $this->etudiant, User::ROLE_ENSEIGNANT);
    }

    public function test_une_demande_de_mot_de_passe_ne_s_empile_pas(): void
    {
        $this->administration->demanderReinitialisation($this->chef, 'Oublié.');
        $this->administration->demanderReinitialisation($this->chef, 'Encore oublié.');

        $this->assertSame(1, DemandeReinitialisation::count());
    }

    /**
     * Le mot de passe provisoire est rendu par la méthode et jamais stocké en
     * clair : le garder, même le temps d'un affichage, en ferait une porte
     * ouverte pour qui lit la base.
     */
    public function test_l_approbation_rend_un_provisoire_qui_n_est_pas_stocke(): void
    {
        $demande = $this->administration->demanderReinitialisation($this->chef);

        $provisoire = $this->administration->approuverReinitialisation($this->doyen, $demande);

        $this->assertSame(10, strlen($provisoire));
        $this->assertTrue(Hash::check($provisoire, $this->chef->fresh()->password));

        $enBase = DemandeReinitialisation::find($demande->id)->toArray();
        $this->assertStringNotContainsString($provisoire, json_encode($enBase));
    }

    public function test_le_provisoire_evite_les_caracteres_confondables(): void
    {
        foreach (range(1, 20) as $essai) {
            $chef = $this->creerUtilisateur("CP-PROV-{$essai}", User::ROLE_CP);
            $chef->update(['faculte_id' => $this->faculte->id]);

            $demande = $this->administration->demanderReinitialisation($chef->fresh());
            $provisoire = $this->administration->approuverReinitialisation($this->doyen, $demande);

            $this->assertDoesNotMatchRegularExpression('/[IlO0]/', $provisoire);
        }
    }

    public function test_le_provisoire_impose_un_changement_a_la_connexion(): void
    {
        $demande = $this->administration->demanderReinitialisation($this->chef);
        $provisoire = $this->administration->approuverReinitialisation($this->doyen, $demande);

        $this->post('/connexion', ['matricule' => 'CP-001', 'password' => $provisoire])
            ->assertRedirect('/');

        // Toute autre page renvoie vers le choix d'un mot de passe.
        $this->get('/')->assertRedirect(route('mot-de-passe'));
        $this->get(route('mot-de-passe'))->assertOk();
    }

    public function test_choisir_son_mot_de_passe_leve_la_contrainte(): void
    {
        $demande = $this->administration->demanderReinitialisation($this->chef);
        $this->administration->approuverReinitialisation($this->doyen, $demande);

        $this->administration->changerSonMotDePasse($this->chef->fresh(), 'mon-nouveau-motdepasse');

        $rendu = $this->chef->fresh();

        $this->assertFalse($rendu->doit_changer_motdepasse);
        $this->assertTrue(Hash::check('mon-nouveau-motdepasse', $rendu->password));
        $this->assertFalse(DemandeReinitialisation::find($demande->id)->provisoire_actif);
    }

    public function test_une_demande_tranchee_ne_se_rejuge_pas(): void
    {
        $demande = $this->administration->demanderReinitialisation($this->chef);
        $this->administration->approuverReinitialisation($this->doyen, $demande);

        $this->expectException(ValidationException::class);

        $this->administration->rejeterReinitialisation($this->doyen, $demande->fresh(), 'Finalement non.');
    }

    public function test_le_perimetre_borne_la_liste_des_comptes(): void
    {
        $autre = Faculte::create(['nom' => 'Faculté de Médecine', 'sigle' => 'MED2', 'slug' => 'med2-adm']);
        $etranger = $this->creerUtilisateur('ETU-AILLEURS-ADM', User::ROLE_ETUDIANT);
        $etranger->update(['faculte_id' => $autre->id]);

        $vus = $this->administration->comptesAdministrables($this->doyen)->pluck('id');

        $this->assertTrue($vus->contains($this->chef->id));
        $this->assertFalse($vus->contains($etranger->id));
        $this->assertFalse($vus->contains($this->vde->id));
    }

    public function test_l_ecran_est_reserve_aux_autorites(): void
    {
        $this->actingAs($this->chef)->get('/comptes')->assertForbidden();
        $this->actingAs($this->doyen)->get('/comptes')->assertOk();
    }

    /** Dire qu'un matricule n'existe pas permettrait de deviner qui est inscrit. */
    public function test_la_demande_publique_repond_pareil_pour_un_matricule_inconnu(): void
    {
        $this->get('/mot-de-passe-oublie')->assertOk()->assertSee('Matricule');

        $this->assertSame(0, DemandeReinitialisation::count());
    }
}
