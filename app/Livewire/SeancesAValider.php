<?php

namespace App\Livewire;

use App\Models\Seance;
use App\Services\RegistreDesSeances;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;

/**
 * La file de contreseing de l'enseignant.
 *
 * Valider ou contester, rien d'autre. Chaque ligne rappelle ce que le chef de
 * promotion a déclaré avoir traité : c'est sur cette déclaration que porte la
 * signature.
 */
class SeancesAValider extends Component
{
    public ?int $seanceContestee = null;

    public string $motif = '';

    public function valider(int $seanceId, RegistreDesSeances $registre): void
    {
        $seance = $this->seanceDeLaFile($seanceId);

        if (! $seance) {
            return;
        }

        try {
            $registre->valider(auth()->user(), $seance);
            session()->flash('succes', 'Séance contresignée.');
        } catch (ValidationException $e) {
            session()->flash('erreur', collect($e->errors())->flatten()->first());
        }
    }

    public function ouvrirContestation(int $seanceId): void
    {
        $this->seanceContestee = $seanceId;
        $this->motif = '';
    }

    public function contester(RegistreDesSeances $registre): void
    {
        $this->validate([
            'motif' => ['required', 'string', 'min:10', 'max:2000'],
        ], attributes: ['motif' => 'motif de contestation']);

        $seance = $this->seanceDeLaFile($this->seanceContestee);

        if (! $seance) {
            return;
        }

        try {
            $registre->contester(auth()->user(), $seance, $this->motif);
            session()->flash('succes', 'Séance renvoyée au chef de promotion.');
        } catch (ValidationException $e) {
            session()->flash('erreur', collect($e->errors())->flatten()->first());
        }

        $this->reset(['seanceContestee', 'motif']);
    }

    private function seanceDeLaFile(?int $seanceId): ?Seance
    {
        if (! $seanceId) {
            return null;
        }

        return Seance::with('cours')
            ->whereIn('cours_id', auth()->user()->coursEnseignes()->select('cours.id'))
            ->find($seanceId);
    }

    public function render(): View
    {
        return view('livewire.seances-a-valider', [
            'seances' => Seance::with(['cours.uniteEnseignement.promotion', 'saisiePar', 'local'])
                ->enAttente()
                ->whereIn('cours_id', auth()->user()->coursEnseignes()->select('cours.id'))
                ->orderBy('date_seance')
                ->get(),
        ]);
    }
}
