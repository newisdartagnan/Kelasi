<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemandeReinitialisation extends Model
{
    use HasFactory;

    protected $table = 'demandes_reinitialisation';

    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_APPROUVEE = 'approuvee';
    public const STATUT_REJETEE = 'rejetee';

    protected $fillable = [
        'user_id', 'motif', 'statut', 'decideur_id',
        'decidee_at', 'motif_decision', 'provisoire_actif',
    ];

    protected function casts(): array
    {
        return [
            'decidee_at' => 'datetime',
            'provisoire_actif' => 'boolean',
        ];
    }

    public function utilisateur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function decideur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decideur_id');
    }

    public function scopeEnAttente(Builder $query): Builder
    {
        return $query->where('statut', self::STATUT_EN_ATTENTE);
    }
}
