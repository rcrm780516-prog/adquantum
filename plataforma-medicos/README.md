# Plataforma de médicos (nombre provisional: Med's Anatomy)

Directorio médico con SEO local, agenda de citas, reseñas verificadas y herramientas de marketing con IA.
Es la puerta de entrada (plan de $500 MXN al año) a los servicios de Virtuoso Growth Marketing.

**Stack:** PHP 8.3 + Laravel 13 + MySQL. Funciona en cualquier plan de Hostinger (incluido Premium).
Despliegue paso a paso: [`../docs/plataforma-medicos/DESPLIEGUE_HOSTINGER.md`](../docs/plataforma-medicos/DESPLIEGUE_HOSTINGER.md)

## Módulos

| Módulo | Dónde | Notas |
|---|---|---|
| Directorio SEO | `DirectoryController`, `public/directory` | Páginas `/medicos/{especialidad}/{ciudad}` + `sitemap.xml` |
| Perfil del médico | `DoctorController`, `SchemaOrg` | JSON-LD `Physician` con horario, ubicación y calificación |
| Panel de Virtuoso | `Admin\*`, `/admin` | El equipo da de alta médicos, activa planes, atiende cambios y leads |
| Agenda | `AppointmentController`, `SlotService` | Horarios por día de la semana; evita dobles reservas |
| Google Calendar | `GoogleCalendar` | Cuenta de servicio: el médico comparte su calendario; se leen ocupados y se crean eventos |
| Reseñas | `ReviewController`, `resenas:invitar` | **Sin review gating**: todos ven el mismo botón de Google |
| Recordatorios | `citas:recordatorios`, `WhatsAppCloud` | WhatsApp Cloud API oficial (opcional) |
| Estudio de anuncios | `Panel\StudioController`, `panel/studio` | Editor en el navegador (canvas) + copys con IA |
| Asesor IA | `MarketingAi`, `ClaudeClient` | Reglas COFEPRIS en el prompt; límite mensual por plan |
| Embudo de upgrade | `Panel\PlansController`, `upgrade_leads` | Cada clic de "quiero más" genera un lead y un correo a Virtuoso |
| Ficha de Google | `GoogleBusinessProfile` | Fase 2: leer y responder reseñas por la API |

## Desarrollo local

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed          # crea catálogos, planes y un médico demo
php artisan serve                   # http://localhost:8000
```

Usuarios demo: `admin@virtuoso.test` (equipo) y `demo@medico.test` (médico), contraseña `password`

Estilos: se editan en `resources/css/app.css` y se compilan con `npm install && npm run build`.
El resultado (`public/css/app.css`) se sube al repo para que el servidor no necesite Node.js.

## Comandos útiles

```bash
php artisan test                                  # pruebas
php artisan admin:crear correo@virtuoso.mx "Nombre"  # cuenta del equipo de Virtuoso
php artisan plan:activar correo@medico.com basico # activar plan desde la terminal (también desde /admin)
php artisan citas:sincronizar-google              # (lo corre el cron)
php artisan citas:recordatorios                   # (lo corre el cron)
php artisan resenas:invitar                       # (lo corre el cron)
```
