<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'utilisateur, rattache a l'ossature académique.
 *
 * Le rattachement determine la portee : un DF voit sa faculté, un CP sa
 * promotion, un enseignant ses cours attribues. Les roles eux-memes sont
 * geres par spatie/laravel-permission.
 *
 * Le matricule est l'identifiant institutionnel ; l'email peut manquer, pas
 * le matricule.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('matricule', 40)->nullable()->unique()->after('id');
            $table->string('postnom')->nullable()->after('name');
            $table->string('prenom')->nullable()->after('postnom');
            $table->string('telephone', 30)->nullable()->after('email');
            $table->string('photo')->nullable()->after('telephone');

            $table->foreignId('faculte_id')->nullable()->after('photo')->constrained('facultes')->nullOnDelete();
            $table->foreignId('departement_id')->nullable()->after('faculte_id')->constrained('departements')->nullOnDelete();
            $table->foreignId('promotion_id')->nullable()->after('departement_id')->constrained('promotions')->nullOnDelete();

            $table->boolean('actif')->default(true)->after('promotion_id');
            $table->timestamp('suspendu_at')->nullable()->after('actif');
            $table->foreignId('suspendu_par_id')->nullable()->after('suspendu_at')->constrained('users')->nullOnDelete();
            $table->string('motif_suspension')->nullable()->after('suspendu_par_id');
            $table->timestamp('derniere_connexion_at')->nullable()->after('motif_suspension');

            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('faculte_id');
            $table->dropConstrainedForeignId('departement_id');
            $table->dropConstrainedForeignId('promotion_id');
            $table->dropConstrainedForeignId('suspendu_par_id');
            $table->dropColumn([
                'matricule', 'postnom', 'prenom', 'telephone', 'photo',
                'actif', 'suspendu_at', 'motif_suspension', 'derniere_connexion_at',
            ]);
        });
    }
};
