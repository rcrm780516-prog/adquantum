<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'slug', 'name', 'price_mxn', 'billing_interval', 'ai_monthly_limit',
        'creatives_monthly_limit', 'features', 'is_public', 'sort',
    ];

    protected function casts(): array
    {
        return ['features' => 'array', 'is_public' => 'boolean'];
    }
}
