<?php

namespace App\Services;

use App\Models\Cours;
use App\Models\DemandeModification;
use App\Models\User;
use App\Support\VolumeHoraire;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Le circuit de modification du programme.
 *
 * Le programme n'appartient pas à l'enseignant : il est arrêté par l'autorité
 * académique. Un enseignant qui veut plus d'heures, un intitulé corrigé ou
 * une autre répartition entre cours magistral et travaux pratiques dépose une
 * demande ; le VDE tranche.
 *
 * L'approbation applique réellement la modification au cours. Sans cela, le
 * circuit ne serait qu'un fil de discussion : le programme affiché resterait
 * faux, et l'avancement se calculerait toujours sur l'ancien volume.
 */
class ArbitrageDesDemandes
{
    /**
     * Dépose une demande.
     *
     * @param  array<string, mixed>  $donnees
     */
    public function deposer(User $demandeur, Cours $cours, array $donnees): DemandeModification
    {
        $this->verifierQualitePourDemander($demandeur, $cours);

        $modifications = $this->normaliser($donnees['type'], $donnees['modifications'] ?? []);

        $this->verifierCoherence($donnees['type'], $cours, $modifications);

        return DemandeModification::create([
            'cours_id' => $cours->id,
            'demandeur_id' => $demandeur->id,
            'type' => $donnees['type'],
            'description' => $donnees['description'],
            'justification' => $donnees['justification'],
            'modifications' => $modifications ?: null,
            'statut' => DemandeModification::STATUT_EN_ATTENTE,
        ]);
    }

    /**
     * Le VDE approuve : la décision est enregistrée et, si la demande porte
     * sur des valeurs, celles-ci sont écrites sur le cours dans la même
     * transaction. Approuver sans appliquer laisserait le programme faux.
     */
    public function approuver(User $decideur, DemandeModification $demande, ?string $motif = null): DemandeModification
    {
        $this->verifierQualitePourArbitrer($decideur, $demande);

        return DB::transaction(function () use ($decideur, $demande, $motif) {
            $ancien = $this->etatDuCours($demande->cours);

            $this->appliquer($demande);

            $demande->update([
                'statut' => DemandeModification::STATUT_APPROUVEE,
                'decideur_id' => $decideur->id,
                'decidee_at' => now(),
                'motif_decision' => $motif,
                // On garde l'état d'avant : sans lui, personne ne pourrait dire
                // plus tard ce que l'approbation a réellement changé.
                'modifications' => [
                    ...($demande->modifications ?? []),
                    'etat_precedent' => $ancien,
                ],
            ]);

            return $demande->refresh();
        });
    }

    public function rejeter(User $decideur, DemandeModification $demande, string $motif): DemandeModification
    {
        $this->verifierQualitePourArbitrer($decideur, $demande);

        $demande->update([
            'statut' => DemandeModification::STATUT_REJETEE,
            'decideur_id' => $decideur->id,
            'decidee_at' => now(),
            'motif_decision' => $motif,
        ]);

        return $demande;
    }

    /** Le demandeur peut retirer sa demande tant qu'elle n'a pas été tranchée. */
    public function retirer(User $utilisateur, DemandeModification $demande): DemandeModification
    {
        if ($demande->demandeur_id !== $utilisateur->id) {
            throw ValidationException::withMessages([
                'demande' => 'Seul l\'auteur peut retirer sa demande.',
            ]);
        }

        $this->verifierEnAttente($demande);

        $demande->update(['statut' => DemandeModification::STATUT_RETIREE]);

        return $demande;
    }

    /**
     * Écrit la modification sur le cours.
     *
     * Les demandes de report ou les demandes libres ne portent aucune valeur :
     * la décision est enregistrée, le programme reste tel quel.
     */
    private function appliquer(DemandeModification $demande): void
    {
        $valeurs = $demande->modifications ?? [];
        $cours = $demande->cours;

        $champs = match ($demande->type) {
            'intitule' => array_intersect_key($valeurs, array_flip(['intitule'])),
            'volume', 'repartition' => array_intersect_key(
                $valeurs,
                array_flip(['heures_cmi', 'heures_td', 'heures_tp', 'credits']),
            ),
            default => [],
        };

        if ($champs === []) {
            return;
        }

        // Changer les crédits change le volume total : le TPE suit la règle
        // ministérielle plutôt que de rester sur son ancienne valeur.
        if (isset($champs['credits'])) {
            $champs['heures_tpe'] = VolumeHoraire::heuresTpe((int) $champs['credits']);
        }

        $cours->update($champs);
    }

