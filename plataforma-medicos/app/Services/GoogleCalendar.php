<?php

namespace App\Services;

use App\Models\Appointment;
use App\Models\Doctor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Conexión con Google Calendar mediante una cuenta de servicio de Virtuoso.
 *
 * Cada médico comparte su calendario (Configuración → Compartir con personas específicas)
 * con el correo de la cuenta de servicio, con el permiso "Hacer cambios en los eventos".
 * Así no hace falta que el médico inicie sesión ni que Google apruebe la aplicación.
 *
 * - Lee horarios ocupados (freeBusy) para no ofrecerlos en la agenda.
 * - Crea un evento en su calendario por cada cita (y lo borra si se cancela).
 */
class GoogleCalendar
{
    private const API = 'https://www.googleapis.com/calendar/v3';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    public function enabled(): bool
    {
        return $this->credentials() !== null;
    }

    public function serviceAccountEmail(): ?string
    {
        return $this->credentials()['client_email'] ?? null;
    }

    public function isConnected(Doctor $doctor): bool
    {
        return $this->enabled() && filled($doctor->google_calendar_id);
    }

    /**
     * Intervalos ocupados del médico entre dos fechas.
     *
     * @return Collection<int, array{start: CarbonImmutable, end: CarbonImmutable}>
     */
    public function busy(Doctor $doctor, CarbonImmutable $from, CarbonImmutable $to, bool $fresh = false): Collection
    {
        if (! $this->isConnected($doctor)) {
            return collect();
        }

        $key = "gcal:busy:{$doctor->id}:{$from->timestamp}:{$to->timestamp}";
        if ($fresh) {
            Cache::forget($key);
        }

        try {
            return Cache::remember($key, now()->addMinutes(5), fn () => $this->fetchBusy($doctor, $from, $to));
        } catch (Throwable $e) {
            // Si Google falla, la agenda sigue funcionando con los horarios de la plataforma.
            Log::warning('Google Calendar freeBusy falló', ['doctor' => $doctor->id, 'error' => $e->getMessage()]);

            return collect();
        }
    }

    /** Revisa que el calendario esté compartido correctamente. Devuelve null si todo está bien o el error. */
    public function check(Doctor $doctor): ?string
    {
        if (! $this->enabled()) {
            return 'Falta configurar la cuenta de servicio de Google (GOOGLE_SERVICE_ACCOUNT_JSON).';
        }
        if (blank($doctor->google_calendar_id)) {
            return 'El médico no tiene un calendario asignado.';
        }

        try {
            $now = CarbonImmutable::now();
            $this->fetchBusy($doctor, $now, $now->addDay());
            $writable = $this->request()->get(self::API.'/calendars/'.rawurlencode($doctor->google_calendar_id).'/events', [
                'maxResults' => 1, 'timeMin' => $now->toRfc3339String(),
            ]);
            $error = $writable->successful() ? null : 'El calendario no está compartido con permiso para hacer cambios en los eventos.';
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }

        $doctor->forceFill([
            'google_calendar_checked_at' => now(),
            'google_calendar_error' => $error ? mb_substr($error, 0, 250) : null,
        ])->save();

        return $error;
    }

    public function createEvent(Appointment $appointment): void
    {
        $doctor = $appointment->doctor;
        if (! $this->isConnected($doctor)) {
            return;
        }

        try {
            $start = CarbonImmutable::parse($appointment->starts_at);
            $response = $this->request()->post(self::API.'/calendars/'.rawurlencode($doctor->google_calendar_id).'/events', [
                'summary' => 'Cita: '.$appointment->patient_name,
                'description' => "Paciente: {$appointment->patient_name}\nWhatsApp: {$appointment->patient_phone}"
                    ."\n\nAgendada en ".config('plataforma.nombre'),
                'start' => ['dateTime' => $start->toRfc3339String(), 'timeZone' => config('app.timezone')],
                'end' => ['dateTime' => $start->addMinutes($appointment->duration_minutes)->toRfc3339String(), 'timeZone' => config('app.timezone')],
                'visibility' => 'private',
                'reminders' => ['useDefault' => true],
                'extendedProperties' => ['private' => ['plataforma_cita_id' => (string) $appointment->id]],
            ])->throw();

            $appointment->forceFill(['google_event_id' => $response->json('id'), 'google_sync_error' => null])->save();
        } catch (Throwable $e) {
            Log::warning('No se pudo crear el evento en Google Calendar', ['cita' => $appointment->id, 'error' => $e->getMessage()]);
            $appointment->forceFill(['google_sync_error' => mb_substr($e->getMessage(), 0, 250)])->save();
        }
    }

