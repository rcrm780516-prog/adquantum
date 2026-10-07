# Plataforma de médicos — notas para agentes

- Laravel 13, PHP 8.3, MySQL en producción (Hostinger compartido), SQLite en local y pruebas.
- No hay Node.js ni procesos permanentes en el servidor: los estilos se precompilan (`npm run build`, se sube `public/css/app.css`)
  y todo lo asíncrono corre desde `schedule:run` (cron cada minuto). No agregar dependencias que requieran workers, Redis o WebSockets.
- Textos de la interfaz en español de México.
- Modelo "hecho por Virtuoso": no hay registro público; el equipo (rol `admin`, rutas `/admin`) captura y edita los perfiles.
  El médico solo ve su perfil, pide cambios y usa las herramientas del panel.
- Google Calendar va por cuenta de servicio (calendario compartido), no por OAuth del médico.
- Reseñas: nunca condicionar la invitación a Google según la calificación (política de Google contra "review gating").
  `tests/Feature/ReviewFlowTest.php` lo verifica.
- No guardar datos clínicos de pacientes (solo contacto). Publicidad: respetar las reglas de `MarketingAi::BASE_RULES` (COFEPRIS).
- Corre `php artisan test` antes de subir cambios.
