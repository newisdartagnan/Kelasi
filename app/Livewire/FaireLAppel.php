<?php

namespace App\Livewire;

use App\Models\Presence;
use App\Models\Seance;
use App\Services\ReleveDePresence;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;

/**
 * L'appel d'une séance.
 *
 * Tout le monde est présent par défaut : dans un amphi de deux cents
 * inscrits, on pointe les absents, pas les présents. L'écran suit ce geste.
 */
class FaireLAppel extends Component
{
    public Seance $seance;

    /** @var array<int, string> */
    public array $statuts = [];

    /** @var array<int, string> */
    public array $motifs = [];

    public function mount(Seance $seance, ReleveDePresence $releve): void
    {
        abort_unless($seance->promotion_id === auth()->user()->promotion_id, 403);
        abort_unless(auth()->user()->estChefDePromotion(), 403);

        $this->seance = $seance->load('cours', 'promotion');

        $existant = $releve->releveExistant($seance);

        foreach ($releve->inscritsDe($seance->promotion_id) as $etudiant) {
            $this->statuts[$etudiant->id] = $existant[$etudiant->id]->statut ?? Presence::PRESENT;
            $this->motifs[$etudiant->id] = $existant[$etudiant->id]->motif ?? '';
        }
    }

    public function basculer(int $etudiantId): void
    {
        // Un appui fait le tour des états : présent, absent, excusé, retard.
        $ordre = array_keys(Presence::STATUTS);
        $position = array_search($this->statuts[$etudiantId] ?? Presence::PRESENT, $ordre, true);

        $this->statuts[$etudiantId] = $ordre[($position + 1) % count($ordre)];
    }

    public function toutPresent(): void
    {
        $this->statuts = array_map(fn () => Presence::PRESENT, $this->statuts);
    }

    public function enregistrer(ReleveDePresence $releve): void
    {
        try {
            $nombre = $releve->enregistrer(auth()->user(), $this->seance, $this->statuts, $this->motifs);
        } catch (ValidationException $e) {
            session()->flash('erreur', collect($e->errors())->flatten()->first());

            return;
        }

        session()->flash('succes', "Appel enregistré pour {$nombre} étudiant(s).");

        $this->redirectRoute('seances.journal', navigate: false);
    }

    public function render(ReleveDePresence $releve): View
    {
        $inscrits = $releve->inscritsDe($this->seance->promotion_id);

        return view('livewire.faire-l-appel', [
            'inscrits' => $inscrits,
            'statuts' => Presence::STATUTS,
            'compte' => $this->compter($inscrits),
        ]);
    }

    /** @return array<string, int> */
    private function compter(Collection $inscrits): array
    {
        $compte = array_fill_keys(array_keys(Presence::STATUTS), 0);

        foreach ($inscrits as $etudiant) {
            $statut = $this->statuts[$etudiant->id] ?? Presence::PRESENT;
            $compte[$statut] = ($compte[$statut] ?? 0) + 1;
        }

        return $compte;
    }
}
