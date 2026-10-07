<?php

namespace Tests\Feature;

use App\Models\Doctor;
use App\Models\Specialty;
use App\Models\User;
use App\Notifications\AccessInvitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\Feature\Concerns\CreatesDoctors;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use CreatesDoctors, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalogs();
        $this->admin = User::create(['name' => 'Virtuoso', 'email' => 'equipo@virtuoso.mx', 'password' => 'password', 'role' => 'admin']);
    }

    private function doctorPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Dra.', 'name' => 'Karla Verdugo', 'email' => 'karla@test.mx',
            'specialty_id' => Specialty::where('slug', 'ortopedia-y-traumatologia')->value('id'), 'city_id' => 1,
            'cedula_profesional' => '7654321', 'whatsapp' => '6671112233',
            'services_text' => "Rodilla\nHombro\nColumna", 'slot_minutes' => 30,
            'schedules' => [1 => ['enabled' => '1', 'start_time' => '09:00', 'end_time' => '13:00']],
            'google_calendar_id' => 'karla@gmail.com',
            'send_access' => '1',
        ], $overrides);
    }

    public function test_public_signup_no_longer_exists(): void
    {
        $this->get('/registro')->assertNotFound();
    }

    public function test_doctor_cannot_enter_admin(): void
    {
        $doctor = $this->makeDoctor();

        $this->actingAs($doctor->user)->get('/admin')->assertForbidden();
    }

    public function test_admin_pages_render(): void
    {
        $doctor = $this->makeDoctor();

        foreach (['/admin', '/admin/medicos', '/admin/medicos/nuevo', "/admin/medicos/{$doctor->slug}", '/admin/leads'] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_admin_login_lands_on_admin_dashboard(): void
    {
        $this->post('/entrar', ['email' => 'equipo@virtuoso.mx', 'password' => 'password'])->assertRedirect('/admin');
    }

    public function test_admin_creates_doctor_and_sends_access_email(): void
    {
        Notification::fake();

        $this->actingAs($this->admin)->post('/admin/medicos', $this->doctorPayload())->assertRedirect();

        $doctor = Doctor::firstWhere('slug', 'dra-karla-verdugo');
        $this->assertNotNull($doctor);
        $this->assertFalse($doctor->is_published);
        $this->assertSame(['Rodilla', 'Hombro', 'Columna'], $doctor->services);
        $this->assertSame('karla@gmail.com', $doctor->google_calendar_id);
        $this->assertSame(1, $doctor->schedules()->count());
        $this->assertSame('doctor', $doctor->user->role);

        Notification::assertSentTo($doctor->user, AccessInvitation::class, fn ($n) => $n->welcome === true);
    }

    public function test_doctor_sets_password_from_invitation_and_lands_on_panel(): void
    {
        $doctor = $this->makeDoctor();
        $token = Password::createToken($doctor->user);

        $this->post('/contrasena', [
            'token' => $token, 'email' => $doctor->user->email,
            'password' => 'nueva-segura-1', 'password_confirmation' => 'nueva-segura-1',
        ])->assertRedirect('/panel');

        $this->post('/salir');
        $this->post('/entrar', ['email' => $doctor->user->email, 'password' => 'nueva-segura-1'])->assertRedirect('/panel');
    }

    public function test_admin_updates_doctor_profile(): void
    {
        $doctor = $this->makeDoctor();

        $this->actingAs($this->admin)
            ->put("/admin/medicos/{$doctor->slug}", $this->doctorPayload(['name' => 'Prueba Editada', 'email' => $doctor->user->email, 'slot_minutes' => 20]))
            ->assertSessionHasNoErrors();

        $doctor->refresh();
        $this->assertSame('Prueba Editada', $doctor->name);
        $this->assertSame(20, $doctor->schedules()->first()->slot_minutes);
    }

    public function test_admin_activates_courtesy_plan_and_publishes(): void
    {
        $doctor = $this->makeDoctor(['is_published' => false, 'plan_id' => null, 'plan_expires_at' => null]);

        $this->actingAs($this->admin)
            ->post("/admin/medicos/{$doctor->slug}/plan", ['plan_id' => 1, 'courtesy' => '1'])
            ->assertSessionHas('status');

        $doctor->refresh();
        $this->assertTrue($doctor->is_published);
        $this->assertSame(0, $doctor->subscriptions()->first()->amount_mxn);
    }

    public function test_admin_resolves_change_request(): void
    {
        $doctor = $this->makeDoctor();
        $request = $doctor->changeRequests()->create(['message' => 'Cambiar foto']);

        $this->actingAs($this->admin)->post("/admin/solicitudes/{$request->id}/resolver");

        $this->assertSame('done', $request->fresh()->status);
    }
}