    /**
     * Ne conserve que les champs que le type de demande autorise. Une demande
     * d'intitulé ne doit pas pouvoir glisser un changement d'horaire.
     *
     * @param  array<string, mixed>  $brutes
     * @return array<string, mixed>
     */
    private function normaliser(string $type, array $brutes): array
    {
        $autorises = match ($type) {
            'intitule' => ['intitule'],
            'volume' => ['heures_cmi', 'heures_td', 'heures_tp', 'credits'],
            'repartition' => ['heures_cmi', 'heures_td', 'heures_tp'],
            default => [],
        };

        return collect($brutes)
            ->only($autorises)
            ->reject(fn ($valeur) => $valeur === null || $valeur === '')
            ->map(fn ($valeur, $cle) => $cle === 'intitule' ? trim((string) $valeur) : (int) $valeur)
            ->all();
    }

    /**
     * Une redistribution ne crée pas d'heures : elle déplace du volume entre
     * cours magistral, travaux dirigés et travaux pratiques.
     *
     * @param  array<string, mixed>  $modifications
     */
    private function verifierCoherence(string $type, Cours $cours, array $modifications): void
    {
        if ($modifications === []) {
            if (in_array($type, ['intitule', 'volume', 'repartition'], true)) {
                throw ValidationException::withMessages([
                    'modifications' => 'Précisez au moins une valeur à modifier.',
                ]);
            }

            return;
        }

        if ($type === 'repartition') {
            $nouveau = ($modifications['heures_cmi'] ?? $cours->heures_cmi)
                + ($modifications['heures_td'] ?? $cours->heures_td)
                + ($modifications['heures_tp'] ?? $cours->heures_tp);

            if ($nouveau !== $cours->heures_prevues) {
                throw ValidationException::withMessages([
                    'modifications' => sprintf(
                        'Une redistribution conserve le volume total : %d h attendues, %d h proposées. Pour changer le volume, déposez une demande de type « volume horaire ».',
                        $cours->heures_prevues,
                        $nouveau,
                    ),
                ]);
            }
        }

        if ($type === 'intitule' && ($modifications['intitule'] ?? '') === '') {
            throw ValidationException::withMessages([
                'modifications' => 'Le nouvel intitulé ne peut pas être vide.',
            ]);
        }
    }

    /** L'enseignant attribué au cours, ou l'autorité de sa faculté. */
    private function verifierQualitePourDemander(User $demandeur, Cours $cours): void
    {
        if ($demandeur->aPorteeUniversitaire()) {
            return;   // le VDE modifie directement, sans passer par une demande
        }

        $attribue = $cours->attributions()->where('user_id', $demandeur->id)->exists();

        if ($attribue) {
            return;
        }

        $faculteDuCours = $cours->uniteEnseignement->promotion->departement->faculte_id;

        if ($demandeur->estAutoriteFacultaire() && $demandeur->faculte_id === $faculteDuCours) {
            return;
        }

        throw ValidationException::withMessages([
            'demande' => 'Seul un enseignant attribué à ce cours ou l\'autorité de sa faculté peut demander une modification.',
        ]);
    }

    private function verifierQualitePourArbitrer(User $decideur, DemandeModification $demande): void
    {
        if (! $decideur->can('demande.arbitrer')) {
            throw ValidationException::withMessages([
                'demande' => 'Seul le vice-recteur chargé de l\'enseignement arbitre les demandes.',
            ]);
        }

        if ($decideur->id === $demande->demandeur_id) {
            throw ValidationException::withMessages([
                'demande' => 'On n\'arbitre pas sa propre demande.',
            ]);
        }

        $this->verifierEnAttente($demande);
    }

    private function verifierEnAttente(DemandeModification $demande): void
    {
        if ($demande->statut !== DemandeModification::STATUT_EN_ATTENTE) {
            throw ValidationException::withMessages([
                'demande' => 'Cette demande a déjà été traitée.',
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function etatDuCours(Cours $cours): array
    {
        return $cours->only(['intitule', 'credits', 'heures_cmi', 'heures_td', 'heures_tp', 'heures_tpe']);
    }
}
