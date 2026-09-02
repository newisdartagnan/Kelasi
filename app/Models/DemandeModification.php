<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemandeModification extends Model
{
    use HasFactory;

    protected $table = 'demandes_modification';

    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_APPROUVEE = 'approuvee';
    public const STATUT_REJETEE = 'rejetee';
    public const STATUT_RETIREE = 'retiree';

    public const TYPES = [
        'volume' => 'Volume horaire',
        'intitule' => 'Intitulé du cours',
        'repartition' => 'Répartition CMI / TD / TP',
        'report' => 'Report de séances',
        'autre' => 'Autre',
    ];

    protected $fillable = [
        'cours_id', 'demandeur_id', 'type', 'description', 'justification',
        'modifications', 'statut', 'decideur_id', 'decidee_at', 'motif_decision',
    ];

    protected function casts(): array
    {
        return [
            'modifications' => 'array',
            'decidee_at' => 'datetime',
        ];
    }

    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class, 'cours_id');
    }

    public function demandeur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'demandeur_id');
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
