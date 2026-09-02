<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Document extends Model
{
    use HasFactory;

    protected $table = 'documents';

    protected $fillable = [
        'cours_id', 'deposant_id', 'titre', 'description',
        'chemin', 'nom_original', 'mime', 'taille', 'telechargements', 'publie',
    ];

    protected function casts(): array
    {
        return ['publie' => 'boolean'];
    }

    public function cours(): BelongsTo
    {
        return $this->belongsTo(Cours::class, 'cours_id');
    }

    public function deposant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deposant_id');
    }

    public function getTailleLisibleAttribute(): string
    {
        $unites = ['o', 'Ko', 'Mo', 'Go'];
        $taille = (float) $this->taille;
        $i = 0;

        while ($taille >= 1024 && $i < count($unites) - 1) {
            $taille /= 1024;
            $i++;
        }

        return round($taille, 1).' '.$unites[$i];
    }
}
