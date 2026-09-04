<?php

namespace App\Http\Controllers;

use App\Models\AbonnementPush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * L'enregistrement d'un abonnement au push, un par appareil.
 *
 * L'empreinte de l'endpoint sert de clé : se réabonner depuis le même
 * navigateur met à jour la ligne au lieu d'en créer une seconde, et l'on
 * n'envoie pas deux fois la même notification au même écran.
 */
class AbonnementPushController extends Controller
{
    public function enregistrer(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'endpoint' => ['required', 'string', 'max:2000'],
            'cles.p256dh' => ['required', 'string', 'max:255'],
            'cles.auth' => ['required', 'string', 'max:255'],
            'appareil' => ['nullable', 'string', 'max:255'],
        ]);

        AbonnementPush::updateOrCreate(
            ['empreinte' => hash('sha256', $donnees['endpoint'])],
            [
                'user_id' => $request->user()->id,
                'endpoint' => $donnees['endpoint'],
                'cle_publique' => $donnees['cles']['p256dh'],
                'jeton_auth' => $donnees['cles']['auth'],
                'appareil' => $donnees['appareil'] ?? null,
                'derniere_erreur_at' => null,
            ],
        );

        return response()->json(['statut' => 'abonne']);
    }

    public function retirer(Request $request): JsonResponse
    {
        $donnees = $request->validate([
            'endpoint' => ['required', 'string', 'max:2000'],
        ]);

        AbonnementPush::where('user_id', $request->user()->id)
            ->where('empreinte', hash('sha256', $donnees['endpoint']))
            ->delete();

        return response()->json(['statut' => 'desabonne']);
    }
}
