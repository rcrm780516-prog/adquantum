<?php

namespace Tests\Feature;

use App\Models\Appointment;
use App\Models\Doctor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesDoctors;
use Tests\TestCase;

class ReviewFlowTest extends TestCase
{
    use CreatesDoctors, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalogs();
    }

    private function pastAppointment(Doctor $doctor): Appointment
    {
        return $doctor->appointments()->create([
            'starts_at' => now()->subHours(5),
            'patient_name' => 'María Fernanda López',
            'patient_phone' => '6671234567',
            'status' => 'confirmed',
            'privacy_accepted_at' => now(),
        ]);
    }

    /**
     * Política de Google: prohibido el "review gating". El botón de Google debe mostrarse
     * igual para una calificación de 1 estrella que para una de 5.
     */
    public function test_google_invitation_is_shown_for_every_rating(): void
    {
        foreach ([1, 3, 5] as $rating) {
            $doctor = $this->makeDoctor();
            $appointment = $this->pastAppointment($doctor);

            $this->post("/calificar/{$appointment->review_token}", ['rating' => $rating, 'comment' => 'Opinión'])
                ->assertRedirect("/calificar/{$appointment->review_token}/gracias");

            $this->get("/calificar/{$appointment->review_token}/gracias")
                ->assertOk()
                ->assertSee('data-testid="google-review-button"', false);
        }
    }

    public function test_review_is_published_and_updates_rating(): void
    {
        $doctor = $this->makeDoctor();
        $appointment = $this->pastAppointment($doctor);

        $this->post("/calificar/{$appointment->review_token}", ['rating' => 4]);

        $doctor->refresh();
        $this->assertSame(1, $doctor->rating_count);
        $this->assertEquals(4.0, $doctor->rating_avg);
        $review = $doctor->reviews()->first();
        $this->assertSame('María F. L.', $review->patient_name);
        $this->assertTrue($review->is_verified);
        $this->assertSame('published', $review->status);
    }

    public function test_only_one_review_per_appointment(): void
    {
        $appointment = $this->pastAppointment($this->makeDoctor());

        $this->post("/calificar/{$appointment->review_token}", ['rating' => 5]);
        $this->post("/calificar/{$appointment->review_token}", ['rating' => 1])->assertStatus(409);
    }

    public function test_cannot_review_before_the_appointment(): void
    {
        $doctor = $this->makeDoctor();
        $future = $doctor->appointments()->create([
            'starts_at' => now()->addDay(), 'patient_name' => 'Ana', 'patient_phone' => '6670000000',
            'status' => 'confirmed', 'privacy_accepted_at' => now(),
        ]);

        $this->get("/calificar/{$future->review_token}")->assertNotFound();
    }

    public function test_google_redirect_records_click(): void
    {
        $appointment = $this->pastAppointment($this->makeDoctor());
        $this->post("/calificar/{$appointment->review_token}", ['rating' => 2]);

        $this->get("/calificar/{$appointment->review_token}/google")
            ->assertRedirect('https://search.google.com/local/writereview?placeid=ChIJ_prueba');

        $this->assertNotNull($appointment->review->fresh()->google_invite_clicked_at);
    }

    public function test_invitation_command_invites_every_attended_patient(): void
    {
        $appointment = $this->pastAppointment($this->makeDoctor());

        $this->artisan('resenas:invitar')->assertSuccessful();

        $this->assertNotNull($appointment->fresh()->review_requested_at);
    }
}
