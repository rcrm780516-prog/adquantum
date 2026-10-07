<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/** Crea (o convierte en) un usuario del equipo de Virtuoso: php artisan admin:crear correo@virtuoso.mx "Nombre" */
class CreateAdmin extends Command
{
    protected $signature = 'admin:crear {email} {name}';

    protected $description = 'Crea una cuenta del equipo de Virtuoso con acceso al panel de administración';

    public function handle(): int
    {
        $password = $this->secret('Contraseña (mínimo 8 caracteres)');
        if (strlen((string) $password) < 8) {
            $this->error('La contraseña debe tener al menos 8 caracteres.');

            return self::FAILURE;
        }

        $user = User::updateOrCreate(
            ['email' => $this->argument('email')],
            ['name' => $this->argument('name'), 'password' => $password, 'role' => 'admin'],
        );

        $this->info("Listo. {$user->email} puede entrar en ".route('login'));

        return self::SUCCESS;
    }
}
