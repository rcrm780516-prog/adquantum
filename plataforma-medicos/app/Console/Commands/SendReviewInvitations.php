<?php

namespace App\Console\Commands;

use App\Models\Appointment;
use App\Services\WhatsAppCloud;
use Illuminate\Console\Command;

/** Invita a TODOS los pacientes atendidos a calificar (sin filtrar por satisfacción). */
class SendReviewInvitations extends Command
{
    protected $signature = 'resenas:invitar';

    protected $description = 'Envía la invitación a calificar después de la cita';

    public function handle(WhatsAppCloud $whatsapp): int
    {
        $hours = config('plataforma.resenas.invitar_despues_horas');

        $appointments = Appointment::with('doctor')
            ->whereIn('status', ['confirmed', 'completed'])
            ->whereNull('review_requested_at')
            ->whereBetween('starts_at', [now()->subDays(7), now()->subHours($hours)])
            ->doesntHave('review')
            ->limit(100)
            ->get();

        foreach ($appointments as $appointment) {
            // Plantilla: "Hola {{1}}, ¿cómo fue tu consulta con {{2}}? Califícala aquí: {{3}}"
            $whatsapp->sendTemplate($appointment->patient_phone, config('plataforma.whatsapp.template_resena'), [
                $appointment->patient_name,
                $appointment->doctor->displayName(),
                route('reviews.create', $appointment->review_token),
            ]);
            $appointment->forceFill(['review_requested_at' => now()])->save();
        }

        $this->info("Invitaciones procesadas: {$appointments->count()}");

        return self::SUCCESS;
    }
}
