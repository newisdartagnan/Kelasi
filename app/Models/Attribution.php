<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attribution extends Model
{
    use HasFactory;

    protected $table = 'attributions';

    public const ROLE_TITULAIRE = 'titulaire';
    public const ROLE_ASSISTANT = 'assistant';

    protected $fillable = ['cours_id', 'user_id', 'role'];

    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class, 'cours_id');
    }

    public function enseignant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
