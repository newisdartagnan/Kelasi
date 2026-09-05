<?php

namespace App\Services;

use App\Models\DemandeReinitialisation;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * L'administration des comptes : suspendre, réactiver, désigner un chef de
 * promotion, et remettre un mot de passe.
 *
 * Tout se joue sur le périmètre. Un doyen administre sa faculté, le
 * vice-recteur toute l'université, et personne n'administre au-dessus de soi :
 * un doyen ne suspend pas un autre doyen, et surtout pas le vice-recteur.
 */
class AdministrationDesComptes
{
    /** Longueur du mot de passe provisoire : assez court pour être dicté. */
    private const LONGUEUR_PROVISOIRE = 10;

    /**
     * Les rôles qu'un administrateur peut atteindre.
     *
     * @var array<string, list<string>>
     */
    private const PORTEE = [
        User::ROLE_DF => [User::ROLE_ETUDIANT, User::ROLE_CP, User::ROLE_CPA, User::ROLE_ENSEIGNANT],
        User::ROLE_DFA => [User::ROLE_ETUDIANT, User::ROLE_CP, User::ROLE_CPA, User::ROLE_ENSEIGNANT],
        User::ROLE_VDE => [User::ROLE_ETUDIANT, User::ROLE_CP, User::ROLE_CPA, User::ROLE_ENSEIGNANT, User::ROLE_DF, User::ROLE_DFA],
        User::ROLE_ADMIN => [User::ROLE_ETUDIANT, User::ROLE_CP, User::ROLE_CPA, User::ROLE_ENSEIGNANT, User::ROLE_DF, User::ROLE_DFA],
    ];

    public function suspendre(User $administrateur, User $cible, string $motif): User
    {
        $this->verifierPortee($administrateur, $cible);

        if ($motif === '') {
            throw ValidationException::withMessages([
                'motif' => 'Une suspension se motive : la personne doit savoir ce qu\'on lui reproche.',
            ]);
        }

        $cible->update([
            'suspendu_at' => now(),
            'suspendu_par_id' => $administrateur->id,
            'motif_suspension' => $motif,
        ]);

        return $cible;
    }

    public function reactiver(User $administrateur, User $cible): User
    {
        $this->verifierPortee($administrateur, $cible);

        $cible->update([
            'suspendu_at' => null,
            'suspendu_par_id' => null,
            'motif_suspension' => null,
        ]);

        return $cible;
    }

    /**
     * Désigne un étudiant comme chef de promotion, ou le rend à son rang.
     *
     * Un seul titulaire par promotion : nommer un nouveau chef rend
     * automatiquement l'ancien à son rang d'étudiant, faute de quoi deux
     * personnes saisiraient les mêmes séances.
     */
    public function designerChef(User $administrateur, User $etudiant, string $role): User
    {
        $this->verifierPortee($administrateur, $etudiant);

        if (! in_array($role, [User::ROLE_ETUDIANT, User::ROLE_CP, User::ROLE_CPA], true)) {
            throw ValidationException::withMessages([
                'role' => 'On ne désigne ici qu\'un chef de promotion, son adjoint, ou le retour au rang d\'étudiant.',
            ]);
        }

        if (! $etudiant->promotion_id) {
            throw ValidationException::withMessages([
                'role' => 'Cette personne n\'est rattachée à aucune promotion.',
            ]);
        }

        return DB::transaction(function () use ($etudiant, $role) {
            if ($role !== User::ROLE_ETUDIANT) {
                User::where('promotion_id', $etudiant->promotion_id)
                    ->whereKeyNot($etudiant->id)
                    ->role($role)
                    ->get()
                    ->each(fn (User $ancien) => $ancien->syncRoles([User::ROLE_ETUDIANT]));
            }

            $etudiant->syncRoles([$role]);

            return $etudiant->refresh();
        });
    }

    /** L'intéressé demande une remise de mot de passe. */
    public function demanderReinitialisation(User $demandeur, ?string $motif = null): DemandeReinitialisation
    {
        $enCours = DemandeReinitialisation::enAttente()->where('user_id', $demandeur->id)->first();

        if ($enCours) {
            return $enCours;   // une demande en attente suffit : on n'en empile pas
        }

        return DemandeReinitialisation::create([
            'user_id' => $demandeur->id,
            'motif' => $motif,
            'statut' => DemandeReinitialisation::STATUT_EN_ATTENTE,
        ]);
    }

