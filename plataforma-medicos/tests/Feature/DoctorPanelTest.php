<?php

namespace Tests\Feature;

use App\Models\AiGeneration;
use App\Models\Doctor;
use App\Models\Specialty;
use App\Models\UpgradeLead;
use App\Services\ClaudeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesDoctors;
use Tests\TestCase;

class DoctorPanelTest extends TestCase
{
    use CreatesDoctors, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalogs();
    }

    private function fakeClaude(string $text = 'Copy de prueba. Cédula profesional: 1234567'): void
    {
        $this->mock(ClaudeClient::class)
            ->shouldReceive('complete')
            ->andReturn(['text' => $text, 'input_tokens' => 100, 'output_tokens' => 50]);
    }

    public function test_registration_creates_unpublished_doctor(): void
    {
        $this->post('/registro', [
            'title' => 'Dr.', 'name' => 'Luis Ramírez', 'email' => 'luis@test.mx',
            'password' => 'secreta123', 'password_confirmation' => 'secreta123',
            'cedula_profesional' => '7654321',
            'specialty_id' => Specialty::where('slug', 'pediatria')->value('id'),
            'city_id' => 1, 'whatsapp' => '6671112233', 'terms' => '1',
        ])->assertRedirect('/panel/perfil');

        $doctor = Doctor::firstWhere('slug', 'dr-luis-ramirez');
        $this->assertNotNull($doctor);
        $this->assertFalse($doctor->is_published);
        $this->assertAuthenticated();
    }

    public function test_panel_pages_render_for_doctor(): void
    {
        $doctor = $this->makeDoctor();

        foreach (['/panel', '/panel/perfil', '/panel/citas', '/panel/resenas', '/panel/estudio', '/panel/asesor', '/panel/planes'] as $url) {
            $this->actingAs($doctor->user)->get($url)->assertOk();
        }
    }

    public function test_panel_requires_login(): void
    {
        $this->get('/panel')->assertRedirect('/entrar');
    }

    public function test_ad_copy_is_generated_and_logged(): void
    {
        $this->fakeClaude();
        $doctor = $this->makeDoctor();

        $this->actingAs($doctor->user)
            ->post('/panel/estudio/copy', ['objective' => 'Dar a conocer mi consulta', 'channel' => 'Google Ads'])
            ->assertSessionHas('copy');

        $this->assertSame(1, AiGeneration::where('doctor_id', $doctor->id)->where('type', 'copy')->count());
    }

    public function test_ai_limit_redirects_to_upgrade(): void
    {
        $this->fakeClaude();
        $doctor = $this->makeDoctor();
        foreach (range(1, $doctor->plan->ai_monthly_limit) as $i) {
            $doctor->aiGenerations()->create(['type' => 'copy', 'input' => 'x', 'output' => 'y']);
        }

        $this->actingAs($doctor->user)
            ->post('/panel/asesor', ['question' => '¿Cómo consigo más pacientes?'])
            ->assertRedirect('/panel/planes?motivo=ia');
    }

    public function test_creative_limit_returns_upgrade_url(): void
    {
        $doctor = $this->makeDoctor();
        $payload = ['template' => 'clasica', 'format' => 'post', 'data' => ['headline' => 'Hola']];

        foreach (range(1, $doctor->plan->creatives_monthly_limit) as $i) {
            $this->actingAs($doctor->user)->postJson('/panel/estudio/creativo', $payload)->assertOk();
        }

        $this->actingAs($doctor->user)->postJson('/panel/estudio/creativo', $payload)
            ->assertStatus(402)
            ->assertJsonPath('error', 'limit');
    }

    public function test_upgrade_interest_creates_lead(): void
    {
        $doctor = $this->makeDoctor();

        $this->actingAs($doctor->user)
            ->post('/panel/planes/interes', ['plan_interest' => 'virtuoso', 'source' => 'studio'])
            ->assertSessionHas('status');

        $this->assertDatabaseHas(UpgradeLead::class, ['doctor_id' => $doctor->id, 'plan_interest' => 'virtuoso', 'source' => 'studio']);
    }

    public function test_doctor_cannot_touch_another_doctors_appointment(): void
    {
        $owner = $this->makeDoctor();
        $intruder = $this->makeDoctor();
        $appointment = $owner->appointments()->create([
            'starts_at' => now()->addDay(), 'patient_name' => 'Ana', 'patient_phone' => '6670000000',
            'status' => 'confirmed', 'privacy_accepted_at' => now(),
        ]);

        $this->actingAs($intruder->user)
            ->patch("/panel/citas/{$appointment->id}", ['status' => 'cancelled'])
            ->assertForbidden();
    }

    public function test_profile_update_saves_services_and_schedule(): void
    {
        $doctor = $this->makeDoctor();

        $this->actingAs($doctor->user)->put('/panel/perfil', [
            'title' => 'Dra.', 'name' => 'Prueba Editada', 'specialty_id' => $doctor->specialty_id,
            'city_id' => $doctor->city_id, 'cedula_profesional' => '1234567',
            'services_text' => "Acné\nLunares\nPeeling",
            'slot_minutes' => 20,
            'schedules' => [1 => ['enabled' => '1', 'start_time' => '09:00', 'end_time' => '13:00']],
        ])->assertSessionHasNoErrors();

        $doctor->refresh();
        $this->assertSame(['Acné', 'Lunares', 'Peeling'], $doctor->services);
        $this->assertSame(1, $doctor->schedules()->count());
        $this->assertSame(20, $doctor->schedules()->first()->slot_minutes);
    }

    public function test_activate_plan_command_publishes_profile(): void
    {
        $doctor = $this->makeDoctor(['is_published' => false, 'plan_id' => null, 'plan_expires_at' => null]);

        $this->artisan('plan:activar', ['email' => $doctor->user->email, 'plan' => 'basico'])->assertSuccessful();

        $doctor->refresh();
        $this->assertTrue($doctor->is_published);
        $this->assertTrue($doctor->plan_expires_at->isAfter(now()->addMonths(11)));
    }
}
