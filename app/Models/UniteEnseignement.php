<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UniteEnseignement extends Model
{
    use HasFactory;

    protected $table = 'unites_enseignement';

    protected $fillable = [
        'promotion_id', 'code', 'intitule', 'semestre', 'credits', 'ordre',
    ];

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function cours(): HasMany
    {
        return $this->hasMany(Cours::class, 'unite_enseignement_id');
    }
}
