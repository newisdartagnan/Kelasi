<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le relevé de présence, étudiant par étudiant.
 *
 * Le champ effectif_present de la séance donne un nombre ; il ne dit pas qui.
 * Or c'est nommément que l'assiduité conditionne l'accès aux examens dans les
 * universités congolaises. Les deux coexistent : le nombre reste utile quand
 * l'appel n'a pas été fait, le relevé le remplace quand il l'a été.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seance_id')->constrained('seances')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // present | absent | excuse | retard
            $table->string('statut', 12)->default('present');
            $table->string('motif')->nullable();

            $table->foreignId('releve_par_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            // Un étudiant n'a qu'un état par séance : refaire l'appel corrige
            // la ligne au lieu d'en ajouter une seconde.
            $table->unique(['seance_id', 'user_id']);
            $table->index(['user_id', 'statut']);
        });

        Schema::table('seances', function (Blueprint $table) {
            // Distingue « personne n'était là » de « l'appel n'a pas été fait ».
            $table->timestamp('appel_fait_at')->nullable()->after('effectif_present');
        });
    }

    public function down(): void
    {
        Schema::table('seances', fn (Blueprint $table) => $table->dropColumn('appel_fait_at'));
        Schema::dropIfExists('presences');
    }
};
