<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\User;
use App\Services\Messagerie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\ConstruitUnContexteAcademique;
use Tests\TestCase;

/**
 * La messagerie et son cadrage hiérarchique.
 *
 * Sans ce cadrage, un millier d'étudiants pourraient écrire directement au
 * vice-recteur : la messagerie deviendrait inutilisable pour lui, donc
 * inutilisable tout court.
 */
class MessagerieTest extends TestCase
{
    use ConstruitUnContexteAcademique, RefreshDatabase;

    private Messagerie $messagerie;

    private User $etudiant;

    private User $vde;

    protected function setUp(): void
    {
        parent::setUp();

        $this->construireContexte();
        $this->messagerie = app(Messagerie::class);

        $this->etudiant = $this->creerUtilisateur('ETU-MSG', User::ROLE_ETUDIANT);
        $this->etudiant->update([
            'promotion_id' => $this->promotion->id,
            'faculte_id' => $this->faculte->id,
        ]);
        $this->etudiant = $this->etudiant->fresh();

        $this->vde = $this->creerUtilisateur('VDE-MSG', User::ROLE_VDE);
    }

    public function test_le_chef_de_promotion_ecrit_a_son_enseignant(): void
    {
        $conversation = $this->messagerie->ouvrirAvec($this->chef, $this->enseignant);

        $this->assertSame(2, $conversation->participants()->count());
        $this->assertTrue($conversation->compte($this->chef));
        $this->assertTrue($conversation->compte($this->enseignant));
    }

    /** Deux personnes n'ont qu'un fil : rouvrir ne doit pas disperser l'échange. */
    public function test_rouvrir_une_conversation_retrouve_la_meme(): void
    {
        $premiere = $this->messagerie->ouvrirAvec($this->chef, $this->enseignant);
        $seconde = $this->messagerie->ouvrirAvec($this->enseignant, $this->chef);

        $this->assertSame($premiere->id, $seconde->id);
        $this->assertSame(1, Conversation::count());
    }

    public function test_un_etudiant_ne_peut_pas_ecrire_au_vice_recteur(): void
    {
        $this->expectException(ValidationException::class);

        $this->messagerie->ouvrirAvec($this->etudiant, $this->vde);
    }

    public function test_un_etudiant_ecrit_a_son_chef_de_promotion(): void
    {
        $conversation = $this->messagerie->ouvrirAvec($this->etudiant, $this->chef);

        $this->assertTrue($conversation->compte($this->etudiant));
    }

    public function test_le_chef_de_promotion_atteint_le_vice_recteur(): void
    {
        $conversation = $this->messagerie->ouvrirAvec($this->chef, $this->vde);

        $this->assertTrue($conversation->compte($this->vde));
    }

    public function test_envoyer_un_message_le_rattache_au_fil(): void
    {
        $conversation = $this->messagerie->ouvrirAvec($this->chef, $this->enseignant);

        $message = $this->messagerie->envoyer($this->chef, $conversation, 'La séance de mardi a été reportée.');

        $this->assertSame($conversation->id, $message->conversation_id);
        $this->assertNotNull($conversation->fresh()->dernier_message_at);
    }

    public function test_un_tiers_ne_peut_pas_ecrire_dans_un_fil_qui_n_est_pas_le_sien(): void
    {
        $conversation = $this->messagerie->ouvrirAvec($this->chef, $this->enseignant);

        $this->expectException(ValidationException::class);

        $this->messagerie->envoyer($this->etudiant, $conversation, 'Je m\'invite.');
    }

    public function test_le_destinataire_compte_un_message_non_lu(): void
    {
        $conversation = $this->messagerie->ouvrirAvec($this->chef, $this->enseignant);
        $this->messagerie->envoyer($this->chef, $conversation, 'Bonjour professeur.');

        $this->assertSame(1, $this->messagerie->nonLus($this->enseignant));
    }

    /** L'auteur a forcément lu ce qu'il vient d'écrire. */
    public function test_son_propre_message_ne_compte_pas_comme_non_lu(): void
    {
        $conversation = $this->messagerie->ouvrirAvec($this->chef, $this->enseignant);
        $this->messagerie->envoyer($this->chef, $conversation, 'Bonjour professeur.');

        $this->assertSame(0, $this->messagerie->nonLus($this->chef));
    }

    public function test_ouvrir_le_fil_solde_les_non_lus(): void
    {
        $conversation = $this->messagerie->ouvrirAvec($this->chef, $this->enseignant);
        $this->messagerie->envoyer($this->chef, $conversation, 'Bonjour professeur.');

        $this->messagerie->marquerLu($this->enseignant, $conversation);

        $this->assertSame(0, $this->messagerie->nonLus($this->enseignant));
    }

    public function test_les_destinataires_proposes_suivent_les_roles_autorises(): void
    {
        $proposes = $this->messagerie->destinatairesPossibles($this->etudiant);

        $this->assertTrue($proposes->contains('id', $this->chef->id));
        $this->assertFalse($proposes->contains('id', $this->vde->id));
    }

    /** Le cadrage par rôle ne suffit pas : le périmètre compte aussi. */
    public function test_un_etudiant_ne_voit_que_les_chefs_de_sa_promotion(): void
    {
        $chefAilleurs = $this->creerUtilisateur('CP-AILLEURS-MSG', User::ROLE_CP);

        $proposes = $this->messagerie->destinatairesPossibles($this->etudiant);

        $this->assertTrue($proposes->contains('id', $this->chef->id));
        $this->assertFalse($proposes->contains('id', $chefAilleurs->id));
    }

    public function test_la_recherche_filtre_par_nom_ou_matricule(): void
    {
        $proposes = $this->messagerie->destinatairesPossibles($this->etudiant, 'CP-001');

        $this->assertCount(1, $proposes);
        $this->assertSame($this->chef->id, $proposes->first()->id);
    }

    /**
     * PostgreSQL rend LIKE sensible à la casse, là où SQLite ne l'est pas.
     * Chercher « ilunga » doit trouver ILUNGA : personne ne tape un nom de
     * famille en capitales.
     */
    public function test_la_recherche_ignore_la_casse(): void
    {
        foreach (['KABEYA', 'kabeya', 'Kabeya', 'kab'] as $saisie) {
            $this->assertTrue(
                $this->messagerie->destinatairesPossibles($this->etudiant, $saisie)
                    ->contains('id', $this->chef->id),
                "La recherche « {$saisie} » n'a pas retrouvé le chef de promotion.",
            );
        }
    }

    public function test_la_recherche_par_matricule_ignore_aussi_la_casse(): void
    {
        $trouves = $this->messagerie->destinatairesPossibles($this->etudiant, 'cp-001');

        $this->assertTrue($trouves->contains('id', $this->chef->id));
    }

    public function test_l_ecran_s_ouvre(): void
    {
        $this->actingAs($this->chef)->get('/messages')->assertOk()->assertSee('Messages');
    }
}
