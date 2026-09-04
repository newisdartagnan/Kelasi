<?php

namespace Tests\Feature;

use App\Models\DemandeModification;
use App\Models\User;
use App\Services\ArbitrageDesDemandes;
use App\Support\VolumeHoraire;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\ConstruitUnContexteAcademique;
use Tests\TestCase;

/**
 * Le circuit de modification du programme.
 *
 * Ce qui compte ici : approuver doit réellement changer le cours. Une
 * approbation qui laisse le programme inchangé rendrait tout l'avancement
 * faux sans que personne ne s'en aperçoive.
 */
class DemandesDeModificationTest extends TestCase
{
    use ConstruitUnContexteAcademique, RefreshDatabase;

    private ArbitrageDesDemandes $arbitrage;

    private User $vde;

    protected function setUp(): void
    {
        parent::setUp();

        $this->construireContexte();
        $this->arbitrage = app(ArbitrageDesDemandes::class);
        $this->vde = $this->creerUtilisateur('VDE-TEST', User::ROLE_VDE);
    }

    public function test_l_enseignant_attribue_depose_une_demande(): void
    {
        $demande = $this->deposer();

        $this->assertSame(DemandeModification::STATUT_EN_ATTENTE, $demande->statut);
        $this->assertSame($this->enseignant->id, $demande->demandeur_id);
    }

    public function test_un_enseignant_etranger_au_cours_ne_peut_pas_demander(): void
    {
        $autre = $this->creerUtilisateur('ENS-ETRANGER', User::ROLE_ENSEIGNANT);

        $this->expectException(ValidationException::class);

        $this->arbitrage->deposer($autre, $this->cours, [
            'type' => 'volume',
            'description' => 'Il faudrait plus d\'heures.',
            'justification' => 'Le programme est trop dense pour le volume prévu.',
            'modifications' => ['heures_cmi' => 90],
        ]);
    }

    /** L'approbation doit écrire sur le cours, pas seulement enregistrer un avis. */
    public function test_approuver_applique_reellement_la_modification(): void
    {
        $ancien = $this->cours->heures_cmi;
        $demande = $this->deposer(['heures_cmi' => $ancien + 15]);

        $this->arbitrage->approuver($this->vde, $demande);

        $this->assertSame($ancien + 15, $this->cours->fresh()->heures_cmi);
        $this->assertSame(DemandeModification::STATUT_APPROUVEE, $demande->fresh()->statut);
    }

    /** Changer les crédits change le volume total : le TPE doit suivre la règle. */
    public function test_changer_les_credits_recalcule_le_travail_personnel(): void
    {
        $demande = $this->deposer(['credits' => 8, 'heures_cmi' => 100]);

        $this->arbitrage->approuver($this->vde, $demande);

        $this->assertSame(8, $this->cours->fresh()->credits);
        $this->assertSame(VolumeHoraire::heuresTpe(8), $this->cours->fresh()->heures_tpe);
    }

    public function test_l_etat_precedent_est_conserve_pour_la_tracabilite(): void
    {
        $ancien = $this->cours->heures_cmi;
        $demande = $this->deposer(['heures_cmi' => $ancien + 10]);

        $this->arbitrage->approuver($this->vde, $demande);

        $this->assertSame($ancien, $demande->fresh()->modifications['etat_precedent']['heures_cmi']);
    }

    public function test_une_redistribution_doit_conserver_le_volume_total(): void
    {
        $this->expectException(ValidationException::class);

        $this->arbitrage->deposer($this->enseignant, $this->cours, [
            'type' => 'repartition',
            'description' => 'Basculer des heures vers les travaux dirigés.',
            'justification' => 'Les étudiants ont besoin de plus de pratique.',
            'modifications' => ['heures_cmi' => 10, 'heures_td' => 10, 'heures_tp' => 0],
        ]);
    }

