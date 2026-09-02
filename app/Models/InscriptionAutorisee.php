<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Une ligne de la liste des matricules deposee par le secrétariat académique.
 * Tant qu'elle n'est pas activee, aucun compte ne lui correspond.
 */
class InscriptionAutorisee extends Model
{
    use HasFactory;

    protected $table = 'inscriptions_autorisees';

    protected $fillable = [
        'matricule', 'annee_academique_id', 'promotion_id', 'faculte_id',
        'nom', 'postnom', 'prenom', 'role_prevu',
        'user_id', 'activee_at', 'deposee_par_id',
    ];

    protected function casts(): array
    {
        return ['activee_at' => 'datetime'];
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function faculte(): BelongsTo
    {
        return $this->belongsTo(Faculte::class);
    }

    public function anneeAcademique(): BelongsTo
    {
        return $this->belongsTo(AnneeAcademique::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function estDisponible(): bool
    {
        return $this->activee_at === null && $this->user_id === null;
    }
}
