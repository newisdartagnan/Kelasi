<?php

namespace App\Support;

use App\Models\DemandeModification;
use App\Models\Seance;
use App\Models\User;

/**
 * Ce que chaque role voit dans la barre de navigation.
 *
 * L'application ne montre pas les memes ecrans a tout le monde : un chef de
 * promotion vient pour saisir, un enseignant pour contresigner, un doyen pour
 * lire. La navigation le dit d'emblee plutot que de proposer a chacun des
 * pages qui lui seront refusees.
 */
final class Navigation
{
    /** @return list<array{route: string, libelle: string, icone: string, pastille?: int}> */
    public static function pour(User $utilisateur): array
    {
        $liens = [
            ['route' => 'tableau-de-bord', 'libelle' => 'Avancement', 'icone' => '◴'],
        ];

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

        $liens[] = ['route' => 'activites', 'libelle' => 'Activités', 'icone' => '◈'];
        $liens[] = ['route' => 'documents', 'libelle' => 'Documents', 'icone' => '⎙'];

        if ($utilisateur->can('demande.arbitrer')) {
            $liens[] = [
                'route' => 'demandes',
                'libelle' => 'Demandes',
                'icone' => '⇄',
                'pastille' => DemandeModification::enAttente()->count(),
            ];
        } elseif ($utilisateur->can('demande.creer')) {
            $liens[] = ['route' => 'demandes', 'libelle' => 'Demandes', 'icone' => '⇄'];
        }

        $liens[] = ['route' => 'seances.journal', 'libelle' => 'Journal', 'icone' => '☰'];

        if ($utilisateur->can('inscription.deposer')) {
            $liens[] = ['route' => 'inscrits', 'libelle' => 'Inscrits', 'icone' => '☷'];
        }

        return $liens;
    }

    /** Le nombre de seances qui attendent le contreseing de cet enseignant. */
    private static function seancesAValider(User $enseignant): int
    {
        return Seance::enAttente()
            ->whereIn('cours_id', $enseignant->coursEnseignes()->select('cours.id'))
            ->count();
    }
}
