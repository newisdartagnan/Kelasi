<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Les abonnements au push web.
 *
 * Une personne s'abonne par appareil : le même chef de promotion peut avoir
 * son téléphone et l'ordinateur du secrétariat. L'endpoint, fourni par le
 * navigateur, identifie l'abonnement de façon unique.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abonnements_push', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('endpoint');
            $table->string('cle_publique');
            $table->string('jeton_auth');
            $table->string('appareil')->nullable();
            $table->timestamp('derniere_erreur_at')->nullable();
            $table->timestamps();

            // L'endpoint fait souvent plus de 255 caractères : on indexe son
            // empreinte plutôt que sa valeur.
            $table->string('empreinte', 64)->unique();
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abonnements_push');
    }
};
