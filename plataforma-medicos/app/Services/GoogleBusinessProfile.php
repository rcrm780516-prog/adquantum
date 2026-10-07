<?php

namespace App\Services;

use App\Models\Doctor;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Integración con Google Business Profile (fase 2).
 *
 * Requiere: proyecto en Google Cloud con acceso aprobado a la API de Business Profile
 * (se solicita a Google), OAuth del médico (o que el médico agregue a Virtuoso como
 * administrador de su ficha) y guardar el refresh token de forma cifrada.
 *
 * Importante: Google NO permite publicar reseñas por la API. Solo se pueden leer y responder.
 */
class GoogleBusinessProfile
{
    private const REVIEWS_API = 'https://mybusiness.googleapis.com/v4';

    public function __construct(private ?string $accessToken = null) {}

    public function withToken(string $accessToken): self
    {
        return new self($accessToken);
    }

    /** @return array<int, array<string, mixed>> */
    public function reviews(Doctor $doctor): array
    {
        return $this->get("/{$this->location($doctor)}/reviews")['reviews'] ?? [];
    }

    public function replyToReview(Doctor $doctor, string $reviewId, string $comment): void
    {
        $this->request()->put(self::REVIEWS_API."/{$this->location($doctor)}/reviews/{$reviewId}/reply", [
            'comment' => $comment,
        ])->throw();
    }

    /** Auditoría rápida de completitud de la ficha (0-100) con los datos que tenemos. */
    public function profileScore(Doctor $doctor): int
    {
        $checks = [
            filled($doctor->google_place_id),
            filled($doctor->bio) && strlen($doctor->bio) >= 250,
            filled($doctor->phone),
            filled($doctor->address),
            filled($doctor->website),
            filled($doctor->photo_path),
            count($doctor->services ?? []) >= 3,
            $doctor->schedules()->exists(),
            $doctor->rating_count >= 10,
            $doctor->rating_avg >= 4.5,
        ];

        return (int) round(count(array_filter($checks)) / count($checks) * 100);
    }

    private function location(Doctor $doctor): string
    {
        return $doctor->google_location_name
            ?? throw new RuntimeException('El médico no tiene vinculada su ficha de Google.');
    }

    private function get(string $path): array
    {
        return $this->request()->get(self::REVIEWS_API.$path)->throw()->json();
    }

    private function request()
    {
        if (! $this->accessToken) {
            throw new RuntimeException('Falta el token de acceso de Google.');
        }

        return Http::withToken($this->accessToken)->acceptJson();
    }
}
