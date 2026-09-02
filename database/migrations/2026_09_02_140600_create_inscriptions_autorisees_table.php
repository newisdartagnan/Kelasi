<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le verrou d'entree.
 *
 * Le cahier des charges d'origine prevoyait une inscription libre. Dans une
 * universite, cela signifie que n'importe qui peut se declarer etudiant d'une
 * promotion -- et l'avancement des cours perd toute valeur probante.
 *
 * Ici, l'inscription est adossee a une liste de matricules deposee par le
 * secretariat academique. On ne cree pas un compte : on active une ligne qui
 * existe deja.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inscriptions_autorisees', function (Blueprint $table) {
            $table->id();
            $table->string('matricule', 40);
            $table->foreignId('annee_academique_id')->constrained('annees_academiques')->cascadeOnDelete();
            $table->foreignId('promotion_id')->nullable()->constrained('promotions')->cascadeOnDelete();
            $table->foreignId('faculte_id')->nullable()->constrained('facultes')->cascadeOnDelete();

            $table->string('nom');
            $table->string('postnom')->nullable();
            $table->string('prenom')->nullable();
            $table->string('role_prevu', 20)->default('etudiant');

            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('activee_at')->nullable();
            $table->foreignId('deposee_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['matricule', 'annee_academique_id']);
            $table->index('activee_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inscriptions_autorisees');
    }
};
