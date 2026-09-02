<?php

namespace Database\Seeders;

use App\Models\AnneeAcademique;
use App\Models\Cours;
use App\Models\Departement;
use App\Models\Faculte;
use App\Models\Promotion;
use App\Models\UniteEnseignement;
use App\Support\VolumeHoraire;
use Illuminate\Database\Seeder;

/**
 * Charge les maquettes decrites dans database/data/programmes.php.
 *
 * Les volumes horaires ne sont pas stockes dans le fichier de donnees : ils
 * se deduisent des crédits. Une maquette se corrige donc en changeant un
 * nombre de crédits, jamais en recalculant des heures a la main.
 */
class ProgrammeSeeder extends Seeder
{
    public function run(): void
    {
        $annee = AnneeAcademique::courante();

        if (! $annee) {
            $this->command?->warn('Aucune année académique active : programmes non charges.');

            return;
        }

        $programmes = require database_path('data/programmes.php');

        foreach ($programmes as $sigleFaculte => $departements) {
            $faculte = Faculte::where('sigle', $sigleFaculte)->first();

            if (! $faculte) {
                continue;
            }

            foreach ($departements as $sigleDepartement => $niveaux) {
                $departement = Departement::where('faculte_id', $faculte->id)
                    ->where('sigle', $sigleDepartement)
                    ->first();

                if (! $departement) {
                    continue;
                }

                foreach ($niveaux as $niveau => $maquette) {
                    $this->chargerNiveau($departement, $annee, $niveau, $maquette);
                }
            }
        }
    }

    private function chargerNiveau(
        Departement $departement,
        AnneeAcademique $annee,
        string $niveau,
        array $maquette,
    ): void {
        $promotion = Promotion::updateOrCreate(
            [
                'departement_id' => $departement->id,
                'annee_academique_id' => $annee->id,
                'niveau' => $niveau,
            ],
            [
                'intitule' => $maquette['intitule'],
                'effectif_attendu' => $maquette['effectif'] ?? 0,
                'active' => true,
            ],
        );

        foreach ($maquette['unites'] as $ordre => $donnees) {
            $ue = UniteEnseignement::updateOrCreate(
                ['promotion_id' => $promotion->id, 'code' => $donnees['code']],
                [
                    'intitule' => $donnees['intitule'],
                    'semestre' => $donnees['semestre'],
                    'credits' => $donnees['credits'],
                    'ordre' => $ordre,
                ],
            );

            foreach ($donnees['cours'] as $cours) {
                $volume = VolumeHoraire::ventiler($cours['credits'], $cours['parts'] ?? []);

                Cours::updateOrCreate(
                    ['unite_enseignement_id' => $ue->id, 'code' => $cours['code']],
                    [
                        'intitule' => $cours['intitule'],
                        'credits' => $cours['credits'],
                        'heures_cmi' => $volume['cmi'],
                        'heures_td' => $volume['td'],
                        'heures_tp' => $volume['tp'],
                        'heures_tpe' => $volume['tpe'],
                        'actif' => true,
                    ],
                );
            }
        }
    }
}
