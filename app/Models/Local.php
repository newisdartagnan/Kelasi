<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Local extends Model
{
    use HasFactory;

    protected $table = 'locaux';

    protected $fillable = ['faculte_id', 'nom', 'batiment', 'capacite', 'actif'];

    protected function casts(): array
    {
        return ['actif' => 'boolean'];
    }

    public function faculte(): BelongsTo
    {
        return $this->belongsTo(Faculte::class);
    }
}
