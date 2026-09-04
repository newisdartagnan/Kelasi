<?php

namespace App\Services;

use App\Models\AnneeAcademique;
use App\Models\Cours;
use App\Models\Faculte;
use App\Models\Promotion;
use App\Support\Avancement;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * L'export de l'avancement, pour le vice-recteur.
 *
 * Le classeur comporte deux feuilles : une synthèse par faculté, qu'on lit
 * d'un coup d'œil, et le détail cours par cours, qui sert à instruire. La
 * seconde reprend les colonnes brutes -- heures prévues, réalisées, écart --
 * pour que le destinataire puisse trier et filtrer lui-même plutôt que de
 * redemander un autre export.
 */
class ExportDeLAvancement
{
    private const ENTETE = 'FF1E3A8A';

    private const ALERTE = 'FFFEF2F2';

    public function __construct(private readonly CalculateurAvancement $calculateur) {}

    /** Écrit le classeur et rend son chemin. */
    public function produire(?AnneeAcademique $annee = null, ?int $semestre = null, ?Faculte $faculte = null): string
    {
        $annee ??= AnneeAcademique::courante();
        $tauxAttendu = $this->calculateur->tauxAttendu($annee);

        $classeur = new Spreadsheet;
        $classeur->getProperties()
            ->setCreator('Kelasi')
            ->setTitle('Avancement des enseignements')
            ->setSubject($annee?->libelle ?? '');

        $facultes = $faculte
            ? collect([$faculte])
            : Faculte::whereHas('departements.promotions')->orderBy('ordre')->get();

        $this->feuilleSynthese($classeur, $facultes, $annee, $semestre, $tauxAttendu);
        $this->feuilleDetail($classeur, $facultes, $annee, $semestre, $tauxAttendu);

        $classeur->setActiveSheetIndex(0);

        $chemin = tempnam(sys_get_temp_dir(), 'kelasi').'.xlsx';
        (new Xlsx($classeur))->save($chemin);

        return $chemin;
    }

    public function nomDuFichier(?AnneeAcademique $annee, ?int $semestre): string
    {
        $morceaux = ['avancement', $annee?->libelle ?? 'sans-annee'];

        if ($semestre) {
            $morceaux[] = "semestre-{$semestre}";
        }

        $morceaux[] = now()->format('Y-m-d');

        return implode('_', $morceaux).'.xlsx';
    }

    private function feuilleSynthese(
        Spreadsheet $classeur,
        $facultes,
        ?AnneeAcademique $annee,
        ?int $semestre,
        float $tauxAttendu,
    ): void {
        $feuille = $classeur->getActiveSheet();
        $feuille->setTitle('Synthèse');

        $feuille->setCellValue('A1', 'Avancement des enseignements');
        $feuille->mergeCells('A1:G1');
        $feuille->getStyle('A1')->getFont()->setBold(true)->setSize(14);

        $sousTitre = sprintf(
            'Année %s · %s · %s%% de la période écoulée · édité le %s',
            $annee?->libelle ?? '—',
            $semestre ? "semestre {$semestre}" : 'année entière',
            $tauxAttendu,
            now()->translatedFormat('d/m/Y'),
        );
        $feuille->setCellValue('A2', $sousTitre);
        $feuille->mergeCells('A2:G2');
        $feuille->getStyle('A2')->getFont()->setItalic(true)->getColor()->setARGB('FF64748B');

        $colonnes = ['Faculté', 'Promotions', 'Heures prévues', 'Heures contresignées', 'En attente', 'Avancement', 'Écart'];
        $this->ecrireEntete($feuille, $colonnes, 4);

        $ligne = 5;
        $total = Avancement::vide();

        foreach ($facultes as $uneFaculte) {
            $parPromotion = $this->calculateur->parPromotionDeFaculte($uneFaculte, $annee, $semestre);
            $avancement = $parPromotion->reduce(
                fn (Avancement $porte, Avancement $a) => $porte->plus($a),
                Avancement::vide(),
            );
            $total = $total->plus($avancement);

            $this->ecrireLigne($feuille, $ligne, [
                $uneFaculte->nom,
                $parPromotion->count(),
                $avancement->heuresPrevues(),
                $avancement->heuresRealisees(),
                $avancement->heuresEnAttente(),
                $avancement->tauxReel() / 100,
                $avancement->ecartSurAttendu($tauxAttendu),
            ], $tauxAttendu);

            $ligne++;
        }

        $this->ecrireLigne($feuille, $ligne, [
            'Total',
            '',
            $total->heuresPrevues(),
            $total->heuresRealisees(),
            $total->heuresEnAttente(),
            $total->tauxReel() / 100,
            $total->ecartSurAttendu($tauxAttendu),
        ], $tauxAttendu);
        $feuille->getStyle("A{$ligne}:G{$ligne}")->getFont()->setBold(true);

        $this->finaliser($feuille, 'G', $ligne, entete: 4);
    }

