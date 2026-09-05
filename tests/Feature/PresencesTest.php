<?php

namespace Tests\Feature;

use App\Models\Presence;
use App\Models\Seance;
use App\Models\User;
use App\Services\ReleveDePresence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\ConstruitUnContexteAcademique;
use Tests\TestCase;

/**
 * Le relevé de présence.
 *
 * L'assiduité conditionne l'accès aux examens : le relevé doit donc être
 * nominatif, corrigeable, et ne jamais pénaliser un étudiant pour un appel
 * que le chef de promotion a oublié de faire.
 */
class PresencesTest extends TestCase
{
    use ConstruitUnContexteAcademique, RefreshDatabase;

    private ReleveDePresence $releve;

    private User $premier;

    private User $second;

    protected function setUp(): void
    {
        parent::setUp();

        $this->construireContexte();
        $this->releve = app(ReleveDePresence::class);

        $this->premier = $this->inscrire('ETU-P1');
        $this->second = $this->inscrire('ETU-P2');
    }

    public function test_l_appel_enregistre_chaque_etudiant(): void
    {
        $seance = $this->seance();

        $nombre = $this->releve->enregistrer($this->chef, $seance, [
            $this->premier->id => Presence::PRESENT,
            $this->second->id => Presence::ABSENT,
        ]);

        $this->assertSame(2, $nombre);
        $this->assertSame(Presence::ABSENT, Presence::where('user_id', $this->second->id)->value('statut'));
    }

    /** L'effectif de la séance doit suivre le relevé, sans le contredire. */
    public function test_l_effectif_de_la_seance_suit_le_releve(): void
    {
        $seance = $this->seance();

        $this->releve->enregistrer($this->chef, $seance, [
            $this->premier->id => Presence::PRESENT,
            $this->second->id => Presence::ABSENT,
            $this->chef->id => Presence::RETARD,
        ]);

        $rendu = $seance->fresh();

        $this->assertSame(2, $rendu->effectif_present);   // le retard compte comme une présence
        $this->assertTrue($rendu->appelFait());
    }

    /** Un retardataire a suivi la séance : il compte comme présent. */
    public function test_un_retard_compte_comme_une_presence(): void
    {
        $seance = $this->seance();
        $this->releve->enregistrer($this->chef, $seance, [$this->premier->id => Presence::RETARD]);

        $this->assertSame(100.0, $this->releve->assiduite($this->premier)['taux']);
    }

    /** Refaire l'appel corrige : un absent revenu avec un justificatif. */
    public function test_refaire_l_appel_corrige_au_lieu_de_dupliquer(): void
    {
        $seance = $this->seance();

        $this->releve->enregistrer($this->chef, $seance, [$this->premier->id => Presence::ABSENT]);
        $this->releve->enregistrer($this->chef, $seance, [$this->premier->id => Presence::EXCUSE], [
            $this->premier->id => 'Certificat médical présenté.',
        ]);

        $ligne = Presence::where('seance_id', $seance->id)->where('user_id', $this->premier->id);

        $this->assertSame(1, $ligne->count());
        $this->assertSame(Presence::EXCUSE, $ligne->value('statut'));
        $this->assertSame('Certificat médical présenté.', $ligne->value('motif'));
    }

    public function test_seul_le_chef_de_promotion_fait_l_appel(): void
    {
        $this->expectException(ValidationException::class);

        $this->releve->enregistrer($this->enseignant, $this->seance(), [$this->premier->id => Presence::PRESENT]);
    }

    public function test_un_chef_ne_fait_pas_l_appel_d_une_autre_promotion(): void
    {
        $intrus = $this->creerUtilisateur('CP-AILLEURS-P', User::ROLE_CP);

        $this->expectException(ValidationException::class);

        $this->releve->enregistrer($intrus, $this->seance(), [$this->premier->id => Presence::PRESENT]);
    }

    /** Un étudiant d'ailleurs glissé dans le relevé est simplement ignoré. */
    public function test_un_etranger_a_la_promotion_est_ecarte_du_releve(): void
    {
        $etranger = $this->creerUtilisateur('ETU-AILLEURS-P', User::ROLE_ETUDIANT);
        $seance = $this->seance();

        $nombre = $this->releve->enregistrer($this->chef, $seance, [
            $this->premier->id => Presence::PRESENT,
            $etranger->id => Presence::PRESENT,
        ]);

        $this->assertSame(1, $nombre);
        $this->assertSame(0, Presence::where('user_id', $etranger->id)->count());
    }

