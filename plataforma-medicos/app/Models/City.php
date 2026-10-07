<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class City extends Model
{
    protected $fillable = ['name', 'slug', 'state'];

    public function doctors(): HasMany
    {
        return $this->hasMany(Doctor::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
