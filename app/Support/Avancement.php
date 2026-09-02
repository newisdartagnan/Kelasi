<?php

namespace App\Support;

/**
 * L'avancement d'un enseignement : ce qui etait prevu, ce qui a ete
 * effectivement tenu et contresigne, et l'ecart entre les deux.
 *
 * Les heures realisees ne comptent que les seances validees par l'enseignant.
 * Une seance saisie mais non contresignee est du declaratif : elle apparait
 * ailleurs, en attente, jamais dans ce chiffre.
 */
readonly class Avancement
{
    public function __construct(
        public int $minutesPrevues,
        public int $minutesRealisees,
        public int $minutesEnAttente = 0,
    ) {}

    public static function vide(): self
    {
        return new self(0, 0, 0);
    }

    public function heuresPrevues(): float
    {
        return round($this->minutesPrevues / 60, 1);
    }

    public function heuresRealisees(): float
    {
        return round($this->minutesRealisees / 60, 1);
    }

    public function heuresEnAttente(): float
    {
        return round($this->minutesEnAttente / 60, 1);
    }

    public function heuresRestantes(): float
    {
        return round(max(0, $this->minutesPrevues - $this->minutesRealisees) / 60, 1);
    }

    /** Taux d'avancement en pourcentage, plafonne a 100 pour l'affichage. */
    public function taux(): float
    {
        if ($this->minutesPrevues <= 0) {
            return 0.0;
        }

        return round(min(100, $this->minutesRealisees / $this->minutesPrevues * 100), 1);
    }

    /**
     * Le taux reel, non plafonne. Un cours a 118 % signale un depassement de
     * volume -- une information que le plafonnement effacerait.
     */
    public function tauxReel(): float
    {
        if ($this->minutesPrevues <= 0) {
            return 0.0;
        }

        return round($this->minutesRealisees / $this->minutesPrevues * 100, 1);
    }

    public function enDepassement(): bool
    {
        return $this->minutesRealisees > $this->minutesPrevues;
    }

    public function acheve(): bool
    {
        return $this->minutesPrevues > 0 && $this->minutesRealisees >= $this->minutesPrevues;
    }

    /**
     * Ecart, en points, entre l'avancement constate et celui qu'on attendrait
     * a cette date compte tenu du calendrier. Negatif = retard.
     */
    public function ecartSurAttendu(float $tauxAttendu): float
    {
        return round($this->tauxReel() - $tauxAttendu, 1);
    }

    public function plus(self $autre): self
    {
        return new self(
            $this->minutesPrevues + $autre->minutesPrevues,
            $this->minutesRealisees + $autre->minutesRealisees,
            $this->minutesEnAttente + $autre->minutesEnAttente,
        );
    }
}
