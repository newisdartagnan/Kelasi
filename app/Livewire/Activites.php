<?php

namespace App\Livewire;

use App\Models\Activite;
use App\Models\Cours;
use App\Models\Faculte;
use App\Models\Local;
use App\Models\Promotion;
use App\Services\GestionDesActivites;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Le calendrier des activités.
 *
 * Chacun y voit ce que la portée des activités lui destine, et n'y crée que
 * ce que sa fonction l'autorise à annoncer.
 */
class Activites extends Component
{
    #[Url(as: 'passees')]
    public bool $inclurePassees = false;

    public bool $formulaireOuvert = false;

    public ?int $activiteModifiee = null;

    public string $titre = '';

    public string $description = '';

    public string $type = 'examen';

    public string $portee = '';

    public ?int $promotionId = null;

    public ?int $faculteId = null;

    public ?int $coursId = null;

    public ?int $localId = null;

    public string $debut = '';

    public string $fin = '';

    public function mount(GestionDesActivites $gestion): void
    {
        $this->portee = array_key_first($gestion->porteesAutorisees(auth()->user())) ?? '';
        $this->debut = now()->addDay()->setTime(8, 0)->format('Y-m-d\TH:i');
    }

    public function ouvrirFormulaire(): void
    {
        $this->formulaireOuvert = true;
    }

    public function fermerFormulaire(): void
    {
        $this->reset([
            'formulaireOuvert', 'activiteModifiee', 'titre', 'description',
            'coursId', 'localId', 'fin',
        ]);
        $this->resetValidation();
    }

    public function modifier(int $activiteId): void
    {
        $activite = Activite::find($activiteId);

        if (! $activite) {
            return;
        }

        $this->activiteModifiee = $activite->id;
        $this->formulaireOuvert = true;
        $this->titre = $activite->titre;
        $this->description = $activite->description ?? '';
        $this->type = $activite->type;
        $this->portee = $activite->portee;
        $this->promotionId = $activite->promotion_id;
        $this->faculteId = $activite->faculte_id;
        $this->coursId = $activite->cours_id;
        $this->localId = $activite->local_id;
        $this->debut = $activite->debut->format('Y-m-d\TH:i');
        $this->fin = $activite->fin?->format('Y-m-d\TH:i') ?? '';
    }

    public function enregistrer(GestionDesActivites $gestion): void
    {
        $this->validate([
            'titre' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'type' => ['required', 'string'],
            'portee' => ['required', 'string'],
            'debut' => ['required', 'date'],
            'fin' => ['nullable', 'date'],
        ], attributes: [
            'titre' => 'titre', 'type' => 'type', 'portee' => 'portée',
            'debut' => 'début', 'fin' => 'fin',
        ]);

        $donnees = [
            'titre' => $this->titre,
            'description' => $this->description ?: null,
            'type' => $this->type,
            'portee' => $this->portee,
            'promotion_id' => $this->promotionId,
            'faculte_id' => $this->faculteId,
            'cours_id' => $this->coursId,
            'local_id' => $this->localId,
            'debut' => $this->debut,
            'fin' => $this->fin ?: null,
        ];

        try {
            if ($this->activiteModifiee) {
                $gestion->mettreAJour(auth()->user(), Activite::findOrFail($this->activiteModifiee), $donnees);
                session()->flash('succes', 'Activité mise à jour.');
            } else {
                $gestion->creer(auth()->user(), $donnees);
                session()->flash('succes', 'Activité annoncée.');
            }
        } catch (ValidationException $e) {
            foreach ($e->errors() as $champ => $messages) {
                $this->addError($this->champLivewire($champ), $messages[0]);
            }

            return;
        }

        $this->fermerFormulaire();
    }

    public function cloturer(int $activiteId, GestionDesActivites $gestion): void
    {
        $this->agir($activiteId, fn (Activite $a) => $gestion->cloturer(auth()->user(), $a), 'Activité clôturée.');
    }

    public function annuler(int $activiteId, GestionDesActivites $gestion): void
    {
        $this->agir($activiteId, fn (Activite $a) => $gestion->annuler(auth()->user(), $a), 'Activité annulée.');
    }

    private function agir(int $activiteId, callable $action, string $succes): void
    {
        $activite = Activite::with('promotion.departement')->find($activiteId);

        if (! $activite) {
            return;
        }

        try {
            $action($activite);
            session()->flash('succes', $succes);
        } catch (ValidationException $e) {
            session()->flash('erreur', collect($e->errors())->flatten()->first());
        }
    }

    /** Les erreurs du service portent des noms de colonnes ; l'écran, des noms de propriétés. */
    private function champLivewire(string $champ): string
    {
        return match ($champ) {
            'promotion_id' => 'promotionId',
            'faculte_id' => 'faculteId',
            'activite' => 'titre',
            default => $champ,
        };
    }

    public function render(GestionDesActivites $gestion): View
    {
        $utilisateur = auth()->user();
        $portees = $gestion->porteesAutorisees($utilisateur);

        return view('livewire.activites', [
            'activites' => $this->calendrier(),
            'gestion' => $gestion,
            'portees' => $portees,
            'peutAnnoncer' => $portees !== [],
            'types' => Activite::TYPES,
            'promotions' => $this->promotionsChoisissables($utilisateur),
            'facultes' => $utilisateur->aPorteeUniversitaire() ? Faculte::orderBy('ordre')->get() : collect(),
            'locaux' => $utilisateur->faculte_id
                ? Local::where('faculte_id', $utilisateur->faculte_id)->where('actif', true)->get()
                : Local::where('actif', true)->limit(50)->get(),
            'cours' => $utilisateur->promotion_id
                ? Cours::whereHas('uniteEnseignement', fn ($q) => $q->where('promotion_id', $utilisateur->promotion_id))->get()
                : collect(),
        ]);
    }

    /** @return Collection<int, Activite> */
    private function calendrier(): Collection
    {
        return Activite::with(['promotion.departement', 'faculte', 'cours', 'local', 'createur'])
            ->visiblesPour(auth()->user())
            ->when(! $this->inclurePassees, fn ($q) => $q->where(function ($sous) {
                $sous->where('debut', '>=', now()->startOfDay())
                    ->orWhere('fin', '>=', now());
            }))
            ->orderBy('debut')
            ->limit(100)
            ->get();
    }

    /** @return Collection<int, Promotion> */
    private function promotionsChoisissables($utilisateur): Collection
    {
        if ($utilisateur->aPorteeUniversitaire()) {
            return Promotion::with('departement.faculte')->active()->get();
        }

        if ($utilisateur->estAutoriteFacultaire()) {
            return Promotion::with('departement')
                ->whereHas('departement', fn ($q) => $q->where('faculte_id', $utilisateur->faculte_id))
                ->active()
                ->get();
        }

        return collect();
    }
}
