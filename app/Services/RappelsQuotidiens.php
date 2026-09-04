<?php

namespace App\Services;

use App\Models\Activite;
use App\Models\AnneeAcademique;
use App\Models\Seance;
use App\Models\User;
use App\Notifications\RappelQuotidien;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Ce que chacun doit voir arriver le matin.
 *
 * Le principe : une seule notification par personne, qui rassemble ce qui la
 * concerne, et rien du tout quand il n'y a rien à dire. Des rappels envoyés
 * dans le vide apprennent au destinataire à les ignorer, et le jour où il
 * faut vraiment le prévenir, il ne lit plus.
 */
class RappelsQuotidiens
{
    public function __construct(
        private readonly PushWeb $push,
        private readonly CalculateurAvancement $calculateur,
    ) {}

    /** Envoie les rappels du jour et rend le nombre de destinataires touchés. */
    public function envoyer(): int
    {
        $touches = 0;

        foreach (User::actifs()->with('roles')->cursor() as $utilisateur) {
            $points = $this->pointsPour($utilisateur);

            if ($points === []) {
                continue;
            }

            $rappel = new RappelQuotidien($points);
            $utilisateur->notify($rappel);
            $this->push->envoyerA($utilisateur, $rappel->toPush());

            $touches++;
        }

        return $touches;
    }

    /**
     * Ce qui mérite d'être signalé à cette personne aujourd'hui.
     *
     * @return list<array{titre: string, detail: string, route: string}>
     */
    public function pointsPour(User $utilisateur): array
    {
        return collect([
            $this->seancesAContresigner($utilisateur),
            $this->saisieDuJour($utilisateur),
            $this->activitesImminentes($utilisateur),
            $this->coursEnRetard($utilisateur),
        ])->filter()->values()->all();
    }

    /** L'enseignant qui laisse traîner des contreseings bloque l'avancement. */
    private function seancesAContresigner(User $utilisateur): ?array
    {
        if (! $utilisateur->hasRole(User::ROLE_ENSEIGNANT)) {
            return null;
        }

        $nombre = Seance::enAttente()
            ->whereIn('cours_id', $utilisateur->coursEnseignes()->select('cours.id'))
            ->count();

        if ($nombre === 0) {
            return null;
        }

        return [
            'titre' => 'Séances à contresigner',
            'detail' => $nombre === 1
                ? 'Une séance attend votre signature.'
                : "{$nombre} séances attendent votre signature.",
            'route' => '/seances/a-valider',
        ];
    }

    /** Le chef de promotion qui n'a rien saisi hier a probablement oublié. */
    private function saisieDuJour(User $utilisateur): ?array
    {
        if (! $utilisateur->estChefDePromotion() || ! $utilisateur->promotion_id) {
            return null;
        }

        $hier = now()->subDay();

        if ($hier->isWeekend()) {
            return null;
        }

        $saisies = Seance::where('promotion_id', $utilisateur->promotion_id)
            ->whereDate('date_seance', $hier->toDateString())
            ->exists();

        if ($saisies) {
            return null;
        }

        return [
            'titre' => 'Séances d\'hier',
            'detail' => 'Aucune séance saisie pour '.$hier->translatedFormat('l j F').'.',
            'route' => '/seances/saisir',
        ];
    }

    private function activitesImminentes(User $utilisateur): ?array
    {
        $activites = Activite::visiblesPour($utilisateur)
            ->where('statut', 'planifiee')
            ->whereBetween('debut', [now(), now()->addDays(2)])
            ->orderBy('debut')
            ->get();

        if ($activites->isEmpty()) {
            return null;
        }

        $premiere = $activites->first();

        return [
            'titre' => $activites->count() === 1 ? $premiere->titre : 'Activités à venir',
            'detail' => $activites->count() === 1
                ? $premiere->debut->translatedFormat('l j F à H\hi')
                : $activites->count().' activités dans les deux jours.',
            'route' => '/activites',
        ];
    }

    /**
     * L'alerte de retard ne va qu'à ceux qui peuvent agir : les autorités.
     * Un étudiant n'y pourrait rien, et la recevoir chaque matin l'userait.
     */
    private function coursEnRetard(User $utilisateur): ?array
    {
        if (! $utilisateur->estAutoriteFacultaire() && ! $utilisateur->aPorteeUniversitaire()) {
            return null;
        }

        $annee = AnneeAcademique::courante();

        if (! $annee) {
            return null;
        }

        $attendu = $this->calculateur->tauxAttendu($annee);
        $enRetard = $this->promotionsSuivies($utilisateur, $annee)
            ->filter(fn ($avancement) => $avancement->ecartSurAttendu($attendu) < -15)
            ->count();

        if ($enRetard === 0) {
            return null;
        }

        return [
            'titre' => 'Promotions en retard',
            'detail' => $enRetard === 1
                ? 'Une promotion accuse plus de 15 points de retard.'
                : "{$enRetard} promotions accusent plus de 15 points de retard.",
            'route' => '/',
        ];
    }

    /** @return Collection<int, \App\Support\Avancement> */
    private function promotionsSuivies(User $utilisateur, AnneeAcademique $annee): Collection
    {
        $facultes = $utilisateur->aPorteeUniversitaire()
            ? \App\Models\Faculte::whereHas('departements.promotions')->get()
            : \App\Models\Faculte::whereKey($utilisateur->faculte_id)->get();

        return $facultes->flatMap(
            fn ($faculte) => $this->calculateur->parPromotionDeFaculte($faculte, $annee)->values(),
        );
    }
}