    public function test_l_assiduite_se_calcule_sur_les_seances_relevees(): void
    {
        $this->releve->enregistrer($this->chef, $this->seance(), [$this->premier->id => Presence::PRESENT]);
        $this->releve->enregistrer($this->chef, $this->seance(), [$this->premier->id => Presence::ABSENT]);
        $this->releve->enregistrer($this->chef, $this->seance(), [$this->premier->id => Presence::PRESENT]);

        $assiduite = $this->releve->assiduite($this->premier);

        $this->assertSame(3, $assiduite['seances']);
        $this->assertSame(2, $assiduite['presences']);
        $this->assertEqualsWithDelta(66.7, $assiduite['taux'], 0.1);
    }

    /**
     * Une séance sans appel ne doit pas peser au dénominateur : l'étudiant
     * serait pénalisé pour un oubli qui n'est pas le sien.
     */
    public function test_une_seance_sans_appel_ne_penalise_personne(): void
    {
        $this->releve->enregistrer($this->chef, $this->seance(), [$this->premier->id => Presence::PRESENT]);
        $this->seance();   // séance tenue, appel jamais fait

        $this->assertSame(1, $this->releve->assiduite($this->premier)['seances']);
        $this->assertSame(100.0, $this->releve->assiduite($this->premier)['taux']);
    }

    public function test_l_assiduite_se_restreint_a_un_cours(): void
    {
        $this->releve->enregistrer($this->chef, $this->seance(), [$this->premier->id => Presence::PRESENT]);

        $this->assertSame(1, $this->releve->assiduite($this->premier, $this->cours)['seances']);
    }

    /** Ceux qui décrochent doivent apparaître en premier. */
    public function test_la_promotion_est_classee_du_taux_le_plus_faible(): void
    {
        $seance = $this->seance();

        $this->releve->enregistrer($this->chef, $seance, [
            $this->premier->id => Presence::PRESENT,
            $this->second->id => Presence::ABSENT,
        ]);

        $classement = $this->releve->assiduiteDePromotion($this->promotion);

        $this->assertSame($this->second->id, $classement->first()['etudiant']->id);
        $this->assertSame(0.0, $classement->first()['taux']);
    }

    public function test_un_etudiant_sans_releve_apparait_sans_taux(): void
    {
        $classement = $this->releve->assiduiteDePromotion($this->promotion);
        $ligne = $classement->firstWhere('etudiant.id', $this->premier->id);

        $this->assertSame(0, $ligne['seances']);
        $this->assertSame(0.0, $ligne['taux']);
    }

    public function test_l_ecran_d_appel_est_reserve_au_chef_de_la_promotion(): void
    {
        $seance = $this->seance();

        $this->actingAs($this->chef)->get(route('seances.appel', $seance))->assertOk();
        $this->actingAs($this->premier)->get(route('seances.appel', $seance))->assertForbidden();
    }

    public function test_l_ecran_d_assiduite_s_ouvre(): void
    {
        $this->actingAs($this->chef)->get('/assiduite')->assertOk()->assertSee('Assiduité');
    }

    private function inscrire(string $matricule): User
    {
        $etudiant = $this->creerUtilisateur($matricule, User::ROLE_ETUDIANT);
        $etudiant->update([
            'promotion_id' => $this->promotion->id,
            'faculte_id' => $this->faculte->id,
        ]);

        return $etudiant->fresh();
    }

    private function seance(): Seance
    {
        return Seance::create([
            'uuid' => (string) Str::uuid(),
            'cours_id' => $this->cours->id,
            'promotion_id' => $this->promotion->id,
            'date_seance' => now()->subDays(random_int(1, 30))->toDateString(),
            'heure_debut' => '08:00',
            'heure_fin' => '10:00',
            'duree_minutes' => 120,
            'type' => Seance::TYPE_CMI,
            'matiere_couverte' => 'Chapitre du jour.',
            'statut' => Seance::STATUT_VALIDEE,
            'saisie_par_id' => $this->chef->id,
            'soumise_at' => now(),
        ]);
    }
}
