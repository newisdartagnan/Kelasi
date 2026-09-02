<?php

namespace Database\Seeders;

use App\Models\AnneeAcademique;
use App\Models\Attribution;
use App\Models\Cours;
use App\Models\Faculte;
use App\Models\InscriptionAutorisee;
use App\Models\Local;
use App\Models\Promotion;
use App\Models\Seance;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Un jeu de demonstration : des comptes pour chaque role et un semestre deja
 * entame, pour qu'on puisse ouvrir l'application et voir quelque chose.
 *
 * Les seances sont generees avec un avancement volontairement inegal d'un
 * cours a l'autre. C'est la situation reelle qu'un doyen veut reperer d'un
 * coup d'oeil : le cours qui a pris trois semaines de retard pendant que les
 * autres avancaient.
 */
class DemonstrationSeeder extends Seeder
{
    private const MOT_DE_PASSE = 'kelasi2026';

    public function run(): void
    {
        $annee = AnneeAcademique::courante();

        if (! $annee) {
            return;
        }

        $annee = $this->cadrerAnneePourLaDemonstration($annee);

        $vde = $this->creerUtilisateur('VDE-001', 'MUKENDI', 'Joseph', User::ROLE_VDE);

        foreach (Promotion::with('departement.faculte')->active()->get() as $promotion) {
            $faculte = $promotion->departement->faculte;

            $this->creerAutoritesFacultaires($faculte);
            $chef = $this->creerChefDePromotion($promotion, $annee);
            $this->creerCorpsEnseignant($promotion, $faculte);
            $this->genererSeances($promotion, $chef);
        }

        $this->command?->info("Comptes de demonstration crees. Mot de passe : ".self::MOT_DE_PASSE);
        $this->command?->info("VDE : {$vde->matricule}");
    }

    /**
     * Le calendrier congolais court de la mi-octobre a fin juillet. Executee
     * en aout ou en septembre, la demonstration tomberait dans le creux entre
     * deux annees : rien a montrer, et un taux attendu de 100 %.
     *
     * On fait donc glisser la fenetre pour que le jour de la demonstration se
     * situe aux trois cinquiemes de l'annee -- le moment ou l'ecart entre les
     * cours a jour et ceux qui decrochent est le plus lisible.
     *
     * Ajustement propre a la demonstration : en production, ces dates sont
     * saisies par le secretariat academique.
     */
    private function cadrerAnneePourLaDemonstration(AnneeAcademique $annee): AnneeAcademique
    {
        if (now()->between($annee->date_debut, $annee->date_fin)) {
            return $annee;
        }

        $annee->update([
            'date_debut' => now()->copy()->subMonths(6)->startOfMonth()->toDateString(),
            'date_fin' => now()->copy()->addMonths(4)->endOfMonth()->toDateString(),
        ]);

        return $annee->refresh();
    }

    private function creerAutoritesFacultaires(Faculte $faculte): void
    {
        if (User::where('faculte_id', $faculte->id)->role(User::ROLE_DF)->exists()) {
            return;
        }

        $doyen = $this->creerUtilisateur(
            "DF-{$faculte->sigle}",
            'KABANGU',
            'Marie',
            User::ROLE_DF,
        );

        $doyen->update(['faculte_id' => $faculte->id]);
    }

    private function creerChefDePromotion(Promotion $promotion, AnneeAcademique $annee): User
    {
        $matricule = "CP-{$promotion->id}";

        $chef = $this->creerUtilisateur($matricule, 'ILUNGA', 'Patrick', User::ROLE_CP);

        $chef->update([
            'faculte_id' => $promotion->departement->faculte_id,
            'departement_id' => $promotion->departement_id,
            'promotion_id' => $promotion->id,
        ]);

        InscriptionAutorisee::updateOrCreate(
            ['matricule' => $matricule, 'annee_academique_id' => $annee->id],
            [
                'promotion_id' => $promotion->id,
                'faculte_id' => $promotion->departement->faculte_id,
                'nom' => 'ILUNGA',
                'prenom' => 'Patrick',
                'role_prevu' => User::ROLE_CP,
                'user_id' => $chef->id,
                'activee_at' => now(),
            ],
        );

        return $chef;
    }

    /** Un enseignant par cours, titulaire. */
    private function creerCorpsEnseignant(Promotion $promotion, Faculte $faculte): void
    {
        $cours = Cours::whereHas('uniteEnseignement', fn ($q) => $q->where('promotion_id', $promotion->id))->get();

        foreach ($cours as $index => $unCours) {
            if ($unCours->attributions()->exists()) {
                continue;
            }

            $enseignant = $this->creerUtilisateur(
                "ENS-{$unCours->id}",
                self::NOMS[$index % count(self::NOMS)],
                self::PRENOMS[$index % count(self::PRENOMS)],
                User::ROLE_ENSEIGNANT,
            );

            $enseignant->update(['faculte_id' => $faculte->id]);

            Attribution::updateOrCreate(
                ['cours_id' => $unCours->id, 'user_id' => $enseignant->id],
                ['role' => Attribution::ROLE_TITULAIRE],
            );
        }
    }

