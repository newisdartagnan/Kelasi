<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    public const ROLE_ETUDIANT = 'etudiant';
    public const ROLE_CP = 'cp';
    public const ROLE_CPA = 'cpa';
    public const ROLE_ENSEIGNANT = 'enseignant';
    public const ROLE_DF = 'df';
    public const ROLE_DFA = 'dfa';
    public const ROLE_VDE = 'vde';
    public const ROLE_ADMIN = 'admin';

    public const ROLES = [
        self::ROLE_ETUDIANT => 'Etudiant',
        self::ROLE_CP => 'Chef de promotion',
        self::ROLE_CPA => 'Chef de promotion adjoint',
        self::ROLE_ENSEIGNANT => 'Enseignant',
        self::ROLE_DF => 'Doyen de faculte',
        self::ROLE_DFA => 'Doyen de faculte adjoint',
        self::ROLE_VDE => 'Vice-recteur charge de l\'enseignement',
        self::ROLE_ADMIN => 'Administrateur',
    ];

    protected $fillable = [
        'matricule', 'name', 'postnom', 'prenom', 'email', 'password',
        'telephone', 'photo', 'faculte_id', 'departement_id', 'promotion_id',
        'actif', 'suspendu_at', 'suspendu_par_id', 'motif_suspension',
        'derniere_connexion_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'actif' => 'boolean',
            'suspendu_at' => 'datetime',
            'derniere_connexion_at' => 'datetime',
        ];
    }

    public function faculte(): BelongsTo
    {
        return $this->belongsTo(Faculte::class);
    }

    public function departement(): BelongsTo
    {
        return $this->belongsTo(Departement::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    /** Les cours qu'il enseigne, comme titulaire ou comme assistant. */
    public function coursEnseignes(): BelongsToMany
    {
        return $this->belongsToMany(Cours::class, 'attributions', 'user_id', 'cours_id')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function seancesSaisies(): HasMany
    {
        return $this->hasMany(Seance::class, 'saisie_par_id');
    }

    public function seancesValidees(): HasMany
    {
        return $this->hasMany(Seance::class, 'validee_par_id');
    }

    public function documentsDeposes(): HasMany
    {
        return $this->hasMany(Document::class, 'deposant_id');
    }

    public function scopeActifs(Builder $query): Builder
    {
        return $query->where('actif', true)->whereNull('suspendu_at');
    }

    public function estSuspendu(): bool
    {
        return $this->suspendu_at !== null;
    }

    public function estChefDePromotion(): bool
    {
        return $this->hasAnyRole([self::ROLE_CP, self::ROLE_CPA]);
    }

    public function estAutoriteFacultaire(): bool
    {
        return $this->hasAnyRole([self::ROLE_DF, self::ROLE_DFA]);
    }

    /** Le VDE et l'administrateur voient toute l'universite. */
    public function aPorteeUniversitaire(): bool
    {
        return $this->hasAnyRole([self::ROLE_VDE, self::ROLE_ADMIN]);
    }

    public function getNomCompletAttribute(): string
    {
        return trim(implode(' ', array_filter([$this->name, $this->postnom, $this->prenom])));
    }

    public function getInitialesAttribute(): string
    {
        $parts = array_filter([$this->name, $this->prenom ?: $this->postnom]);

        return strtoupper(implode('', array_map(fn ($p) => mb_substr($p, 0, 1), $parts)));
    }
}
