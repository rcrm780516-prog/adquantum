<?php

namespace App\Services;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\RateLimitException;
use RuntimeException;

/**
 * Envoltura mínima sobre el SDK oficial de Anthropic.
 * Devuelve el texto y el consumo de tokens para poder medir costos por médico.
 */
class ClaudeClient
{
    public function __construct(private ?Client $client = null) {}

    /** @return array{text: string, input_tokens: int, output_tokens: int} */
    public function complete(string $system, string $prompt): array
    {
        try {
            // fallbacks "default": si el modelo declina por política, la API reintenta
            // sola con el modelo de respaldo que define Anthropic.
            $message = $this->client()->beta->messages->create(
                betas: ['server-side-fallback-2026-07-01'],
                fallbacks: 'default',
                model: config('plataforma.ia.model'),
                maxTokens: config('plataforma.ia.max_tokens'),
                outputConfig: ['effort' => config('plataforma.ia.effort')],
                system: [
                    ['type' => 'text', 'text' => $system, 'cacheControl' => ['type' => 'ephemeral']],
                ],
                messages: [
                    ['role' => 'user', 'content' => $prompt],
                ],
            );
        } catch (RateLimitException) {
            throw new RuntimeException('La IA está saturada en este momento. Intenta de nuevo en un minuto.');
        } catch (APIStatusException $e) {
            report($e);
            throw new RuntimeException('No pudimos generar el contenido. Intenta de nuevo.');
        } catch (APIConnectionException $e) {
            report($e);
            throw new RuntimeException('No hay conexión con el servicio de IA.');
        }

        if ($message->stopReason === 'refusal') {
            throw new RuntimeException('La IA no puede generar este contenido. Reformula tu solicitud.');
        }

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }

        return [
            'text' => trim($text),
            'input_tokens' => (int) ($message->usage->inputTokens ?? 0),
            'output_tokens' => (int) ($message->usage->outputTokens ?? 0),
        ];
    }

    private function client(): Client
    {
        $apiKey = config('services.anthropic.key');
        if (! $apiKey) {
            throw new RuntimeException('Falta configurar ANTHROPIC_API_KEY en el archivo .env.');
        }

        return $this->client ??= new Client(apiKey: $apiKey);
    }
}