    /**
     * L'autorité approuve et reçoit le mot de passe provisoire, une seule fois.
     *
     * Il est rendu par la méthode plutôt qu'enregistré : le stocker en clair,
     * même le temps d'un affichage, en ferait une porte ouverte pour qui lit
     * la base.
     */
    public function approuverReinitialisation(User $decideur, DemandeReinitialisation $demande): string
    {
        $this->verifierPortee($decideur, $demande->utilisateur);
        $this->verifierEnAttente($demande);

        $provisoire = $this->motDePasseProvisoire();

        DB::transaction(function () use ($decideur, $demande, $provisoire) {
            $demande->utilisateur->update([
                'password' => $provisoire,
                'doit_changer_motdepasse' => true,
            ]);

            $demande->update([
                'statut' => DemandeReinitialisation::STATUT_APPROUVEE,
                'decideur_id' => $decideur->id,
                'decidee_at' => now(),
                'provisoire_actif' => true,
            ]);
        });

        return $provisoire;
    }

    public function rejeterReinitialisation(User $decideur, DemandeReinitialisation $demande, string $motif): DemandeReinitialisation
    {
        $this->verifierPortee($decideur, $demande->utilisateur);
        $this->verifierEnAttente($demande);

        $demande->update([
            'statut' => DemandeReinitialisation::STATUT_REJETEE,
            'decideur_id' => $decideur->id,
            'decidee_at' => now(),
            'motif_decision' => $motif,
        ]);

        return $demande;
    }

    /** La personne choisit enfin son propre mot de passe. */
    public function changerSonMotDePasse(User $utilisateur, string $nouveau): User
    {
        $utilisateur->update([
            'password' => $nouveau,
            'doit_changer_motdepasse' => false,
        ]);

        DemandeReinitialisation::where('user_id', $utilisateur->id)
            ->where('provisoire_actif', true)
            ->update(['provisoire_actif' => false]);

        return $utilisateur;
    }

    /**
     * Les comptes qu'un administrateur peut voir et toucher.
     *
     * @return Builder<User>
     */
    public function comptesAdministrables(User $administrateur, string $recherche = ''): Builder
    {
        $roles = self::PORTEE[$administrateur->getRoleNames()->first()] ?? [];

        return User::query()
            ->with('roles', 'promotion.departement')
            ->whereKeyNot($administrateur->id)
            ->when($roles !== [], fn (Builder $q) => $q->role($roles), fn (Builder $q) => $q->whereRaw('1 = 0'))
            ->when(
                ! $administrateur->aPorteeUniversitaire(),
                fn (Builder $q) => $q->where('faculte_id', $administrateur->faculte_id),
            )
            ->when($recherche !== '', fn (Builder $q) => $q->where(
                fn (Builder $s) => collect(['name', 'prenom', 'matricule'])
                    ->each(fn (string $champ) => $s->orWhereRaw(
                        "LOWER({$champ}) LIKE ?",
                        ['%'.mb_strtolower($recherche).'%'],
                    )),
            ))
            ->orderBy('name');
    }

    /**
     * Le périmètre : la faculté pour un doyen, l'université pour le
     * vice-recteur — et jamais quelqu'un dont le rôle échappe à sa portée.
     */
    private function verifierPortee(User $administrateur, User $cible): void
    {
        $roles = self::PORTEE[$administrateur->getRoleNames()->first()] ?? [];

        if (array_intersect($roles, $cible->getRoleNames()->all()) === []) {
            throw ValidationException::withMessages([
                'compte' => 'Votre fonction ne vous permet pas d\'administrer ce compte.',
            ]);
        }

        if ($administrateur->aPorteeUniversitaire()) {
            return;
        }

        if ($cible->faculte_id !== $administrateur->faculte_id) {
            throw ValidationException::withMessages([
                'compte' => 'Ce compte n\'appartient pas à votre faculté.',
            ]);
        }
    }

    private function verifierEnAttente(DemandeReinitialisation $demande): void
    {
        if ($demande->statut !== DemandeReinitialisation::STATUT_EN_ATTENTE) {
            throw ValidationException::withMessages([
                'demande' => 'Cette demande a déjà été traitée.',
            ]);
        }
    }

    /**
     * Sans I, l, O ni 0 : le mot de passe est dicté de vive voix ou recopié
     * d'un écran, et ces caractères se confondent.
     */
    private function motDePasseProvisoire(): string
    {
        $alphabet = 'ABCDEFGHJKMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';

        return collect(range(1, self::LONGUEUR_PROVISOIRE))
            ->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])
            ->implode('');
    }
}
