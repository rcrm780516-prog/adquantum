<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Creative extends Model
{
    protected $fillable = ['doctor_id', 'template', 'format', 'data', 'image_path'];

    protected function casts(): array
    {
        return ['data' => 'array'];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }
}
