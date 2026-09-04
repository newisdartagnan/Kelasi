<?php

namespace App\Services;

use App\Models\Activite;
use App\Models\Promotion;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Les activités : examens, interrogations, visites guidées, conférences.
 *
 * Une activité a une portée, et c'est elle — jamais le créateur — qui décide
 * qui la voit. Un chef de promotion ne peut en créer que pour sa promotion,
 * un doyen pour sa faculté, le vice-recteur pour toute l'université. Chacun
 * annonce donc exactement aussi loin que son mandat le permet.
 */
class GestionDesActivites
{
    /** @param  array<string, mixed>  $donnees */
    public function creer(User $auteur, array $donnees): Activite
    {
        $portee = $donnees['portee'];

        $this->verifierMandat($auteur, $portee);
        $rattachement = $this->rattacher($auteur, $portee, $donnees);
        $this->verifierDates($donnees['debut'], $donnees['fin'] ?? null);

        return Activite::create([
            'titre' => $donnees['titre'],
            'description' => $donnees['description'] ?? null,
            'type' => $donnees['type'],
            'portee' => $portee,
            ...$rattachement,
            'cours_id' => $donnees['cours_id'] ?? null,
            'local_id' => $donnees['local_id'] ?? null,
            'debut' => $donnees['debut'],
            'fin' => $donnees['fin'] ?? null,
            'statut' => 'planifiee',
            'createur_id' => $auteur->id,
        ]);
    }

    /** @param  array<string, mixed>  $donnees */
    public function mettreAJour(User $auteur, Activite $activite, array $donnees): Activite
    {
        $this->verifierPouvoirSur($auteur, $activite);

        if ($activite->statut === 'cloturee') {
            throw ValidationException::withMessages([
                'activite' => 'Une activité clôturée ne se modifie plus.',
            ]);
        }

        $this->verifierDates($donnees['debut'] ?? $activite->debut, $donnees['fin'] ?? $activite->fin);

        $activite->update(collect($donnees)->only([
            'titre', 'description', 'type', 'debut', 'fin', 'local_id', 'cours_id',
        ])->all());

        return $activite;
    }

    public function cloturer(User $auteur, Activite $activite): Activite
    {
        $this->verifierPouvoirSur($auteur, $activite);

        $activite->update(['statut' => 'cloturee']);

        return $activite;
    }

    public function annuler(User $auteur, Activite $activite): Activite
    {
        $this->verifierPouvoirSur($auteur, $activite);

        $activite->update(['statut' => 'annulee']);

        return $activite;
    }

    /** Les portées qu'un utilisateur a le droit d'employer. */
    public function porteesAutorisees(User $utilisateur): array
    {
        if ($utilisateur->can('activite.creer.universite')) {
            return [
                Activite::PORTEE_UNIVERSITE => 'Toute l\'université',
                Activite::PORTEE_FACULTE => 'Une faculté',
                Activite::PORTEE_PROMOTION => 'Une promotion',
            ];
        }

        if ($utilisateur->can('activite.creer.faculte')) {
            return [
                Activite::PORTEE_FACULTE => 'Toute ma faculté',
                Activite::PORTEE_PROMOTION => 'Une promotion',
            ];
        }

        if ($utilisateur->can('activite.creer.promotion')) {
            return [Activite::PORTEE_PROMOTION => 'Ma promotion'];
        }

        return [];
    }

    private function verifierMandat(User $auteur, string $portee): void
    {
        if (! array_key_exists($portee, $this->porteesAutorisees($auteur))) {
            throw ValidationException::withMessages([
                'portee' => 'Votre fonction ne vous permet pas d\'annoncer une activité à cette échelle.',
            ]);
        }
    }

    /**
     * Rattache l'activité à l'objet que sa portée désigne.
     *
     * Un chef de promotion ne choisit pas sa promotion : c'est la sienne. Un
     * doyen ne choisit pas sa faculté. Sans cela, l'un pourrait annoncer un
     * examen à la promotion d'à côté.
     *
     * @param  array<string, mixed>  $donnees
     * @return array<string, int|null>
     */
    private function rattacher(User $auteur, string $portee, array $donnees): array
    {
        $vide = ['promotion_id' => null, 'departement_id' => null, 'faculte_id' => null];

        return match ($portee) {
            Activite::PORTEE_UNIVERSITE => $vide,

            Activite::PORTEE_FACULTE => [
                ...$vide,
                'faculte_id' => $auteur->aPorteeUniversitaire()
                    ? ($donnees['faculte_id'] ?? throw ValidationException::withMessages(
                        ['faculte_id' => 'Choisissez la faculté concernée.'],
                    ))
                    : $auteur->faculte_id,
            ],

            Activite::PORTEE_PROMOTION => [
                ...$vide,
                'promotion_id' => $this->promotionVisee($auteur, $donnees),
            ],

            default => throw ValidationException::withMessages(['portee' => 'Portée inconnue.']),
        };
    }

    /** @param  array<string, mixed>  $donnees */
    private function promotionVisee(User $auteur, array $donnees): int
    {
        if ($auteur->estChefDePromotion()) {
            return $auteur->promotion_id
                ?? throw ValidationException::withMessages([
                    'promotion_id' => 'Aucune promotion n\'est rattachée à votre compte.',
                ]);
        }

        $promotionId = $donnees['promotion_id']
            ?? throw ValidationException::withMessages([
                'promotion_id' => 'Choisissez la promotion concernée.',
            ]);

        if ($auteur->estAutoriteFacultaire()) {
            $appartient = Promotion::where('id', $promotionId)
                ->whereHas('departement', fn ($q) => $q->where('faculte_id', $auteur->faculte_id))
                ->exists();

            if (! $appartient) {
                throw ValidationException::withMessages([
                    'promotion_id' => 'Cette promotion n\'appartient pas à votre faculté.',
                ]);
            }
        }

        return (int) $promotionId;
    }

    /**
     * Modifier ou clôturer une activité suppose de pouvoir l'annoncer : son
     * auteur, ou une autorité dont le mandat couvre sa portée.
     */
    /** La même règle que verifierPouvoirSur, posée en question plutôt qu'en garde. */
    public function peutAgirSur(User $auteur, Activite $activite): bool
    {
        try {
            $this->verifierPouvoirSur($auteur, $activite);

            return true;
        } catch (ValidationException) {
            return false;
        }
    }

    private function verifierPouvoirSur(User $auteur, Activite $activite): void
    {
        if ($activite->createur_id === $auteur->id) {
            return;
        }

        if ($auteur->aPorteeUniversitaire()) {
            return;
        }

        $couvre = $auteur->estAutoriteFacultaire()
            && in_array($activite->portee, [Activite::PORTEE_FACULTE, Activite::PORTEE_PROMOTION], true)
            && $this->faculteDeLActivite($activite) === $auteur->faculte_id;

        if (! $couvre) {
            throw ValidationException::withMessages([
                'activite' => 'Vous ne pouvez pas modifier cette activité.',
            ]);
        }
    }

    private function faculteDeLActivite(Activite $activite): ?int
    {
        return $activite->faculte_id
            ?? $activite->promotion?->departement->faculte_id;
    }

    private function verifierDates(mixed $debut, mixed $fin): void
    {
        if ($fin && Carbon::parse($fin)->lt(Carbon::parse($debut))) {
            throw ValidationException::withMessages([
                'fin' => 'La fin doit suivre le début.',
            ]);
        }
    }
}
