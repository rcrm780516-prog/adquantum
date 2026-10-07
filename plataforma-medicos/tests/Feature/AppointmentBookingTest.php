<?php

namespace Tests\Feature;

use App\Models\Appointment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesDoctors;
use Tests\TestCase;

class AppointmentBookingTest extends TestCase
{
    use CreatesDoctors, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalogs();
    }

    private function payload(string $startsAt): array
    {
        return [
            'starts_at' => $startsAt,
            'patient_name' => 'Juan Pérez López',
            'patient_phone' => '6671234567',
            'privacy' => '1',
        ];
    }

    public function test_patient_can_book_an_available_slot(): void
    {
        $doctor = $this->makeDoctor();
        $slot = now()->addDay()->setTime(10, 0)->format('Y-m-d H:i:s');

        $response = $this->post("/medico/{$doctor->slug}/citas", $this->payload($slot));

        $appointment = Appointment::first();
        $response->assertRedirect("/cita/{$appointment->review_token}");
        $this->assertSame('confirmed', $appointment->status);
        $this->assertNotNull($appointment->privacy_accepted_at);
        $this->get("/cita/{$appointment->review_token}")->assertOk()->assertSee('confirmada');
    }

    public function test_same_slot_cannot_be_booked_twice(): void
    {
        $doctor = $this->makeDoctor();
        $slot = now()->addDay()->setTime(10, 0)->format('Y-m-d H:i:s');

        $this->post("/medico/{$doctor->slug}/citas", $this->payload($slot));
        $this->post("/medico/{$doctor->slug}/citas", $this->payload($slot))->assertSessionHasErrors('starts_at');

        $this->assertSame(1, Appointment::count());
    }

    public function test_slot_outside_schedule_is_rejected(): void
    {
        $doctor = $this->makeDoctor();
        $slot = now()->addDay()->setTime(23, 0)->format('Y-m-d H:i:s');

        $this->post("/medico/{$doctor->slug}/citas", $this->payload($slot))->assertSessionHasErrors('starts_at');
    }

    public function test_privacy_notice_must_be_accepted(): void
    {
        $doctor = $this->makeDoctor();
        $payload = $this->payload(now()->addDay()->setTime(10, 0)->format('Y-m-d H:i:s'));
        unset($payload['privacy']);

        $this->post("/medico/{$doctor->slug}/citas", $payload)->assertSessionHasErrors('privacy');
    }
}
