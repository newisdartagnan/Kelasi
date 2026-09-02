<?php

namespace App\Services;

use App\Models\AnneeAcademique;
use App\Models\Cours;
use App\Models\Faculte;
use App\Models\Promotion;
use App\Models\Seance;
use App\Support\Avancement;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Repond a la question que toute l'application existe pour resoudre :
 * ou en est reellement le cours X, et de combien est-il en retard ?
 *
 * Les agregats sont calcules en une requete groupee plutot que cours par
 * cours -- un doyen ouvre son tableau de bord sur trois cents cours, et le
 * fait souvent depuis une connexion mobile lente.
 */
class CalculateurAvancement
{
    /** Avancement d'un cours unique. */
    public function pourCours(Cours $cours): Avancement
    {
        $minutes = $this->minutesParCours([$cours->id]);

        return $this->composer($cours->heures_prevues * 60, $minutes[$cours->id] ?? []);
    }

    /**
     * Avancement de chaque cours d'une promotion, indexe par identifiant de
     * cours. Une seule requete pour les seances, une pour les cours.
     *
     * @return Collection<int, Avancement>
     */
    public function parCoursDePromotion(Promotion $promotion): Collection
    {
        $cours = Cours::query()
            ->whereHas('uniteEnseignement', fn ($q) => $q->where('promotion_id', $promotion->id))
            ->where('actif', true)
            ->get(['id', 'heures_cmi', 'heures_td', 'heures_tp']);

        $minutes = $this->minutesParCours($cours->pluck('id')->all());

        return $cours->mapWithKeys(fn (Cours $c) => [
            $c->id => $this->composer($c->heures_prevues * 60, $minutes[$c->id] ?? []),
        ]);
    }

    /** Avancement consolide d'une promotion. */
    public function pourPromotion(Promotion $promotion): Avancement
    {
        return $this->parCoursDePromotion($promotion)
            ->reduce(fn (Avancement $porte, Avancement $a) => $porte->plus($a), Avancement::vide());
    }

    /**
     * Avancement de chaque promotion d'une faculte, indexe par identifiant de
     * promotion.
     *
     * @return Collection<int, Avancement>
     */
    public function parPromotionDeFaculte(Faculte $faculte, ?AnneeAcademique $annee = null): Collection
    {
        $annee ??= AnneeAcademique::courante();

        $promotions = Promotion::query()
            ->whereHas('departement', fn ($q) => $q->where('faculte_id', $faculte->id))
            ->when($annee, fn ($q) => $q->where('annee_academique_id', $annee->id))
            ->active()
            ->pluck('id');

        if ($promotions->isEmpty()) {
            return collect();
        }

        $prevues = DB::table('cours')
            ->join('unites_enseignement', 'cours.unite_enseignement_id', '=', 'unites_enseignement.id')
            ->whereIn('unites_enseignement.promotion_id', $promotions)
            ->where('cours.actif', true)
            ->groupBy('unites_enseignement.promotion_id')
            ->selectRaw('unites_enseignement.promotion_id, SUM(cours.heures_cmi + cours.heures_td + cours.heures_tp) * 60 AS minutes')
            ->pluck('minutes', 'promotion_id');

        $realisees = DB::table('seances')
            ->whereIn('promotion_id', $promotions)
            ->whereIn('type', Seance::TYPES_ENSEIGNEMENT)
            ->whereIn('statut', [Seance::STATUT_VALIDEE, Seance::STATUT_SOUMISE])
            ->groupBy('promotion_id', 'statut')
            ->selectRaw('promotion_id, statut, SUM(duree_minutes) AS minutes')
            ->get()
            ->groupBy('promotion_id');

        return $promotions->mapWithKeys(function (int $id) use ($prevues, $realisees) {
            $lignes = $realisees->get($id, collect());

            return [$id => new Avancement(
                (int) ($prevues[$id] ?? 0),
                (int) ($lignes->firstWhere('statut', Seance::STATUT_VALIDEE)->minutes ?? 0),
                (int) ($lignes->firstWhere('statut', Seance::STATUT_SOUMISE)->minutes ?? 0),
            )];
        });
    }

    /** Avancement consolide d'une faculte. */
    public function pourFaculte(Faculte $faculte, ?AnneeAcademique $annee = null): Avancement
    {
        return $this->parPromotionDeFaculte($faculte, $annee)
            ->reduce(fn (Avancement $porte, Avancement $a) => $porte->plus($a), Avancement::vide());
    }

    /**
     * Part de l'annee academique ecoulee, en pourcentage. Sert de reference
     * pour dire si un cours est en avance ou en retard : a la mi-parcours, on
     * attend la moitie du volume.
     */
    public function tauxAttendu(?AnneeAcademique $annee = null, ?CarbonInterface $a = null): float
    {
        $annee ??= AnneeAcademique::courante();

        if (! $annee) {
            return 0.0;
        }

        $a ??= now();
        $total = $annee->date_debut->diffInDays($annee->date_fin, absolute: true);

        if ($total <= 0) {
            return 0.0;
        }

        $ecoules = $annee->date_debut->diffInDays($a, absolute: false);

        return round(max(0, min(100, $ecoules / $total * 100)), 1);
    }

    /**
     * Minutes de seances par cours et par statut.
     *
     * @param  array<int>  $coursIds
     * @return array<int, array<string, int>>
     */
    private function minutesParCours(array $coursIds): array
    {
        if ($coursIds === []) {
            return [];
        }

        return DB::table('seances')
            ->whereIn('cours_id', $coursIds)
            ->whereIn('type', Seance::TYPES_ENSEIGNEMENT)
            ->whereIn('statut', [Seance::STATUT_VALIDEE, Seance::STATUT_SOUMISE])
            ->groupBy('cours_id', 'statut')
            ->selectRaw('cours_id, statut, SUM(duree_minutes) AS minutes')
            ->get()
            ->groupBy('cours_id')
            ->map(fn ($lignes) => $lignes->pluck('minutes', 'statut')->all())
            ->all();
    }

    /** @param  array<string, int>  $minutesParStatut */
    private function composer(int $minutesPrevues, array $minutesParStatut): Avancement
    {
        return new Avancement(
            $minutesPrevues,
            (int) ($minutesParStatut[Seance::STATUT_VALIDEE] ?? 0),
            (int) ($minutesParStatut[Seance::STATUT_SOUMISE] ?? 0),
        );
    }
}
