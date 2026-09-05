<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Presence extends Model
{
    use HasFactory;

    protected $table = 'presences';

    public const PRESENT = 'present';
    public const ABSENT = 'absent';
    public const EXCUSE = 'excuse';
    public const RETARD = 'retard';

    public const STATUTS = [
        self::PRESENT => 'Présent',
        self::RETARD => 'En retard',
        self::EXCUSE => 'Excusé',
        self::ABSENT => 'Absent',
    ];

    /** Un retard reste une présence : l'étudiant a suivi la séance. */
    public const COMPTENT_COMME_PRESENT = [self::PRESENT, self::RETARD];

    protected $fillable = ['seance_id', 'user_id', 'statut', 'motif', 'releve_par_id'];

    public function seance(): BelongsTo
    {
        return $this->belongsTo(Seance::class);
    }

    public function etudiant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function relevePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'releve_par_id');
    }

    public function scopePresents(Builder $query): Builder
    {
        return $query->whereIn('statut', self::COMPTENT_COMME_PRESENT);
    }

    public function getLibelleStatutAttribute(): string
    {
        return self::STATUTS[$this->statut] ?? $this->statut;
    }
}
