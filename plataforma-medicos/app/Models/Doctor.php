<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Doctor extends Model
{
    protected $fillable = [
        'user_id', 'specialty_id', 'city_id', 'plan_id', 'plan_expires_at',
        'name', 'slug', 'title', 'cedula_profesional', 'cedula_especialidad', 'bio',
        'services', 'insurances', 'consultation_price_mxn',
        'phone', 'whatsapp', 'email', 'address', 'neighborhood', 'postal_code', 'lat', 'lng',
        'photo_path', 'website', 'google_place_id', 'google_location_name', 'gbp_score',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'services' => 'array',
            'insurances' => 'array',
            'plan_expires_at' => 'datetime',
            'is_published' => 'boolean',
            'rating_avg' => 'float',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function schedules(): HasMany
    {
        return $this->hasMany(DoctorSchedule::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function aiGenerations(): HasMany
    {
        return $this->hasMany(AiGeneration::class);
    }

    public function creatives(): HasMany
    {
        return $this->hasMany(Creative::class);
    }

    public function upgradeLeads(): HasMany
    {
        return $this->hasMany(UpgradeLead::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function displayName(): string
    {
        return trim($this->title.' '.$this->name);
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    /** Enlace directo para que el paciente escriba su reseña en Google. */
    public function googleReviewUrl(): ?string
    {
        return $this->google_place_id
            ? 'https://search.google.com/local/writereview?placeid='.urlencode($this->google_place_id)
            : null;
    }

    public function hasActivePlan(): bool
    {
        return $this->plan_id !== null
            && ($this->plan_expires_at === null || $this->plan_expires_at->isFuture());
    }

    public function aiUsageThisMonth(): int
    {
        return $this->aiGenerations()->where('created_at', '>=', now()->startOfMonth())->count();
    }

    public function aiMonthlyLimit(): int
    {
        return $this->plan?->ai_monthly_limit ?? 0;
    }

    public function recalculateRating(): void
    {
        $published = $this->reviews()->where('status', 'published');
        $this->forceFill([
            'rating_count' => $published->count(),
            'rating_avg' => round((float) $published->avg('rating'), 2),
        ])->save();
    }
}
