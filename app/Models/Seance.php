<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Une seance réellement tenue.
 *
 * Elle n'entre dans l'avancement qu'une fois validée par l'enseignant. Tout
 * le reste de l'application -- tableaux de bord, exports, alertes de retard --
 * se lit a travers ce filtre.
 */
class Seance extends Model
{
    use HasFactory;

    protected $table = 'seances';

    public const TYPE_CMI = 'cmi';
    public const TYPE_TD = 'td';
    public const TYPE_TP = 'tp';
    public const TYPE_EXAMEN = 'examen';
    public const TYPE_INTERROGATION = 'interrogation';

    public const STATUT_BROUILLON = 'brouillon';
    public const STATUT_SOUMISE = 'soumise';
    public const STATUT_VALIDEE = 'validee';
    public const STATUT_CONTESTEE = 'contestee';
    public const STATUT_ANNULEE = 'annulee';

    public const TYPES = [
        self::TYPE_CMI => 'Cours magistral',
        self::TYPE_TD => 'Travaux dirigés',
        self::TYPE_TP => 'Travaux pratiques',
        self::TYPE_EXAMEN => 'Examen',
        self::TYPE_INTERROGATION => 'Interrogation',
    ];

    /** Seuls ces types consomment le volume horaire du programme. */
    public const TYPES_ENSEIGNEMENT = [self::TYPE_CMI, self::TYPE_TD, self::TYPE_TP];

    protected $fillable = [
        'uuid', 'cours_id', 'promotion_id', 'local_id',
        'date_seance', 'heure_debut', 'heure_fin', 'duree_minutes', 'type',
        'matiere_couverte', 'observations', 'effectif_present',
        'statut', 'saisie_par_id', 'soumise_at',
        'validee_par_id', 'validee_at', 'motif_contestation',
        'source', 'saisie_locale_at',
    ];

    protected function casts(): array
    {
        return [
            'date_seance' => 'date',
            'soumise_at' => 'datetime',
            'validee_at' => 'datetime',
            'saisie_locale_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $seance) {
            $seance->uuid ??= (string) Str::uuid();
        });
    }

    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class, 'cours_id');
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }

    public function local(): BelongsTo
    {
        return $this->belongsTo(Local::class);
    }

    public function saisiePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'saisie_par_id');
    }

    public function valideePar(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validee_par_id');
    }

    public function scopeValidees(Builder $query): Builder
    {
        return $query->where('statut', self::STATUT_VALIDEE);
    }

    public function scopeEnAttente(Builder $query): Builder
    {
        return $query->where('statut', self::STATUT_SOUMISE);
    }

    /** Les seances qui consomment le programme, examens exclus. */
    public function scopeDEnseignement(Builder $query): Builder
    {
        return $query->whereIn('type', self::TYPES_ENSEIGNEMENT);
    }

    public function getDureeHeuresAttribute(): float
    {
        return round($this->duree_minutes / 60, 2);
    }

    public function getLibelleTypeAttribute(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }

    public function estModifiable(): bool
    {
        return in_array($this->statut, [self::STATUT_BROUILLON, self::STATUT_CONTESTEE], true);
    }
}
