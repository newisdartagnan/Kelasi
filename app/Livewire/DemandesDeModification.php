<?php

namespace App\Livewire;

use App\Models\Cours;
use App\Models\DemandeModification;
use App\Models\User;
use App\Services\ArbitrageDesDemandes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Component;

/**
 * Un seul écran pour les deux bouts du circuit.
 *
 * L'enseignant y dépose et suit ses demandes ; le VDE y voit celles qui
 * l'attendent et tranche. Séparer les deux vues aurait obligé chacun à
 * apprendre un écran de plus pour la même conversation.
 */
class DemandesDeModification extends Component
{
    public bool $formulaireOuvert = false;

    public ?int $coursId = null;

    public string $type = 'volume';

    public string $description = '';

    public string $justification = '';

    /** @var array<string, string> */
    public array $modifications = [
        'intitule' => '',
        'credits' => '',
        'heures_cmi' => '',
        'heures_td' => '',
        'heures_tp' => '',
    ];

    public ?int $demandeArbitree = null;

    public string $motifDecision = '';

    public function ouvrirFormulaire(): void
    {
        $this->formulaireOuvert = true;
        $this->coursId ??= $this->coursDisponibles()->first()?->id;
    }

    public function fermerFormulaire(): void
    {
        $this->reset(['formulaireOuvert', 'description', 'justification', 'modifications']);
        $this->resetValidation();
    }

    /** Pré-remplit avec les valeurs actuelles : on modifie à partir de l'existant. */
    public function updatedCoursId(): void
    {
        $cours = $this->coursDisponibles()->firstWhere('id', (int) $this->coursId);

        if ($cours) {
            $this->modifications = [
                'intitule' => $cours->intitule,
                'credits' => (string) $cours->credits,
                'heures_cmi' => (string) $cours->heures_cmi,
                'heures_td' => (string) $cours->heures_td,
                'heures_tp' => (string) $cours->heures_tp,
            ];
        }
    }

    public function deposer(ArbitrageDesDemandes $arbitrage): void
    {
        $this->validate([
            'coursId' => ['required', 'integer'],
            'type' => ['required', 'string'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'justification' => ['required', 'string', 'min:10', 'max:2000'],
        ], attributes: [
            'coursId' => 'cours',
            'type' => 'type de demande',
            'description' => 'description',
            'justification' => 'justification',
        ]);

        $cours = $this->coursDisponibles()->firstWhere('id', (int) $this->coursId);

        if (! $cours) {
            $this->addError('coursId', 'Ce cours n\'est pas dans votre périmètre.');

            return;
        }

        try {
            $arbitrage->deposer(auth()->user(), $cours, [
                'type' => $this->type,
                'description' => $this->description,
                'justification' => $this->justification,
                'modifications' => $this->modifications,
            ]);
        } catch (ValidationException $e) {
            $this->addError('description', collect($e->errors())->flatten()->first());

            return;
        }

        session()->flash('succes', 'Demande déposée. Le vice-recteur en sera saisi.');
        $this->fermerFormulaire();
    }

    public function approuver(int $demandeId, ArbitrageDesDemandes $arbitrage): void
    {
        $this->trancher($demandeId, fn (DemandeModification $d) => $arbitrage->approuver(
            auth()->user(),
            $d,
            $this->motifDecision ?: null,
        ), 'Demande approuvée. Le programme est mis à jour.');
    }

    public function ouvrirRejet(int $demandeId): void
    {
        $this->demandeArbitree = $demandeId;
        $this->motifDecision = '';
    }

    public function rejeter(ArbitrageDesDemandes $arbitrage): void
    {
        $this->validate([
            'motifDecision' => ['required', 'string', 'min:10', 'max:2000'],
        ], attributes: ['motifDecision' => 'motif de la décision']);

        $this->trancher(
            $this->demandeArbitree,
            fn (DemandeModification $d) => $arbitrage->rejeter(auth()->user(), $d, $this->motifDecision),
            'Demande rejetée. Le demandeur en est informé.',
        );
    }

    public function retirer(int $demandeId, ArbitrageDesDemandes $arbitrage): void
    {
        $this->trancher(
            $demandeId,
            fn (DemandeModification $d) => $arbitrage->retirer(auth()->user(), $d),
            'Demande retirée.',
        );
    }

    private function trancher(?int $demandeId, callable $action, string $succes): void
    {
        $demande = $demandeId ? DemandeModification::with('cours')->find($demandeId) : null;

        if (! $demande) {
            return;
        }

        try {
            $action($demande);
            session()->flash('succes', $succes);
        } catch (ValidationException $e) {
            session()->flash('erreur', collect($e->errors())->flatten()->first());
        }

        $this->reset(['demandeArbitree', 'motifDecision']);
    }

    /** Les cours sur lesquels l'utilisateur peut demander quelque chose. */
    public function coursDisponibles(): Collection
    {
        $utilisateur = auth()->user();

        if ($utilisateur->hasRole(User::ROLE_ENSEIGNANT)) {
            return $utilisateur->coursEnseignes()->with('uniteEnseignement.promotion')->get();
        }

        if ($utilisateur->estAutoriteFacultaire()) {
            return Cours::with('uniteEnseignement.promotion')
                ->whereHas(
                    'uniteEnseignement.promotion.departement',
                    fn (Builder $q) => $q->where('faculte_id', $utilisateur->faculte_id),
                )
                ->get();
        }

        return collect();
    }

    public function render(): View
    {
        $utilisateur = auth()->user();

        return view('livewire.demandes-de-modification', [
            'arbitre' => $utilisateur->can('demande.arbitrer'),
            'peutDemander' => $this->coursDisponibles()->isNotEmpty(),
            'cours' => $this->coursDisponibles(),
            'demandes' => $this->requete()->get(),
            'types' => DemandeModification::TYPES,
        ]);
    }

    /**
     * Le VDE voit tout ce qui attend une décision, puis l'historique. Les
     * autres ne voient que leurs propres demandes.
     */
    private function requete(): Builder
    {
        $utilisateur = auth()->user();

        $requete = DemandeModification::with(['cours.uniteEnseignement.promotion', 'demandeur', 'decideur'])
            ->orderByRaw("CASE WHEN statut = ? THEN 0 ELSE 1 END", [DemandeModification::STATUT_EN_ATTENTE])
            ->latest('created_at');

        if ($utilisateur->can('demande.arbitrer')) {
            return $requete;
        }

        return $requete->where('demandeur_id', $utilisateur->id);
    }
}
