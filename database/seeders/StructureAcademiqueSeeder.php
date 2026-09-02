<?php

namespace Database\Seeders;

use App\Models\AnneeAcademique;
use App\Models\Departement;
use App\Models\Faculte;
use App\Models\Local;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * L'ossature institutionnelle : les treize facultés de l'Université de
 * Kinshasa, leurs departements, et une année académique ouverte.
 *
 * La liste des facultés suit celle publiee par l'université. Les departements
 * retenus sont ceux dont les programmes sont charges par le ProgrammeSeeder ;
 * les autres facultés sont creees sans departement, pretes a etre completees
 * par le secrétariat académique.
 */
class StructureAcademiqueSeeder extends Seeder
{
    /** @var list<array{nom: string, sigle: string, departements?: list<array{nom: string, sigle: string}>}> */
    private const FACULTES = [
        [
            'nom' => 'Faculté de Droit',
            'sigle' => 'DROIT',
            'departements' => [
                ['nom' => 'Droit privé et judiciaire', 'sigle' => 'DPJ'],
                ['nom' => 'Droit public interne et international', 'sigle' => 'DPI'],
                ['nom' => 'Droit économique et social', 'sigle' => 'DES'],
            ],
        ],
        [
            'nom' => 'Faculté de Médecine',
            'sigle' => 'MED',
            'departements' => [
                ['nom' => 'Sciences biomédicales', 'sigle' => 'BIOMED'],
                ['nom' => 'Médecine générale', 'sigle' => 'MG'],
            ],
        ],
        [
            'nom' => 'Faculté Polytechnique',
            'sigle' => 'POLY',
            'departements' => [
                ['nom' => 'Génie civil', 'sigle' => 'GC'],
                ['nom' => 'Génie electrique', 'sigle' => 'GE'],
                ['nom' => 'Génie informatique', 'sigle' => 'GI'],
                ['nom' => 'Génie mécanique', 'sigle' => 'GM'],
            ],
        ],
        [
            'nom' => 'Faculté des Sciences Économiques et de Gestion',
            'sigle' => 'FASEG',
            'departements' => [
                ['nom' => 'Sciences économiques', 'sigle' => 'ECO'],
                ['nom' => 'Sciences de gestion', 'sigle' => 'GESTION'],
            ],
        ],
        ['nom' => 'Faculté des Lettres et Sciences Humaines', 'sigle' => 'FLSH'],
        ['nom' => 'Faculté des Sciences et Technologies', 'sigle' => 'FST'],
        ['nom' => 'Faculté des Sciences Pharmaceutiques', 'sigle' => 'PHARMA'],
        ['nom' => 'Faculté de Médecine Vétérinaire', 'sigle' => 'VETO'],
        ['nom' => 'Faculté de Médecine Bucco-Dentaire', 'sigle' => 'DENT'],
        ['nom' => 'Faculté des Sciences Agronomiques et Environnement', 'sigle' => 'AGRO'],
        ['nom' => 'Faculté de Psychologie et des Sciences de l\'Éducation', 'sigle' => 'FPSE'],
        ['nom' => 'Faculté des Sciences Sociales, Administratives et Politiques', 'sigle' => 'FSSAP'],
        ['nom' => 'Faculté de Pétrole, Gaz et Énergies Renouvelables', 'sigle' => 'FPGER'],
    ];

    public function run(): void
    {
        $this->ouvrirAnneeAcademique();

        foreach (self::FACULTES as $ordre => $donnees) {
            $faculte = Faculte::updateOrCreate(
                ['sigle' => $donnees['sigle']],
                [
                    'nom' => $donnees['nom'],
                    'slug' => Str::slug($donnees['nom']),
                    'ordre' => $ordre,
                    'active' => true,
                ],
            );

            foreach ($donnees['departements'] ?? [] as $departement) {
                Departement::updateOrCreate(
                    ['faculte_id' => $faculte->id, 'sigle' => $departement['sigle']],
                    ['nom' => $departement['nom'], 'actif' => true],
                );
            }

            $this->creerLocaux($faculte);
        }
    }

    /**
     * Ouvre l'année académique qui contient la date du jour, sur le calendrier
     * congolais : rentree a la mi-octobre, cloture fin juillet.
     *
     * Le seeder de demonstration s'appuie sur ces bornes pour situer les
     * seances. En production, ces dates sont saisies par le secrétariat
     * académique et non deduites.
     */
    private function ouvrirAnneeAcademique(): AnneeAcademique
    {
        $rentree = now()->month >= 10
            ? now()->copy()->setDate(now()->year, 10, 15)
            : now()->copy()->setDate(now()->year - 1, 10, 15);

        $cloture = $rentree->copy()->addYear()->setDate($rentree->year + 1, 7, 31);

        return AnneeAcademique::updateOrCreate(
            ['libelle' => $rentree->year.'-'.($rentree->year + 1)],
            [
                'date_debut' => $rentree->toDateString(),
                'date_fin' => $cloture->toDateString(),
                'statut' => 'en_cours',
                'active' => true,
            ],
        );
    }

    /** Quelques salles par faculté, pour que la saisie ait ou se poser. */
    private function creerLocaux(Faculte $faculte): void
    {
        $salles = [
            ['nom' => 'Auditoire A', 'capacite' => 400],
            ['nom' => 'Auditoire B', 'capacite' => 250],
            ['nom' => 'Salle 1', 'capacite' => 80],
            ['nom' => 'Laboratoire', 'capacite' => 40],
        ];

        foreach ($salles as $salle) {
            Local::updateOrCreate(
                ['faculte_id' => $faculte->id, 'nom' => $salle['nom']],
                ['batiment' => $faculte->sigle, 'capacite' => $salle['capacite'], 'actif' => true],
            );
        }
    }
}
