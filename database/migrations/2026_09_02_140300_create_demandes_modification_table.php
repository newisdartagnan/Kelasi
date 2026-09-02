<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le programme des cours n'appartient pas a l'enseignant : il est arrete par
 * l'autorite académique. Toute demande de modification (volume horaire,
 * intitule, répartition CMI/TD/TP) remonte donc au VDE, qui tranche.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('demandes_modification', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cours_id')->constrained('cours')->cascadeOnDelete();
            $table->foreignId('demandeur_id')->constrained('users')->cascadeOnDelete();

            $table->string('type', 30);        // volume|intitule|répartition|report|autre
            $table->text('description');
            $table->text('justification');
            $table->json('modifications')->nullable();  // valeurs proposees, champ -> valeur

            $table->string('statut', 20)->default('en_attente'); // en_attente|approuvee|rejetee|retiree
            $table->foreignId('decideur_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decidee_at')->nullable();
            $table->text('motif_decision')->nullable();
            $table->timestamps();

            $table->index(['statut', 'created_at']);
            $table->index('cours_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('demandes_modification');
    }
};
