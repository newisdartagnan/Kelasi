<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Cours;
use App\Models\Message;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * La messagerie interne, cadrée par la hiérarchie académique.
 *
 * Le cahier des charges d'origine énumérait, rôle par rôle, avec qui chacun
 * peut échanger. Cette table est reproduite ici telle quelle, parce qu'elle
 * a une raison d'être : sans elle, un millier d'étudiants pourraient écrire
 * directement au vice-recteur, et la messagerie deviendrait inutilisable pour
 * lui — donc inutilisable tout court.
 */
class Messagerie
{
    /**
     * Qui peut ouvrir une conversation avec qui.
     *
     * @var array<string, list<string>>
     */
    private const INTERLOCUTEURS = [
        User::ROLE_ETUDIANT => [User::ROLE_ETUDIANT, User::ROLE_CP, User::ROLE_CPA],
        User::ROLE_CP => [User::ROLE_ETUDIANT, User::ROLE_CP, User::ROLE_CPA, User::ROLE_ENSEIGNANT, User::ROLE_DF, User::ROLE_DFA, User::ROLE_VDE],
        User::ROLE_CPA => [User::ROLE_ETUDIANT, User::ROLE_CP, User::ROLE_CPA, User::ROLE_ENSEIGNANT, User::ROLE_DF, User::ROLE_DFA, User::ROLE_VDE],
        User::ROLE_ENSEIGNANT => [User::ROLE_CP, User::ROLE_CPA, User::ROLE_ENSEIGNANT, User::ROLE_DF, User::ROLE_DFA, User::ROLE_VDE],
        User::ROLE_DF => [User::ROLE_CP, User::ROLE_CPA, User::ROLE_ENSEIGNANT, User::ROLE_DF, User::ROLE_DFA, User::ROLE_VDE],
        User::ROLE_DFA => [User::ROLE_CP, User::ROLE_CPA, User::ROLE_ENSEIGNANT, User::ROLE_DF, User::ROLE_DFA, User::ROLE_VDE],
        User::ROLE_VDE => [User::ROLE_CP, User::ROLE_CPA, User::ROLE_ENSEIGNANT, User::ROLE_DF, User::ROLE_DFA],
        User::ROLE_ADMIN => [User::ROLE_CP, User::ROLE_CPA, User::ROLE_ENSEIGNANT, User::ROLE_DF, User::ROLE_DFA, User::ROLE_VDE],
    ];

    /**
     * Ouvre une conversation directe, ou retrouve celle qui existe déjà.
     *
     * Deux personnes n'ont qu'un fil : rouvrir une conversation ne doit pas
     * disperser l'échange sur plusieurs pages.
     */
    public function ouvrirAvec(User $auteur, User $destinataire): Conversation
    {
        $this->verifierInterlocuteur($auteur, $destinataire);

        $existante = Conversation::where('type', 'directe')
            ->whereHas('participants', fn (Builder $q) => $q->where('user_id', $auteur->id))
            ->whereHas('participants', fn (Builder $q) => $q->where('user_id', $destinataire->id))
            ->withCount('participants')
            ->get()
            ->firstWhere('participants_count', 2);

        if ($existante) {
            return $existante;
        }

        return DB::transaction(function () use ($auteur, $destinataire) {
            $conversation = Conversation::create([
                'type' => 'directe',
                'createur_id' => $auteur->id,
            ]);

            $conversation->participants()->createMany([
                ['user_id' => $auteur->id],
                ['user_id' => $destinataire->id],
            ]);

            return $conversation;
        });
    }

    /**
     * Ouvre le fil d'une promotion, ou retrouve celui qui existe.
     *
     * Un fil de promotion réunit ses étudiants, ses chefs et les enseignants
     * qui y sont attribués. Les participants sont ajoutés à l'ouverture puis
     * réajustés à chaque envoi : une promotion gagne des inscrits en cours
     * d'année, et un fil figé les laisserait dehors.
     */
    public function ouvrirFilDePromotion(User $auteur, Promotion $promotion): Conversation
    {
        $this->verifierAppartenance($auteur, $promotion);

        $conversation = Conversation::firstOrCreate(
            ['type' => 'promotion', 'promotion_id' => $promotion->id],
            ['sujet' => $promotion->nom_complet, 'createur_id' => $auteur->id],
        );

        $this->ajusterParticipants($conversation, $this->membresDePromotion($promotion));

        return $conversation;
    }