    private function feuilleDetail(
        Spreadsheet $classeur,
        $facultes,
        ?AnneeAcademique $annee,
        ?int $semestre,
        float $tauxAttendu,
    ): void {
        $feuille = $classeur->createSheet();
        $feuille->setTitle('Détail par cours');

        $colonnes = [
            'Faculté', 'Département', 'Promotion', 'Semestre', 'UE', 'Code', 'Cours',
            'Crédits', 'Heures prévues', 'Heures contresignées', 'En attente', 'Avancement', 'Écart',
        ];
        $this->ecrireEntete($feuille, $colonnes, 1);

        $ligne = 2;

        foreach ($facultes as $uneFaculte) {
            $promotions = Promotion::with('departement')
                ->whereHas('departement', fn ($q) => $q->where('faculte_id', $uneFaculte->id))
                ->when($annee, fn ($q) => $q->where('annee_academique_id', $annee->id))
                ->active()
                ->orderBy('niveau')
                ->get();

            foreach ($promotions as $promotion) {
                $avancements = $this->calculateur->parCoursDePromotion($promotion, $semestre);

                $cours = Cours::with('uniteEnseignement')
                    ->whereIn('id', $avancements->keys())
                    ->get()
                    ->sortBy(['uniteEnseignement.semestre', 'uniteEnseignement.ordre', 'code']);

                foreach ($cours as $unCours) {
                    $avancement = $avancements[$unCours->id];

                    $this->ecrireLigne($feuille, $ligne, [
                        $uneFaculte->sigle,
                        $promotion->departement->sigle,
                        $promotion->niveau,
                        $unCours->uniteEnseignement->semestre,
                        $unCours->uniteEnseignement->code,
                        $unCours->code,
                        $unCours->intitule,
                        $unCours->credits,
                        $avancement->heuresPrevues(),
                        $avancement->heuresRealisees(),
                        $avancement->heuresEnAttente(),
                        $avancement->tauxReel() / 100,
                        $avancement->ecartSurAttendu($tauxAttendu),
                    ], $tauxAttendu, colonneTaux: 'L', colonneEcart: 'M');

                    $ligne++;
                }
            }
        }

        $this->finaliser($feuille, 'M', $ligne - 1, entete: 1);

        // Le destinataire trie et filtre lui-même plutôt que de redemander un
        // autre export.
        if ($ligne > 2) {
            $feuille->setAutoFilter("A1:M".($ligne - 1));
        }
    }

    /** @param  list<string>  $colonnes */
    private function ecrireEntete($feuille, array $colonnes, int $ligne): void
    {
        foreach ($colonnes as $index => $titre) {
            $feuille->setCellValue([$index + 1, $ligne], $titre);
        }

        $derniere = chr(ord('A') + count($colonnes) - 1);
        $style = $feuille->getStyle("A{$ligne}:{$derniere}{$ligne}");
        $style->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
        $style->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::ENTETE);
        $style->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $feuille->getRowDimension($ligne)->setRowHeight(22);
        $feuille->freezePane("A".($ligne + 1));
    }

    /** @param  list<mixed>  $valeurs */
    private function ecrireLigne(
        $feuille,
        int $ligne,
        array $valeurs,
        float $tauxAttendu,
        string $colonneTaux = 'F',
        string $colonneEcart = 'G',
    ): void {
        foreach ($valeurs as $index => $valeur) {
            $feuille->setCellValue([$index + 1, $ligne], $valeur);
        }

        $feuille->getStyle("{$colonneTaux}{$ligne}")->getNumberFormat()->setFormatCode('0.0%');

        // Un retard de plus de dix points saute aux yeux dans le tableur comme
        // il saute aux yeux dans l'application.
        $ecart = (float) end($valeurs);

        if ($ecart < -10) {
            $feuille->getStyle("A{$ligne}:{$colonneEcart}{$ligne}")
                ->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()
                ->setARGB(self::ALERTE);
        }
    }

    private function finaliser($feuille, string $derniereColonne, int $derniereLigne, int $entete): void
    {
        foreach (range('A', $derniereColonne) as $colonne) {
            $feuille->getColumnDimension($colonne)->setAutoSize(true);
        }

        if ($derniereLigne >= $entete) {
            $feuille->getStyle("A{$entete}:{$derniereColonne}{$derniereLigne}")
                ->getBorders()
                ->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)
                ->getColor()
                ->setARGB('FFE2E8F0');
        }
    }
}
