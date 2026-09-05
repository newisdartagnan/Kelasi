<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La réinitialisation de mot de passe, approuvée par la hiérarchie.
 *
 * L'envoi d'un lien par courriel ne fonctionne pas ici : beaucoup d'étudiants
 * et de chefs de promotion n'ont pas d'adresse électronique, et le réseau ne
 * permet pas de compter dessus. La demande remonte donc à l'autorité, qui
 * l'approuve de visu et remet un mot de passe provisoire.
 *
 * Le mot de passe provisoire n'est jamais stocké en clair : on garde son
 * empreinte, et il est affiché une seule fois à l'autorité qui l'approuve.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demandes_reinitialisation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('motif')->nullable();

            $table->string('statut', 20)->default('en_attente'); // en_attente|approuvee|rejetee
            $table->foreignId('decideur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decidee_at')->nullable();
            $table->text('motif_decision')->nullable();

            // Vrai tant que la personne n'a pas choisi son propre mot de passe.
            $table->boolean('provisoire_actif')->default(false);
            $table->timestamps();

            $table->index(['statut', 'created_at']);
            $table->index('user_id');
        });

        Schema::table('users', function (Blueprint $table) {
            // Force le choix d'un nouveau mot de passe à la prochaine connexion.
            $table->boolean('doit_changer_motdepasse')->default(false)->after('actif');
        });
    }

    public function down(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('doit_changer_motdepasse'));
        Schema::dropIfExists('demandes_reinitialisation');
    }
};
