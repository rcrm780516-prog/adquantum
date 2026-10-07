<?php

namespace App\Http\Controllers\Panel;

use App\Models\City;
use App\Models\Specialty;
use Illuminate\Http\Request;

class ProfileController extends PanelController
{
    public function edit()
    {
        return view('panel.profile', [
            'doctor' => $this->doctor()->load('schedules'),
            'specialties' => Specialty::orderBy('name')->get(),
            'cities' => City::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request)
    {
        $doctor = $this->doctor();

        $data = $request->validate([
            'title' => ['required', 'in:Dr.,Dra.'],
            'name' => ['required', 'string', 'max:120'],
            'specialty_id' => ['required', 'exists:specialties,id'],
            'city_id' => ['required', 'exists:cities,id'],
            'cedula_profesional' => ['required', 'string', 'max:20'],
            'cedula_especialidad' => ['nullable', 'string', 'max:20'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'services_text' => ['nullable', 'string', 'max:1000'],
            'insurances_text' => ['nullable', 'string', 'max:500'],
            'consultation_price_mxn' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'phone' => ['nullable', 'string', 'max:20'],
            'whatsapp' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:200'],
            'neighborhood' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'website' => ['nullable', 'url', 'max:200'],
            'google_place_id' => ['nullable', 'string', 'max:200'],
            'photo' => ['nullable', 'image', 'max:3072'],
            'schedules' => ['array'],
            'schedules.*.enabled' => ['nullable'],
            'schedules.*.start_time' => ['nullable', 'date_format:H:i'],
            'schedules.*.end_time' => ['nullable', 'date_format:H:i'],
            'slot_minutes' => ['required', 'integer', 'in:15,20,30,45,60'],
        ]);

        $toList = fn (?string $text) => array_values(array_filter(array_map('trim', preg_split('/[\n,]+/', (string) $text))));

        $doctor->fill(collect($data)->except(['services_text', 'insurances_text', 'photo', 'schedules', 'slot_minutes'])->all());
        $doctor->services = $toList($data['services_text'] ?? '');
        $doctor->insurances = $toList($data['insurances_text'] ?? '');

        if ($request->hasFile('photo')) {
            $doctor->photo_path = $request->file('photo')->store('doctors', 'public');
        }

        $doctor->save();

        $doctor->schedules()->delete();
        foreach ($data['schedules'] ?? [] as $weekday => $row) {
            if (! empty($row['enabled']) && ! empty($row['start_time']) && ! empty($row['end_time']) && $row['start_time'] < $row['end_time']) {
                $doctor->schedules()->create([
                    'weekday' => (int) $weekday,
                    'start_time' => $row['start_time'],
                    'end_time' => $row['end_time'],
                    'slot_minutes' => $data['slot_minutes'],
                ]);
            }
        }

        return back()->with('status', 'Perfil actualizado.');
    }
}
