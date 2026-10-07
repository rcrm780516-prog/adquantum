<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Services\SchemaOrg;
use App\Services\SlotService;

class DoctorController extends Controller
{
    public function show(Doctor $doctor, SlotService $slots, SchemaOrg $schema)
    {
        abort_unless($doctor->is_published, 404);

        $doctor->load(['specialty', 'city', 'schedules']);

        $days = $slots->upcoming($doctor, 7);

        return view('public.doctor', [
            'doctor' => $doctor,
            'days' => $days,
            'reviews' => $doctor->reviews()->where('status', 'published')->latest()->limit(20)->get(),
            'jsonLd' => $schema->physician($doctor),
        ]);
    }
}
