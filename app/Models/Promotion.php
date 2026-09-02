<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Promotion extends Model
{
    use HasFactory;

    protected $table = 'promotions';

    protected $fillable = [
        'departement_id', 'annee_academique_id', 'niveau',
        'intitule', 'effectif_attendu', 'active',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function departement(): BelongsTo
    {
        return $this->belongsTo(Departement::class);
    }

    public function anneeAcademique(): BelongsTo
    {
        return $this->belongsTo(AnneeAcademique::class);
    }

    public function unitesEnseignement(): HasMany
    {
        return $this->hasMany(UniteEnseignement::class);
    }

    public function cours(): HasManyThrough
    {
        return $this->hasManyThrough(Cours::class, UniteEnseignement::class);
    }

    public function seances(): HasMany
    {
        return $this->hasMany(Seance::class);
    }

    public function etudiants(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function activites(): HasMany
    {
        return $this->hasMany(Activite::class);
    }

    /** Les chefs de promotion, titulaire et adjoint. */
    public function chefs()
    {
        return $this->etudiants()->role(['cp', 'cpa']);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }

    public function getNomCompletAttribute(): string
    {
        return "{$this->niveau} - {$this->intitule}";
    }
}
