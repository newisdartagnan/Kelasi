<?php

namespace App\Livewire;

use App\Services\AdministrationDesComptes;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Le choix d'un mot de passe après une réinitialisation.
 *
 * Tant que la personne n'a pas choisi le sien, elle circule avec un mot de
 * passe que son doyen connaît : l'écran s'impose donc à elle avant tout le
 * reste.
 */
#[Layout('layouts.nu')]
class ChangerMonMotDePasse extends Component
{
    public string $motDePasse = '';

    public string $confirmation = '';

    public function enregistrer(AdministrationDesComptes $administration): void
    {
        $this->validate([
            'motDePasse' => ['required', 'string', 'min:8', 'same:confirmation'],
        ], [
            'motDePasse.same' => 'Les deux mots de passe ne correspondent pas.',
            'motDePasse.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
        ], [
            'motDePasse' => 'mot de passe',
        ]);

        $administration->changerSonMotDePasse(auth()->user(), $this->motDePasse);

        session()->flash('succes', 'Votre mot de passe est enregistré.');

        $this->redirectRoute('tableau-de-bord', navigate: false);
    }

    public function render(): View
    {
        return view('livewire.changer-mon-mot-de-passe');
    }
}
