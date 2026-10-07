<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $fillable = [
        'doctor_id', 'appointment_id', 'patient_name', 'rating', 'comment',
        'is_verified', 'status', 'doctor_reply',
    ];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'google_invite_clicked_at' => 'datetime',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}
