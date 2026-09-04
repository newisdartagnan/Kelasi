<?php

namespace App\Http\Controllers;

use App\Models\AnneeAcademique;
use App\Models\Faculte;
use App\Services\ExportDeLAvancement;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * L'export du classeur d'avancement.
 *
 * Un doyen n'exporte que sa faculté : la portée du fichier suit celle de
 * l'écran, sans quoi l'export deviendrait le moyen de contourner les
 * habilitations.
 */
class ExportController extends Controller
{
    public function __invoke(Request $request, ExportDeLAvancement $export): BinaryFileResponse
    {
        $utilisateur = $request->user();

        abort_unless(
            $utilisateur->can('export.generer.universite') || $utilisateur->can('export.generer.faculte'),
            403,
        );

        $donnees = $request->validate([
            'semestre' => ['nullable', 'integer', 'in:1,2'],
            'faculte' => ['nullable', 'integer', 'exists:facultes,id'],
        ]);

        $annee = AnneeAcademique::courante();
        $semestre = $donnees['semestre'] ?? null;
        $faculte = $this->faculteVisee($utilisateur, $donnees['faculte'] ?? null);

        $chemin = $export->produire($annee, $semestre, $faculte);

        return response()
            ->download($chemin, $export->nomDuFichier($annee, $semestre))
            ->deleteFileAfterSend();
    }

    private function faculteVisee($utilisateur, ?int $demandee): ?Faculte
    {
        if ($utilisateur->can('export.generer.universite')) {
            return $demandee ? Faculte::find($demandee) : null;
        }

        // Le doyen n'exporte que la sienne, quoi qu'il demande.
        return Faculte::find($utilisateur->faculte_id);
    }
}
