<?php

namespace App\Livewire;

use App\Models\Seance;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Le journal : toutes les seances visibles par l'utilisateur, dans l'ordre
 * ou elles se sont tenues.
 *
 * C'est la piece que l'on sort quand une contestation remonte -- qui a saisi
 * quoi, quel jour, contresigne par qui.
 */
class JournalDesSeances extends Component
{
    use WithPagination;

    #[Url(as: 'statut')]
    public string $statut = '';

    public function updatingStatut(): void
    {
        $this->resetPage();
    }

    public function render(): View
    {
        return view('livewire.journal-des-seances', [
            'seances' => $this->requete()->paginate(20),
            'statuts' => [
                '' => 'Toutes',
                Seance::STATUT_VALIDEE => 'Contresignées',
                Seance::STATUT_SOUMISE => 'En attente',
                Seance::STATUT_CONTESTEE => 'Contestees',
            ],
        ]);
    }

    /**
     * Le perimetre de lecture suit la fonction : l'université pour le VDE, la
     * faculté pour un doyen, ses cours pour un enseignant, sa promotion pour
     * les autres.
     */
    private function requete(): Builder
    {
        $utilisateur = auth()->user();

        $requete = Seance::with(['cours.uniteEnseignement.promotion.departement', 'saisiePar', 'valideePar'])
            ->when($this->statut, fn (Builder $q) => $q->where('statut', $this->statut))
            ->latest('date_seance')
            ->latest('id');

        if ($utilisateur->aPorteeUniversitaire()) {
            return $requete;
        }

        if ($utilisateur->estAutoriteFacultaire()) {
            return $requete->whereHas(
                'promotion.departement',
                fn (Builder $q) => $q->where('faculte_id', $utilisateur->faculte_id),
            );
        }

        if ($utilisateur->hasRole(User::ROLE_ENSEIGNANT)) {
            return $requete->whereIn('cours_id', $utilisateur->coursEnseignes()->select('cours.id'));
        }

        return $requete->where('promotion_id', $utilisateur->promotion_id);
    }
}
