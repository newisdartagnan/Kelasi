<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tant qu'une personne circule avec un mot de passe provisoire — que son
 * doyen connaît, puisqu'il vient de le lui remettre — elle ne va nulle part
 * ailleurs que sur l'écran qui lui en fait choisir un.
 */
class ExigerUnMotDePasseChoisi
{
    /** Les routes qui restent accessibles : sinon on s'enferme dehors. */
    private const TOLEREES = ['mot-de-passe', 'deconnexion', 'hors-ligne'];

    public function handle(Request $request, Closure $suite): Response
    {
        $utilisateur = $request->user();

        if (! $utilisateur?->doit_changer_motdepasse) {
            return $suite($request);
        }

        if (in_array($request->route()?->getName(), self::TOLEREES, true)) {
            return $suite($request);
        }

        // Livewire poste sur son propre point d'entrée : le bloquer
        // empêcherait justement de valider le nouveau mot de passe.
        if ($request->is('livewire/*')) {
            return $suite($request);
        }

        return redirect()->route('mot-de-passe');
    }
}
