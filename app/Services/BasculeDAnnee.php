<?php

namespace App\Services;

use App\Models\AnneeAcademique;
use App\Models\Cours;
use App\Models\Promotion;
use App\Models\Seance;
use App\Models\UniteEnseignement;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * La clôture d'une année académique et l'ouverture de la suivante.
 *
 * C'est l'opération la plus lourde de conséquences de l'application : elle
 * décide de ce que devient chaque promotion. Elle se fait donc en deux temps
 * — un aperçu qu'on lit, puis une exécution qu'on confirme — et rien n'est
 * détruit : l'année close reste consultable, ses séances et ses relevés
 * intacts.
 *
 * Ce qui n'est pas fait ici, volontairement : le passage des étudiants au
 * niveau supérieur. Il dépend des délibérations, qui n'ont pas leur place
 * dans cette application. Le secrétariat dépose la nouvelle liste d'inscrits,
 * et chacun rejoint la promotion que le jury lui a assignée.
 */
class BasculeDAnnee
{
    /** L'ordre des niveaux : une L1 devient une L2, une L3 s'arrête là. */
    private const SUITE = ['L1' => 'L2', 'L2' => 'L3', 'L3' => null, 'M1' => 'M2', 'M2' => null];

    /**
     * Les intitulés s'écrivent en toutes lettres — « Première année de licence
     * en droit » — et non « L1 » : reconduire une promotion doit donc aussi
     * faire avancer l'ordinal, sinon la L2 s'appellerait encore « Première ».
     */
    private const ORDINAUX = ['L1' => 'Première', 'L2' => 'Deuxième', 'L3' => 'Troisième', 'M1' => 'Première', 'M2' => 'Deuxième'];

    /**
     * Ce que la bascule ferait, sans rien écrire.
     *
     * @return array{
     *     annee: AnneeAcademique,
     *     promotions: int,
     *     seances_en_attente: int,
     *     promotions_reconduites: Collection<int, array{depuis: string, vers: string}>,
     *     promotions_terminales: Collection<int, string>
     * }
     */
    public function apercu(AnneeAcademique $annee): array
    {
        $promotions = $this->promotionsDe($annee);

        return [
            'annee' => $annee,
            'promotions' => $promotions->count(),
            'seances_en_attente' => Seance::enAttente()
                ->whereIn('promotion_id', $promotions->pluck('id'))
                ->count(),
            'promotions_reconduites' => $promotions
                ->filter(fn (Promotion $p) => self::SUITE[$p->niveau] ?? null)
                ->map(fn (Promotion $p) => [
                    'depuis' => $p->nom_complet,
                    'vers' => self::SUITE[$p->niveau],
                ])
                ->values(),
            'promotions_terminales' => $promotions
                ->filter(fn (Promotion $p) => ! (self::SUITE[$p->niveau] ?? null))
                ->map(fn (Promotion $p) => $p->nom_complet)
                ->values(),
        ];
    }

    /**
     * Clôture l'année et ouvre la suivante.
     *
     * Le programme est recopié tel quel : la maquette d'une L1 vaut pour la
     * L1 de l'année prochaine. Les attributions d'enseignants suivent, parce
     * que c'est presque toujours le même titulaire — le secrétariat corrige
     * les exceptions plutôt que de tout ressaisir.
     *
     * @return array{annee: AnneeAcademique, promotions: int, cours: int}
     */
    public function basculer(User $auteur, AnneeAcademique $annee, string $libelle, string $debut, string $fin): array
    {
        $this->verifierQualite($auteur);
        $this->verifierBornes($libelle, $debut, $fin);

        $enAttente = Seance::enAttente()
            ->whereIn('promotion_id', $this->promotionsDe($annee)->pluck('id'))
            ->count();

        if ($enAttente > 0) {
            throw ValidationException::withMessages([
                'annee' => "{$enAttente} séance(s) attendent encore un contreseing. "
                    .'Clôturer maintenant les figerait sans qu\'elles comptent dans l\'avancement.',
            ]);
        }

        return DB::transaction(function () use ($annee, $libelle, $debut, $fin) {
            $suivante = AnneeAcademique::create([
                'libelle' => $libelle,
                'date_debut' => $debut,
                'date_fin' => $fin,
                'statut' => 'en_cours',
                'active' => true,
            ]);

            // Une seule année active à la fois : tout le reste de
            // l'application lit « l'année courante » au singulier.
            AnneeAcademique::whereKeyNot($suivante->id)->update(['active' => false]);
            $annee->update(['statut' => 'cloturee']);

            $compte = ['promotions' => 0, 'cours' => 0];

            foreach ($this->promotionsDe($annee) as $ancienne) {
                $reconduite = $this->reconduire($ancienne, $suivante);

                if ($reconduite) {
                    $compte['promotions']++;
                    $compte['cours'] += $this->recopierProgramme($ancienne, $reconduite);
                }

                // Les promotions de l'année close ne sont plus actives, mais
                // demeurent : leurs séances et leurs relevés restent lisibles.
                $ancienne->update(['active' => false]);
            }

            return ['annee' => $suivante, ...$compte];
        });
    }

