<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesDoctors;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use CreatesDoctors, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalogs();
    }

    public function test_home_and_static_pages_render(): void
    {
        $this->makeDoctor();

        $this->get('/')->assertOk()->assertSee('Dra. Prueba');
        $this->get('/para-medicos')->assertOk()->assertSee('$500');
        $this->get('/aviso-de-privacidad')->assertOk();
        $this->get('/robots.txt')->assertOk()->assertSee('Sitemap: '.url('/sitemap.xml'));
    }

    public function test_directory_page_lists_doctors_by_specialty_and_city(): void
    {
        $doctor = $this->makeDoctor();
        $hidden = $this->makeDoctor(['is_published' => false]);

        $this->get('/medicos/dermatologia/culiacan')
            ->assertOk()
            ->assertSee('Dermatología en Culiacán')
            ->assertSee($doctor->displayName())
            ->assertDontSee($hidden->displayName());
    }

    public function test_doctor_profile_has_schema_org_json_ld_and_slots(): void
    {
        $doctor = $this->makeDoctor(['rating_avg' => 4.8, 'rating_count' => 12]);

        $this->get("/medico/{$doctor->slug}")
            ->assertOk()
            ->assertSee('application/ld+json', false)
            ->assertSee('"@type":"Physician"', false)
            ->assertSee('"medicalSpecialty":"Dermatología"', false)
            ->assertSee('Confirmar cita');
    }

    public function test_unpublished_profile_is_not_found(): void
    {
        $doctor = $this->makeDoctor(['is_published' => false]);

        $this->get("/medico/{$doctor->slug}")->assertNotFound();
    }

    public function test_sitemap_includes_doctor_and_directory_urls(): void
    {
        $doctor = $this->makeDoctor();

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml')
            ->assertSee("/medico/{$doctor->slug}", false)
            ->assertSee('/medicos/dermatologia/culiacan', false);
    }
}
