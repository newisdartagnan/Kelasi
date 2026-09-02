<?php

namespace App\Livewire;

use App\Models\AnneeAcademique;
use App\Models\Cours;
use App\Models\Faculte;
use App\Models\Promotion;
use App\Models\User;
use App\Services\CalculateurAvancement;
use App\Support\Avancement;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * L'ecran d'accueil : l'avancement, a la maille qui correspond au role.
 *
 * Le VDE voit les facultés, le doyen ses promotions, le chef de promotion et
 * l'étudiant leurs cours, l'enseignant les siens. Personne ne choisit sa
 * maille : elle decoule de sa fonction.
 */
class TableauDeBord extends Component
{
    #[Url(as: 'semestre')]
    public ?int $semestre = 1;

    #[Url(as: 'faculte')]
    public ?int $faculteId = null;

    public function render(CalculateurAvancement $calculateur): View
    {
        $utilisateur = auth()->user();
        $annee = AnneeAcademique::courante();

        return view('livewire.tableau-de-bord', [
            'annee' => $annee,
            'tauxAttendu' => $calculateur->tauxAttendu($annee),
            'maille' => $this->maille($utilisateur),
            'lignes' => $this->lignes($utilisateur, $calculateur, $annee),
        ]);
    }

    /** Le niveau de lecture impose par le role. */
    private function maille(User $utilisateur): string
    {
        return match (true) {
            $utilisateur->aPorteeUniversitaire() => 'facultes',
            $utilisateur->estAutoriteFacultaire() => 'promotions',
            $utilisateur->hasRole(User::ROLE_ENSEIGNANT) => 'mes-cours',
            default => 'cours',
        };
    }

    /** @return Collection<int, array{libelle: string, detail: string, avancement: Avancement}> */
    private function lignes(User $utilisateur, CalculateurAvancement $calculateur, ?AnneeAcademique $annee): Collection
    {
        if (! $annee) {
            return collect();
        }

        return match ($this->maille($utilisateur)) {
            'facultes' => $this->parFaculte($calculateur, $annee),
            'promotions' => $this->parPromotion($calculateur, $annee, $utilisateur->faculte_id),
            'mes-cours' => $this->parCoursEnseignes($utilisateur, $calculateur),
            default => $this->parCoursDeLaPromotion($utilisateur, $calculateur),
        };
    }

    private function parFaculte(CalculateurAvancement $calculateur, AnneeAcademique $annee): Collection
    {
        // Le VDE peut plonger dans une faculté : la maille bascule alors sur
        // ses promotions, sans changer d'ecran.
        if ($this->faculteId) {
            return $this->parPromotion($calculateur, $annee, $this->faculteId);
        }

        return Faculte::query()
            ->whereHas('departements.promotions', fn ($q) => $q->where('annee_academique_id', $annee->id))
            ->orderBy('ordre')
            ->get()
            ->map(fn (Faculte $f) => [
                'id' => $f->id,
                'libelle' => $f->nom,
                'detail' => $f->sigle,
                'lien' => ['faculteId' => $f->id],
                'avancement' => $calculateur->pourFaculte($f, $annee, $this->semestre),
            ]);
    }

    private function parPromotion(CalculateurAvancement $calculateur, AnneeAcademique $annee, ?int $faculteId): Collection
    {
        $faculte = Faculte::find($faculteId);

        if (! $faculte) {
            return collect();
        }

        $avancements = $calculateur->parPromotionDeFaculte($faculte, $annee, $this->semestre);

        return Promotion::with('departement')
            ->whereIn('id', $avancements->keys())
            ->orderBy('niveau')
            ->get()
            ->map(fn (Promotion $p) => [
                'id' => $p->id,
                'libelle' => $p->nom_complet,
                'detail' => $p->departement->nom,
                'avancement' => $avancements[$p->id],
            ]);
    }

    private function parCoursDeLaPromotion(User $utilisateur, CalculateurAvancement $calculateur): Collection
    {
        $promotion = $utilisateur->promotion;

        if (! $promotion) {
            return collect();
        }

        return $this->habillerCours(
            $calculateur->parCoursDePromotion($promotion, $this->semestre),
        );
    }

    private function parCoursEnseignes(User $enseignant, CalculateurAvancement $calculateur): Collection
    {
        $cours = $enseignant->coursEnseignes()
            ->with('uniteEnseignement.promotion.departement')
            ->when($this->semestre, fn ($q) => $q->whereHas(
                'uniteEnseignement',
                fn ($u) => $u->where('semestre', $this->semestre),
            ))
            ->get();

        return $cours->map(fn (Cours $c) => [
            'id' => $c->id,
            'libelle' => $c->intitule,
            'detail' => $c->uniteEnseignement->promotion->nom_complet,
            'avancement' => $calculateur->pourCours($c),
        ]);
    }

    /** @param  Collection<int, Avancement>  $avancements */
    private function habillerCours(Collection $avancements): Collection
    {
        return Cours::with('uniteEnseignement')
            ->whereIn('id', $avancements->keys())
            ->get()
            ->sortBy('uniteEnseignement.ordre')
            ->values()
            ->map(fn (Cours $c) => [
                'id' => $c->id,
                'libelle' => $c->intitule,
                'detail' => "{$c->uniteEnseignement->code} · {$c->credits} crédits",
                'avancement' => $avancements[$c->id],
            ]);
    }
}
