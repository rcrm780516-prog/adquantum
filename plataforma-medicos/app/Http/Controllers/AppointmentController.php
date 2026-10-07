<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Doctor;
use App\Services\SlotService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppointmentController extends Controller
{
    public function store(Request $request, Doctor $doctor, SlotService $slots)
    {
        abort_unless($doctor->is_published, 404);

        $data = $request->validate([
            'starts_at' => ['required', 'date'],
            'patient_name' => ['required', 'string', 'max:120'],
            'patient_phone' => ['required', 'regex:/^[\d\s\-\+\(\)]{10,20}$/'],
            'patient_email' => ['nullable', 'email', 'max:160'],
            'privacy' => ['accepted'],
        ], [
            'privacy.accepted' => 'Debes aceptar el aviso de privacidad.',
            'patient_phone.regex' => 'Escribe un teléfono válido de 10 dígitos.',
        ]);

        $startsAt = CarbonImmutable::parse($data['starts_at']);

        $appointment = DB::transaction(function () use ($doctor, $slots, $startsAt, $data) {
            // Bloqueo para que dos pacientes no tomen el mismo horario al mismo tiempo.
            Doctor::whereKey($doctor->id)->lockForUpdate()->first();

            if (! $slots->isAvailable($doctor->fresh('schedules'), $startsAt)) {
                return null;
            }

            return $doctor->appointments()->create([
                'starts_at' => $startsAt,
                'duration_minutes' => $doctor->schedules->firstWhere('weekday', $startsAt->dayOfWeek)?->slot_minutes ?? 30,
                'patient_name' => $data['patient_name'],
                'patient_phone' => $data['patient_phone'],
                'patient_email' => $data['patient_email'] ?? null,
                'status' => 'confirmed',
                'privacy_accepted_at' => now(),
            ]);
        });

        if (! $appointment) {
            return back()->withInput()->withErrors(['starts_at' => 'Ese horario ya no está disponible. Elige otro.']);
        }

        return redirect()->route('appointments.confirmed', $appointment->review_token);
    }

    public function confirmed(string $token)
    {
        $appointment = Appointment::with('doctor')->where('review_token', $token)->firstOrFail();

        return view('public.appointment-confirmed', compact('appointment'));
    }
}
