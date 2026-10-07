<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Services\WhatsAppCloud;
use Illuminate\Console\Command;

class SendAppointmentReminders extends Command
{
    protected $signature = 'citas:recordatorios';

    protected $description = 'Envía recordatorios por WhatsApp de las citas próximas';

    public function handle(WhatsAppCloud $whatsapp): int
    {
        $hours = config('plataforma.recordatorios.horas_antes');

        $appointments = Appointment::with('doctor')
            ->where('status', 'confirmed')
            ->whereNull('reminder_sent_at')
            ->whereBetween('starts_at', [now(), now()->addHours($hours)])
            ->limit(100)
            ->get();

        foreach ($appointments as $appointment) {
            // Plantilla: "Hola {{1}}, te recordamos tu cita con {{2}} el {{3}}. Dirección: {{4}}"
            $whatsapp->sendTemplate($appointment->patient_phone, config('plataforma.whatsapp.template_recordatorio'), [
                $appointment->patient_name,
                $appointment->doctor->displayName(),
                $appointment->starts_at->locale('es')->isoFormat('dddd D [de] MMMM, h:mm a'),
                $appointment->doctor->address ?? '',
            ]);
            $appointment->forceFill(['reminder_sent_at' => now()])->save();
        }

        $this->info("Recordatorios procesados: {$appointments->count()}");

        return self::SUCCESS;
    }
}
