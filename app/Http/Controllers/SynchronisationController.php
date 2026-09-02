<?php

namespace App\Http\Controllers;

use App\Services\RegistreDesSeances;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Le point d'arrivee de la file hors ligne.
 *
 * Chaque seance du lot est traitée pour elle-meme : une ligne refusee
 * n'empeche pas les autres de passer. Le client sait ainsi exactement ce
 * qu'il peut retirer de sa file locale.
 */
class SynchronisationController extends Controller
{
    public function __invoke(Request $request, RegistreDesSeances $registre): JsonResponse
    {
        $donnees = $request->validate([
            'seances' => ['required', 'array', 'max:100'],
            'seances.*.uuid' => ['required', 'uuid'],
            'seances.*.cours_id' => ['required', 'integer', 'exists:cours,id'],
            'seances.*.date_seance' => ['required', 'date'],
            'seances.*.heure_debut' => ['required', 'string'],
            'seances.*.heure_fin' => ['required', 'string'],
            'seances.*.type' => ['required', 'string'],
            'seances.*.matiere_couverte' => ['required', 'string', 'max:2000'],
            'seances.*.local_id' => ['nullable', 'integer', 'exists:locaux,id'],
            'seances.*.effectif_present' => ['nullable', 'integer', 'min:0'],
            'seances.*.observations' => ['nullable', 'string', 'max:2000'],
            'seances.*.saisie_locale_at' => ['nullable', 'date'],
        ]);

        return response()->json(
            $registre->synchroniser($request->user(), $donnees['seances']),
        );
    }
}
