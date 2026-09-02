<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Messagerie interne, cadree par la hierarchie academique.
 *
 * Une conversation est rattachee a un contexte (une promotion, un cours) ou
 * bien directe entre deux personnes. Le cadrage des interlocuteurs autorises
 * se fait dans les policies, pas dans le schema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->string('sujet')->nullable();
            $table->string('type', 20)->default('directe'); // directe|promotion|cours|faculte
            $table->foreignId('promotion_id')->nullable()->constrained('promotions')->cascadeOnDelete();
            $table->foreignId('cours_id')->nullable()->constrained('cours')->cascadeOnDelete();
            $table->foreignId('createur_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('dernier_message_at')->nullable();
            $table->timestamps();

            $table->index('dernier_message_at');
        });

        Schema::create('conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('lu_jusqu_a')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('auteur_id')->constrained('users')->cascadeOnDelete();
            $table->text('corps');
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversation_participants');
        Schema::dropIfExists('conversations');
    }
};
