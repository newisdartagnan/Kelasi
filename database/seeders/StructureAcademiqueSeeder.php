<?php

namespace Database\Seeders;

use App\Models\AnneeAcademique;
use App\Models\Departement;
use App\Models\Faculte;
use App\Models\Local;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * L'ossature institutionnelle : les treize facultes de l'Universite de
 * Kinshasa, leurs departements, et une annee academique ouverte.
 *
 * La liste des facultes suit celle publiee par l'universite. Les departements
 * retenus sont ceux dont les programmes sont charges par le ProgrammeSeeder ;
 * les autres facultes sont creees sans departement, pretes a etre completees
 * par le secretariat academique.
 */
class StructureAcademiqueSeeder extends Seeder
{
    /** @var list<array{nom: string, sigle: string, departements?: list<array{nom: string, sigle: string}>}> */
    private const FACULTES = [
        [
            'nom' => 'Faculte de Droit',
            'sigle' => 'DROIT',
            'departements' => [
                ['nom' => 'Droit prive et judiciaire', 'sigle' => 'DPJ'],
                ['nom' => 'Droit public interne et international', 'sigle' => 'DPI'],
                ['nom' => 'Droit economique et social', 'sigle' => 'DES'],
            ],
        ],
        [
            'nom' => 'Faculte de Medecine',
            'sigle' => 'MED',
            'departements' => [
                ['nom' => 'Sciences biomedicales', 'sigle' => 'BIOMED'],
                ['nom' => 'Medecine generale', 'sigle' => 'MG'],
            ],
        ],
        [
            'nom' => 'Faculte Polytechnique',
            'sigle' => 'POLY',
            'departements' => [
                ['nom' => 'Genie civil', 'sigle' => 'GC'],
                ['nom' => 'Genie electrique', 'sigle' => 'GE'],
                ['nom' => 'Genie informatique', 'sigle' => 'GI'],
                ['nom' => 'Genie mecanique', 'sigle' => 'GM'],
            ],
        ],
        [
            'nom' => 'Faculte des Sciences Economiques et de Gestion',
            'sigle' => 'FASEG',
            'departements' => [
                ['nom' => 'Sciences economiques', 'sigle' => 'ECO'],
                ['nom' => 'Sciences de gestion', 'sigle' => 'GESTION'],
            ],
        ],
        ['nom' => 'Faculte des Lettres et Sciences Humaines', 'sigle' => 'FLSH'],
        ['nom' => 'Faculte des Sciences et Technologies', 'sigle' => 'FST'],
        ['nom' => 'Faculte des Sciences Pharmaceutiques', 'sigle' => 'PHARMA'],
        ['nom' => 'Faculte de Medecine Veterinaire', 'sigle' => 'VETO'],
        ['nom' => 'Faculte de Medecine Bucco-Dentaire', 'sigle' => 'DENT'],
        ['nom' => 'Faculte des Sciences Agronomiques et Environnement', 'sigle' => 'AGRO'],
        ['nom' => 'Faculte de Psychologie et des Sciences de l\'Education', 'sigle' => 'FPSE'],
        ['nom' => 'Faculte des Sciences Sociales, Administratives et Politiques', 'sigle' => 'FSSAP'],
        ['nom' => 'Faculte de Petrole, Gaz et Energies Renouvelables', 'sigle' => 'FPGER'],
    ];

    public function run(): void
    {
        AnneeAcademique::updateOrCreate(
            ['libelle' => '2025-2026'],
            [
                'date_debut' => '2025-10-13',
                'date_fin' => '2026-07-31',
                'statut' => 'en_cours',
                'active' => true,
            ],
        );

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

    /** Quelques salles par faculte, pour que la saisie ait ou se poser. */
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
