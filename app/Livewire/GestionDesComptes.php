<?php

namespace App\Livewire;

use App\Models\DemandeReinitialisation;
use App\Models\User;
use App\Services\AdministrationDesComptes;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * L'écran d'administration des comptes.
 *
 * Il sert deux choses à la fois : la file des demandes de réinitialisation,
 * qui appelle une décision, et la liste des comptes, qu'on consulte. La file
 * passe donc en premier.
 */
class GestionDesComptes extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $recherche = '';

    public ?int $compteVise = null;

    public string $motifSuspension = '';

    public ?int $demandeVisee = null;

    public string $motifRejet = '';

    /** Affiché une seule fois, jamais enregistré. */
    public ?string $motDePasseProvisoire = null;

    public ?string $beneficiaireProvisoire = null;

    public function updatingRecherche(): void
    {
        $this->resetPage();
    }

    public function ouvrirSuspension(int $userId): void
    {
        $this->compteVise = $userId;
        $this->motifSuspension = '';
    }

    public function suspendre(AdministrationDesComptes $administration): void
    {
        $this->validate(
            ['motifSuspension' => ['required', 'string', 'min:10', 'max:255']],
            attributes: ['motifSuspension' => 'motif'],
        );

        $this->agir(
            fn (User $cible) => $administration->suspendre(auth()->user(), $cible, $this->motifSuspension),
            $this->compteVise,
            'Compte suspendu.',
        );

        $this->reset(['compteVise', 'motifSuspension']);
    }

    public function reactiver(int $userId, AdministrationDesComptes $administration): void
    {
        $this->agir(
            fn (User $cible) => $administration->reactiver(auth()->user(), $cible),
            $userId,
            'Compte réactivé.',
        );
    }

    public function designer(int $userId, string $role, AdministrationDesComptes $administration): void
    {
        $this->agir(
            fn (User $cible) => $administration->designerChef(auth()->user(), $cible, $role),
            $userId,
            match ($role) {
                User::ROLE_CP => 'Chef de promotion désigné. L\'ancien titulaire revient au rang d\'étudiant.',
                User::ROLE_CPA => 'Adjoint désigné.',
                default => 'Retour au rang d\'étudiant.',
            },
        );
    }

    public function approuverReinitialisation(int $demandeId, AdministrationDesComptes $administration): void
    {
        $demande = DemandeReinitialisation::with('utilisateur')->find($demandeId);

        if (! $demande) {
            return;
        }

        try {
            $this->motDePasseProvisoire = $administration->approuverReinitialisation(auth()->user(), $demande);
            $this->beneficiaireProvisoire = $demande->utilisateur->nom_complet;
        } catch (ValidationException $e) {
            session()->flash('erreur', collect($e->errors())->flatten()->first());
        }
    }

    public function ouvrirRejet(int $demandeId): void
    {
        $this->demandeVisee = $demandeId;
        $this->motifRejet = '';
    }

    public function rejeterReinitialisation(AdministrationDesComptes $administration): void
    {
        $this->validate(
            ['motifRejet' => ['required', 'string', 'min:5', 'max:255']],
            attributes: ['motifRejet' => 'motif'],
        );

        $demande = DemandeReinitialisation::with('utilisateur')->find($this->demandeVisee);

        if ($demande) {
            try {
                $administration->rejeterReinitialisation(auth()->user(), $demande, $this->motifRejet);
                session()->flash('succes', 'Demande rejetée.');
            } catch (ValidationException $e) {
                session()->flash('erreur', collect($e->errors())->flatten()->first());
            }
        }

        $this->reset(['demandeVisee', 'motifRejet']);
    }

    public function fermerProvisoire(): void
    {
        $this->reset(['motDePasseProvisoire', 'beneficiaireProvisoire']);
    }

    private function agir(callable $action, ?int $userId, string $succes): void
    {
        $cible = $userId ? User::find($userId) : null;

        if (! $cible) {
            return;
        }

        try {
            $action($cible);
            session()->flash('succes', $succes);
        } catch (ValidationException $e) {
            session()->flash('erreur', collect($e->errors())->flatten()->first());
        }
    }

    public function render(AdministrationDesComptes $administration): View
    {
        $administrables = $administration->comptesAdministrables(auth()->user(), $this->recherche);

        return view('livewire.gestion-des-comptes', [
            'comptes' => $administrables->paginate(20),
            'demandes' => DemandeReinitialisation::with('utilisateur.promotion')
                ->enAttente()
                ->whereIn('user_id', $administration->comptesAdministrables(auth()->user())->select('users.id'))
                ->latest()
                ->get(),
        ]);
    }
}
