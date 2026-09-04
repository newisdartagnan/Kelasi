<?php

namespace Tests\Feature;

use App\Models\AbonnementPush;
use App\Models\Activite;
use App\Models\Seance;
use App\Models\User;
use App\Notifications\RappelQuotidien;
use App\Services\RappelsQuotidiens;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\ConstruitUnContexteAcademique;
use Tests\TestCase;

/**
 * Les rappels quotidiens.
 *
 * La règle qui gouverne tout : on n'envoie rien quand il n'y a rien à dire.
 * Des rappels expédiés dans le vide apprennent au destinataire à les ignorer,
 * et le jour où il faut vraiment le prévenir, il ne lit plus.
 */
class RappelsTest extends TestCase
{
    use ConstruitUnContexteAcademique, RefreshDatabase;

    private RappelsQuotidiens $rappels;

    protected function setUp(): void
    {
        parent::setUp();

        $this->construireContexte();
        $this->rappels = app(RappelsQuotidiens::class);
    }

    /**
     * Un lundi, la veille est un dimanche et aucune séance n'est attendue :
     * il ne reste alors plus rien à signaler à personne.
     */
    public function test_rien_a_signaler_ne_declenche_aucun_rappel(): void
    {
        Notification::fake();
        $this->travelTo(now()->next('Monday')->setTime(6, 0));

        $this->rappels->envoyer();

        Notification::assertNothingSent();
    }

    public function test_l_enseignant_est_prevenu_des_seances_a_contresigner(): void
    {
        $this->seanceEnAttente();

        $points = $this->rappels->pointsPour($this->enseignant);

        $this->assertCount(1, $points);
        $this->assertSame('Séances à contresigner', $points[0]['titre']);
        $this->assertStringContainsString('Une séance', $points[0]['detail']);
    }

    public function test_le_chef_est_relance_quand_rien_n_a_ete_saisi_la_veille(): void
    {
        $this->travelTo(now()->next('Wednesday')->setTime(6, 0));

        $points = $this->rappels->pointsPour($this->chef);

        $this->assertSame('Séances d\'hier', $points[0]['titre']);
    }

    /** Une séance saisie la veille : plus rien à rappeler. */
    public function test_le_chef_n_est_pas_relance_s_il_a_saisi(): void
    {
        $this->travelTo(now()->next('Wednesday')->setTime(6, 0));
        $this->seanceEnAttente(now()->subDay());

        $points = collect($this->rappels->pointsPour($this->chef));

        $this->assertFalse($points->contains('titre', 'Séances d\'hier'));
    }

    /** Le lundi, la veille est un dimanche : personne n'a enseigné. */
    public function test_le_chef_n_est_pas_relance_le_lendemain_d_un_week_end(): void
    {
        $this->travelTo(now()->next('Monday')->setTime(6, 0));

        $points = collect($this->rappels->pointsPour($this->chef));

        $this->assertFalse($points->contains('titre', 'Séances d\'hier'));
    }

    public function test_les_activites_imminentes_sont_annoncees(): void
    {
        Activite::create([
            'titre' => 'Examen de droit civil',
            'type' => 'examen',
            'portee' => Activite::PORTEE_PROMOTION,
            'promotion_id' => $this->promotion->id,
            'debut' => now()->addDay(),
            'statut' => 'planifiee',
            'createur_id' => $this->chef->id,
        ]);

        $points = collect($this->rappels->pointsPour($this->chef));

        $this->assertTrue($points->contains('titre', 'Examen de droit civil'));
    }

    /**
     * L'alerte de retard ne va qu'à ceux qui peuvent agir. Un étudiant n'y
     * pourrait rien, et la recevoir chaque matin l'userait.
     */
    public function test_l_alerte_de_retard_ne_va_pas_aux_etudiants(): void
    {
        $etudiant = $this->creerUtilisateur('ETU-RAPPEL', User::ROLE_ETUDIANT);
        $etudiant->update(['promotion_id' => $this->promotion->id, 'faculte_id' => $this->faculte->id]);

        $points = collect($this->rappels->pointsPour($etudiant->fresh()));

        $this->assertFalse($points->contains('titre', 'Promotions en retard'));
    }

