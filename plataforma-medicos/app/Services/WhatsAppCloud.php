<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WhatsApp Cloud API oficial (Meta). Solo HTTP, así que funciona en hosting compartido.
 * Las plantillas (recordatorio_cita, invitacion_resena) se aprueban en Meta Business Manager.
 */
class WhatsAppCloud
{
    public function enabled(): bool
    {
        return filled(config('plataforma.whatsapp.token')) && filled(config('plataforma.whatsapp.phone_number_id'));
    }

    /** @param list<string> $params Variables {{1}}, {{2}}... de la plantilla */
    public function sendTemplate(string $phone, string $template, array $params): bool
    {
        if (! $this->enabled()) {
            Log::info('WhatsApp desactivado; mensaje no enviado.', compact('phone', 'template'));

            return false;
        }

        $response = Http::withToken(config('plataforma.whatsapp.token'))
            ->post('https://graph.facebook.com/v21.0/'.config('plataforma.whatsapp.phone_number_id').'/messages', [
                'messaging_product' => 'whatsapp',
                'to' => $this->normalize($phone),
                'type' => 'template',
                'template' => [
                    'name' => $template,
                    'language' => ['code' => 'es_MX'],
                    'components' => [[
                        'type' => 'body',
                        'parameters' => array_map(fn ($p) => ['type' => 'text', 'text' => (string) $p], $params),
                    ]],
                ],
            ]);

        if ($response->failed()) {
            Log::warning('Falló envío de WhatsApp', ['status' => $response->status(), 'body' => $response->json()]);
        }

        return $response->successful();
    }

    /** Convierte un teléfono mexicano de 10 dígitos al formato internacional 52XXXXXXXXXX. */
    public function normalize(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        return strlen($digits) === 10 ? '52'.$digits : $digits;
    }
}
