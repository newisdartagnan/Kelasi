<?php

namespace Tests\Feature;

use App\Models\Attribution;
use App\Models\Cours;
use App\Models\Promotion;
use App\Models\Seance;
use App\Models\User;
use App\Services\CalculateurAvancement;
use App\Services\RegistreDesSeances;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;
use Tests\ConstruitUnContexteAcademique;

/**
 * Les regles qui donnent au chiffre d'avancement sa valeur probante.
 */
class RegistreDesSeancesTest extends TestCase
{
    use ConstruitUnContexteAcademique, RefreshDatabase;

    private RegistreDesSeances $registre;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registre = app(RegistreDesSeances::class);
        $this->construireContexte();
    }

    public function test_le_chef_de_promotion_saisit_une_seance_qui_part_en_attente_de_contreseing(): void
    {
        $seance = $this->registre->saisir($this->chef, $this->cours, $this->donneesSeance());

        $this->assertSame(Seance::STATUT_SOUMISE, $seance->statut);
        $this->assertSame($this->chef->id, $seance->saisie_par_id);
        $this->assertSame(120, $seance->duree_minutes);
        $this->assertNotNull($seance->uuid);
    }

    public function test_un_etudiant_ordinaire_ne_peut_pas_saisir(): void
    {
        $etudiant = $this->creerUtilisateur('ETU-999', User::ROLE_ETUDIANT);
        $etudiant->update(['promotion_id' => $this->promotion->id]);

        $this->expectException(ValidationException::class);

        $this->registre->saisir($etudiant, $this->cours, $this->donneesSeance());
    }

    public function test_un_chef_ne_saisit_pas_pour_une_autre_promotion(): void
    {
        $voisine = Promotion::create([
            'departement_id' => $this->promotion->departement_id,
            'annee_academique_id' => $this->annee->id,
            'niveau' => 'L2',
            'intitule' => 'Deuxième année de licence en droit',
            'active' => true,
        ]);

        $intrus = $this->creerUtilisateur('CP-AUTRE', User::ROLE_CP);
        $intrus->update(['promotion_id' => $voisine->id]);

        $this->expectException(ValidationException::class);

        $this->registre->saisir($intrus, $this->cours, $this->donneesSeance());
    }

    public function test_on_ne_saisit_pas_une_seance_a_venir(): void
    {
        $this->expectException(ValidationException::class);

        $this->registre->saisir($this->chef, $this->cours, $this->donneesSeance([
            'date_seance' => now()->addWeek()->toDateString(),
        ]));
    }

    public function test_l_heure_de_fin_doit_suivre_l_heure_de_debut(): void
    {
        $this->expectException(ValidationException::class);

        $this->registre->saisir($this->chef, $this->cours, $this->donneesSeance([
            'heure_debut' => '10:00',
            'heure_fin' => '08:00',
        ]));
    }

    public function test_l_enseignant_attribue_valide_la_seance(): void
    {
        $seance = $this->registre->saisir($this->chef, $this->cours, $this->donneesSeance());

        $validee = $this->registre->valider($this->enseignant, $seance);

        $this->assertSame(Seance::STATUT_VALIDEE, $validee->statut);
        $this->assertSame($this->enseignant->id, $validee->validee_par_id);
        $this->assertNotNull($validee->validee_at);
    }

    public function test_un_enseignant_etranger_au_cours_ne_valide_pas(): void
    {
        $seance = $this->registre->saisir($this->chef, $this->cours, $this->donneesSeance());
        $autre = $this->creerUtilisateur('ENS-ETRANGER', User::ROLE_ENSEIGNANT);

        $this->expectException(ValidationException::class);

        $this->registre->valider($autre, $seance);
    }

    /**
     * La regle qui fonde tout le dispositif : celui qui saisit ne contresigne
     * pas. Sans elle, un chef de promotion pourrait attester ses propres
     * declarations.
     */
    public function test_celui_qui_a_saisi_ne_peut_pas_valider(): void
    {
        $seance = $this->registre->saisir($this->chef, $this->cours, $this->donneesSeance());

        Attribution::create([
            'cours_id' => $this->cours->id,
            'user_id' => $this->chef->id,
            'role' => Attribution::ROLE_ASSISTANT,
        ]);

        $this->expectException(ValidationException::class);

        $this->registre->valider($this->chef->fresh(), $seance);
    }

    public function test_valider_deux_fois_ne_change_rien(): void
    {
        $seance = $this->registre->saisir($this->chef, $this->cours, $this->donneesSeance());

        $premiere = $this->registre->valider($this->enseignant, $seance);
        $horodatage = $premiere->validee_at;

        $seconde = $this->registre->valider($this->enseignant, $premiere->fresh());

        $this->assertSame(Seance::STATUT_VALIDEE, $seconde->statut);
        $this->assertEquals($horodatage, $seconde->validee_at);
    }

    public function test_une_seance_contestee_sort_de_l_avancement(): void
    {
        $seance = $this->registre->saisir($this->chef, $this->cours, $this->donneesSeance());
        $this->registre->contester($this->enseignant, $seance, 'Le chapitre annonce n\'a pas ete traité.');

        $avancement = app(CalculateurAvancement::class)->pourCours($this->cours->fresh());

        $this->assertSame(Seance::STATUT_CONTESTEE, $seance->fresh()->statut);
        $this->assertSame(0.0, $avancement->heuresRealisees());
        $this->assertSame(0.0, $avancement->heuresEnAttente());
    }

    public function test_seules_les_seances_validees_comptent_dans_l_avancement(): void
    {
        $soumise = $this->registre->saisir($this->chef, $this->cours, $this->donneesSeance());
        $this->registre->saisir($this->chef, $this->cours, $this->donneesSeance([
            'date_seance' => now()->subDays(2)->toDateString(),
        ]));

        $this->registre->valider($this->enseignant, $soumise);

        $avancement = app(CalculateurAvancement::class)->pourCours($this->cours->fresh());

        $this->assertSame(2.0, $avancement->heuresRealisees());
        $this->assertSame(2.0, $avancement->heuresEnAttente());
    }

    /** @param  array<string, mixed>  $surcharges */
    private function donneesSeance(array $surcharges = []): array
    {
        return array_merge([
            'date_seance' => now()->subDay()->toDateString(),
            'heure_debut' => '08:00',
            'heure_fin' => '10:00',
            'type' => Seance::TYPE_CMI,
            'matiere_couverte' => 'Chapitre 1 : les sources du droit.',
        ], $surcharges);
    }
}
