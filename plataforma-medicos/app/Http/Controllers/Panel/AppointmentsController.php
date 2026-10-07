<?php

namespace App\Http\Controllers\Panel;

use App\Models\Appointment;
use App\Services\GoogleCalendar;
use Illuminate\Http\Request;

class AppointmentsController extends PanelController
{
    public function index()
    {
        return view('panel.appointments', [
            'appointments' => $this->doctor()->appointments()->orderByDesc('starts_at')->paginate(30),
        ]);
    }

    public function update(Request $request, Appointment $appointment, GoogleCalendar $calendar)
    {
        abort_unless($appointment->doctor_id === $this->doctor()->id, 403);

        $data = $request->validate(['status' => ['required', 'in:confirmed,completed,cancelled,no_show']]);
        $appointment->update($data);

        if ($data['status'] === 'cancelled') {
            $calendar->deleteEvent($appointment);
        }

        return back()->with('status', 'Cita actualizada.');
    }
}
