<?php

namespace App\Services;

use App\Models\Cours;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * Les documents de cours déposés par les enseignants.
 *
 * Les fichiers sont stockés hors de la racine publique et servis par
 * l'application : un lien deviné ne doit pas suffire à télécharger le
 * support d'un cours qu'on ne suit pas.
 */
class BibliothequeDeCours
{
    public const DISQUE = 'local';

    /** 20 Mo : au-delà, le téléversement échoue sur une connexion de campus. */
    public const TAILLE_MAX_KO = 20480;

    public const TYPES_ACCEPTES = ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'odt', 'odp', 'ods', 'txt', 'jpg', 'jpeg', 'png', 'zip'];

    /** @param  array<string, mixed>  $donnees */
    public function deposer(User $enseignant, Cours $cours, UploadedFile $fichier, array $donnees): Document
    {
        $this->verifierQualitePourDeposer($enseignant, $cours);

        $chemin = $fichier->store("documents/cours-{$cours->id}", self::DISQUE);

        return Document::create([
            'cours_id' => $cours->id,
            'deposant_id' => $enseignant->id,
            'titre' => $donnees['titre'],
            'description' => $donnees['description'] ?? null,
            'chemin' => $chemin,
            'nom_original' => $fichier->getClientOriginalName(),
            'mime' => $fichier->getClientMimeType(),
            'taille' => $fichier->getSize(),
            'publie' => $donnees['publie'] ?? true,
        ]);
    }

    /** Retirer un document efface aussi le fichier : rien ne traîne sur le disque. */
    public function retirer(User $utilisateur, Document $document): void
    {
        if ($document->deposant_id !== $utilisateur->id && ! $utilisateur->aPorteeUniversitaire()) {
            throw ValidationException::withMessages([
                'document' => 'Seul le déposant peut retirer ce document.',
            ]);
        }

        Storage::disk(self::DISQUE)->delete($document->chemin);

        $document->delete();
    }

    public function basculerPublication(User $utilisateur, Document $document): Document
    {
        if ($document->deposant_id !== $utilisateur->id) {
            throw ValidationException::withMessages([
                'document' => 'Seul le déposant décide de la publication.',
            ]);
        }

        $document->update(['publie' => ! $document->publie]);

        return $document;
    }

    /**
     * Qui peut télécharger : les étudiants de la promotion à laquelle le cours
     * est enseigné, les enseignants qui en ont la charge, et la hiérarchie
     * dans son périmètre. Un document non publié reste au seul déposant.
     */
    public function peutTelecharger(User $utilisateur, Document $document): bool
    {
        $cours = $document->cours;

        if ($document->deposant_id === $utilisateur->id || $utilisateur->aPorteeUniversitaire()) {
            return true;
        }

        if (! $document->publie) {
            return false;
        }

        if ($cours->attributions()->where('user_id', $utilisateur->id)->exists()) {
            return true;
        }

        $promotion = $cours->uniteEnseignement->promotion;

        if ($utilisateur->promotion_id === $promotion->id) {
            return true;
        }

        return $utilisateur->estAutoriteFacultaire()
            && $utilisateur->faculte_id === $promotion->departement->faculte_id;
    }

    public function comptabiliserTelechargement(Document $document): void
    {
        $document->increment('telechargements');
    }

    private function verifierQualitePourDeposer(User $enseignant, Cours $cours): void
    {
        $attribue = $cours->attributions()->where('user_id', $enseignant->id)->exists();

        if (! $attribue && ! $enseignant->aPorteeUniversitaire()) {
            throw ValidationException::withMessages([
                'document' => 'Seul un enseignant attribué à ce cours peut y déposer un document.',
            ]);
        }
    }
}
