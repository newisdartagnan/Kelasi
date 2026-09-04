<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Les huit roles de Kelasi et ce que chacun a le droit de faire.
 *
 * La regle qui structure tout : le chef de promotion saisit mais ne valide
 * pas, l'enseignant valide mais ne saisit pas. Aucun role ne cumule les deux
 * -- c'est cette separation qui donne a l'avancement sa valeur probante.
 */
class RolesEtPermissionsSeeder extends Seeder
{
    /**
     * @var array<string, list<string>>
     */
    private const PERMISSIONS_PAR_ROLE = [
        User::ROLE_ETUDIANT => [
            'avancement.voir.promotion',
            'seance.voir',
            'activite.voir',
            'document.telecharger',
            'message.envoyer',
        ],
        User::ROLE_CP => [
            'avancement.voir.promotion',
            'seance.voir',
            'seance.saisir',
            'seance.modifier.propre',
            'activite.voir',
            'activite.creer.promotion',
            'activite.cloturer.propre',
            'document.telecharger',
            'message.envoyer',
        ],
        User::ROLE_ENSEIGNANT => [
            'avancement.voir.cours',
            'seance.voir',
            'seance.valider',
            'seance.contester',
            'activite.voir',
            'document.telecharger',
            'document.deposer',
            'demande.creer',
            'message.envoyer',
        ],
        User::ROLE_DF => [
            'avancement.voir.faculte',
            'seance.voir',
            'activite.voir',
            'activite.creer.faculte',
            'activite.cloturer.faculte',
            'utilisateur.suspendre.faculte',
            'utilisateur.designer.cp',
            'inscription.deposer',
            'utilisateur.reinitialiser.motdepasse',
            'document.telecharger',
            'demande.creer',
            'export.generer.faculte',
            'message.envoyer',
        ],
        User::ROLE_VDE => [
            'avancement.voir.universite',
            'seance.voir',
            'activite.voir',
            'activite.creer.universite',
            'activite.cloturer.universite',
            'utilisateur.suspendre.universite',
            'utilisateur.designer.cp',
            'utilisateur.reinitialiser.motdepasse',
            'inscription.deposer',
            'document.telecharger',
            'demande.arbitrer',
            'programme.modifier',
            'export.generer.universite',
            'message.envoyer',
        ],
        User::ROLE_ADMIN => [
            'avancement.voir.universite',
            'seance.voir',
            'activite.voir',
            'programme.modifier',
            'inscription.deposer',
            'utilisateur.suspendre.universite',
            'utilisateur.designer.cp',
            'utilisateur.reinitialiser.motdepasse',
            'export.generer.universite',
            'message.envoyer',
        ],
    ];

    /** Les adjoints heritent des permissions de leur titulaire. */
    private const ROLES_MIROIRS = [
        User::ROLE_CPA => User::ROLE_CP,
        User::ROLE_DFA => User::ROLE_DF,
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $toutes = collect(self::PERMISSIONS_PAR_ROLE)->flatten()->unique();

        foreach ($toutes as $nom) {
            Permission::findOrCreate($nom, 'web');
        }

        foreach (self::PERMISSIONS_PAR_ROLE as $role => $permissions) {
            Role::findOrCreate($role, 'web')->syncPermissions($permissions);
        }

        foreach (self::ROLES_MIROIRS as $adjoint => $titulaire) {
            Role::findOrCreate($adjoint, 'web')
                ->syncPermissions(self::PERMISSIONS_PAR_ROLE[$titulaire]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
