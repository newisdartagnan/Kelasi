<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Le fichier qui relie le domaine à l'application Android.
 *
 * L'application publiée sur le Play Store n'est qu'une fenêtre plein écran
 * ouverte sur ce site. Chrome, qui la fait tourner, refuse de masquer la barre
 * d'adresse tant que le site n'a pas confirmé qu'il reconnaît ce paquet : sans
 * cette confirmation, l'utilisateur voit une application avec une barre
 * d'adresse en haut, ce qui trahit l'enveloppe et fait mauvais effet.
 *
 * La confirmation est ce fichier, servi ici plutôt que déposé en dur dans
 * public/ : l'empreinte de signature change entre la version de test et celle
 * que Google resigne pour le Store, et on ne reconstruit pas une image pour
 * corriger une empreinte.
 */
class LiensDApplicationController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $empreintes = config('kelasi.android.empreintes');

        // Rien de configuré : on ne sert pas un fichier vide. Une vérification
        // qui échoue sur un fichier absent se diagnostique ; une vérification
        // qui échoue sur une liste vide ressemble à un bug du navigateur.
        if ($empreintes === []) {
            throw new NotFoundHttpException;
        }

        return response()->json([
            [
                'relation' => ['delegate_permission/common.handle_all_urls'],
                'target' => [
                    'namespace' => 'android_app',
                    'package_name' => config('kelasi.android.paquet'),
                    'sha256_cert_fingerprints' => $empreintes,
                ],
            ],
        ]);
    }
}
