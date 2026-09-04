<?php

namespace Tests\Feature;

use App\Models\Document;
use App\Models\User;
use App\Services\BibliothequeDeCours;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\ConstruitUnContexteAcademique;
use Tests\TestCase;

/**
 * Les supports de cours.
 *
 * Le point sensible : le téléchargement passe par l'application. Un lien
 * deviné ne doit pas suffire à récupérer le support d'un cours qu'on ne suit
 * pas.
 */
class DocumentsTest extends TestCase
{
    use ConstruitUnContexteAcademique, RefreshDatabase;

    private BibliothequeDeCours $bibliotheque;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(BibliothequeDeCours::DISQUE);

        $this->construireContexte();
        $this->bibliotheque = app(BibliothequeDeCours::class);
    }

    public function test_l_enseignant_attribue_depose_un_support(): void
    {
        $document = $this->deposer();

        $this->assertSame($this->cours->id, $document->cours_id);
        $this->assertTrue($document->publie);
        Storage::disk(BibliothequeDeCours::DISQUE)->assertExists($document->chemin);
    }

    public function test_un_enseignant_etranger_au_cours_ne_depose_rien(): void
    {
        $autre = $this->creerUtilisateur('ENS-ETRANGER-DOC', User::ROLE_ENSEIGNANT);

        $this->expectException(ValidationException::class);

        $this->bibliotheque->deposer(
            $autre,
            $this->cours,
            UploadedFile::fake()->create('cours.pdf', 100, 'application/pdf'),
            ['titre' => 'Support'],
        );
    }

    public function test_l_etudiant_de_la_promotion_telecharge(): void
    {
        $document = $this->deposer();
        $etudiant = $this->etudiantDeLaPromotion();

        $this->assertTrue($this->bibliotheque->peutTelecharger($etudiant, $document));

        $this->actingAs($etudiant)
            ->get(route('documents.telecharger', $document))
            ->assertOk();

        $this->assertSame(1, $document->fresh()->telechargements);
    }

    /** Le contrôle porte sur la requête, pas seulement sur l'affichage du lien. */
    public function test_un_etudiant_d_une_autre_promotion_est_refuse(): void
    {
        $document = $this->deposer();
        $etranger = $this->creerUtilisateur('ETU-AILLEURS-DOC', User::ROLE_ETUDIANT);

        $this->assertFalse($this->bibliotheque->peutTelecharger($etranger, $document));

        $this->actingAs($etranger)
            ->get(route('documents.telecharger', $document))
            ->assertForbidden();
    }

    public function test_un_document_non_publie_reste_invisible_des_etudiants(): void
    {
        $document = $this->deposer(['publie' => false]);
        $etudiant = $this->etudiantDeLaPromotion();

        $this->assertFalse($this->bibliotheque->peutTelecharger($etudiant, $document));
        $this->assertTrue($this->bibliotheque->peutTelecharger($this->enseignant, $document));
    }

    public function test_publier_ouvre_l_acces_a_la_promotion(): void
    {
        $document = $this->deposer(['publie' => false]);
        $etudiant = $this->etudiantDeLaPromotion();

        $this->bibliotheque->basculerPublication($this->enseignant, $document);

        $this->assertTrue($this->bibliotheque->peutTelecharger($etudiant, $document->fresh()));
    }

    public function test_seul_le_deposant_bascule_la_publication(): void
    {
        $document = $this->deposer();

        $this->expectException(ValidationException::class);

        $this->bibliotheque->basculerPublication($this->etudiantDeLaPromotion(), $document);
    }

    /** Retirer un document doit aussi effacer le fichier : rien ne traîne. */
    public function test_retirer_efface_le_fichier_du_disque(): void
    {
        $document = $this->deposer();
        $chemin = $document->chemin;

        $this->bibliotheque->retirer($this->enseignant, $document);

        Storage::disk(BibliothequeDeCours::DISQUE)->assertMissing($chemin);
        $this->assertSame(0, Document::count());
    }

    public function test_un_tiers_ne_supprime_pas_le_document_d_un_autre(): void
    {
        $document = $this->deposer();

        $this->expectException(ValidationException::class);

        $this->bibliotheque->retirer($this->etudiantDeLaPromotion(), $document);
    }

    public function test_le_doyen_de_la_faculte_accede_aux_supports(): void
    {
        $document = $this->deposer();

        $doyen = $this->creerUtilisateur('DF-DOC', User::ROLE_DF);
        $doyen->update(['faculte_id' => $this->faculte->id]);

        $this->assertTrue($this->bibliotheque->peutTelecharger($doyen->fresh(), $document));
    }

    public function test_l_ecran_s_ouvre_pour_l_enseignant_et_pour_l_etudiant(): void
    {
        $this->deposer();

        $this->actingAs($this->enseignant)->get('/documents')->assertOk()->assertSee('Déposer un document');
        $this->actingAs($this->etudiantDeLaPromotion())->get('/documents')->assertOk()->assertSee('Télécharger');
    }

    /** @param  array<string, mixed>  $donnees */
    private function deposer(array $donnees = []): Document
    {
        return $this->bibliotheque->deposer(
            $this->enseignant,
            $this->cours,
            UploadedFile::fake()->create('syllabus.pdf', 120, 'application/pdf'),
            array_merge(['titre' => 'Syllabus — chapitres 1 à 4'], $donnees),
        );
    }

    private function etudiantDeLaPromotion(): User
    {
        $etudiant = User::where('matricule', 'ETU-DOC')->first()
            ?? $this->creerUtilisateur('ETU-DOC', User::ROLE_ETUDIANT);

        $etudiant->update([
            'promotion_id' => $this->promotion->id,
            'faculte_id' => $this->faculte->id,
        ]);

        return $etudiant->fresh();
    }
}
