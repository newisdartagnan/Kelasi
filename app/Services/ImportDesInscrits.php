<?php

namespace App\Services;

use App\Models\AnneeAcademique;
use App\Models\Departement;
use App\Models\Faculte;
use App\Models\InscriptionAutorisee;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * L'import de la liste des inscrits, déposée par le secrétariat académique.
 *
 * C'est la porte d'entrée unique de l'application : sans ligne dans cette
 * liste, aucun compte ne peut exister. Le fichier attendu est celui que les
 * secrétariats produisent déjà, exporté en CSV depuis un tableur.
 *
 * Le séparateur n'est pas imposé : Excel en configuration française écrit des
 * points-virgules, LibreOffice des virgules. On détecte plutôt que d'exiger.
 */
class ImportDesInscrits
{
    /** Les colonnes attendues, dans n'importe quel ordre. */
    public const COLONNES = [
        'matricule', 'nom', 'postnom', 'prenom',
        'faculte', 'departement', 'niveau', 'role',
    ];

    private const OBLIGATOIRES = ['matricule', 'nom'];

    /**
     * Lit un fichier et en tire des lignes exploitables, sans rien écrire.
     * Le secrétariat voit ce qui sera importé avant de valider.
     *
     * @return array{lignes: Collection<int, array<string, mixed>>, erreurs: list<string>}
     */
    public function analyser(string $chemin, AnneeAcademique $annee): array
    {
        $contenu = file_get_contents($chemin);

        if ($contenu === false || trim($contenu) === '') {
            return ['lignes' => collect(), 'erreurs' => ['Le fichier est vide.']];
        }

        $lignesBrutes = $this->decouper($contenu);
        $entetes = $this->normaliserEntetes(array_shift($lignesBrutes) ?? []);

        $manquantes = array_diff(self::OBLIGATOIRES, $entetes);

        if ($manquantes !== []) {
            return [
                'lignes' => collect(),
                'erreurs' => ['Colonnes obligatoires absentes : '.implode(', ', $manquantes).'.'],
            ];
        }

        $referentiel = $this->chargerReferentiel($annee);
        $lignes = collect();
        $erreurs = [];
        $vus = [];

        foreach ($lignesBrutes as $numero => $cellules) {
            $ligne = $this->composer($entetes, $cellules);
            $numeroAffiche = $numero + 2;   // l'en-tête occupe la première ligne

            if ($ligne['matricule'] === '') {
                continue;   // ligne vide en fin de fichier : on l'ignore sans bruit
            }

            if (isset($vus[$ligne['matricule']])) {
                $erreurs[] = "Ligne {$numeroAffiche} : le matricule {$ligne['matricule']} apparaît deux fois dans le fichier.";

                continue;
            }

            $vus[$ligne['matricule']] = true;
            $lignes->push($this->resoudre($ligne, $referentiel, $numeroAffiche, $erreurs));
        }

        return ['lignes' => $lignes, 'erreurs' => $erreurs];
    }

    /**
     * Écrit les lignes retenues. Un matricule déjà présent pour cette année
     * est mis à jour tant qu'il n'a pas servi à ouvrir un compte : le
     * secrétariat corrige une orthographe sans casser l'existant.
     *
     * @param  Collection<int, array<string, mixed>>  $lignes
     * @return array{creees: int, mises_a_jour: int, ignorees: int}
     */
    public function importer(Collection $lignes, AnneeAcademique $annee, User $auteur): array
    {
        $bilan = ['creees' => 0, 'mises_a_jour' => 0, 'ignorees' => 0];

        DB::transaction(function () use ($lignes, $annee, $auteur, &$bilan) {
            foreach ($lignes->where('valide', true) as $ligne) {
                $existante = InscriptionAutorisee::where('matricule', $ligne['matricule'])
                    ->where('annee_academique_id', $annee->id)
                    ->first();

                if ($existante && ! $existante->estDisponible()) {
                    $bilan['ignorees']++;   // le compte est déjà ouvert, on n'y touche pas

                    continue;
                }

                InscriptionAutorisee::updateOrCreate(
                    ['matricule' => $ligne['matricule'], 'annee_academique_id' => $annee->id],
                    [
                        'promotion_id' => $ligne['promotion_id'],
                        'faculte_id' => $ligne['faculte_id'],
                        'nom' => $ligne['nom'],
                        'postnom' => $ligne['postnom'] ?: null,
                        'prenom' => $ligne['prenom'] ?: null,
                        'role_prevu' => $ligne['role'],
                        'deposee_par_id' => $auteur->id,
                    ],
                );

                $existante ? $bilan['mises_a_jour']++ : $bilan['creees']++;
            }
        });

        return $bilan;
    }

    /** @return list<list<string>> */
    private function decouper(string $contenu): array
    {
        $contenu = preg_replace('/^\x{FEFF}/u', '', $contenu);   // BOM des exports Excel
        $premiere = strtok($contenu, "\r\n") ?: '';

        // Le séparateur le plus fréquent dans l'en-tête l'emporte.
        $separateur = substr_count($premiere, ';') >= substr_count($premiere, ',') ? ';' : ',';

        $lignes = [];

        foreach (preg_split('/\r\n|\r|\n/', $contenu) as $ligne) {
            if (trim($ligne) === '') {
                continue;
            }

            $lignes[] = array_map(trim(...), str_getcsv($ligne, $separateur, '"', '\\'));
        }

        return $lignes;
    }

