<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Specialty extends Model
{
    protected $fillable = ['name', 'slug', 'schema_type', 'description', 'ad_keywords'];

    protected function casts(): array
    {
        return ['ad_keywords' => 'array'];
    }

    public function doctors(): HasMany
    {
        return $this->hasMany(Doctor::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
