<?php

use Illuminate\Support\Facades\Schedule;

// En Hostinger (Premium/Business) un solo Cron Job cada minuto ejecuta `php artisan schedule:run`.
Schedule::command('citas:recordatorios')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('citas:sincronizar-google')->everyTenMinutes()->withoutOverlapping();
Schedule::command('resenas:invitar')->hourly()->withoutOverlapping();

// Sin procesos permanentes en hosting compartido: la cola se vacía desde el cron.
Schedule::command('queue:work --stop-when-empty --max-time=50')->everyMinute()->withoutOverlapping();

// Al vencer el plan, el perfil se despublica.
Schedule::call(function () {
    \App\Models\Doctor::where('is_published', true)
        ->whereNotNull('plan_expires_at')
        ->where('plan_expires_at', '<', now())
        ->update(['is_published' => false]);
})->daily()->name('planes:vencidos');
