<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Services\GoogleCalendar;
use Illuminate\Console\Command;

/** Reintenta enviar a Google Calendar las citas que no se pudieron sincronizar al momento de agendar. */
class SyncGoogleCalendar extends Command
{
    protected $signature = 'citas:sincronizar-google';

    protected $description = 'Reintenta crear en Google Calendar las citas pendientes de sincronizar';

    public function handle(GoogleCalendar $calendar): int
    {
        if (! $calendar->enabled()) {
            $this->info('Google Calendar no está configurado.');

            return self::SUCCESS;
        }

        $pending = Appointment::with('doctor')
            ->whereNull('google_event_id')
            ->where('status', 'confirmed')
            ->where('starts_at', '>', now())
            ->whereHas('doctor', fn ($q) => $q->whereNotNull('google_calendar_id'))
            ->limit(50)
            ->get();

        $pending->each(fn ($appointment) => $calendar->createEvent($appointment));
        $this->info("Citas procesadas: {$pending->count()}");

        return self::SUCCESS;
    }
}
