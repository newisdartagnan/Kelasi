<?php

namespace App\Services;

use App\Models\Cours;
use App\Models\Presence;
use App\Models\Promotion;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * L'appel, étudiant par étudiant.
 *
 * L'assiduité conditionne l'accès aux examens : un relevé doit donc être
 * nominatif et daté, pas un simple décompte. Le chef de promotion le fait,
 * comme il saisit la séance, et l'enseignant le voit en contresignant.
 */
class ReleveDePresence
{
    /**
     * Enregistre l'appel d'une séance.
     *
     * L'appel se refait : une correction remplace la ligne de l'étudiant au
     * lieu d'en créer une seconde. C'est courant — un retardataire arrive
     * après le début, un absent revient avec un justificatif.
     *
     * @param  array<int, string>  $statuts  identifiant d'étudiant => statut
     * @param  array<int, string>  $motifs
     */
    public function enregistrer(User $auteur, Seance $seance, array $statuts, array $motifs = []): int
    {
        $this->verifierQualite($auteur, $seance);

        $inscrits = $this->inscritsDe($seance->promotion_id)->pluck('id');
        $retenus = collect($statuts)->only($inscrits);

        if ($retenus->isEmpty()) {
            throw ValidationException::withMessages([
                'presences' => 'Aucun étudiant de cette promotion dans le relevé.',
            ]);
        }

        DB::transaction(function () use ($auteur, $seance, $retenus, $motifs) {
            foreach ($retenus as $etudiantId => $statut) {
                if (! array_key_exists($statut, Presence::STATUTS)) {
                    continue;
                }

                Presence::updateOrCreate(
                    ['seance_id' => $seance->id, 'user_id' => $etudiantId],
                    [
                        'statut' => $statut,
                        'motif' => $motifs[$etudiantId] ?? null,
                        'releve_par_id' => $auteur->id,
                    ],
                );
            }

            // L'effectif suit le relevé : les deux chiffres ne doivent pas
            // se contredire sur la même séance.
            $seance->update([
                'appel_fait_at' => now(),
                'effectif_present' => $seance->presences()->presents()->count(),
            ]);
        });

        return $retenus->count();
    }

    /**
     * Les étudiants d'une promotion, chefs compris : le chef suit les cours
     * comme les autres.
     *
     * @return Collection<int, User>
     */
    public function inscritsDe(int $promotionId): Collection
    {
        return User::actifs()
            ->where('promotion_id', $promotionId)
            ->role([User::ROLE_ETUDIANT, User::ROLE_CP, User::ROLE_CPA])
            ->orderBy('name')
            ->orderBy('prenom')
            ->get();
    }

    /**
     * Le taux d'assiduité d'un étudiant sur un cours, ou sur tous.
     *
     * Seules les séances dont l'appel a été fait entrent au dénominateur :
     * compter une séance sans relevé pénaliserait l'étudiant pour un oubli
     * qui n'est pas le sien.
     *
     * @return array{seances: int, presences: int, taux: float}
     */
    public function assiduite(User $etudiant, ?Cours $cours = null): array
    {
        $requete = Presence::where('user_id', $etudiant->id)
            ->whereHas('seance', fn (Builder $q) => $q
                ->whereNotNull('appel_fait_at')
                ->when($cours, fn (Builder $s) => $s->where('cours_id', $cours->id)));

        $total = (clone $requete)->count();
        $presents = (clone $requete)->presents()->count();

        return [
            'seances' => $total,
            'presences' => $presents,
            'taux' => $total > 0 ? round($presents / $total * 100, 1) : 0.0,
        ];
    }

    /**
     * L'assiduité de toute une promotion, pour repérer ceux qui décrochent.
     *
     * @return Collection<int, array{etudiant: User, seances: int, presences: int, taux: float}>
     */
    public function assiduiteDePromotion(Promotion $promotion, ?Cours $cours = null): Collection
    {
        $inscrits = $this->inscritsDe($promotion->id);

        if ($inscrits->isEmpty()) {
            return collect();
        }

        // Une requête groupée plutôt qu'une par étudiant : une promotion
        // compte souvent plusieurs centaines d'inscrits.
        $lignes = DB::table('presences')
            ->join('seances', 'presences.seance_id', '=', 'seances.id')
            ->whereIn('presences.user_id', $inscrits->pluck('id'))
            ->whereNotNull('seances.appel_fait_at')
            ->when($cours, fn ($q) => $q->where('seances.cours_id', $cours->id))
            ->groupBy('presences.user_id')
            ->selectRaw('presences.user_id AS uid, COUNT(*) AS total')
            ->selectRaw(
                'SUM(CASE WHEN presences.statut IN (?, ?) THEN 1 ELSE 0 END) AS presents',
                Presence::COMPTENT_COMME_PRESENT,
            )
            ->get()
            ->keyBy('uid');

        return $inscrits->map(function (User $etudiant) use ($lignes) {
            $ligne = $lignes->get($etudiant->id);
            $total = (int) ($ligne->total ?? 0);
            $presents = (int) ($ligne->presents ?? 0);

            return [
                'etudiant' => $etudiant,
                'seances' => $total,
                'presences' => $presents,
                'taux' => $total > 0 ? round($presents / $total * 100, 1) : 0.0,
            ];
        })
            // Ceux qui ont un relevé d'abord, du taux le plus faible au plus
            // élevé ; ceux qui n'en ont aucun à la fin. Un étudiant sans
            // donnée n'est pas le plus absent, il est inconnu : le placer en
            // tête d'une liste de décrochage serait trompeur.
            ->sortBy(fn (array $ligne) => [$ligne['seances'] === 0 ? 1 : 0, $ligne['taux']])
            ->values();
    }

    /** Le relevé déjà enregistré, pour préremplir une correction. */
    public function releveExistant(Seance $seance): Collection
    {
        return $seance->presences()->get()->keyBy('user_id');
    }

    /** Comme pour la saisie : seul le chef de promotion fait l'appel. */
    private function verifierQualite(User $auteur, Seance $seance): void
    {
        if (! $auteur->estChefDePromotion()) {
            throw ValidationException::withMessages([
                'presences' => 'Seul un chef de promotion fait l\'appel.',
            ]);
        }

        if ($auteur->promotion_id !== $seance->promotion_id) {
            throw ValidationException::withMessages([
                'presences' => 'Cette séance n\'est pas celle de votre promotion.',
            ]);
        }
    }
}
