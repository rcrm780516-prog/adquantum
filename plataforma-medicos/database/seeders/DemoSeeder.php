<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Doctor;
use App\Models\Plan;
use App\Models\Specialty;
use App\Models\User;
use Illuminate\Database\Seeder;

/** Datos de prueba para desarrollo local. No corre en producción. */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::updateOrCreate(['email' => 'demo@medico.test'], [
            'name' => 'Demo Médico',
            'password' => 'password',
            'role' => 'doctor',
        ]);

        $doctor = Doctor::updateOrCreate(['slug' => 'dra-ana-demo'], [
            'user_id' => $user->id,
            'specialty_id' => Specialty::where('slug', 'dermatologia')->value('id'),
            'city_id' => City::where('slug', 'culiacan')->value('id'),
            'plan_id' => Plan::where('slug', 'basico')->value('id'),
            'plan_expires_at' => now()->addYear(),
            'title' => 'Dra.',
            'name' => 'Ana Demo',
            'cedula_profesional' => '12345678',
            'bio' => 'Dermatóloga certificada con consulta en Culiacán. Atiendo acné, revisión de lunares y cuidado de la piel para toda la familia.',
            'services' => ['Consulta dermatológica', 'Revisión de lunares', 'Tratamiento de acné'],
            'consultation_price_mxn' => 900,
            'phone' => '6671234567',
            'whatsapp' => '6671234567',
            'address' => 'Av. Ejemplo 123, Consultorio 4',
            'neighborhood' => 'Centro',
            'is_published' => true,
        ]);

        $doctor->schedules()->delete();
        foreach ([1, 2, 3, 4, 5] as $weekday) {
            $doctor->schedules()->create(['weekday' => $weekday, 'start_time' => '09:00', 'end_time' => '14:00', 'slot_minutes' => 30]);
        }
    }
}
