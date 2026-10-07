<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::updateOrCreate(['slug' => 'basico'], [
            'name' => 'Básico',
            'price_mxn' => 500,
            'billing_interval' => 'year',
            'ai_monthly_limit' => 15,
            'creatives_monthly_limit' => 5,
            'sort' => 1,
            'features' => [
                'Perfil en el directorio con SEO por especialidad y ciudad',
                'Agenda de citas en línea',
                'Reseñas verificadas e invitación a Google',
                'Optimización inicial de tu ficha de Google',
                '5 anuncios al mes con plantillas de tu especialidad',
                '15 consultas de IA al mes (copys, respuestas y asesor)',
            ],
        ]);

        Plan::updateOrCreate(['slug' => 'pro'], [
            'name' => 'Pro',
            'price_mxn' => 699,
            'billing_interval' => 'month',
            'ai_monthly_limit' => 200,
            'creatives_monthly_limit' => 60,
            'sort' => 2,
            'features' => [
                'Todo lo del plan Básico',
                'Recordatorios de cita por WhatsApp',
                'Publicaciones mensuales en tu ficha de Google',
                'Respuestas a reseñas de Google desde el panel',
                'Anuncios e IA prácticamente ilimitados',
                'Reporte mensual de visitas y citas',
            ],
        ]);

        Plan::updateOrCreate(['slug' => 'virtuoso'], [
            'name' => 'Virtuoso Growth',
            'price_mxn' => 0, // cotización a la medida
            'billing_interval' => 'custom',
            'ai_monthly_limit' => 500,
            'creatives_monthly_limit' => 200,
            'sort' => 3,
            'features' => [
                'Todo lo del plan Pro',
                'Campañas en Meta y Google Ads gestionadas por Virtuoso',
                'Contenido profesional para redes sociales',
                'Sitio web propio y estrategia de crecimiento',
                'Asesor de marketing dedicado',
            ],
        ]);
    }
}