    public function deleteEvent(Appointment $appointment): void
    {
        $doctor = $appointment->doctor;
        if (! $appointment->google_event_id || ! $this->isConnected($doctor)) {
            return;
        }

        try {
            $response = $this->request()->delete(
                self::API.'/calendars/'.rawurlencode($doctor->google_calendar_id).'/events/'.rawurlencode($appointment->google_event_id)
            );
            if ($response->successful() || in_array($response->status(), [404, 410], true)) {
                $appointment->forceFill(['google_event_id' => null])->save();
            }
        } catch (Throwable $e) {
            Log::warning('No se pudo borrar el evento de Google Calendar', ['cita' => $appointment->id, 'error' => $e->getMessage()]);
        }
    }

    private function fetchBusy(Doctor $doctor, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $id = $doctor->google_calendar_id;
        $response = $this->request()->post(self::API.'/freeBusy', [
            'timeMin' => $from->toRfc3339String(),
            'timeMax' => $to->toRfc3339String(),
            'timeZone' => config('app.timezone'),
            'items' => [['id' => $id]],
        ])->throw();

        // No usar json("calendars.{$id}"): el ID es un correo y su punto rompe la notación de puntos.
        $calendar = $response->json('calendars')[$id] ?? [];
        if (! empty($calendar['errors'])) {
            throw new RuntimeException('Google no da acceso al calendario '.$id.' ('.($calendar['errors'][0]['reason'] ?? 'error').'). ¿Está compartido con '.$this->serviceAccountEmail().'?');
        }

        return collect($calendar['busy'] ?? [])->map(fn ($b) => [
            'start' => CarbonImmutable::parse($b['start'])->setTimezone(config('app.timezone')),
            'end' => CarbonImmutable::parse($b['end'])->setTimezone(config('app.timezone')),
        ]);
    }

    private function request()
    {
        return Http::withToken($this->accessToken())->acceptJson()->timeout(10);
    }

    /** Token OAuth de la cuenta de servicio (JWT firmado con su llave privada), guardado 50 minutos. */
    private function accessToken(): string
    {
        return Cache::remember('gcal:token', now()->addMinutes(50), function () {
            $credentials = $this->credentials() ?? throw new RuntimeException('Cuenta de servicio de Google no configurada.');
            $now = time();
            $segments = [
                $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
                $this->base64Url(json_encode([
                    'iss' => $credentials['client_email'],
                    'scope' => 'https://www.googleapis.com/auth/calendar',
                    'aud' => self::TOKEN_URL,
                    'iat' => $now,
                    'exp' => $now + 3600,
                ])),
            ];

            if (! openssl_sign(implode('.', $segments), $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('No se pudo firmar el token de Google: revisa la llave de la cuenta de servicio.');
            }
            $segments[] = $this->base64Url($signature);

            return Http::asForm()->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => implode('.', $segments),
            ])->throw()->json('access_token');
        });
    }

    /** @return array{client_email: string, private_key: string}|null */
    private function credentials(): ?array
    {
        $path = config('plataforma.google.service_account_json');
        if (! $path) {
            return null;
        }
        $path = str_starts_with($path, '/') ? $path : base_path($path);
        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) file_get_contents($path), true);

        return isset($data['client_email'], $data['private_key']) ? $data : null;
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
