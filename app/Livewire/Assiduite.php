<?php

namespace App\Livewire;

use App\Models\Cours;
use App\Models\Promotion;
use App\Models\User;
use App\Services\ReleveDePresence;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * L'assiduité d'une promotion, du plus faible taux au plus élevé.
 *
 * L'ordre n'est pas neutre : ceux qui décrochent doivent apparaître en haut,
 * puisque c'est pour eux qu'on ouvre cet écran.
 */
class Assiduite extends Component
{
    #[Url(as: 'promotion')]
    public ?int $promotionId = null;

    #[Url(as: 'cours')]
    public ?int $coursId = null;

    public function mount(): void
    {
        $this->promotionId ??= $this->promotionsVisibles()->first()?->id;
    }

    public function render(ReleveDePresence $releve): View
    {
        $promotion = $this->promotionsVisibles()->firstWhere('id', $this->promotionId);
        $cours = $this->coursId ? Cours::find($this->coursId) : null;

        return view('livewire.assiduite', [
            'promotions' => $this->promotionsVisibles(),
            'promotion' => $promotion,
            'cours' => $promotion ? $this->coursDe($promotion) : collect(),
            'lignes' => $promotion ? $releve->assiduiteDePromotion($promotion, $cours) : collect(),
        ]);
    }

    /** @return Collection<int, Promotion> */
    public function promotionsVisibles(): Collection
    {
        $utilisateur = auth()->user();

        if ($utilisateur->aPorteeUniversitaire()) {
            return Promotion::with('departement')->active()->get();
        }

        if ($utilisateur->estAutoriteFacultaire()) {
            return Promotion::with('departement')
                ->whereHas('departement', fn ($q) => $q->where('faculte_id', $utilisateur->faculte_id))
                ->active()
                ->get();
        }

        if ($utilisateur->hasRole(User::ROLE_ENSEIGNANT)) {
            return Promotion::with('departement')
                ->whereHas(
                    'unitesEnseignement.cours.attributions',
                    fn ($q) => $q->where('user_id', $utilisateur->id),
                )
                ->get();
        }

        return $utilisateur->promotion_id
            ? Promotion::with('departement')->whereKey($utilisateur->promotion_id)->get()
            : collect();
    }

    /** @return Collection<int, Cours> */
    private function coursDe(Promotion $promotion): Collection
    {
        return Cours::whereHas('uniteEnseignement', fn ($q) => $q->where('promotion_id', $promotion->id))
            ->orderBy('intitule')
            ->get();
    }
}
