<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\AdministrationDesComptes;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * La demande de remise de mot de passe, faite sans être connecté.
 *
 * Aucun lien n'est envoyé par courriel : beaucoup d'étudiants et de chefs de
 * promotion n'ont pas d'adresse, et le réseau ne permet pas d'y compter. La
 * demande remonte à l'autorité, qui remet le mot de passe de la main à la
 * main — c'est plus lent, mais cela fonctionne réellement sur le terrain.
 */
#[Layout('layouts.nu')]
class DemanderUnMotDePasse extends Component
{
    public string $matricule = '';

    public string $motif = '';

    public bool $envoyee = false;

    public function envoyer(AdministrationDesComptes $administration): void
    {
        $this->validate([
            'matricule' => ['required', 'string', 'max:40'],
            'motif' => ['nullable', 'string', 'max:255'],
        ], attributes: ['matricule' => 'matricule', 'motif' => 'motif']);

        $utilisateur = User::where('matricule', strtoupper(trim($this->matricule)))->first();

        if ($utilisateur) {
            $administration->demanderReinitialisation($utilisateur, $this->motif ?: null);
        }

        // La réponse est la même dans les deux cas : dire qu'un matricule
        // n'existe pas permettrait de deviner qui est inscrit.
        $this->envoyee = true;
    }

    public function render(): View
    {
        return view('livewire.demander-un-mot-de-passe');
    }
}
