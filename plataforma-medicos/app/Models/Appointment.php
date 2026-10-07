<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class Appointment extends Model
{
    protected $fillable = [
        'doctor_id', 'starts_at', 'duration_minutes', 'patient_name', 'patient_phone',
        'patient_email', 'status', 'privacy_accepted_at',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'privacy_accepted_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'review_requested_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Appointment $appointment) {
            $appointment->review_token ??= Str::random(48);
        });
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }
}
