<?php

namespace App\Services;

use App\Models\Cours;
use App\Models\Seance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Le registre des seances : saisie, contreseing, contestation.
 *
 * Toutes les ecritures sur une seance passent par ici. La regle qui les
 * gouverne tient en une phrase : celui qui saisit ne valide pas, celui qui
 * valide ne saisit pas.
 */
class RegistreDesSeances
{
    /**
     * Enregistre une seance saisie par un chef de promotion.
     *
     * @param  array<string, mixed>  $donnees
     */
    public function saisir(User $auteur, Cours $cours, array $donnees): Seance
    {
        $promotion = $cours->uniteEnseignement->promotion;

        $this->verifierPerimetreDeSaisie($auteur, $promotion->id);
        $this->verifierBornes($donnees['date_seance'], $donnees['heure_debut'], $donnees['heure_fin']);

        return Seance::create([
            'uuid' => $donnees['uuid'] ?? null,
            'cours_id' => $cours->id,
            'promotion_id' => $promotion->id,
            'local_id' => $donnees['local_id'] ?? null,
            'date_seance' => $donnees['date_seance'],
            'heure_debut' => $donnees['heure_debut'],
            'heure_fin' => $donnees['heure_fin'],
            'duree_minutes' => $this->dureeEnMinutes($donnees['heure_debut'], $donnees['heure_fin']),
            'type' => $donnees['type'],
            'matiere_couverte' => $donnees['matiere_couverte'],
            'observations' => $donnees['observations'] ?? null,
            'effectif_present' => $donnees['effectif_present'] ?? null,
            'statut' => Seance::STATUT_SOUMISE,
            'saisie_par_id' => $auteur->id,
            'soumise_at' => now(),
            'source' => $donnees['source'] ?? 'web',
            'saisie_locale_at' => $donnees['saisie_locale_at'] ?? null,
        ]);
    }

    /**
     * Contreseing de l'enseignant. C'est cet acte, et lui seul, qui fait
     * entrer la seance dans l'avancement.
     */
    public function valider(User $enseignant, Seance $seance): Seance
    {
        $this->verifierQualitePourValider($enseignant, $seance);

        if ($seance->statut === Seance::STATUT_VALIDEE) {
            return $seance;   // idempotent : deux clics ne valent pas deux validations
        }

        if ($seance->statut !== Seance::STATUT_SOUMISE) {
            throw ValidationException::withMessages([
                'seance' => 'Seule une séance soumise peut être validée.',
            ]);
        }

        $seance->update([
            'statut' => Seance::STATUT_VALIDEE,
            'validee_par_id' => $enseignant->id,
            'validee_at' => now(),
            'motif_contestation' => null,
        ]);

        return $seance;
    }

    /**
     * L'enseignant conteste : la seance retourne au chef de promotion pour
     * correction. Elle sort de l'avancement le temps du differend.
     */
    public function contester(User $enseignant, Seance $seance, string $motif): Seance
    {
        $this->verifierQualitePourValider($enseignant, $seance);

        if ($seance->statut !== Seance::STATUT_SOUMISE) {
            throw ValidationException::withMessages([
                'seance' => 'Seule une séance soumise peut être contestée.',
            ]);
        }

        $seance->update([
            'statut' => Seance::STATUT_CONTESTEE,
            'motif_contestation' => $motif,
            'validee_par_id' => null,
            'validee_at' => null,
        ]);

        return $seance;
    }

    /**
     * Synchronise un lot de seances saisies hors ligne.
     *
     * L'uuid vient de l'appareil : une seance déjà connue est ignoree plutot
     * que dupliquee. C'est ce qui rend la synchronisation rejouable -- un CP
     * dont la connexion coupe en plein envoi peut relancer sans crainte.
     *
     * @param  array<int, array<string, mixed>>  $lot
     * @return array{acceptees: list<string>, ignorees: list<string>, refusees: array<string, string>}
     */
    public function synchroniser(User $auteur, array $lot): array
    {
        $resultat = ['acceptees' => [], 'ignorees' => [], 'refusees' => []];

        $connus = Seance::whereIn('uuid', array_column($lot, 'uuid'))->pluck('uuid')->flip();

        foreach ($lot as $donnees) {
            $uuid = $donnees['uuid'] ?? null;

            if (! $uuid) {
                continue;
            }

            if ($connus->has($uuid)) {
                $resultat['ignorees'][] = $uuid;

                continue;
            }

            try {
                DB::transaction(function () use ($auteur, $donnees) {
                    $cours = Cours::with('uniteEnseignement.promotion')->findOrFail($donnees['cours_id']);
                    $this->saisir($auteur, $cours, [...$donnees, 'source' => 'offline']);
                });

                $resultat['acceptees'][] = $uuid;
            } catch (\Throwable $e) {
                $resultat['refusees'][$uuid] = $e instanceof ValidationException
                    ? collect($e->errors())->flatten()->first()
                    : 'Séance refusée.';
            }
        }

        return $resultat;
    }

    /** Un chef de promotion ne saisit que pour sa propre promotion. */
    private function verifierPerimetreDeSaisie(User $auteur, int $promotionId): void
    {
        if (! $auteur->estChefDePromotion()) {
            throw ValidationException::withMessages([
                'seance' => 'Seul un chef de promotion peut saisir une séance.',
            ]);
        }

        if ($auteur->promotion_id !== $promotionId) {
            throw ValidationException::withMessages([
                'seance' => 'Vous ne pouvez saisir que pour votre propre promotion.',
            ]);
        }
    }

    /**
     * Seul un enseignant attribue au cours peut trancher. Le titulaire comme
     * l'assistant : c'est souvent l'assistant qui a tenu les travaux
     * pratiques.
     */
    private function verifierQualitePourValider(User $enseignant, Seance $seance): void
    {
        $attribue = $seance->cours->attributions()
            ->where('user_id', $enseignant->id)
            ->exists();

        if (! $attribue) {
            throw ValidationException::withMessages([
                'seance' => 'Seul un enseignant attribue a ce cours peut se prononcer.',
            ]);
        }

        if ($enseignant->id === $seance->saisie_par_id) {
            throw ValidationException::withMessages([
                'seance' => 'La personne qui a saisi la séance ne peut pas la valider.',
            ]);
        }
    }

    /** Une seance se tient dans le passe, dans la journee, et dure. */
    private function verifierBornes(mixed $date, string $debut, string $fin): void
    {
        if (Carbon::parse($date)->startOfDay()->isFuture()) {
            throw ValidationException::withMessages([
                'date_seance' => 'On ne saisit pas une séance qui ne s\'est pas encore tenue.',
            ]);
        }

        if ($this->dureeEnMinutes($debut, $fin) <= 0) {
            throw ValidationException::withMessages([
                'heure_fin' => 'L\'heure de fin doit suivre l\'heure de début.',
            ]);
        }
    }

    private function dureeEnMinutes(string $debut, string $fin): int
    {
        $d = Carbon::createFromFormat('H:i', substr($debut, 0, 5));
        $f = Carbon::createFromFormat('H:i', substr($fin, 0, 5));

        return max(0, (int) $d->diffInMinutes($f, absolute: false));
    }
}
