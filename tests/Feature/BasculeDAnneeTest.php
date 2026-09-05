<?php

namespace Tests\Feature;

use App\Models\AnneeAcademique;
use App\Models\Cours;
use App\Models\Promotion;
use App\Models\Seance;
use App\Models\User;
use App\Services\BasculeDAnnee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\ConstruitUnContexteAcademique;
use Tests\TestCase;

/**
 * La clôture d'année.
 *
 * L'opération la plus lourde de conséquences de l'application : elle décide
 * de ce que devient chaque promotion. Rien ne doit y être détruit, et rien
 * ne doit s'y perdre en silence.
 */
class BasculeDAnneeTest extends TestCase
{
    use ConstruitUnContexteAcademique, RefreshDatabase;

    private BasculeDAnnee $bascule;

    private User $vde;

    protected function setUp(): void
    {
        parent::setUp();

        $this->construireContexte();
        $this->bascule = app(BasculeDAnnee::class);
        $this->vde = $this->creerUtilisateur('VDE-BASCULE', User::ROLE_VDE);
    }

    public function test_l_apercu_annonce_ce_qui_sera_reconduit(): void
    {
        $apercu = $this->bascule->apercu($this->annee);

        $this->assertSame(1, $apercu['promotions']);
        $this->assertSame('L2', $apercu['promotions_reconduites']->first()['vers']);
        $this->assertTrue($apercu['promotions_terminales']->isEmpty());
    }

    public function test_une_promotion_terminale_ne_se_reconduit_pas(): void
    {
        Promotion::create([
            'departement_id' => $this->promotion->departement_id,
            'annee_academique_id' => $this->annee->id,
            'niveau' => 'L3',
            'intitule' => 'Troisième année de licence en droit',
            'active' => true,
        ]);

        $apercu = $this->bascule->apercu($this->annee);

        $this->assertCount(1, $apercu['promotions_terminales']);
    }

    public function test_la_bascule_ouvre_l_annee_suivante_et_clot_la_precedente(): void
    {
        $resultat = $this->basculer();

        $this->assertSame('2026-2027', $resultat['annee']->libelle);
        $this->assertTrue($resultat['annee']->active);
        $this->assertSame('cloturee', $this->annee->fresh()->statut);
        $this->assertFalse($this->annee->fresh()->active);
    }

    /** Une seule année active : tout le reste lit « l'année courante » au singulier. */
    public function test_une_seule_annee_reste_active(): void
    {
        $this->basculer();

        $this->assertSame(1, AnneeAcademique::where('active', true)->count());
    }

    public function test_la_promotion_passe_au_niveau_suivant(): void
    {
        $resultat = $this->basculer();

        $reconduite = Promotion::where('annee_academique_id', $resultat['annee']->id)->first();

        $this->assertSame('L2', $reconduite->niveau);
        $this->assertSame($this->promotion->departement_id, $reconduite->departement_id);
        // L'intitulé s'écrit en toutes lettres : l'ordinal doit avancer aussi.
        $this->assertSame('Deuxième année de licence en droit', $reconduite->intitule);
    }

    public function test_le_programme_est_recopie_avec_ses_attributions(): void
    {
        $resultat = $this->basculer();

        $reconduite = Promotion::where('annee_academique_id', $resultat['annee']->id)->first();
        $copie = Cours::whereHas('uniteEnseignement', fn ($q) => $q->where('promotion_id', $reconduite->id))->first();

        $this->assertSame(1, $resultat['cours']);
        $this->assertSame($this->cours->intitule, $copie->intitule);
        $this->assertSame($this->cours->heures_cmi, $copie->heures_cmi);
        $this->assertTrue($copie->attributions()->where('user_id', $this->enseignant->id)->exists());
    }

    /** Le programme copié est indépendant : le modifier ne touche pas l'ancien. */
    public function test_le_programme_recopie_est_independant(): void
    {
        $resultat = $this->basculer();

        $reconduite = Promotion::where('annee_academique_id', $resultat['annee']->id)->first();
        $copie = Cours::whereHas('uniteEnseignement', fn ($q) => $q->where('promotion_id', $reconduite->id))->first();

        $copie->update(['heures_cmi' => 999]);

        $this->assertNotSame(999, $this->cours->fresh()->heures_cmi);
    }