    /** Le fil d'un cours : ses enseignants et la promotion qui le suit. */
    public function ouvrirFilDeCours(User $auteur, Cours $cours): Conversation
    {
        $promotion = $cours->uniteEnseignement->promotion;

        $this->verifierAppartenance($auteur, $promotion, $cours);

        $conversation = Conversation::firstOrCreate(
            ['type' => 'cours', 'cours_id' => $cours->id],
            [
                'sujet' => $cours->intitule,
                'promotion_id' => $promotion->id,
                'createur_id' => $auteur->id,
            ],
        );

        $this->ajusterParticipants($conversation, $this->membresDePromotion($promotion));

        return $conversation;
    }

    /**
     * Les fils de groupe auxquels l'utilisateur a droit, qu'ils existent déjà
     * ou non : on propose d'ouvrir plutôt que d'exiger que quelqu'un l'ait
     * fait avant.
     *
     * @return Collection<int, array{type: string, cle: int, libelle: string, detail: string}>
     */
    public function filsDeGroupePossibles(User $utilisateur): Collection
    {
        $promotions = $this->promotionsDe($utilisateur);

        if ($promotions->isEmpty()) {
            return collect();
        }

        $fils = $promotions->map(fn (Promotion $p) => [
            'type' => 'promotion',
            'cle' => $p->id,
            'libelle' => $p->nom_complet,
            'detail' => 'Toute la promotion',
        ]);

        $cours = $utilisateur->hasRole(User::ROLE_ENSEIGNANT)
            ? $utilisateur->coursEnseignes()->with('uniteEnseignement.promotion')->get()
            : Cours::with('uniteEnseignement.promotion')
                ->whereHas('uniteEnseignement', fn (Builder $q) => $q->whereIn('promotion_id', $promotions->pluck('id')))
                ->get();

        return $fils->concat($cours->map(fn (Cours $c) => [
            'type' => 'cours',
            'cle' => $c->id,
            'libelle' => $c->intitule,
            'detail' => $c->uniteEnseignement->promotion->nom_complet,
        ]))->values();
    }

    public function envoyer(User $auteur, Conversation $conversation, string $corps): Message
    {
        if (! $conversation->compte($auteur)) {
            throw ValidationException::withMessages([
                'corps' => 'Vous ne participez pas à cette conversation.',
            ]);
        }

        return DB::transaction(function () use ($auteur, $conversation, $corps) {
            $message = $conversation->messages()->create([
                'uuid' => (string) Str::uuid(),
                'auteur_id' => $auteur->id,
                'corps' => $corps,
            ]);

            $conversation->update(['dernier_message_at' => $message->created_at]);

            // L'auteur a forcément lu ce qu'il vient d'écrire.
            $conversation->participants()
                ->where('user_id', $auteur->id)
                ->update(['lu_jusqu_a' => $message->created_at]);

            return $message;
        });
    }

    public function marquerLu(User $utilisateur, Conversation $conversation): void
    {
        $conversation->participants()
            ->where('user_id', $utilisateur->id)
            ->update(['lu_jusqu_a' => now()]);
    }

    /** Le nombre de messages non lus, toutes conversations confondues. */
    public function nonLus(User $utilisateur): int
    {
        return Message::whereHas(
            'conversation.participants',
            fn (Builder $q) => $q->where('user_id', $utilisateur->id)
                ->where(fn (Builder $s) => $s->whereNull('lu_jusqu_a')
                    ->orWhereColumn('lu_jusqu_a', '<', 'messages.created_at')),
        )
            ->where('auteur_id', '!=', $utilisateur->id)
            ->count();
    }

    /**
     * Les personnes à qui l'utilisateur peut écrire.
     *
     * Le cadrage par rôle ne suffit pas : un chef de promotion peut écrire à
     * un enseignant, mais pas à n'importe lequel de l'université. On restreint
     * donc aussi au périmètre — sa faculté, sa promotion.
     *
     * La recherche compare en minuscules des deux côtés. PostgreSQL rend LIKE
     * sensible à la casse, là où SQLite ne l'est pas : sans cela, chercher
     * « bolima » ne trouverait pas BOLIMA, et personne ne tape un nom de
     * famille en capitales.
     *
     * @return Collection<int, User>
     */
    public function destinatairesPossibles(User $utilisateur, string $recherche = ''): Collection
    {
        $roles = $this->rolesAutorises($utilisateur);

        if ($roles === []) {
            return collect();
        }

        return User::query()
            ->actifs()
            ->whereKeyNot($utilisateur->id)
            ->role($roles)
            ->when(! $utilisateur->aPorteeUniversitaire(), fn (Builder $q) => $this->limiterAuPerimetre($q, $utilisateur))
            ->when($recherche !== '', fn (Builder $q) => $q->where(
                fn (Builder $s) => collect(['name', 'prenom', 'matricule'])
                    ->each(fn (string $champ) => $s->orWhereRaw(
                        "LOWER({$champ}) LIKE ?",
                        ['%'.mb_strtolower($recherche).'%'],
                    )),
            ))
            ->with('roles')
            ->orderBy('name')
            ->limit(30)
            ->get();
    }

