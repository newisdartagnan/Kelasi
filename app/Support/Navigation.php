<?php

namespace App\Support;

use App\Models\DemandeModification;
use App\Models\DemandeReinitialisation;
use App\Models\Seance;
use App\Models\User;
use App\Services\Messagerie;

/**
 * Ce que chaque rôle voit dans la barre de navigation.
 *
 * L'application ne montre pas les mêmes écrans à tout le monde : un chef de
 * promotion vient pour saisir, un enseignant pour contresigner, un doyen pour
 * lire. La navigation le dit d'emblée plutôt que de proposer à chacun des
 * pages qui lui seront refusées.
 *
 * Les entrées portent un rang. Sur téléphone, seules les quatre premières
 * tiennent dans la barre du bas : au-delà, les libellés se touchent et
 * deviennent illisibles. Le reste passe derrière un bouton « Plus ».
 */
final class Navigation
{
    /** Nombre d'entrées que la barre du bas peut porter sans se tasser. */
    public const PLACES_EN_BAS = 4;

    /** @return list<array{route: string, libelle: string, icone: string, pastille?: int}> */
    public static function pour(User $utilisateur): array
    {
        $liens = [
            ['route' => 'tableau-de-bord', 'libelle' => 'Avancement', 'icone' => '◴'],
        ];

        // L'action quotidienne du rôle vient en deuxième : c'est celle pour
        // laquelle la personne ouvre l'application.
        if ($utilisateur->estChefDePromotion()) {
            $liens[] = ['route' => 'seances.saisir', 'libelle' => 'Saisir', 'icone' => '✎'];
        }

        if ($utilisateur->hasRole(User::ROLE_ENSEIGNANT)) {
            $liens[] = [
                'route' => 'seances.a-valider',
                'libelle' => 'À valider',
                'icone' => '✓',
                'pastille' => self::seancesAValider($utilisateur),
            ];
        }

        if ($utilisateur->can('demande.arbitrer')) {
            $liens[] = [
                'route' => 'demandes',
                'libelle' => 'Demandes',
                'icone' => '⇄',
                'pastille' => DemandeModification::enAttente()->count(),
            ];
        }

        $liens[] = [
            'route' => 'messages',
            'libelle' => 'Messages',
            'icone' => '✉',
            'pastille' => app(Messagerie::class)->nonLus($utilisateur),
        ];

        $liens[] = [
            'route' => 'notifications',
            'libelle' => 'Rappels',
            'icone' => '◔',
            'pastille' => $utilisateur->unreadNotifications()->count(),
        ];

        $liens[] = ['route' => 'activites', 'libelle' => 'Activités', 'icone' => '◈'];
        $liens[] = ['route' => 'documents', 'libelle' => 'Documents', 'icone' => '⎙'];
        $liens[] = ['route' => 'assiduite', 'libelle' => 'Assiduité', 'icone' => '◑'];

        if (! $utilisateur->can('demande.arbitrer') && $utilisateur->can('demande.creer')) {
            $liens[] = ['route' => 'demandes', 'libelle' => 'Demandes', 'icone' => '⇄'];
        }

        $liens[] = ['route' => 'seances.journal', 'libelle' => 'Journal', 'icone' => '☰'];

        if ($utilisateur->can('utilisateur.designer.cp')) {
            $liens[] = [
                'route' => 'comptes',
                'libelle' => 'Comptes',
                'icone' => '☖',
                'pastille' => DemandeReinitialisation::enAttente()->count(),
            ];
        }

        if ($utilisateur->can('inscription.deposer')) {
            $liens[] = ['route' => 'inscrits', 'libelle' => 'Inscrits', 'icone' => '☷'];
        }

        if ($utilisateur->can('programme.modifier')) {
            $liens[] = ['route' => 'annees', 'libelle' => 'Année', 'icone' => '⌛'];
        }

        return $liens;
    }

    /**
     * Les entrées de la barre du bas. Un écran ouvert qui n'y figure pas y
     * prend la dernière place : sinon rien n'indiquerait où l'on se trouve.
     *
     * @return list<array{route: string, libelle: string, icone: string, pastille?: int}>
     */
    public static function principales(User $utilisateur, ?string $routeCourante = null): array
    {
        $tous = self::pour($utilisateur);
        $retenus = array_slice($tous, 0, self::PLACES_EN_BAS);

        if ($routeCourante && ! collect($retenus)->contains('route', $routeCourante)) {
            $courant = collect($tous)->firstWhere('route', $routeCourante);

            if ($courant) {
                $retenus[self::PLACES_EN_BAS - 1] = $courant;
            }
        }

        return $retenus;
    }

    /**
     * Ce qui passe derrière le bouton « Plus ».
     *
     * @return list<array{route: string, libelle: string, icone: string, pastille?: int}>
     */
    public static function secondaires(User $utilisateur, ?string $routeCourante = null): array
    {
        $principales = collect(self::principales($utilisateur, $routeCourante))->pluck('route');

        return collect(self::pour($utilisateur))
            ->reject(fn (array $lien) => $principales->contains($lien['route']))
            ->values()
            ->all();
    }

    /** Le nombre total d'éléments en attente derrière le bouton « Plus ». */
    public static function pastilleSecondaire(User $utilisateur, ?string $routeCourante = null): int
    {
        return collect(self::secondaires($utilisateur, $routeCourante))
            ->sum(fn (array $lien) => $lien['pastille'] ?? 0);
    }

    /** Le nombre de séances qui attendent le contreseing de cet enseignant. */
    private static function seancesAValider(User $enseignant): int
    {
        return Seance::enAttente()
            ->whereIn('cours_id', $enseignant->coursEnseignes()->select('cours.id'))
            ->count();
    }
}