    public function test_une_redistribution_a_volume_constant_est_acceptee(): void
    {
        $total = $this->cours->heures_prevues;

        $demande = $this->arbitrage->deposer($this->enseignant, $this->cours, [
            'type' => 'repartition',
            'description' => 'Basculer dix heures vers les travaux dirigés.',
            'justification' => 'Les étudiants ont besoin de plus de pratique.',
            'modifications' => [
                'heures_cmi' => $this->cours->heures_cmi - 10,
                'heures_td' => $this->cours->heures_td + 10,
                'heures_tp' => $this->cours->heures_tp,
            ],
        ]);

        $this->arbitrage->approuver($this->vde, $demande);

        $this->assertSame($total, $this->cours->fresh()->heures_prevues);
    }

    /** Une demande d'intitulé ne doit pas pouvoir glisser un changement d'horaire. */
    public function test_un_type_ne_peut_pas_porter_les_champs_d_un_autre(): void
    {
        $ancien = $this->cours->heures_cmi;

        $demande = $this->arbitrage->deposer($this->enseignant, $this->cours, [
            'type' => 'intitule',
            'description' => 'Corriger l\'intitulé du cours.',
            'justification' => 'L\'intitulé actuel comporte une faute.',
            'modifications' => ['intitule' => 'Introduction au droit', 'heures_cmi' => 500],
        ]);

        $this->arbitrage->approuver($this->vde, $demande);

        $this->assertSame('Introduction au droit', $this->cours->fresh()->intitule);
        $this->assertSame($ancien, $this->cours->fresh()->heures_cmi);
    }

    public function test_rejeter_ne_touche_pas_au_programme(): void
    {
        $ancien = $this->cours->heures_cmi;
        $demande = $this->deposer(['heures_cmi' => $ancien + 20]);

        $this->arbitrage->rejeter($this->vde, $demande, 'Le volume global de la promotion est déjà atteint.');

        $this->assertSame($ancien, $this->cours->fresh()->heures_cmi);
        $this->assertSame(DemandeModification::STATUT_REJETEE, $demande->fresh()->statut);
    }

    public function test_seul_le_vice_recteur_arbitre(): void
    {
        $demande = $this->deposer();

        $this->expectException(ValidationException::class);

        $this->arbitrage->approuver($this->chef, $demande);
    }

    public function test_on_n_arbitre_pas_sa_propre_demande(): void
    {
        $demande = $this->arbitrage->deposer($this->vde, $this->cours, [
            'type' => 'volume',
            'description' => 'Ajouter des heures à ce cours.',
            'justification' => 'Le volume actuel ne suffit pas au programme.',
            'modifications' => ['heures_cmi' => 90],
        ]);

        $this->expectException(ValidationException::class);

        $this->arbitrage->approuver($this->vde, $demande);
    }

    public function test_une_demande_deja_tranchee_ne_se_rejuge_pas(): void
    {
        $demande = $this->deposer();
        $this->arbitrage->approuver($this->vde, $demande);

        $this->expectException(ValidationException::class);

        $this->arbitrage->rejeter($this->vde, $demande->fresh(), 'Finalement non.');
    }

    public function test_l_auteur_retire_sa_demande_tant_qu_elle_attend(): void
    {
        $demande = $this->deposer();

        $this->arbitrage->retirer($this->enseignant, $demande);

        $this->assertSame(DemandeModification::STATUT_RETIREE, $demande->fresh()->statut);
    }

    public function test_un_tiers_ne_retire_pas_la_demande_d_un_autre(): void
    {
        $demande = $this->deposer();

        $this->expectException(ValidationException::class);

        $this->arbitrage->retirer($this->chef, $demande);
    }

    public function test_l_ecran_s_ouvre_pour_l_enseignant_et_pour_le_vde(): void
    {
        $this->actingAs($this->enseignant)->get('/demandes')->assertOk()->assertSee('Mes demandes');
        $this->actingAs($this->vde)->get('/demandes')->assertOk()->assertSee('arbitrer');
    }

    /** @param  array<string, mixed>  $modifications */
    private function deposer(array $modifications = ['heures_cmi' => 90]): DemandeModification
    {
        return $this->arbitrage->deposer($this->enseignant, $this->cours, [
            'type' => 'volume',
            'description' => 'Revoir le volume horaire de ce cours.',
            'justification' => 'Le programme ne tient pas dans le volume actuellement prévu.',
            'modifications' => $modifications,
        ]);
    }
}