    /**
     * Le périmètre : sa faculté, ou sa promotion pour un étudiant.
     *
     * Un enseignant peut être attribué à des cours de plusieurs facultés ;
     * on retient donc aussi les facultés où il enseigne.
     */
    private function limiterAuPerimetre(Builder $requete, User $utilisateur): Builder
    {
        if ($utilisateur->hasAnyRole([User::ROLE_ETUDIANT])) {
            return $requete->where('promotion_id', $utilisateur->promotion_id);
        }

        $facultes = collect([$utilisateur->faculte_id])
            ->merge(
                $utilisateur->coursEnseignes()
                    ->with('uniteEnseignement.promotion.departement')
                    ->get()
                    ->map(fn ($cours) => $cours->uniteEnseignement->promotion->departement->faculte_id),
            )
            ->filter()
            ->unique();

        if ($facultes->isEmpty()) {
            return $requete;
        }

        return $requete->where(fn (Builder $q) => $q
            ->whereIn('faculte_id', $facultes)
            ->orWhereNull('faculte_id'));
    }

    /**
     * Les membres d'un fil de promotion : ses inscrits et les enseignants
     * attribués à ses cours.
     *
     * @return Collection<int, int>
     */
    private function membresDePromotion(Promotion $promotion): Collection
    {
        $inscrits = User::actifs()->where('promotion_id', $promotion->id)->pluck('id');

        $enseignants = User::actifs()
            ->whereHas(
                'coursEnseignes.uniteEnseignement',
                fn (Builder $q) => $q->where('promotion_id', $promotion->id),
            )
            ->pluck('id');

        return $inscrits->concat($enseignants)->unique()->values();
    }

    /**
     * Ajoute les membres qui manquent sans toucher aux autres : réécrire la
     * liste entière effacerait les marqueurs de lecture de chacun.
     *
     * @param  Collection<int, int>  $membres
     */
    private function ajusterParticipants(Conversation $conversation, Collection $membres): void
    {
        $deja = $conversation->participants()->pluck('user_id');
        $manquants = $membres->diff($deja);

        if ($manquants->isEmpty()) {
            return;
        }

        $conversation->participants()->createMany(
            $manquants->map(fn (int $id) => ['user_id' => $id])->all(),
        );
    }

    /** @return Collection<int, Promotion> */
    private function promotionsDe(User $utilisateur): Collection
    {
        if ($utilisateur->promotion_id) {
            return Promotion::whereKey($utilisateur->promotion_id)->with('departement')->get();
        }

        if ($utilisateur->hasRole(User::ROLE_ENSEIGNANT)) {
            return Promotion::with('departement')
                ->whereHas(
                    'unitesEnseignement.cours.attributions',
                    fn (Builder $q) => $q->where('user_id', $utilisateur->id),
                )
                ->get();
        }

        if ($utilisateur->estAutoriteFacultaire()) {
            return Promotion::with('departement')
                ->whereHas('departement', fn (Builder $q) => $q->where('faculte_id', $utilisateur->faculte_id))
                ->active()
                ->get();
        }

        return $utilisateur->aPorteeUniversitaire()
            ? Promotion::with('departement')->active()->get()
            : collect();
    }

    /**
     * On n'écrit pas dans le fil d'une promotion à laquelle on n'appartient
     * pas -- ni comme inscrit, ni comme enseignant, ni comme autorité de sa
     * faculté.
     */
    private function verifierAppartenance(User $utilisateur, Promotion $promotion, ?Cours $cours = null): void
    {
        if ($cours && $cours->attributions()->where('user_id', $utilisateur->id)->exists()) {
            return;
        }

        if ($this->promotionsDe($utilisateur)->contains('id', $promotion->id)) {
            return;
        }

        throw ValidationException::withMessages([
            'conversation' => 'Vous n\'appartenez pas à cette promotion.',
        ]);
    }

    /** @return list<string> */
    private function rolesAutorises(User $utilisateur): array
    {
        return collect($utilisateur->getRoleNames())
            ->flatMap(fn (string $role) => self::INTERLOCUTEURS[$role] ?? [])
            ->unique()
            ->values()
            ->all();
    }

    private function verifierInterlocuteur(User $auteur, User $destinataire): void
    {
        $autorises = $this->rolesAutorises($auteur);
        $rolesCibles = $destinataire->getRoleNames()->all();

        if (array_intersect($autorises, $rolesCibles) === []) {
            throw ValidationException::withMessages([
                'destinataire' => 'Votre fonction ne vous permet pas d\'écrire directement à cette personne.',
            ]);
        }
    }
}