    /** Recrée la promotion dans l'année suivante, si son niveau a une suite. */
    private function reconduire(Promotion $ancienne, AnneeAcademique $suivante): ?Promotion
    {
        $niveau = self::SUITE[$ancienne->niveau] ?? null;

        if (! $niveau) {
            return null;
        }

        return Promotion::firstOrCreate(
            [
                'departement_id' => $ancienne->departement_id,
                'annee_academique_id' => $suivante->id,
                'niveau' => $niveau,
            ],
            [
                'intitule' => $this->renommer($ancienne->intitule, $ancienne->niveau, $niveau),
                'effectif_attendu' => $ancienne->effectif_attendu,
                'active' => true,
            ],
        );
    }

    /**
     * Fait avancer l'intitulé d'un niveau, que la promotion s'écrive « L1 » ou
     * « Première année ». Un intitulé qui ne porte aucune de ces marques est
     * laissé tel quel : mieux vaut un libellé inchangé qu'un libellé faux.
     */
    private function renommer(string $intitule, string $ancien, string $nouveau): string
    {
        if (str_contains($intitule, $ancien)) {
            return str_replace($ancien, $nouveau, $intitule);
        }

        $ordinalAncien = self::ORDINAUX[$ancien] ?? null;
        $ordinalNouveau = self::ORDINAUX[$nouveau] ?? null;

        if ($ordinalAncien && $ordinalNouveau) {
            foreach ([$ordinalAncien, mb_strtolower($ordinalAncien)] as $forme) {
                if (str_contains($intitule, $forme)) {
                    $remplacement = $forme === $ordinalAncien ? $ordinalNouveau : mb_strtolower($ordinalNouveau);

                    return str_replace($forme, $remplacement, $intitule);
                }
            }
        }

        return $intitule;
    }

    /** Recopie unités, cours et attributions. */
    private function recopierProgramme(Promotion $ancienne, Promotion $nouvelle): int
    {
        if ($nouvelle->unitesEnseignement()->exists()) {
            return 0;   // déjà reconduite : on ne double pas le programme
        }

        $cours = 0;

        foreach ($ancienne->unitesEnseignement()->with('cours.attributions')->get() as $ue) {
            $copie = UniteEnseignement::create([
                'promotion_id' => $nouvelle->id,
                'code' => $ue->code,
                'intitule' => $ue->intitule,
                'semestre' => $ue->semestre,
                'credits' => $ue->credits,
                'ordre' => $ue->ordre,
            ]);

            foreach ($ue->cours as $ancienCours) {
                $nouveauCours = Cours::create([
                    'unite_enseignement_id' => $copie->id,
                    'code' => $ancienCours->code,
                    'intitule' => $ancienCours->intitule,
                    'credits' => $ancienCours->credits,
                    'heures_cmi' => $ancienCours->heures_cmi,
                    'heures_td' => $ancienCours->heures_td,
                    'heures_tp' => $ancienCours->heures_tp,
                    'heures_tpe' => $ancienCours->heures_tpe,
                    'actif' => $ancienCours->actif,
                ]);

                foreach ($ancienCours->attributions as $attribution) {
                    $nouveauCours->attributions()->create([
                        'user_id' => $attribution->user_id,
                        'role' => $attribution->role,
                    ]);
                }

                $cours++;
            }
        }

        return $cours;
    }

    /** @return Collection<int, Promotion> */
    private function promotionsDe(AnneeAcademique $annee): Collection
    {
        return Promotion::with('departement')
            ->where('annee_academique_id', $annee->id)
            ->get();
    }

    private function verifierQualite(User $auteur): void
    {
        if (! $auteur->aPorteeUniversitaire()) {
            throw ValidationException::withMessages([
                'annee' => 'Seul le rectorat clôture une année académique.',
            ]);
        }
    }

    private function verifierBornes(string $libelle, string $debut, string $fin): void
    {
        if (AnneeAcademique::where('libelle', $libelle)->exists()) {
            throw ValidationException::withMessages([
                'libelle' => "L'année {$libelle} existe déjà.",
            ]);
        }

        if (strtotime($fin) <= strtotime($debut)) {
            throw ValidationException::withMessages([
                'fin' => 'La clôture doit suivre la rentrée.',
            ]);
        }
    }
}
