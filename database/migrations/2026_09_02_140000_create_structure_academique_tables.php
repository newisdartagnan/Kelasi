<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Referentiel academique : l'ossature institutionnelle.
 *
 * Une faculte contient des departements, un departement accueille une promotion
 * par annee academique. Tout le reste (programme, seances, activites) pend a
 * cette ossature.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('annees_academiques', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 20)->unique();   // 2025-2026
            $table->date('date_debut');
            $table->date('date_fin');
            $table->string('statut', 20)->default('preparation'); // preparation|en_cours|cloturee
            $table->boolean('active')->default(false);
            $table->timestamps();

            $table->index('active');
        });

        Schema::create('facultes', function (Blueprint $table) {
            $table->id();
            $table->string('nom');
            $table->string('sigle', 20)->unique();
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('departements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faculte_id')->constrained('facultes')->cascadeOnDelete();
            $table->string('nom');
            $table->string('sigle', 30);
            $table->boolean('actif')->default(true);
            $table->timestamps();

            $table->unique(['faculte_id', 'sigle']);
        });

        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('departement_id')->constrained('departements')->cascadeOnDelete();
            $table->foreignId('annee_academique_id')->constrained('annees_academiques')->cascadeOnDelete();
            $table->string('niveau', 10);              // L1 L2 L3 M1 M2
            $table->string('intitule');                // "L1 Droit prive et judiciaire"
            $table->unsignedInteger('effectif_attendu')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['departement_id', 'annee_academique_id', 'niveau']);
            $table->index(['annee_academique_id', 'active']);
        });

        Schema::create('locaux', function (Blueprint $table) {
            $table->id();
            $table->foreignId('faculte_id')->nullable()->constrained('facultes')->nullOnDelete();
            $table->string('nom');
            $table->string('batiment')->nullable();
            $table->unsignedInteger('capacite')->default(0);
            $table->boolean('actif')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locaux');
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('departements');
        Schema::dropIfExists('facultes');
        Schema::dropIfExists('annees_academiques');
    }
};
