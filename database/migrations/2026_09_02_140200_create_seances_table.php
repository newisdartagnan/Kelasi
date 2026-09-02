<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La seance de cours : l'objet central de Kelasi.
 *
 * Le chef de promotion saisit ce qui s'est réellement passe dans la salle ;
 * l'enseignant contresigne. Sans cette contresignature, la seance ne compte
 * pas dans l'avancement -- c'est ce qui donne au chiffre sa valeur probante.
 *
 * Cycle de vie :
 *   brouillon -> soumise -> validée
 *                        -> contestée -> (corrigee) -> soumise
 *                annulée
 *
 * L'uuid est fourni par le client. Il rend la synchronisation hors ligne
 * idempotente : un CP qui saisit sans réseau puis se synchronise deux fois
 * ne cree pas deux seances.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seances', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('cours_id')->constrained('cours')->cascadeOnDelete();
            $table->foreignId('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->foreignId('local_id')->nullable()->constrained('locaux')->nullOnDelete();

            $table->date('date_seance');
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->unsignedSmallInteger('duree_minutes');   // derivee, figee a l'ecriture
            $table->string('type', 20);                      // cmi|td|tp|examen|interrogation

            $table->text('matiere_couverte');                // ce qui a réellement ete traité
            $table->text('observations')->nullable();
            $table->unsignedInteger('effectif_present')->nullable();

            $table->string('statut', 20)->default('brouillon');

            // Chaine de responsabilite : qui saisit, qui contresigne.
            $table->foreignId('saisie_par_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('soumise_at')->nullable();
            $table->foreignId('validee_par_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validee_at')->nullable();
            $table->text('motif_contestation')->nullable();

            $table->string('source', 10)->default('web');    // web|offline
            $table->timestamp('saisie_locale_at')->nullable(); // horodatage cote appareil
            $table->timestamps();

            $table->index(['cours_id', 'statut']);
            $table->index(['promotion_id', 'date_seance']);
            $table->index(['statut', 'soumise_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seances');
    }
};
