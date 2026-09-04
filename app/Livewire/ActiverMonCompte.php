<?php

namespace App\Livewire;

use App\Models\InscriptionAutorisee;
use App\Services\ActivationDeCompte;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * L'ouverture d'un compte, en deux temps : on reconnaît d'abord la personne
 * sur la liste des inscrits, elle choisit ensuite son mot de passe.
 *
 * Montrer son nom et sa promotion avant de lui demander un mot de passe évite
 * la méprise la plus fréquente : un homonyme, ou un matricule mal recopié.
 */
#[Layout('layouts.nu')]
class ActiverMonCompte extends Component
{
    public string $matricule = '';

    public string $motDePasse = '';

    public string $motDePasseConfirmation = '';

    public string $telephone = '';

    public ?InscriptionAutorisee $inscription = null;

    public function verifier(ActivationDeCompte $activation): void
    {
        $this->validate(
            ['matricule' => ['required', 'string', 'max:40']],
            attributes: ['matricule' => 'matricule'],
        );

        try {
            $this->inscription = $activation->chercher($this->matricule);
        } catch (ValidationException $e) {
            $this->addError('matricule', collect($e->errors())->flatten()->first());
        }
    }

    public function recommencer(): void
    {
        $this->reset(['inscription', 'motDePasse', 'motDePasseConfirmation', 'telephone']);
        $this->resetValidation();
    }

    public function activer(ActivationDeCompte $activation): void
    {
        if (! $this->inscription) {
            return;
        }

        $this->validate([
            'motDePasse' => ['required', 'string', 'min:8', 'same:motDePasseConfirmation'],
            'telephone' => ['nullable', 'string', 'max:30'],
        ], [
            'motDePasse.same' => 'Les deux mots de passe ne correspondent pas.',
            'motDePasse.min' => 'Le mot de passe doit contenir au moins 8 caractères.',
        ], [
            'motDePasse' => 'mot de passe',
            'telephone' => 'téléphone',
        ]);

        // La ligne a pu être consommée entre-temps depuis un autre appareil.
        $fraiche = $activation->chercher($this->inscription->matricule);

        $utilisateur = $activation->activer($fraiche, $this->motDePasse, $this->telephone ?: null);

        Auth::login($utilisateur, remember: true);
        session()->regenerate();
        session()->flash('succes', 'Votre compte est ouvert. Bienvenue sur Kelasi.');

        $this->redirectRoute('tableau-de-bord', navigate: false);
    }

    public function render(): View
    {
        return view('livewire.activer-mon-compte');
    }
}
