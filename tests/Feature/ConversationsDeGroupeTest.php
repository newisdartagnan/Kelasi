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
 * Les fils de promotion et de cours.
 *
 * Le point délicat : la composition d'une promotion bouge en cours d'année.
 * Un fil dont la liste serait figée à l'ouverture laisserait dehors ceux qui
 * s'inscrivent après.
 */
class ConversationsDeGroupeTest extends TestCase
{
    use ConstruitUnContexteAcademique, RefreshDatabase;

    private Messagerie $messagerie;

    private User $etudiant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->construireContexte();
        $this->messagerie = app(Messagerie::class);

        $this->etudiant = $this->creerUtilisateur('ETU-GRP', User::ROLE_ETUDIANT);
        $this->etudiant->update([
            'promotion_id' => $this->promotion->id,
            'faculte_id' => $this->faculte->id,
        ]);
        $this->etudiant = $this->etudiant->fresh();
    }

    public function test_le_fil_de_promotion_reunit_inscrits_et_enseignants(): void
    {
        $fil = $this->messagerie->ouvrirFilDePromotion($this->chef, $this->promotion);

        $membres = $fil->participants()->pluck('user_id');

        $this->assertTrue($membres->contains($this->chef->id));
        $this->assertTrue($membres->contains($this->etudiant->id));
        $this->assertTrue($membres->contains($this->enseignant->id));
    }

    public function test_rouvrir_le_fil_retrouve_le_meme(): void
    {
        $premier = $this->messagerie->ouvrirFilDePromotion($this->chef, $this->promotion);
        $second = $this->messagerie->ouvrirFilDePromotion($this->etudiant, $this->promotion);

        $this->assertSame($premier->id, $second->id);
        $this->assertSame(1, Conversation::where('type', 'promotion')->count());
    }

    /** Une promotion gagne des inscrits : le fil doit les rattraper. */
    public function test_un_inscrit_tardif_rejoint_le_fil_existant(): void
    {
        $fil = $this->messagerie->ouvrirFilDePromotion($this->chef, $this->promotion);
        $avant = $fil->participants()->count();

        $tardif = $this->creerUtilisateur('ETU-TARDIF', User::ROLE_ETUDIANT);
        $tardif->update(['promotion_id' => $this->promotion->id, 'faculte_id' => $this->faculte->id]);

        $this->messagerie->ouvrirFilDePromotion($this->chef, $this->promotion);

        $this->assertSame($avant + 1, $fil->fresh()->participants()->count());
        $this->assertTrue($fil->participants()->pluck('user_id')->contains($tardif->id));
    }

    /**
     * Réajuster ne doit pas réécrire la liste : les marqueurs de lecture de
     * chacun disparaîtraient, et tout le fil repasserait en non lu.
     */
    public function test_le_reajustement_preserve_les_marqueurs_de_lecture(): void
    {
        $fil = $this->messagerie->ouvrirFilDePromotion($this->chef, $this->promotion);
        $this->messagerie->envoyer($this->chef, $fil, 'Premier message.');
        $this->messagerie->marquerLu($this->etudiant, $fil);

        $lecture = $fil->participants()->where('user_id', $this->etudiant->id)->value('lu_jusqu_a');

        $this->messagerie->ouvrirFilDePromotion($this->chef, $this->promotion);

        $this->assertEquals(
            $lecture,
            $fil->fresh()->participants()->where('user_id', $this->etudiant->id)->value('lu_jusqu_a'),
        );
    }

    public function test_le_fil_de_cours_est_distinct_de_celui_de_la_promotion(): void
    {
        $promotionFil = $this->messagerie->ouvrirFilDePromotion($this->chef, $this->promotion);
        $coursFil = $this->messagerie->ouvrirFilDeCours($this->enseignant, $this->cours);

        $this->assertNotSame($promotionFil->id, $coursFil->id);
        $this->assertSame($this->cours->id, $coursFil->cours_id);
        $this->assertSame($this->cours->intitule, $coursFil->sujet);
    }

    public function test_un_etranger_a_la_promotion_ne_peut_pas_ouvrir_son_fil(): void
    {
        $etranger = $this->creerUtilisateur('ETU-AILLEURS-GRP', User::ROLE_ETUDIANT);

        $this->expectException(ValidationException::class);

        $this->messagerie->ouvrirFilDePromotion($etranger, $this->promotion);
    }

    public function test_l_enseignant_attribue_ouvre_le_fil_de_son_cours(): void
    {
        $fil = $this->messagerie->ouvrirFilDeCours($this->enseignant, $this->cours);

        $this->assertTrue($fil->compte($this->enseignant));
    }

    public function test_les_fils_possibles_suivent_le_rattachement(): void
    {
        $possibles = $this->messagerie->filsDeGroupePossibles($this->etudiant);

        $this->assertTrue($possibles->contains(
            fn (array $f) => $f['type'] === 'promotion' && $f['cle'] === $this->promotion->id,
        ));
        $this->assertTrue($possibles->contains(
            fn (array $f) => $f['type'] === 'cours' && $f['cle'] === $this->cours->id,
        ));
    }

    public function test_un_compte_sans_rattachement_n_a_aucun_fil_possible(): void
    {
        $isole = $this->creerUtilisateur('ETU-ISOLE', User::ROLE_ETUDIANT);

        $this->assertTrue($this->messagerie->filsDeGroupePossibles($isole)->isEmpty());
    }

    public function test_le_fil_de_groupe_apparait_dans_la_liste_de_ses_membres(): void
    {
        $fil = $this->messagerie->ouvrirFilDePromotion($this->chef, $this->promotion);
        $this->messagerie->envoyer($this->chef, $fil, 'Bonjour à toutes et à tous.');

        $this->assertSame(1, $this->messagerie->nonLus($this->etudiant));
        $this->assertTrue($fil->estDeGroupe());
        $this->assertSame($this->promotion->nom_complet, $fil->titrePour($this->etudiant));
    }

    public function test_l_ecran_affiche_les_fils_de_groupe(): void
    {
        $fil = $this->messagerie->ouvrirFilDePromotion($this->chef, $this->promotion);
        $this->messagerie->envoyer($this->chef, $fil, 'Bonjour à toutes et à tous.');

        $this->actingAs($this->etudiant)
            ->get('/messages')
            ->assertOk()
            ->assertSee($this->promotion->nom_complet);
    }
}
