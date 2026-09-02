<?php

namespace App\Livewire;

use App\Models\Cours;
use App\Models\Local;
use App\Models\Seance;
use App\Services\RegistreDesSeances;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * La saisie du chef de promotion.
 *
 * C'est l'ecran le plus utilise de l'application, et celui qui se remplit dans
 * les pires conditions : debout, au telephone, souvent sans réseau. Le
 * formulaire reste donc court, et la page sait basculer en file locale quand
 * la connexion manque.
 */
class SaisirSeance extends Component
{
    #[Validate('required|integer')]
    public ?int $coursId = null;

    #[Validate('required|date|before_or_equal:today')]
    public string $dateSeance = '';

    #[Validate('required')]
    public string $heureDebut = '08:00';

    #[Validate('required')]
    public string $heureFin = '10:00';

    #[Validate('required|string')]
    public string $type = Seance::TYPE_CMI;

    #[Validate('required|string|min:10|max:2000')]
    public string $matiereCouverte = '';

    #[Validate('nullable|integer|min:0|max:2000')]
    public ?int $effectifPresent = null;

    #[Validate('nullable|integer')]
    public ?int $localId = null;

    #[Validate('nullable|string|max:2000')]
    public string $observations = '';

    public function mount(): void
    {
        $this->dateSeance = now()->toDateString();
    }

    public function enregistrer(RegistreDesSeances $registre): void
    {
        $this->validate();

        $cours = $this->coursDeLaPromotion()->firstWhere('id', $this->coursId);

        if (! $cours) {
            $this->addError('coursId', 'Ce cours ne figure pas au programme de votre promotion.');

            return;
        }

        try {
            $registre->saisir(auth()->user(), $cours, [
                'date_seance' => $this->dateSeance,
                'heure_debut' => $this->heureDebut,
                'heure_fin' => $this->heureFin,
                'type' => $this->type,
                'matiere_couverte' => $this->matiereCouverte,
                'effectif_present' => $this->effectifPresent,
                'local_id' => $this->localId,
                'observations' => $this->observations ?: null,
            ]);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $champ => $messages) {
                $this->addError($champ === 'seance' ? 'coursId' : $champ, $messages[0]);
            }

            return;
        }

        session()->flash('succes', 'Séance enregistrée. Elle attend le contreseing de l\'enseignant.');

        $this->reinitialiser();
    }

    private function reinitialiser(): void
    {
        $this->reset(['matiereCouverte', 'observations', 'effectifPresent']);
        $this->resetValidation();
    }

    /** @return Collection<int, Cours> */
    public function coursDeLaPromotion(): Collection
    {
        $promotion = auth()->user()->promotion;

        if (! $promotion) {
            return collect();
        }

        return Cours::with('uniteEnseignement')
            ->whereHas('uniteEnseignement', fn ($q) => $q->where('promotion_id', $promotion->id))
            ->where('actif', true)
            ->get()
            ->sortBy(['uniteEnseignement.semestre', 'uniteEnseignement.ordre'])
            ->values();
    }

    public function render(): View
    {
        $promotion = auth()->user()->promotion;

        return view('livewire.saisir-seance', [
            'cours' => $this->coursDeLaPromotion(),
            'locaux' => $promotion
                ? Local::where('faculte_id', $promotion->departement->faculte_id)->where('actif', true)->get()
                : collect(),
            'dernieres' => Seance::with('cours')
                ->where('saisie_par_id', auth()->id())
                ->latest('date_seance')
                ->limit(5)
                ->get(),
        ]);
    }
}
