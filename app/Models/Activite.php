<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activite extends Model
{
    use HasFactory;

    protected $table = 'activites';

    public const PORTEE_PROMOTION = 'promotion';
    public const PORTEE_DEPARTEMENT = 'departement';
    public const PORTEE_FACULTE = 'faculte';
    public const PORTEE_UNIVERSITE = 'universite';

    public const TYPES = [
        'examen' => 'Examen',
        'interrogation' => 'Interrogation',
        'visite' => 'Visite guidee',
        'conference' => 'Conference',
        'deliberation' => 'Deliberation',
        'autre' => 'Autre',
    ];

    protected $fillable = [
        'titre', 'description', 'type', 'portee',
        'promotion_id', 'departement_id', 'faculte_id', 'cours_id', 'local_id',
        'debut', 'fin', 'statut', 'createur_id',
    ];

    protected function casts(): array
    {
        return [
            'debut' => 'datetime',
            'fin' => 'datetime',
        ];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function departement(): BelongsTo
    {
        return $this->belongsTo(Departement::class);
    }

    public function faculte(): BelongsTo
    {
        return $this->belongsTo(Faculte::class);
    }

    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class, 'cours_id');
    }

    public function local(): BelongsTo
    {
        return $this->belongsTo(Local::class);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'createur_id');
    }

    public function scopeAVenir(Builder $query): Builder
    {
        return $query->where('debut', '>=', now())->orderBy('debut');
    }

    /**
     * Les activites qu'un utilisateur doit voir : celles de sa promotion, de
     * son departement, de sa faculte, plus celles ouvertes a toute
     * l'universite. C'est la portee qui decide, jamais le createur.
     */
    public function scopeVisiblesPour(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->where('portee', self::PORTEE_UNIVERSITE);

            if ($user->promotion_id) {
                $q->orWhere(fn (Builder $s) => $s->where('portee', self::PORTEE_PROMOTION)
                    ->where('promotion_id', $user->promotion_id));
            }

            if ($user->departement_id) {
                $q->orWhere(fn (Builder $s) => $s->where('portee', self::PORTEE_DEPARTEMENT)
                    ->where('departement_id', $user->departement_id));
            }

            if ($user->faculte_id) {
                $q->orWhere(fn (Builder $s) => $s->where('portee', self::PORTEE_FACULTE)
                    ->where('faculte_id', $user->faculte_id));
            }
        });
    }
}
