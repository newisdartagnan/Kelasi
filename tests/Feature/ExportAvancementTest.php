<?php

namespace Tests\Feature;

use App\Models\Faculte;
use App\Models\User;
use App\Services\ExportDeLAvancement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\ConstruitUnContexteAcademique;
use Tests\TestCase;

/**
 * L'export du classeur d'avancement.
 *
 * Le point à ne pas rater : la portée du fichier doit suivre celle de
 * l'écran. Sinon l'export devient le moyen de contourner les habilitations.
 */
class ExportAvancementTest extends TestCase
{
    use ConstruitUnContexteAcademique, RefreshDatabase;

    private ExportDeLAvancement $export;

    private User $vde;

    protected function setUp(): void
    {
        parent::setUp();

        $this->construireContexte();
        $this->export = app(ExportDeLAvancement::class);
        $this->vde = $this->creerUtilisateur('VDE-EXP', User::ROLE_VDE);
    }

    public function test_le_classeur_porte_les_deux_feuilles_attendues(): void
    {
        $classeur = $this->lire($this->export->produire($this->annee));

        $this->assertSame(['Synthèse', 'Détail par cours'], $classeur->getSheetNames());
    }

    public function test_la_synthese_liste_les_facultes_et_totalise(): void
    {
        $feuille = $this->lire($this->export->produire($this->annee))->getSheetByName('Synthèse');

        $this->assertSame('Faculté', $feuille->getCell('A4')->getValue());
        $this->assertSame($this->faculte->nom, $feuille->getCell('A5')->getValue());
        $this->assertSame('Total', $feuille->getCell('A6')->getValue());
    }

    public function test_le_detail_descend_au_cours(): void
    {
        $feuille = $this->lire($this->export->produire($this->annee))->getSheetByName('Détail par cours');

        $this->assertSame('Cours', $feuille->getCell('G1')->getValue());
        $this->assertSame($this->cours->intitule, $feuille->getCell('G2')->getValue());
        $this->assertSame($this->cours->heures_prevues, (int) $feuille->getCell('I2')->getValue());
    }

    public function test_l_export_peut_se_limiter_a_une_faculte(): void
    {
        $autre = Faculte::create(['nom' => 'Faculté de Médecine', 'sigle' => 'MED', 'slug' => 'med-exp']);

        $feuille = $this->lire($this->export->produire($this->annee, null, $this->faculte))
            ->getSheetByName('Synthèse');

        $this->assertSame($this->faculte->nom, $feuille->getCell('A5')->getValue());
        $this->assertSame('Total', $feuille->getCell('A6')->getValue());
        $this->assertNotSame($autre->nom, $feuille->getCell('A5')->getValue());
    }

    public function test_le_vice_recteur_telecharge_le_classeur(): void
    {
        $this->actingAs($this->vde)
            ->get(route('avancement.export'))
            ->assertOk()
            ->assertHeader(
                'content-type',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            );
    }

    public function test_un_chef_de_promotion_n_exporte_rien(): void
    {
        $this->actingAs($this->chef)->get(route('avancement.export'))->assertForbidden();
    }

    /** Le doyen exporte sa faculté, quoi qu'il demande dans l'URL. */
    public function test_le_doyen_ne_peut_pas_exporter_une_autre_faculte(): void
    {
        $autre = Faculte::create(['nom' => 'Faculté de Médecine', 'sigle' => 'MED2', 'slug' => 'med2-exp']);

        $doyen = $this->creerUtilisateur('DF-EXP', User::ROLE_DF);
        $doyen->update(['faculte_id' => $this->faculte->id]);

        $reponse = $this->actingAs($doyen->fresh())
            ->get(route('avancement.export', ['faculte' => $autre->id]));

        $reponse->assertOk();

        $feuille = $this->lire($this->cheminTelecharge($reponse))->getSheetByName('Synthèse');
        $this->assertSame($this->faculte->nom, $feuille->getCell('A5')->getValue());
    }

    public function test_le_nom_du_fichier_porte_l_annee_et_le_semestre(): void
    {
        $nom = $this->export->nomDuFichier($this->annee, 1);

        $this->assertStringContainsString($this->annee->libelle, $nom);
        $this->assertStringContainsString('semestre-1', $nom);
        $this->assertStringEndsWith('.xlsx', $nom);
    }

    private function lire(string $chemin)
    {
        return IOFactory::load($chemin);
    }

    /** La réponse est diffusée : on la capture pour la relire comme un fichier. */
    private function cheminTelecharge($reponse): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'export').'.xlsx';
        file_put_contents($chemin, $reponse->streamedContent() ?: $reponse->getContent());

        return $chemin;
    }
}
