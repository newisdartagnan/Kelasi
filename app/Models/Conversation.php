<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $table = 'conversations';

    protected $fillable = [
        'sujet', 'type', 'promotion_id', 'cours_id', 'createur_id', 'dernier_message_at',
    ];

    protected function casts(): array
    {
        return ['dernier_message_at' => 'datetime'];
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function membres(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'conversation_participants')
            ->withPivot('lu_jusqu_a')
            ->withTimestamps();
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function dernierMessage(): HasMany
    {
        return $this->messages()->latest()->limit(1);
    }

    public function createur(): BelongsTo
    {
        return $this->belongsTo(User::class, 'createur_id');
    }

    public function compte(User $utilisateur): bool
    {
        return $this->participants()->where('user_id', $utilisateur->id)->exists();
    }

    public function scopeDe(Builder $query, User $utilisateur): Builder
    {
        return $query->whereHas('participants', fn (Builder $q) => $q->where('user_id', $utilisateur->id));
    }

    /** L'autre bout d'une conversation directe. */
    public function interlocuteur(User $utilisateur): ?User
    {
        return $this->membres->firstWhere('id', '!=', $utilisateur->id);
    }

    public function estDeGroupe(): bool
    {
        return $this->type !== 'directe';
    }

    /**
     * Le nom du fil tel qu'il apparaît dans la liste : l'interlocuteur pour
     * un tête-à-tête, le sujet pour un groupe.
     */
    public function titrePour(User $utilisateur): string
    {
        if ($this->estDeGroupe()) {
            return $this->sujet ?? 'Conversation';
        }

        return $this->interlocuteur($utilisateur)?->nom_complet ?? 'Conversation';
    }

    /** Deux lettres pour la pastille : les initiales, ou le pictogramme du groupe. */
    public function vignettePour(User $utilisateur): string
    {
        return $this->estDeGroupe()
            ? '👥'
            : ($this->interlocuteur($utilisateur)?->initiales ?? '··');
    }
}
