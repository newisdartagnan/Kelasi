<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le programme des cours, au format LMD tel qu'applique en RDC.
 *
 * Une promotion suit des unites d'enseignement (UE) reparties sur deux
 * semestres de 30 credits chacun. Chaque UE se decompose en elements
 * constitutifs -- les cours proprement dits -- dont le volume horaire est
 * ventile en CMI (cours magistral interactif), TD, TP et TPE (travail
 * personnel de l'etudiant).
 *
 * Regle ministerielle retenue : 1 credit = 25 heures de travail etudiant,
 * dont environ deux tiers d'heures encadrees et un tiers de TPE. Seules les
 * heures encadrees se deroulent en salle -- ce sont elles que les seances
 * viennent consommer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unites_enseignement', function (Blueprint $table) {
            $table->id();
            $table->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->string('code', 30);                 // UE1, UE2...
            $table->string('intitule');
            $table->unsignedTinyInteger('semestre');    // 1 | 2
            $table->unsignedSmallInteger('credits');
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->timestamps();

            $table->unique(['promotion_id', 'code']);
            $table->index(['promotion_id', 'semestre']);
        });

        Schema::create('cours', function (Blueprint $table) {
            $table->id();
            $table->foreignId('unite_enseignement_id')->constrained('unites_enseignement')->cascadeOnDelete();
            $table->string('code', 30);
            $table->string('intitule');
            $table->unsignedSmallInteger('credits')->default(0);
            $table->unsignedSmallInteger('heures_cmi')->default(0);
            $table->unsignedSmallInteger('heures_td')->default(0);
            $table->unsignedSmallInteger('heures_tp')->default(0);
            $table->unsignedSmallInteger('heures_tpe')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['unite_enseignement_id', 'code']);
        });

        // Qui enseigne quoi. Un cours a un titulaire et, souvent, un assistant
        // qui assure les TD et TP -- les deux peuvent valider une seance.
        Schema::create('attributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cours_id')->constrained('cours')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 20)->default('titulaire'); // titulaire|assistant
            $table->timestamps();

            $table->unique(['cours_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attributions');
        Schema::dropIfExists('cours');
        Schema::dropIfExists('unites_enseignement');
    }
};
