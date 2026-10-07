<?php

return [
    // Nombre comercial (pendiente de decisión final). Se cambia solo en .env.
    'nombre' => env('PLATAFORMA_NOMBRE', "Med's Anatomy"),
    'eslogan' => env('PLATAFORMA_ESLOGAN', 'Encuentra a tu médico y agenda en minutos'),

    // A dónde llegan los leads de upgrade (equipo comercial de Virtuoso).
    'virtuoso' => [
        'email' => env('VIRTUOSO_LEADS_EMAIL', 'hola@virtuoso.mx'),
        'whatsapp' => env('VIRTUOSO_WHATSAPP', ''),
        'adquantum_url' => env('ADQUANTUM_API_URL'), // backend Python (Railway)
    ],

    'ia' => [
        'model' => env('ANTHROPIC_MODEL', 'claude-opus-5-5'),
        'effort' => env('ANTHROPIC_EFFORT', 'low'), // copys cortos: esfuerzo bajo = más barato
        'max_tokens' => (int) env('ANTHROPIC_MAX_TOKENS', 2000),
    ],

    'resenas' => [
        // Horas después de la cita para enviar la invitación a calificar.
        'invitar_despues_horas' => (int) env('RESENAS_INVITAR_HORAS', 3),
    ],

    'recordatorios' => [
        'horas_antes' => (int) env('RECORDATORIO_HORAS_ANTES', 24),
    ],

    'whatsapp' => [
        // WhatsApp Cloud API oficial de Meta (funciona desde hosting compartido).
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'template_recordatorio' => env('WHATSAPP_TEMPLATE_RECORDATORIO', 'recordatorio_cita'),
        'template_resena' => env('WHATSAPP_TEMPLATE_RESENA', 'invitacion_resena'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    ],
];