    private const NOMS = ['KASONGO', 'NGOY', 'TSHIBANGU', 'MBUYI', 'LUKUSA', 'BOLIMA', 'KALALA', 'MASENGO'];
    private const PRENOMS = ['Jean', 'Alphonsine', 'Emmanuel', 'Chantal', 'Serge', 'Godelieve', 'Blaise', 'Nadine'];

    /**
     * Genere l'historique du premier semestre. Chaque cours avance a son
     * propre rythme -- certains sont a jour, un sur cinq accuse un retard net.
     */
    private function genererSeances(Promotion $promotion, User $chef): void
    {
        if ($promotion->seances()->exists()) {
            return;
        }

        $locaux = Local::where('faculte_id', $promotion->departement->faculte_id)->pluck('id');
        $cours = Cours::with('attributions')
            ->whereHas('uniteEnseignement', fn ($q) => $q->where('promotion_id', $promotion->id)->where('semestre', 1))
            ->get();

        $debutSemestre = $promotion->anneeAcademique->date_debut->copy();

        foreach ($cours as $index => $unCours) {
            // Un cours sur cinq decroche ; les autres tournent entre 55 et 85 %.
            $cible = $index % 5 === 0 ? 0.30 : (0.55 + ($index % 4) * 0.10);
            $minutesACouvrir = (int) ($unCours->heures_prevues * 60 * $cible);
            $enseignant = $unCours->attributions->first()?->enseignant;

            $jour = $debutSemestre->copy()->addDays($index % 5);
            $minutesFaites = 0;

            while ($minutesFaites < $minutesACouvrir && $jour->lt(now())) {
                $duree = 120;
                $type = $this->typeDeSeance($unCours, $minutesFaites);

                // Les deux dernieres seances restent en attente de contreseing,
                // pour que la file de validation de l'enseignant ne soit pas vide.
                $reste = $minutesACouvrir - $minutesFaites;
                $enAttente = $reste <= $duree * 2;

                Seance::create([
                    'uuid' => (string) Str::uuid(),
                    'cours_id' => $unCours->id,
                    'promotion_id' => $promotion->id,
                    'local_id' => $locaux->random(),
                    'date_seance' => $jour->toDateString(),
                    'heure_debut' => '08:00',
                    'heure_fin' => '10:00',
                    'duree_minutes' => $duree,
                    'type' => $type,
                    'matiere_couverte' => $this->matiereCouverte($unCours, $minutesFaites),
                    'effectif_present' => random_int(40, 180),
                    'statut' => $enAttente ? Seance::STATUT_SOUMISE : Seance::STATUT_VALIDEE,
                    'saisie_par_id' => $chef->id,
                    'soumise_at' => $jour->copy()->setTime(10, 30),
                    'validee_par_id' => $enAttente ? null : $enseignant?->id,
                    'validee_at' => $enAttente ? null : $jour->copy()->setTime(18, 0),
                    'source' => 'web',
                ]);

                $minutesFaites += $duree;
                $jour->addWeek();
            }
        }
    }

    /** Le programme commence par le cours magistral et bascule ensuite. */
    private function typeDeSeance(Cours $cours, int $minutesFaites): string
    {
        if ($minutesFaites < $cours->heures_cmi * 60) {
            return Seance::TYPE_CMI;
        }

        if ($cours->heures_td > 0) {
            return Seance::TYPE_TD;
        }

        return $cours->heures_tp > 0 ? Seance::TYPE_TP : Seance::TYPE_CMI;
    }

    private function matiereCouverte(Cours $cours, int $minutesFaites): string
    {
        $chapitre = intdiv($minutesFaites, 120) + 1;

        return "Chapitre {$chapitre} : suite du programme de {$cours->intitule}.";
    }

    private function creerUtilisateur(string $matricule, string $nom, string $prenom, string $role): User
    {
        $user = User::firstOrCreate(
            ['matricule' => $matricule],
            [
                'name' => $nom,
                'prenom' => $prenom,
                'email' => Str::lower($matricule).'@unikin.ac.cd',
                'password' => self::MOT_DE_PASSE,
                'actif' => true,
            ],
        );

        if (! $user->hasRole($role)) {
            $user->assignRole($role);
        }

        return $user;
    }
}
