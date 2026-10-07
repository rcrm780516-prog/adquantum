<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/** Correo para crear o restablecer la contraseña. En la bienvenida explica qué es la plataforma. */
class AccessInvitation extends ResetPassword
{
    public function __construct(string $token, public bool $welcome = false)
    {
        parent::__construct($token);
    }

    public function toMail($notifiable): MailMessage
    {
        $url = route('password.reset', ['token' => $this->token, 'email' => $notifiable->getEmailForPasswordReset()]);
        $app = config('plataforma.nombre');

        if ($this->welcome) {
            return (new MailMessage)
                ->subject("Tu acceso a {$app}")
                ->greeting("¡Hola, {$notifiable->name}!")
                ->line("El equipo de Virtuoso Growth Marketing ya configuró tu perfil en {$app}.")
                ->line('Desde tu panel puedes ver tus citas y reseñas, crear anuncios y usar las herramientas de IA.')
                ->action('Crear mi contraseña', $url)
                ->line('Este enlace vence en 7 días. Si vence, pide uno nuevo desde "¿Olvidaste tu contraseña?".');
        }

        return (new MailMessage)
            ->subject("Restablece tu contraseña de {$app}")
            ->line('Recibimos una solicitud para restablecer tu contraseña.')
            ->action('Restablecer contraseña', $url)
            ->line('Si no lo pediste, ignora este correo.');
    }
}
