<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AbonnementPush extends Model
{
    use HasFactory;

    protected $table = 'abonnements_push';

    protected $fillable = [
        'user_id', 'endpoint', 'cle_publique', 'jeton_auth',
        'appareil', 'empreinte', 'derniere_erreur_at',
    ];

    protected function casts(): array
    {
        return ['derniere_erreur_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (self $abonnement) {
            $abonnement->empreinte ??= hash('sha256', $abonnement->endpoint);
        });
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
