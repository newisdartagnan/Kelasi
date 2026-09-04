<?php

namespace App\Livewire;

use App\Models\Cours;
use App\Models\Document;
use App\Models\User;
use App\Services\BibliothequeDeCours;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * La bibliothèque des supports de cours.
 *
 * L'enseignant y dépose, l'étudiant y télécharge. Le même écran sert les
 * deux : ce que l'on voit dépend de ce que l'on est.
 */
class DocumentsDeCours extends Component
{
    use WithFileUploads;

    #[Url(as: 'cours')]
    public ?int $coursFiltre = null;

    public bool $formulaireOuvert = false;

    public $fichier;

    public ?int $coursId = null;

    public string $titre = '';

    public string $description = '';

    public bool $publie = true;

    public function ouvrirFormulaire(): void
    {
        $this->formulaireOuvert = true;
        $this->coursId ??= $this->coursOuDeposer()->first()?->id;
    }

    public function fermerFormulaire(): void
    {
        $this->reset(['formulaireOuvert', 'fichier', 'titre', 'description', 'publie']);
        $this->resetValidation();
    }

    public function deposer(BibliothequeDeCours $bibliotheque): void
    {
        $this->validate([
            'fichier' => [
                'required', 'file',
                'mimes:'.implode(',', BibliothequeDeCours::TYPES_ACCEPTES),
                'max:'.BibliothequeDeCours::TAILLE_MAX_KO,
            ],
            'coursId' => ['required', 'integer'],
            'titre' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ], [
            'fichier.max' => 'Le fichier dépasse 20 Mo. Au-delà, le téléversement échoue sur une connexion de campus.',
            'fichier.mimes' => 'Format non accepté. Déposez un PDF, un document bureautique, une image ou une archive.',
        ], [
            'fichier' => 'fichier', 'coursId' => 'cours', 'titre' => 'titre',
        ]);

        $cours = $this->coursOuDeposer()->firstWhere('id', (int) $this->coursId);

        if (! $cours) {
            $this->addError('coursId', 'Ce cours ne vous est pas attribué.');

            return;
        }

        try {
            $bibliotheque->deposer(auth()->user(), $cours, $this->fichier, [
                'titre' => $this->titre,
                'description' => $this->description ?: null,
                'publie' => $this->publie,
            ]);
        } catch (ValidationException $e) {
            $this->addError('fichier', collect($e->errors())->flatten()->first());

            return;
        }

        session()->flash('succes', $this->publie
            ? 'Document déposé et partagé avec la promotion.'
            : 'Document déposé. Il reste invisible tant que vous ne le publiez pas.');

        $this->fermerFormulaire();
    }

    public function basculerPublication(int $documentId, BibliothequeDeCours $bibliotheque): void
    {
        $document = Document::find($documentId);

        if (! $document) {
            return;
        }

        try {
            $bascule = $bibliotheque->basculerPublication(auth()->user(), $document);
            session()->flash('succes', $bascule->publie ? 'Document publié.' : 'Document retiré du partage.');
        } catch (ValidationException $e) {
            session()->flash('erreur', collect($e->errors())->flatten()->first());
        }
    }

    public function retirer(int $documentId, BibliothequeDeCours $bibliotheque): void
    {
        $document = Document::find($documentId);

        if (! $document) {
            return;
        }

        try {
            $bibliotheque->retirer(auth()->user(), $document);
            session()->flash('succes', 'Document retiré.');
        } catch (ValidationException $e) {
            session()->flash('erreur', collect($e->errors())->flatten()->first());
        }
    }

    /** @return Collection<int, Cours> */
    public function coursOuDeposer(): Collection
    {
        $utilisateur = auth()->user();

        if (! $utilisateur->hasRole(User::ROLE_ENSEIGNANT)) {
            return collect();
        }

        return $utilisateur->coursEnseignes()->with('uniteEnseignement.promotion')->get();
    }

    public function render(): View
    {
        return view('livewire.documents-de-cours', [
            'documents' => $this->requete()->get(),
            'coursDepot' => $this->coursOuDeposer(),
            'coursLisibles' => $this->coursLisibles(),
            'peutDeposer' => $this->coursOuDeposer()->isNotEmpty(),
        ]);
    }

    /** Les cours dont l'utilisateur peut voir la bibliothèque. */
    private function coursLisibles(): Collection
    {
        $utilisateur = auth()->user();

        if ($utilisateur->promotion_id) {
            return Cours::with('uniteEnseignement')
                ->whereHas('uniteEnseignement', fn (Builder $q) => $q->where('promotion_id', $utilisateur->promotion_id))
                ->get();
        }

        return $this->coursOuDeposer();
    }

    private function requete(): Builder
    {
        $utilisateur = auth()->user();

        $requete = Document::with(['cours.uniteEnseignement.promotion', 'deposant'])
            ->when($this->coursFiltre, fn (Builder $q) => $q->where('cours_id', $this->coursFiltre))
            ->latest();

        if ($utilisateur->aPorteeUniversitaire()) {
            return $requete;
        }

        // Ses propres dépôts, publiés ou non, plus ce qui est publié dans son
        // périmètre de lecture.
        return $requete->where(function (Builder $q) use ($utilisateur) {
            $q->where('deposant_id', $utilisateur->id)
                ->orWhere(fn (Builder $s) => $s->where('publie', true)
                    ->whereIn('cours_id', $this->coursLisibles()->pluck('id')));
        });
    }
}
