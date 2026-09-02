<?php

namespace App\Support;

/**
 * La conversion crédits -> heures, telle que la fixent les instructions
 * académiques du MINESU pour le système LMD.
 *
 * Un crédit represente 25 heures de travail étudiant, reparties selon un
 * standard de deux tiers d'heures encadrees pour un tiers de travail
 * personnel. Un semestre vaut 30 crédits, une année 60, une licence 180.
 *
 * Les maquettes ministerielles ne publient que les heures encadrees : ce sont
 * elles qui se deroulent en salle, et donc les seules que les seances
 * viennent consommer.
 */
final class VolumeHoraire
{
    /** Heures de travail étudiant pour un crédit. */
    public const HEURES_PAR_CREDIT = 25;

    /** Part encadree du travail étudiant. Le reste est du TPE. */
    public const PART_ENCADREE = 2 / 3;

    public const CREDITS_PAR_SEMESTRE = 30;
    public const CREDITS_PAR_ANNEE = 60;

    /** Heures encadrees correspondant a un nombre de crédits. */
    public static function heuresEncadrees(int $credits): int
    {
        return (int) round($credits * self::HEURES_PAR_CREDIT * self::PART_ENCADREE);
    }

    /** Heures de travail personnel de l'etudiant (TPE). */
    public static function heuresTpe(int $credits): int
    {
        return $credits * self::HEURES_PAR_CREDIT - self::heuresEncadrees($credits);
    }

    /**
     * Ventile les heures encadrees entre CMI, TD et TP selon des parts
     * exprimees en fractions. Le reliquat d'arrondi va au CMI, qui est
     * toujours la part dominante.
     *
     * @param  array{cmi?: float, td?: float, tp?: float}  $parts
     * @return array{cmi: int, td: int, tp: int, tpe: int}
     */
    public static function ventiler(int $credits, array $parts = ['cmi' => 1.0]): array
    {
        $encadrees = self::heuresEncadrees($credits);

        $td = (int) round($encadrees * ($parts['td'] ?? 0));
        $tp = (int) round($encadrees * ($parts['tp'] ?? 0));

        return [
            'cmi' => max(0, $encadrees - $td - $tp),
            'td' => $td,
            'tp' => $tp,
            'tpe' => self::heuresTpe($credits),
        ];
    }
}
