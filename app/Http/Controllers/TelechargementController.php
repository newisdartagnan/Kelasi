<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Services\BibliothequeDeCours;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Le téléchargement passe par l'application, jamais par un lien direct :
 * c'est ici qu'on vérifie que le demandeur a le droit de lire ce support.
 */
class TelechargementController extends Controller
{
    public function __invoke(Document $document, BibliothequeDeCours $bibliotheque): StreamedResponse
    {
        abort_unless($bibliotheque->peutTelecharger(auth()->user(), $document), 403);

        abort_unless(Storage::disk(BibliothequeDeCours::DISQUE)->exists($document->chemin), 404);

        $bibliotheque->comptabiliserTelechargement($document);

        return Storage::disk(BibliothequeDeCours::DISQUE)->download(
            $document->chemin,
            $document->nom_original,
        );
    }
}
