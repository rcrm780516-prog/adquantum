<?php

namespace App\Http\Controllers\Panel;

use App\Services\GoogleCalendar;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** El médico ve su perfil; los cambios los aplica el equipo de Virtuoso. */
class ProfileController extends PanelController
{
    public function show(GoogleCalendar $calendar)
    {
        return view('panel.profile', [
            'doctor' => $this->doctor()->load(['schedules', 'specialty', 'city']),
            'calendarConnected' => $calendar->isConnected($this->doctor()),
            'requests' => $this->doctor()->changeRequests()->latest()->limit(5)->get(),
        ]);
    }

    public function requestChange(Request $request)
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:1500']], [
            'message.required' => 'Escribe qué quieres cambiar.',
        ]);

        $doctor = $this->doctor();
        $doctor->changeRequests()->create($data);

        try {
            Mail::raw(
                "{$doctor->displayName()} pidió un cambio en su perfil:\n\n{$data['message']}\n\nEditar: ".route('admin.doctors.edit', $doctor),
                fn ($m) => $m->to(config('plataforma.virtuoso.email'))->subject("Cambio de perfil: {$doctor->displayName()}")
            );
        } catch (Throwable $e) {
            Log::warning('No se pudo avisar la solicitud de cambio', ['doctor' => $doctor->id, 'error' => $e->getMessage()]);
        }

        return back()->with('status', 'Recibimos tu solicitud. El equipo de Virtuoso la aplicará pronto.');
    }
}