    public function test_le_doyen_est_alerte_du_retard_de_sa_faculte(): void
    {
        $doyen = $this->creerUtilisateur('DF-RAPPEL', User::ROLE_DF);
        $doyen->update(['faculte_id' => $this->faculte->id]);

        // Rien n'a été enseigné alors que l'année est bien entamée.
        $points = collect($this->rappels->pointsPour($doyen->fresh()));

        $this->assertTrue($points->contains('titre', 'Promotions en retard'));
    }

    /** Une seule notification par personne, qui rassemble ce qui la concerne. */
    public function test_un_seul_rappel_rassemble_tous_les_points(): void
    {
        Notification::fake();
        $this->seanceEnAttente();

        Activite::create([
            'titre' => 'Conseil de faculté',
            'type' => 'autre',
            'portee' => Activite::PORTEE_UNIVERSITE,
            'debut' => now()->addDay(),
            'statut' => 'planifiee',
            'createur_id' => $this->chef->id,
        ]);

        $this->rappels->envoyer();

        Notification::assertSentToTimes($this->enseignant, RappelQuotidien::class, 1);
        Notification::assertSentTo(
            $this->enseignant,
            fn (RappelQuotidien $rappel) => count($rappel->points) === 2,
        );
    }

    public function test_le_rappel_est_enregistre_en_base(): void
    {
        $this->seanceEnAttente();

        $this->rappels->envoyer();

        $this->assertSame(1, $this->enseignant->notifications()->count());
        $this->assertSame('rappel_quotidien', $this->enseignant->notifications()->first()->data['type']);
    }

    public function test_la_commande_artisan_envoie_les_rappels(): void
    {
        $this->seanceEnAttente();

        $this->artisan('kelasi:rappels')
            ->expectsOutputToContain('Rappels envoyés')
            ->assertSuccessful();
    }

    public function test_un_appareil_s_abonne_puis_se_desabonne(): void
    {
        $abonnement = [
            'endpoint' => 'https://push.example.test/abc123',
            'cles' => ['p256dh' => 'clepublique', 'auth' => 'jeton'],
            'appareil' => 'Android',
        ];

        $this->actingAs($this->chef)
            ->postJson(route('notifications.abonnement'), $abonnement)
            ->assertOk();

        $this->assertSame(1, AbonnementPush::where('user_id', $this->chef->id)->count());

        $this->actingAs($this->chef)
            ->postJson(route('notifications.abonnement.retrait'), ['endpoint' => $abonnement['endpoint']])
            ->assertOk();

        $this->assertSame(0, AbonnementPush::count());
    }

    /** Se réabonner depuis le même navigateur ne doit pas doubler l'envoi. */
    public function test_se_reabonner_du_meme_appareil_ne_cree_pas_de_doublon(): void
    {
        $abonnement = [
            'endpoint' => 'https://push.example.test/abc123',
            'cles' => ['p256dh' => 'clepublique', 'auth' => 'jeton'],
        ];

        $this->actingAs($this->chef)->postJson(route('notifications.abonnement'), $abonnement)->assertOk();
        $this->actingAs($this->chef)->postJson(route('notifications.abonnement'), $abonnement)->assertOk();

        $this->assertSame(1, AbonnementPush::count());
    }

    public function test_l_ecran_des_notifications_s_ouvre(): void
    {
        $this->actingAs($this->chef)->get('/notifications')->assertOk()->assertSee('Notifications');
    }

    private function seanceEnAttente(?\Carbon\Carbon $date = null): Seance
    {
        return Seance::create([
            'uuid' => (string) Str::uuid(),
            'cours_id' => $this->cours->id,
            'promotion_id' => $this->promotion->id,
            'date_seance' => ($date ?? now()->subDays(2))->toDateString(),
            'heure_debut' => '08:00',
            'heure_fin' => '10:00',
            'duree_minutes' => 120,
            'type' => Seance::TYPE_CMI,
            'matiere_couverte' => 'Chapitre 1.',
            'statut' => Seance::STATUT_SOUMISE,
            'saisie_par_id' => $this->chef->id,
            'soumise_at' => now(),
        ]);
    }
}
