<?php

namespace App\Console\Commands;

use App\Models\Doctor;
use App\Models\Plan;
use Illuminate\Console\Command;

/** Activación manual mientras se integra Mercado Pago: php artisan plan:activar correo@medico.com basico */
class ActivatePlan extends Command
{
    protected $description = 'Activa el plan de un médico y publica su perfil';

    protected $signature = 'plan:activar {email} {plan=basico} {--referencia=}';

    public function handle(): int
    {
        $doctor = Doctor::whereHas('user', fn ($q) => $q->where('email', $this->argument('email')))->first();
        $plan = Plan::where('slug', $this->argument('plan'))->first();

        if (! $doctor || ! $plan) {
            $this->error('No encontré al médico o al plan.');

            return self::FAILURE;
        }

        $endsAt = match ($plan->billing_interval) {
            'month' => now()->addMonth(),
            default => now()->addYear(),
        };

        $doctor->subscriptions()->create([
            'plan_id' => $plan->id,
            'provider' => 'manual',
            'provider_reference' => $this->option('referencia'),
            'amount_mxn' => $plan->price_mxn,
            'status' => 'active',
            'starts_at' => now(),
            'ends_at' => $endsAt,
        ]);

        $doctor->forceFill(['plan_id' => $plan->id, 'plan_expires_at' => $endsAt, 'is_published' => true])->save();

        $this->info("Plan {$plan->name} activo para {$doctor->displayName()} hasta {$endsAt->toDateString()}.");

        return self::SUCCESS;
    }
}
