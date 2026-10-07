<?php

namespace App\Services;

use App\Models\Doctor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Calcula los horarios libres de un médico a partir de su horario semanal, sus citas
 * en la plataforma y (si está conectado) lo ocupado en su Google Calendar.
 */
class SlotService
{
    public function __construct(private GoogleCalendar $calendar) {}

    /**
     * Horarios libres agrupados por día.
     *
     * @return Collection<string, Collection<int, CarbonImmutable>>
     */
    public function upcoming(Doctor $doctor, int $days = 7): Collection
    {
        $from = CarbonImmutable::today();
        $busy = $this->calendar->busy($doctor, $from, $from->addDays($days));

        return collect(range(0, $days - 1))
            ->map(fn ($i) => $from->addDays($i))
            ->mapWithKeys(fn ($day) => [$day->toDateString() => $this->availableSlots($doctor, $day, $busy)])
            ->filter(fn ($slots) => $slots->isNotEmpty());
    }

    /**
     * @param  Collection<int, array{start: CarbonImmutable, end: CarbonImmutable}>|null  $busy
     * @return Collection<int, CarbonImmutable>
     */
    public function availableSlots(Doctor $doctor, CarbonImmutable $date, ?Collection $busy = null): Collection
    {
        $date = $date->startOfDay();
        $now = CarbonImmutable::now();
        $busy ??= $this->calendar->busy($doctor, $date, $date->addDay());

        $taken = $doctor->appointments()
            ->whereDate('starts_at', $date)
            ->whereNotIn('status', ['cancelled'])
            ->pluck('starts_at')
            ->map(fn ($dt) => CarbonImmutable::parse($dt)->format('H:i'))
            ->all();

        return $doctor->schedules
            ->where('weekday', $date->dayOfWeek)
            ->flatMap(function ($schedule) use ($date) {
                $slots = [];
                $cursor = $date->setTimeFromTimeString($schedule->start_time);
                $end = $date->setTimeFromTimeString($schedule->end_time);
                while ($cursor->addMinutes($schedule->slot_minutes) <= $end) {
                    $slots[] = ['start' => $cursor, 'minutes' => $schedule->slot_minutes];
                    $cursor = $cursor->addMinutes($schedule->slot_minutes);
                }

                return $slots;
            })
            ->filter(fn ($slot) => $slot['start']->isAfter($now)
                && ! in_array($slot['start']->format('H:i'), $taken, true)
                && ! $this->overlapsBusy($slot['start'], $slot['start']->addMinutes($slot['minutes']), $busy))
            ->map(fn ($slot) => $slot['start'])
            ->sort()
            ->values();
    }

    /** Verificación final al reservar: consulta Google sin caché. */
    public function isAvailable(Doctor $doctor, CarbonImmutable $startsAt): bool
    {
        $day = $startsAt->startOfDay();
        $busy = $this->calendar->busy($doctor, $day, $day->addDay(), fresh: true);

        return $this->availableSlots($doctor, $startsAt, $busy)
            ->contains(fn (CarbonImmutable $slot) => $slot->equalTo($startsAt));
    }

    private function overlapsBusy(CarbonImmutable $start, CarbonImmutable $end, Collection $busy): bool
    {
        return $busy->contains(fn ($b) => $start->lessThan($b['end']) && $end->greaterThan($b['start']));
    }
}
