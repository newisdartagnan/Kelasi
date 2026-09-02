<?php

namespace Tests;

use App\Models\AnneeAcademique;
use App\Models\Attribution;
use App\Models\Cours;
use App\Models\Departement;
use App\Models\Faculte;
use App\Models\Promotion;
use App\Models\UniteEnseignement;
use App\Models\User;
use App\Support\VolumeHoraire;
use Database\Seeders\RolesEtPermissionsSeeder;

/**
 * Le decor minimal d'un test : une faculte, une promotion, un cours, un chef
 * de promotion et l'enseignant qui lui est attribue.
 */
trait ConstruitUnContexteAcademique
{
    protected AnneeAcademique $annee;

    protected Faculte $faculte;

    protected Promotion $promotion;

    protected Cours $cours;

    protected User $chef;

    protected User $enseignant;

    protected function construireContexte(): void
    {
        $this->seed(RolesEtPermissionsSeeder::class);

        $this->annee = AnneeAcademique::create([
            'libelle' => '2025-2026',
            'date_debut' => now()->subMonths(4)->toDateString(),
            'date_fin' => now()->addMonths(5)->toDateString(),
            'statut' => 'en_cours',
            'active' => true,
        ]);

        $this->faculte = Faculte::create([
            'nom' => 'Faculte de Droit',
            'sigle' => 'DROIT',
            'slug' => 'faculte-de-droit',
        ]);

        $departement = Departement::create([
            'faculte_id' => $this->faculte->id,
            'nom' => 'Droit prive et judiciaire',
            'sigle' => 'DPJ',
        ]);

        $this->promotion = Promotion::create([
            'departement_id' => $departement->id,
            'annee_academique_id' => $this->annee->id,
            'niveau' => 'L1',
            'intitule' => 'Premiere annee de licence en droit',
            'active' => true,
        ]);

        $ue = UniteEnseignement::create([
            'promotion_id' => $this->promotion->id,
            'code' => 'UE1',
            'intitule' => 'Fondements du droit',
            'semestre' => 1,
            'credits' => 6,
        ]);

        $volume = VolumeHoraire::ventiler(6, ['td' => 0.25]);

        $this->cours = Cours::create([
            'unite_enseignement_id' => $ue->id,
            'code' => 'DRT101',
            'intitule' => 'Introduction generale a l\'etude du droit',
            'credits' => 6,
            'heures_cmi' => $volume['cmi'],
            'heures_td' => $volume['td'],
            'heures_tp' => $volume['tp'],
            'heures_tpe' => $volume['tpe'],
        ]);

        $this->chef = $this->creerUtilisateur('CP-001', User::ROLE_CP);
        $this->chef->update([
            'faculte_id' => $this->faculte->id,
            'departement_id' => $departement->id,
            'promotion_id' => $this->promotion->id,
        ]);

        $this->enseignant = $this->creerUtilisateur('ENS-001', User::ROLE_ENSEIGNANT);
        $this->enseignant->update(['faculte_id' => $this->faculte->id]);

        Attribution::create([
            'cours_id' => $this->cours->id,
            'user_id' => $this->enseignant->id,
            'role' => Attribution::ROLE_TITULAIRE,
        ]);
    }

    protected function creerUtilisateur(string $matricule, string $role): User
    {
        $user = User::create([
            'matricule' => $matricule,
            'name' => 'KABEYA',
            'prenom' => 'Test',
            'email' => strtolower($matricule).'@unikin.ac.cd',
            'password' => 'motdepasse',
            'actif' => true,
        ]);

        $user->assignRole($role);

        return $user;
    }
}
