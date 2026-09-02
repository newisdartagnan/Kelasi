<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\ConstruitUnContexteAcademique;
use Tests\TestCase;

/**
 * Les écrans s'ouvrent, et le périmètre de lecture suit la fonction.
 */
class EcransTest extends TestCase
{
    use ConstruitUnContexteAcademique, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->construireContexte();
    }

    public function test_un_visiteur_est_renvoye_vers_la_connexion(): void
    {
        $this->get('/')->assertRedirect('/connexion');
    }

    public function test_la_page_de_connexion_s_affiche(): void
    {
        $this->get('/connexion')->assertOk()->assertSee('Matricule');
    }

    public function test_on_se_connecte_avec_son_matricule(): void
    {
        $this->post('/connexion', ['matricule' => 'CP-001', 'password' => 'motdepasse'])
            ->assertRedirect('/');

        $this->assertAuthenticatedAs($this->chef);
    }

    public function test_un_compte_suspendu_ne_peut_pas_entrer(): void
    {
        $this->chef->update(['suspendu_at' => now(), 'motif_suspension' => 'Enquête en cours']);

        $this->post('/connexion', ['matricule' => 'CP-001', 'password' => 'motdepasse'])
            ->assertSessionHasErrors('matricule');

        $this->assertGuest();
    }

    public function test_le_tableau_de_bord_s_ouvre_pour_chaque_role(): void
    {
        foreach ([$this->chef, $this->enseignant] as $utilisateur) {
            $this->actingAs($utilisateur)->get('/')->assertOk();
        }
    }

    public function test_le_chef_de_promotion_accede_a_la_saisie(): void
    {
        $this->actingAs($this->chef)
            ->get('/seances/saisir')
            ->assertOk()
            ->assertSee('Saisir une séance');
    }

    public function test_l_enseignant_voit_sa_file_de_contreseing(): void
    {
        $this->actingAs($this->enseignant)
            ->get('/seances/a-valider')
            ->assertOk()
            ->assertSee('contresigner', false);
    }

    public function test_le_journal_s_ouvre(): void
    {
        $this->actingAs($this->chef)->get('/seances/journal')->assertOk();
    }

    public function test_la_page_hors_ligne_reste_accessible_sans_compte(): void
    {
        $this->get('/hors-ligne')->assertOk();
    }

    public function test_un_etudiant_ne_voit_pas_le_lien_de_saisie(): void
    {
        $etudiant = $this->creerUtilisateur('ETU-100', User::ROLE_ETUDIANT);
        $etudiant->update(['promotion_id' => $this->promotion->id]);

        $this->actingAs($etudiant)->get('/')->assertOk()->assertDontSee('/seances/saisir');
    }
}
