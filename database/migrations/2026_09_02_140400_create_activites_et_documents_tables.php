<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ce qui gravite autour des seances : les activites (examens, interrogations,
 * visites guidees, conferences) et les documents partages par les enseignants.
 *
 * Une activite a une portee : elle vise une promotion, un departement, une
 * faculte ou toute l'universite. C'est la portee, et non le createur, qui
 * determine qui la voit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activites', function (Blueprint $table) {
            $table->id();
            $table->string('titre');
            $table->text('description')->nullable();
            $table->string('type', 30);          // examen|interrogation|visite|conference|deliberation|autre

            $table->string('portee', 20);        // promotion|departement|faculte|universite
            $table->foreignId('promotion_id')->nullable()->constrained('promotions')->cascadeOnDelete();
            $table->foreignId('departement_id')->nullable()->constrained('departements')->cascadeOnDelete();
            $table->foreignId('faculte_id')->nullable()->constrained('facultes')->cascadeOnDelete();
            $table->foreignId('cours_id')->nullable()->constrained('cours')->nullOnDelete();
            $table->foreignId('local_id')->nullable()->constrained('locaux')->nullOnDelete();

            $table->dateTime('debut');
            $table->dateTime('fin')->nullable();

            $table->string('statut', 20)->default('planifiee'); // planifiee|en_cours|cloturee|annulee
            $table->foreignId('createur_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['portee', 'debut']);
            $table->index(['promotion_id', 'debut']);
            $table->index(['statut', 'debut']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cours_id')->constrained('cours')->cascadeOnDelete();
            $table->foreignId('deposant_id')->constrained('users')->cascadeOnDelete();

            $table->string('titre');
            $table->text('description')->nullable();
            $table->string('chemin');
            $table->string('nom_original');
            $table->string('mime', 120);
            $table->unsignedBigInteger('taille');
            $table->unsignedInteger('telechargements')->default(0);
            $table->boolean('publie')->default(true);
            $table->timestamps();

            $table->index(['cours_id', 'publie']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
        Schema::dropIfExists('activites');
    }
};