    /** L'année close reste lisible : c'est une archive, pas une suppression. */
    public function test_rien_n_est_detruit_de_l_annee_close(): void
    {
        $seance = $this->seanceValidee();

        $this->basculer();

        $this->assertDatabaseHas('seances', ['id' => $seance->id]);
        $this->assertDatabaseHas('promotions', ['id' => $this->promotion->id, 'active' => false]);
        $this->assertDatabaseHas('cours', ['id' => $this->cours->id]);
    }

    /**
     * Clôturer alors que des séances attendent leur contreseing les figerait
     * sans qu'elles comptent jamais dans l'avancement.
     */
    public function test_des_seances_en_attente_bloquent_la_cloture(): void
    {
        Seance::create([
            'uuid' => (string) Str::uuid(),
            'cours_id' => $this->cours->id,
            'promotion_id' => $this->promotion->id,
            'date_seance' => now()->subDay()->toDateString(),
            'heure_debut' => '08:00',
            'heure_fin' => '10:00',
            'duree_minutes' => 120,
            'type' => Seance::TYPE_CMI,
            'matiere_couverte' => 'Chapitre en attente.',
            'statut' => Seance::STATUT_SOUMISE,
            'saisie_par_id' => $this->chef->id,
            'soumise_at' => now(),
        ]);

        $this->expectException(ValidationException::class);

        $this->basculer();
    }

    public function test_seul_le_rectorat_cloture(): void
    {
        $doyen = $this->creerUtilisateur('DF-BASCULE', User::ROLE_DF);

        $this->expectException(ValidationException::class);

        $this->bascule->basculer($doyen, $this->annee, '2026-2027', '2026-10-15', '2027-07-31');
    }

    public function test_un_libelle_deja_pris_est_refuse(): void
    {
        $this->expectException(ValidationException::class);

        $this->bascule->basculer($this->vde, $this->annee, $this->annee->libelle, '2026-10-15', '2027-07-31');
    }

    public function test_la_cloture_doit_suivre_la_rentree(): void
    {
        $this->expectException(ValidationException::class);

        $this->bascule->basculer($this->vde, $this->annee, '2026-2027', '2027-07-31', '2026-10-15');
    }

    /** Rejouer la bascule ne doit pas doubler le programme. */
    public function test_reconduire_deux_fois_ne_double_pas_le_programme(): void
    {
        $premier = $this->basculer();

        $second = $this->bascule->basculer(
            $this->vde,
            $premier['annee'],
            '2027-2028',
            '2027-10-15',
            '2028-07-31',
        );

        $reconduite = Promotion::where('annee_academique_id', $second['annee']->id)->first();

        $this->assertSame('L3', $reconduite->niveau);
        $this->assertSame(1, $reconduite->unitesEnseignement()->count());
    }

    /**
     * Le libellé se déduit du libellé courant, pas des dates : c'est lui que
     * le secrétariat lit, et les deux ne coïncident pas toujours.
     */
    public function test_l_ecran_propose_l_annee_qui_suit_immediatement(): void
    {
        $this->annee->update(['libelle' => '2025-2026']);

        $this->actingAs($this->vde)
            ->get('/annees')
            ->assertOk()
            ->assertSee('2026-2027');
    }

    public function test_l_ecran_est_reserve_au_rectorat(): void
    {
        $this->actingAs($this->chef)->get('/annees')->assertForbidden();
        $this->actingAs($this->vde)->get('/annees')->assertOk()->assertSee('Année académique');
    }

    /** @return array{annee: AnneeAcademique, promotions: int, cours: int} */
    private function basculer(): array
    {
        return $this->bascule->basculer($this->vde, $this->annee, '2026-2027', '2026-10-15', '2027-07-31');
    }

    private function seanceValidee(): Seance
    {
        return Seance::create([
            'uuid' => (string) Str::uuid(),
            'cours_id' => $this->cours->id,
            'promotion_id' => $this->promotion->id,
            'date_seance' => now()->subDays(5)->toDateString(),
            'heure_debut' => '08:00',
            'heure_fin' => '10:00',
            'duree_minutes' => 120,
            'type' => Seance::TYPE_CMI,
            'matiere_couverte' => 'Chapitre traité.',
            'statut' => Seance::STATUT_VALIDEE,
            'saisie_par_id' => $this->chef->id,
            'validee_par_id' => $this->enseignant->id,
            'soumise_at' => now(),
            'validee_at' => now(),
        ]);
    }
}
