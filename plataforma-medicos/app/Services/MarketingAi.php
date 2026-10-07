<?php

namespace App\Services;

use App\Models\AiGeneration;
use App\Models\Doctor;
use RuntimeException;

/**
 * Herramientas de IA "lite" para el médico. Están limitadas por plan a propósito:
 * resuelven lo básico y dejan claro cuándo conviene un plan con Virtuoso.
 */
class MarketingAi
{
    public const LIMIT_REACHED = 'limit_reached';

    private const BASE_RULES = <<<'TXT'
Eres el asistente de marketing de una plataforma mexicana para médicos. Escribes en español de México,
con tono profesional, cálido y claro.

Reglas obligatorias de publicidad en salud (COFEPRIS / Ley General de Salud en materia de publicidad):
- Nunca prometas resultados, curas, garantías ni tiempos de recuperación.
- No uses superlativos absolutos ("el mejor", "el único", "100% efectivo") ni comparaciones con otros médicos.
- No uses testimonios de pacientes, fotos de antes/después ni casos clínicos.
- No inventes datos, estadísticas, precios, certificaciones ni estudios.
- No des consejo médico, diagnóstico ni tratamiento: solo marketing.
- Incluye al final la línea "Cédula profesional: {cedula}" cuando escribas un anuncio.
- No pidas ni menciones datos de pacientes.
TXT;

    public function __construct(private ClaudeClient $claude) {}

    public function remaining(Doctor $doctor): int
    {
        return max(0, $doctor->aiMonthlyLimit() - $doctor->aiUsageThisMonth());
    }

    /** Copys para anuncio (Meta / Google / ficha de Google). */
    public function adCopy(Doctor $doctor, string $objective, string $channel): AiGeneration
    {
        $prompt = <<<TXT
Escribe 3 variantes de copy para un anuncio en {$channel}.
Objetivo del anuncio: {$objective}

Datos del médico:
{$this->doctorContext($doctor)}

Para cada variante da: título (máx. 40 caracteres), texto principal (máx. 125 caracteres) y llamado a la acción.
Separa las variantes con "---".
TXT;

        return $this->run($doctor, 'copy', $prompt);
    }

    /** Asesor de marketing con respuestas cortas. */
    public function advice(Doctor $doctor, string $question): AiGeneration
    {
        $prompt = <<<TXT
El médico pregunta: "{$question}"

Datos del médico:
{$this->doctorContext($doctor)}

Responde en máximo 150 palabras con 3 acciones concretas que pueda hacer esta semana por su cuenta.
Si la pregunta requiere estrategia de pauta, embudos, branding o un plan de varios meses, dilo con
honestidad y sugiere hablar con el equipo de Virtuoso Growth Marketing para un plan a la medida.
TXT;

        return $this->run($doctor, 'advice', $prompt);
    }

    /** Descripción optimizada para la ficha de Google Business Profile (máx. 750 caracteres). */
    public function gbpDescription(Doctor $doctor): AiGeneration
    {
        $prompt = <<<TXT
Escribe la descripción para la ficha de Google Business Profile de este consultorio.
Máximo 700 caracteres. Incluye de forma natural la especialidad, la ciudad, la colonia y los servicios
principales. Sin URLs, sin teléfonos, sin promociones (Google no lo permite en la descripción).

{$this->doctorContext($doctor)}
TXT;

        return $this->run($doctor, 'gbp_description', $prompt);
    }

    /** Respuesta sugerida a una reseña (buena o mala), sin revelar información del paciente. */
    public function reviewReply(Doctor $doctor, int $rating, ?string $comment): AiGeneration
    {
        $comment = $comment ?: '(sin comentario)';
        $prompt = <<<TXT
Escribe una respuesta pública breve (máx. 60 palabras) del {$doctor->displayName()} a esta reseña.
Calificación: {$rating}/5. Comentario: "{$comment}"

No confirmes que la persona es paciente ni menciones ningún dato de salud (secreto profesional).
Si la reseña es negativa, agradece, muestra apertura e invita a comunicarse directamente al consultorio.
TXT;

        return $this->run($doctor, 'review_reply', $prompt);
    }

    private function run(Doctor $doctor, string $type, string $prompt): AiGeneration
    {
        if ($this->remaining($doctor) <= 0) {
            throw new RuntimeException(self::LIMIT_REACHED);
        }

        $system = str_replace('{cedula}', $doctor->cedula_profesional, self::BASE_RULES);
        $result = $this->claude->complete($system, $prompt);

        return $doctor->aiGenerations()->create([
            'type' => $type,
            'input' => $prompt,
            'output' => $result['text'],
            'input_tokens' => $result['input_tokens'],
            'output_tokens' => $result['output_tokens'],
        ]);
    }

    private function doctorContext(Doctor $doctor): string
    {
        $doctor->loadMissing(['specialty', 'city']);
        $services = implode(', ', $doctor->services ?? []) ?: 'no especificados';

        return implode("\n", [
            "- Nombre: {$doctor->displayName()}",
            "- Especialidad: {$doctor->specialty->name}",
            "- Ciudad: {$doctor->city->name}, {$doctor->city->state}",
            '- Colonia: '.($doctor->neighborhood ?: 'no especificada'),
            "- Servicios: {$services}",
            "- Cédula profesional: {$doctor->cedula_profesional}",
        ]);
    }
}
