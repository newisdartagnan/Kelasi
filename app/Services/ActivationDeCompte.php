<?php

namespace App\Services;

use App\Models\AnneeAcademique;
use App\Models\InscriptionAutorisee;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * L'ouverture d'un compte.
 *
 * On ne crée pas un compte : on active une ligne déposée par le secrétariat.
 * Sans matricule inscrit sur la liste de l'année en cours, il n'y a rien à
 * activer — et c'est précisément ce qui donne sa valeur à tout le reste.
 */
class ActivationDeCompte
{
    /**
     * Retrouve l'inscription correspondant à un matricule, ou explique
     * pourquoi elle ne convient pas.
     */
    public function chercher(string $matricule, ?AnneeAcademique $annee = null): InscriptionAutorisee
    {
        $annee ??= AnneeAcademique::courante();

        if (! $annee) {
            throw ValidationException::withMessages([
                'matricule' => 'Aucune année académique n\'est ouverte. Adressez-vous au secrétariat.',
            ]);
        }

        $inscription = InscriptionAutorisee::with('promotion.departement')
            ->where('matricule', strtoupper(trim($matricule)))
            ->where('annee_academique_id', $annee->id)
            ->first();

        if (! $inscription) {
            throw ValidationException::withMessages([
                'matricule' => 'Ce matricule ne figure pas sur la liste des inscrits de cette année.',
            ]);
        }

        if (! $inscription->estDisponible()) {
            throw ValidationException::withMessages([
                'matricule' => 'Un compte a déjà été ouvert pour ce matricule. Utilisez la page de connexion.',
            ]);
        }

        return $inscription;
    }

    /**
     * Ouvre le compte et consomme la ligne d'inscription.
     *
     * Le rattachement — faculté, département, promotion — vient de la liste,
     * jamais de ce que la personne déclare. C'est la différence entre une
     * inscription et une auto-déclaration.
     */
    public function activer(InscriptionAutorisee $inscription, string $motDePasse, ?string $telephone = null): User
    {
        return DB::transaction(function () use ($inscription, $motDePasse, $telephone) {
            $utilisateur = User::create([
                'matricule' => $inscription->matricule,
                'name' => $inscription->nom,
                'postnom' => $inscription->postnom,
                'prenom' => $inscription->prenom,
                'telephone' => $telephone,
                'password' => $motDePasse,
                'faculte_id' => $inscription->promotion?->departement->faculte_id ?? $inscription->faculte_id,
                'departement_id' => $inscription->promotion?->departement_id,
                'promotion_id' => $inscription->promotion_id,
                'actif' => true,
            ]);

            $utilisateur->assignRole($inscription->role_prevu);

            $inscription->update([
                'user_id' => $utilisateur->id,
                'activee_at' => now(),
            ]);

            return $utilisateur;
        });
    }
}
