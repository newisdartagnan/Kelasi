<?php

namespace Tests\Unit;

use App\Support\VolumeHoraire;
use PHPUnit\Framework\TestCase;

class VolumeHoraireTest extends TestCase
{
    /**
     * Le chiffre de reference : la faculté de médecine de l'UNILU publie 750
     * heures de travail étudiant pour les 30 crédits d'un semestre. Si cette
     * assertion tombe, la regle de conversion a derive.
     */
    public function test_un_semestre_de_trente_credits_vaut_750_heures(): void
    {
        $credits = VolumeHoraire::CREDITS_PAR_SEMESTRE;

        $this->assertSame(750, $credits * VolumeHoraire::HEURES_PAR_CREDIT);
        $this->assertSame(500, VolumeHoraire::heuresEncadrees($credits));
        $this->assertSame(250, VolumeHoraire::heuresTpe($credits));
    }

    public function test_les_heures_encadrees_et_le_tpe_couvrent_tout_le_travail_etudiant(): void
    {
        foreach (range(1, 12) as $credits) {
            $this->assertSame(
                $credits * VolumeHoraire::HEURES_PAR_CREDIT,
                VolumeHoraire::heuresEncadrees($credits) + VolumeHoraire::heuresTpe($credits),
                "Le total ne tombe pas juste pour {$credits} crédits.",
            );
        }
    }

    public function test_la_ventilation_conserve_le_volume_encadre(): void
    {
        $ventile = VolumeHoraire::ventiler(6, ['td' => 0.25, 'tp' => 0.25]);

        $this->assertSame(
            VolumeHoraire::heuresEncadrees(6),
            $ventile['cmi'] + $ventile['td'] + $ventile['tp'],
        );
        $this->assertSame(VolumeHoraire::heuresTpe(6), $ventile['tpe']);
    }

    public function test_sans_ventilation_tout_le_volume_encadre_va_au_cours_magistral(): void
    {
        $ventile = VolumeHoraire::ventiler(4);

        $this->assertSame(VolumeHoraire::heuresEncadrees(4), $ventile['cmi']);
        $this->assertSame(0, $ventile['td']);
        $this->assertSame(0, $ventile['tp']);
    }
}
