<?php

namespace App\Services;

use App\Models\Doctor;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Calcula los horarios libres de un médico a partir de su horario semanal y sus citas. */
class SlotService
{
    /** @return Collection<int, CarbonImmutable> */
    public function availableSlots(Doctor $doctor, CarbonImmutable $date): Collection
    {
        $date = $date->startOfDay();
        $now = CarbonImmutable::now();

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
                    $slots[] = $cursor;
                    $cursor = $cursor->addMinutes($schedule->slot_minutes);
                }

                return $slots;
            })
            ->filter(fn (CarbonImmutable $slot) => $slot->isAfter($now) && ! in_array($slot->format('H:i'), $taken, true))
            ->sort()
            ->values();
    }

    public function isAvailable(Doctor $doctor, CarbonImmutable $startsAt): bool
    {
        return $this->availableSlots($doctor, $startsAt)
            ->contains(fn (CarbonImmutable $slot) => $slot->equalTo($startsAt));
    }
}
