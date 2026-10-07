<?php

namespace App\Http\Controllers\Panel;

use App\Models\Appointment;
use Illuminate\Http\Request;

class AppointmentsController extends PanelController
{
    public function index()
    {
        return view('panel.appointments', [
            'appointments' => $this->doctor()->appointments()->orderByDesc('starts_at')->paginate(30),
        ]);
    }

    public function update(Request $request, Appointment $appointment)
    {
        abort_unless($appointment->doctor_id === $this->doctor()->id, 403);

        $data = $request->validate(['status' => ['required', 'in:confirmed,completed,cancelled,no_show']]);
        $appointment->update($data);

        return back()->with('status', 'Cita actualizada.');
    }
}
