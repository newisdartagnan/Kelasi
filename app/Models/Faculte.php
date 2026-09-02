<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class Faculte extends Model
{
    use HasFactory;

    protected $table = 'facultes';

    protected $fillable = ['nom', 'sigle', 'slug', 'ordre', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function departements(): HasMany
    {
        return $this->hasMany(Departement::class);
    }

    public function promotions(): HasManyThrough
    {
        return $this->hasManyThrough(Promotion::class, Departement::class);
    }

    public function locaux(): HasMany
    {
        return $this->hasMany(Local::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
