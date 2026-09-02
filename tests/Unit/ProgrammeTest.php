<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Les maquettes livrees avec l'application doivent respecter la regle
 * ministerielle : trente crédits par semestre, ni plus ni moins.
 *
 * Une maquette qui ne tombe pas juste est un bug, pas une approximation.
 */
class ProgrammeTest extends TestCase
{
    /** @return array<string, array{string, string, string, array}> */
    public static function maquettes(): array
    {
        $programmes = require __DIR__.'/../../database/data/programmes.php';
        $cas = [];

        foreach ($programmes as $faculte => $departements) {
            foreach ($departements as $departement => $niveaux) {
                foreach ($niveaux as $niveau => $maquette) {
                    $cas["{$faculte} {$departement} {$niveau}"] = [$faculte, $departement, $niveau, $maquette];
                }
            }
        }

        return $cas;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('maquettes')]
    public function test_chaque_semestre_totalise_trente_credits(
        string $faculte,
        string $departement,
        string $niveau,
        array $maquette,
    ): void {
        $parSemestre = [];

        foreach ($maquette['unites'] as $ue) {
            $parSemestre[$ue['semestre']] = ($parSemestre[$ue['semestre']] ?? 0) + $ue['credits'];
        }

        foreach ($parSemestre as $semestre => $credits) {
            $this->assertSame(
                30,
                $credits,
                "{$faculte} {$departement} {$niveau}, semestre {$semestre} : {$credits} crédits au lieu de 30.",
            );
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('maquettes')]
    public function test_les_credits_des_cours_reconstituent_ceux_de_leur_unite(
        string $faculte,
        string $departement,
        string $niveau,
        array $maquette,
    ): void {
        foreach ($maquette['unites'] as $ue) {
            $somme = array_sum(array_column($ue['cours'], 'credits'));

            $this->assertSame(
                $ue['credits'],
                $somme,
                "{$faculte} {$departement} {$niveau}, {$ue['code']} : les cours totalisent {$somme} crédits pour une UE annoncee a {$ue['credits']}.",
            );
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('maquettes')]
    public function test_les_codes_de_cours_sont_uniques_dans_la_maquette(
        string $faculte,
        string $departement,
        string $niveau,
        array $maquette,
    ): void {
        $codes = [];

        foreach ($maquette['unites'] as $ue) {
            foreach ($ue['cours'] as $cours) {
                $codes[] = $cours['code'];
            }
        }

        $this->assertSame(
            array_values(array_unique($codes)),
            $codes,
            "{$faculte} {$departement} {$niveau} : un code de cours apparait deux fois.",
        );
    }
}