    /**
     * Ramène les en-têtes à des clés connues : « Matricule », « MATRICULE »
     * et « matricule » désignent la même colonne, et « Faculté » ne doit pas
     * échouer sur son accent.
     *
     * @param  list<string>  $brutes
     * @return list<string>
     */
    private function normaliserEntetes(array $brutes): array
    {
        return array_map(function (string $entete) {
            $sansAccent = \Illuminate\Support\Str::ascii($entete);
            $cle = strtolower(trim(preg_replace('/[^A-Za-z]/', '', $sansAccent)));

            return match ($cle) {
                'matricule', 'mat' => 'matricule',
                'nom' => 'nom',
                'postnom' => 'postnom',
                'prenom' => 'prenom',
                'faculte' => 'faculte',
                'departement', 'dept' => 'departement',
                'niveau', 'promotion' => 'niveau',
                'role', 'qualite' => 'role',
                default => $cle,
            };
        }, $brutes);
    }

    /**
     * @param  list<string>  $entetes
     * @param  list<string>  $cellules
     * @return array<string, string>
     */
    private function composer(array $entetes, array $cellules): array
    {
        $ligne = array_fill_keys(self::COLONNES, '');

        foreach ($entetes as $index => $entete) {
            if (array_key_exists($entete, $ligne)) {
                $ligne[$entete] = $cellules[$index] ?? '';
            }
        }

        $ligne['matricule'] = strtoupper($ligne['matricule']);
        $ligne['nom'] = mb_strtoupper($ligne['nom']);

        return $ligne;
    }

    /**
     * @return array{facultes: Collection<string, Faculte>, promotions: Collection<string, Promotion>}
     */
    private function chargerReferentiel(AnneeAcademique $annee): array
    {
        return [
            'facultes' => Faculte::get()->keyBy(fn (Faculte $f) => mb_strtoupper($f->sigle)),
            'promotions' => Promotion::with('departement')
                ->where('annee_academique_id', $annee->id)
                ->get()
                ->keyBy(fn (Promotion $p) => mb_strtoupper(
                    $p->departement->faculte_id.'|'.$p->departement->sigle.'|'.$p->niveau,
                )),
        ];
    }

    /**
     * Rattache une ligne au référentiel. Une ligne dont la promotion est
     * introuvable n'est pas rejetée en silence : elle revient marquée, avec
     * son motif, pour que le secrétariat corrige son fichier.
     *
     * @param  array<string, string>  $ligne
     * @param  array{facultes: Collection<string, Faculte>, promotions: Collection<string, Promotion>}  $referentiel
     * @param  list<string>  $erreurs
     * @return array<string, mixed>
     */
    private function resoudre(array $ligne, array $referentiel, int $numero, array &$erreurs): array
    {
        $faculte = $referentiel['facultes'][mb_strtoupper($ligne['faculte'])] ?? null;
        $promotion = null;
        $motif = null;

        if ($ligne['faculte'] !== '' && ! $faculte) {
            $motif = "faculté « {$ligne['faculte']} » inconnue";
        }

        if ($faculte && $ligne['departement'] !== '' && $ligne['niveau'] !== '') {
            $cle = mb_strtoupper($faculte->id.'|'.$ligne['departement'].'|'.$ligne['niveau']);
            $promotion = $referentiel['promotions'][$cle] ?? null;

            if (! $promotion) {
                $motif = "aucune promotion {$ligne['niveau']} en {$ligne['departement']} pour cette faculté";
            }
        }

        $role = $this->roleValide($ligne['role']);

        if ($role === null) {
            $motif = "rôle « {$ligne['role']} » non reconnu";
        }

        if ($motif) {
            $erreurs[] = "Ligne {$numero} ({$ligne['matricule']}) : {$motif}.";
        }

        return [
            ...$ligne,
            'role' => $role ?? User::ROLE_ETUDIANT,
            'faculte_id' => $faculte?->id,
            'promotion_id' => $promotion?->id,
            'promotion_libelle' => $promotion?->nom_complet,
            'valide' => $motif === null,
            'motif' => $motif,
        ];
    }

    /** Le secrétariat n'ouvre que des comptes d'étudiant, de chef ou d'enseignant. */
    private function roleValide(string $brut): ?string
    {
        $normalise = strtolower(trim($brut));

        if ($normalise === '') {
            return User::ROLE_ETUDIANT;
        }

        return match ($normalise) {
            'etudiant', 'étudiant' => User::ROLE_ETUDIANT,
            'cp', 'chef de promotion' => User::ROLE_CP,
            'cpa', 'chef de promotion adjoint' => User::ROLE_CPA,
            'enseignant', 'professeur' => User::ROLE_ENSEIGNANT,
            default => null,
        };
    }
}
