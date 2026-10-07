<?php

namespace Tests\Feature\Concerns;

use App\Models\City;
use App\Models\Doctor;
use App\Models\Plan;
use App\Models\Specialty;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\PlanSeeder;

trait CreatesDoctors
{
    protected function seedCatalogs(): void
    {
        $this->seed([CatalogSeeder::class, PlanSeeder::class]);
    }

    protected function makeDoctor(array $attributes = [], string $plan = 'basico'): Doctor
    {
        static $n = 0;
        $n++;
        $user = User::create(['name' => "Médico $n", 'email' => "medico$n@test.mx", 'password' => 'password', 'role' => 'doctor']);

        $doctor = Doctor::create(array_merge([
            'user_id' => $user->id,
            'specialty_id' => Specialty::where('slug', 'dermatologia')->value('id'),
            'city_id' => City::where('slug', 'culiacan')->value('id'),
            'plan_id' => Plan::where('slug', $plan)->value('id'),
            'plan_expires_at' => now()->addYear(),
            'title' => 'Dra.',
            'name' => "Prueba $n",
            'slug' => "dra-prueba-$n",
            'cedula_profesional' => '1234567',
            'google_place_id' => 'ChIJ_prueba',
            'is_published' => true,
        ], $attributes));

        foreach (range(0, 6) as $weekday) {
            $doctor->schedules()->create(['weekday' => $weekday, 'start_time' => '08:00', 'end_time' => '20:00', 'slot_minutes' => 30]);
        }

        return $doctor;
    }
}
