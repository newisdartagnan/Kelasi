<?php

namespace App\Livewire;

use App\Models\AnneeAcademique;
use App\Models\InscriptionAutorisee;
use App\Services\ImportDesInscrits;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * L'écran du secrétariat académique.
 *
 * Le fichier est analysé puis montré avant d'être écrit : le secrétariat voit
 * ce qui va entrer, et surtout ce qui sera rejeté et pourquoi. Un import qui
 * échoue à moitié en silence est pire qu'un import refusé.
 */
class ImporterLesInscrits extends Component
{
    use WithFileUploads, WithPagination;

    public $fichier;

    /** @var Collection<int, array<string, mixed>>|null */
    public ?Collection $apercu = null;

    /** @var list<string> */
    public array $erreurs = [];

    public function updatedFichier(ImportDesInscrits $import): void
    {
        $this->validate(
            ['fichier' => ['required', 'file', 'mimes:csv,txt', 'max:5120']],
            ['fichier.mimes' => 'Déposez un fichier CSV, exporté depuis votre tableur.'],
            ['fichier' => 'fichier'],
        );

        $annee = AnneeAcademique::courante();

        if (! $annee) {
            $this->erreurs = ['Aucune année académique n\'est ouverte.'];

            return;
        }

        $resultat = $import->analyser($this->fichier->getRealPath(), $annee);

        $this->apercu = $resultat['lignes'];
        $this->erreurs = $resultat['erreurs'];
    }

    public function confirmer(ImportDesInscrits $import): void
    {
        $annee = AnneeAcademique::courante();

        if (! $this->apercu || ! $annee) {
            return;
        }

        $bilan = $import->importer($this->apercu, $annee, auth()->user());

        session()->flash('succes', sprintf(
            '%d inscription(s) créée(s), %d mise(s) à jour, %d ignorée(s) car le compte est déjà ouvert.',
            $bilan['creees'],
            $bilan['mises_a_jour'],
            $bilan['ignorees'],
        ));

        $this->annuler();
    }

    public function annuler(): void
    {
        $this->reset(['fichier', 'apercu', 'erreurs']);
        $this->resetValidation();
    }

    public function render(): View
    {
        $annee = AnneeAcademique::courante();

        return view('livewire.importer-les-inscrits', [
            'annee' => $annee,
            'retenues' => $this->apercu?->where('valide', true)->count() ?? 0,
            'rejetees' => $this->apercu?->where('valide', false)->count() ?? 0,
            'deposees' => $annee
                ? InscriptionAutorisee::with('promotion')
                    ->where('annee_academique_id', $annee->id)
                    ->orderByDesc('id')
                    ->paginate(15)
                : null,
            'colonnes' => ImportDesInscrits::COLONNES,
        ]);
    }
}
