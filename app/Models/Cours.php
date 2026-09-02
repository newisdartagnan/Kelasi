<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Un element constitutif d'une unite d'enseignement -- ce que tout le monde
 * appelle simplement "le cours".
 */
class Cours extends Model
{
    use HasFactory;

    protected $table = 'cours';

    protected $fillable = [
        'unite_enseignement_id', 'code', 'intitule', 'credits',
        'heures_cmi', 'heures_td', 'heures_tp', 'heures_tpe', 'actif',
    ];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function uniteEnseignement(): BelongsTo
    {
        return $this->belongsTo(UniteEnseignement::class);
    }

    public function seances(): HasMany
    {
        return $this->hasMany(Seance::class, 'cours_id');
    }

    public function attributions(): HasMany
    {
        return $this->hasMany(Attribution::class, 'cours_id');
    }

    public function enseignants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'attributions', 'cours_id', 'user_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'cours_id');
    }

    public function demandesModification(): HasMany
    {
        return $this->hasMany(DemandeModification::class, 'cours_id');
    }

    /**
     * Le volume horaire encadre : ce qui se passe effectivement en salle, et
     * donc ce que les seances viennent consommer. Le TPE en est exclu -- c'est
     * du travail personnel, il ne se constate pas en seance.
     */
    public function getHeuresPrevuesAttribute(): int
    {
        return $this->heures_cmi + $this->heures_td + $this->heures_tp;
    }

    /** Volume prevu pour un type de seance donne. */
    public function heuresPrevuesPourType(string $type): int
    {
        return match ($type) {
            Seance::TYPE_CMI => $this->heures_cmi,
            Seance::TYPE_TD => $this->heures_td,
            Seance::TYPE_TP => $this->heures_tp,
            default => 0,
        };
    }
}
