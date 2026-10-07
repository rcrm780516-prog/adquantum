<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Services\SchemaOrg;
use App\Services\SlotService;
use Carbon\CarbonImmutable;

class DoctorController extends Controller
{
    public function show(Doctor $doctor, SlotService $slots, SchemaOrg $schema)
    {
        abort_unless($doctor->is_published, 404);

        $doctor->load(['specialty', 'city', 'schedules']);

        // Horarios libres de los próximos 7 días.
        $days = collect(range(0, 6))
            ->map(fn ($i) => CarbonImmutable::today()->addDays($i))
            ->mapWithKeys(fn ($day) => [$day->toDateString() => $slots->availableSlots($doctor, $day)])
            ->filter(fn ($daySlots) => $daySlots->isNotEmpty());

        return view('public.doctor', [
            'doctor' => $doctor,
            'days' => $days,
            'reviews' => $doctor->reviews()->where('status', 'published')->latest()->limit(20)->get(),
            'jsonLd' => $schema->physician($doctor),
        ]);
    }
}
