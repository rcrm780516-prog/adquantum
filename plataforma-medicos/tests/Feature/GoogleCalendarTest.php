<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Doctor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Concerns\CreatesDoctors;
use Tests\TestCase;

class GoogleCalendarTest extends TestCase
{
    use CreatesDoctors, RefreshDatabase;

    private string $keyPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalogs();

        // Llave de prueba para firmar el JWT de la cuenta de servicio.
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $pem);
        $this->keyPath = tempnam(sys_get_temp_dir(), 'sa').'.json';
        file_put_contents($this->keyPath, json_encode(['client_email' => 'plataforma@virtuoso.iam.gserviceaccount.com', 'private_key' => $pem]));
        config(['plataforma.google.service_account_json' => $this->keyPath]);
    }

    protected function tearDown(): void
    {
        @unlink($this->keyPath);
        parent::tearDown();
    }

    private function fakeGoogle(array $busy = []): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'token-prueba', 'expires_in' => 3600]),
            'www.googleapis.com/calendar/v3/freeBusy' => Http::response(['calendars' => ['dra@gmail.com' => ['busy' => $busy]]]),
            'www.googleapis.com/calendar/v3/calendars/*/events/*' => Http::response(null, 204),
            'www.googleapis.com/calendar/v3/calendars/*/events*' => Http::sequence()
                ->push(['id' => 'evento-123'])->push(['id' => 'evento-456'])->push(['items' => []]),
        ]);
    }

    private function doctor(): Doctor
    {
        return $this->makeDoctor(['google_calendar_id' => 'dra@gmail.com']);
    }

    public function test_slots_busy_in_google_are_not_offered(): void
    {
        $tomorrow = now()->addDay()->setTime(10, 0);
        $this->fakeGoogle([['start' => $tomorrow->toRfc3339String(), 'end' => $tomorrow->copy()->addHour()->toRfc3339String()]]);
        $doctor = $this->doctor();

        $html = $this->get("/medico/{$doctor->slug}")->assertOk()->getContent();

        $date = $tomorrow->format('Y-m-d');
        $this->assertStringNotContainsString("value=\"{$date} 10:00:00\"", $html);
        $this->assertStringNotContainsString("value=\"{$date} 10:30:00\"", $html);
        $this->assertStringContainsString("value=\"{$date} 11:00:00\"", $html);
    }

    public function test_booking_a_busy_google_slot_is_rejected(): void
    {
        $tomorrow = now()->addDay()->setTime(10, 0);
        $this->fakeGoogle([['start' => $tomorrow->toRfc3339String(), 'end' => $tomorrow->copy()->addHour()->toRfc3339String()]]);
        $doctor = $this->doctor();

        $this->post("/medico/{$doctor->slug}/citas", [
            'starts_at' => $tomorrow->format('Y-m-d H:i:s'), 'patient_name' => 'Juan', 'patient_phone' => '6671234567', 'privacy' => '1',
        ])->assertSessionHasErrors('starts_at');
    }

    public function test_booking_creates_event_in_doctors_google_calendar(): void
    {
        $this->fakeGoogle();
        $doctor = $this->doctor();

        $this->post("/medico/{$doctor->slug}/citas", [
            'starts_at' => now()->addDay()->setTime(12, 0)->format('Y-m-d H:i:s'),
            'patient_name' => 'Juan Pérez', 'patient_phone' => '6671234567', 'privacy' => '1',
        ])->assertRedirect();

        $this->assertSame('evento-123', Appointment::first()->google_event_id);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/calendars/dra%40gmail.com/events')
            && $r['summary'] === 'Cita: Juan Pérez'
            && $r['visibility'] === 'private');
    }

    public function test_cancelling_removes_the_google_event(): void
    {
        $this->fakeGoogle();
        $doctor = $this->doctor();
        $appointment = $doctor->appointments()->create([
            'starts_at' => now()->addDay()->setTime(12, 0), 'patient_name' => 'Ana', 'patient_phone' => '6670000000',
            'status' => 'confirmed', 'privacy_accepted_at' => now(),
        ]);
        $appointment->forceFill(['google_event_id' => 'evento-999'])->save();

        $this->actingAs($doctor->user)->patch("/panel/citas/{$appointment->id}", ['status' => 'cancelled']);

        Http::assertSent(fn ($r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/events/evento-999'));
        $this->assertNull($appointment->fresh()->google_event_id);
    }

    public function test_google_failure_does_not_block_booking(): void
    {
        Http::fake(['*' => Http::response(['error' => 'boom'], 500)]);
        $doctor = $this->doctor();

        $this->post("/medico/{$doctor->slug}/citas", [
            'starts_at' => now()->addDay()->setTime(12, 0)->format('Y-m-d H:i:s'),
            'patient_name' => 'Juan', 'patient_phone' => '6671234567', 'privacy' => '1',
        ])->assertRedirect();

        $appointment = Appointment::first();
        $this->assertNull($appointment->google_event_id);
        $this->assertNotNull($appointment->google_sync_error);
    }

    public function test_connection_check_reports_unshared_calendar(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 't']),
            'www.googleapis.com/calendar/v3/freeBusy' => Http::response(['calendars' => ['dra@gmail.com' => ['errors' => [['reason' => 'notFound']]]]]),
        ]);
        $doctor = $this->doctor();
        $admin = \App\Models\User::create(['name' => 'A', 'email' => 'a@v.mx', 'password' => 'x', 'role' => 'admin']);

        $this->actingAs($admin)->post("/admin/medicos/{$doctor->slug}/calendario")->assertSessionHas('status');

        $this->assertStringContainsString('notFound', $doctor->fresh()->google_calendar_error);
    }
}
