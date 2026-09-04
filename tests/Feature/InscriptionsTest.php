<?php

namespace Tests\Feature;

use App\Models\InscriptionAutorisee;
use App\Models\User;
use App\Services\ActivationDeCompte;
use App\Services\ImportDesInscrits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\ConstruitUnContexteAcademique;
use Tests\TestCase;

/**
 * La porte d'entrée de l'application : sans ligne déposée par le secrétariat,
 * aucun compte ne peut exister.
 */
class InscriptionsTest extends TestCase
{
    use ConstruitUnContexteAcademique, RefreshDatabase;

    private ImportDesInscrits $import;

    private ActivationDeCompte $activation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->construireContexte();
        $this->import = app(ImportDesInscrits::class);
        $this->activation = app(ActivationDeCompte::class);
    }

    public function test_un_fichier_a_points_virgules_est_lu_et_rattache_a_la_promotion(): void
    {
        $resultat = $this->analyser(<<<'CSV'
        matricule;nom;postnom;prenom;faculte;departement;niveau;role
        ETU-2001;KABILA;Mwamba;Grace;DROIT;DPJ;L1;etudiant
        ETU-2002;NGANDU;Kalume;Josué;DROIT;DPJ;L1;cp
        CSV);

        $this->assertEmpty($resultat['erreurs']);
        $this->assertCount(2, $resultat['lignes']);
        $this->assertSame($this->promotion->id, $resultat['lignes'][0]['promotion_id']);
        $this->assertSame(User::ROLE_CP, $resultat['lignes'][1]['role']);
    }

    /** LibreOffice écrit des virgules là où Excel français met des points-virgules. */
    public function test_un_fichier_a_virgules_est_lu_de_la_meme_facon(): void
    {
        $resultat = $this->analyser(<<<'CSV'
        matricule,nom,faculte,departement,niveau
        ETU-2003,MUKUNA,DROIT,DPJ,L1
        CSV);

        $this->assertEmpty($resultat['erreurs']);
        $this->assertSame('ETU-2003', $resultat['lignes'][0]['matricule']);
    }

    public function test_les_entetes_accentues_ou_en_majuscules_sont_reconnus(): void
    {
        $resultat = $this->analyser(<<<'CSV'
        MATRICULE;NOM;Faculté;Département;Niveau
        ETU-2004;TSHALA;DROIT;DPJ;L1
        CSV);

        $this->assertEmpty($resultat['erreurs']);
        $this->assertSame($this->promotion->id, $resultat['lignes'][0]['promotion_id']);
    }

    public function test_un_fichier_sans_colonne_matricule_est_refuse_en_bloc(): void
    {
        $resultat = $this->analyser("nom;prenom\nKABILA;Grace");

        $this->assertTrue($resultat['lignes']->isEmpty());
        $this->assertStringContainsString('matricule', $resultat['erreurs'][0]);
    }

    /**
     * Une ligne dont la promotion est introuvable revient marquée, avec son
     * motif : le secrétariat corrige son fichier plutôt que de découvrir plus
     * tard qu'une promotion entière manque.
     */
    public function test_une_promotion_introuvable_est_signalee_ligne_par_ligne(): void
    {
        $resultat = $this->analyser(<<<'CSV'
        matricule;nom;faculte;departement;niveau
        ETU-2005;KABILA;DROIT;DPJ;L1
        ETU-2006;NGOY;DROIT;DPJ;L3
        CSV);

        $this->assertCount(1, $resultat['erreurs']);
        $this->assertStringContainsString('L3', $resultat['erreurs'][0]);
        $this->assertTrue($resultat['lignes'][0]['valide']);
        $this->assertFalse($resultat['lignes'][1]['valide']);
    }

    public function test_un_matricule_en_double_dans_le_fichier_est_rejete(): void
    {
        $resultat = $this->analyser(<<<'CSV'
        matricule;nom
        ETU-2007;KABILA
        ETU-2007;KABILA
        CSV);

        $this->assertCount(1, $resultat['lignes']);
        $this->assertStringContainsString('deux fois', $resultat['erreurs'][0]);
    }

    public function test_seules_les_lignes_valides_sont_ecrites(): void
    {
        $resultat = $this->analyser(<<<'CSV'
        matricule;nom;faculte;departement;niveau
        ETU-2008;KABILA;DROIT;DPJ;L1
        ETU-2009;NGOY;DROIT;DPJ;L3
        CSV);

        $bilan = $this->import->importer($resultat['lignes'], $this->annee, $this->chef);

        $this->assertSame(1, $bilan['creees']);
        $this->assertSame(1, InscriptionAutorisee::count());
    }

    public function test_un_reimport_corrige_une_ligne_pas_encore_activee(): void
    {
        $premier = $this->analyser("matricule;nom\nETU-2010;KABILAA");
        $this->import->importer($premier['lignes'], $this->annee, $this->chef);

        $second = $this->analyser("matricule;nom\nETU-2010;KABILA");
        $bilan = $this->import->importer($second['lignes'], $this->annee, $this->chef);

        $this->assertSame(1, $bilan['mises_a_jour']);
        $this->assertSame('KABILA', InscriptionAutorisee::first()->nom);
    }

    /** Une fois le compte ouvert, le réimport ne doit plus rien écraser. */
    public function test_un_reimport_ne_touche_pas_une_inscription_deja_activee(): void
    {
        $resultat = $this->analyser("matricule;nom\nETU-2011;KABILA");
        $this->import->importer($resultat['lignes'], $this->annee, $this->chef);
        $this->activation->activer(InscriptionAutorisee::first(), 'motdepasse');

        $second = $this->analyser("matricule;nom\nETU-2011;AUTRENOM");
        $bilan = $this->import->importer($second['lignes'], $this->annee, $this->chef);

        $this->assertSame(1, $bilan['ignorees']);
        $this->assertSame('KABILA', InscriptionAutorisee::first()->nom);
    }

    public function test_l_activation_ouvre_le_compte_avec_le_rattachement_de_la_liste(): void
    {
        $inscription = InscriptionAutorisee::create([
            'matricule' => 'ETU-3001',
            'annee_academique_id' => $this->annee->id,
            'promotion_id' => $this->promotion->id,
            'nom' => 'MUKENDI',
            'prenom' => 'Sarah',
            'role_prevu' => User::ROLE_ETUDIANT,
        ]);

        $utilisateur = $this->activation->activer($inscription, 'motdepasse');

        $this->assertSame($this->promotion->id, $utilisateur->promotion_id);
        $this->assertSame($this->faculte->id, $utilisateur->faculte_id);
        $this->assertTrue($utilisateur->hasRole(User::ROLE_ETUDIANT));
        $this->assertNotNull($inscription->fresh()->activee_at);
    }

    public function test_un_matricule_absent_de_la_liste_ne_peut_rien_activer(): void
    {
        $this->expectException(ValidationException::class);

        $this->activation->chercher('INCONNU-9999');
    }

    public function test_un_matricule_deja_active_ne_peut_pas_l_etre_deux_fois(): void
    {
        $inscription = InscriptionAutorisee::create([
            'matricule' => 'ETU-3002',
            'annee_academique_id' => $this->annee->id,
            'promotion_id' => $this->promotion->id,
            'nom' => 'BOLIMA',
            'role_prevu' => User::ROLE_ETUDIANT,
        ]);

        $this->activation->activer($inscription, 'motdepasse');

        $this->expectException(ValidationException::class);

        $this->activation->chercher('ETU-3002');
    }

    public function test_la_page_d_activation_est_ouverte_a_tous(): void
    {
        $this->get('/activation')->assertOk()->assertSee('Matricule');
    }

    public function test_seul_un_role_habilite_accede_a_l_import(): void
    {
        $this->actingAs($this->chef)->get('/inscrits')->assertForbidden();

        $vde = $this->creerUtilisateur('VDE-TEST', User::ROLE_VDE);
        $this->actingAs($vde)->get('/inscrits')->assertOk();
    }

    /** @return array{lignes: \Illuminate\Support\Collection, erreurs: list<string>} */
    private function analyser(string $csv): array
    {
        $chemin = tempnam(sys_get_temp_dir(), 'inscrits').'.csv';
        file_put_contents($chemin, preg_replace('/^ +/m', '', $csv));

        $resultat = $this->import->analyser($chemin, $this->annee);
        unlink($chemin);

        return $resultat;
    }
}
