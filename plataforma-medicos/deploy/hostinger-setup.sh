#!/usr/bin/env bash
# Instalación inicial en Hostinger (Premium / Business / Cloud) por SSH.
# Uso: bash deploy/hostinger-setup.sh [ruta_public_html]
#   Ejemplo: bash deploy/hostinger-setup.sh ~/domains/medicos.virtuoso.mx/public_html
set -euo pipefail

APP_DIR="$(cd "$(dirname "$0")/.." && pwd)"
PUBLIC_HTML="${1:-}"
PHP="${PHP_BIN:-php}"
cd "$APP_DIR"

echo "→ Aplicación: $APP_DIR"

# 1. Composer (Hostinger lo trae instalado; si no, se descarga localmente)
if command -v composer >/dev/null 2>&1; then
    COMPOSER="composer"
else
    echo "→ Descargando composer.phar"
    curl -sS https://getcomposer.org/installer | "$PHP" -- --quiet
    COMPOSER="$PHP composer.phar"
fi
$COMPOSER install --no-dev --optimize-autoloader --no-interaction

# 2. .env
if [ ! -f .env ]; then
    cp .env.hostinger.example .env
    "$PHP" artisan key:generate --force
    echo
    echo "⚠️  Se creó .env. Edítalo con los datos de MySQL, correo y ANTHROPIC_API_KEY:"
    echo "    nano $APP_DIR/.env"
    echo "   Después vuelve a correr este script."
    exit 0
fi

# 3. Base de datos y catálogos (especialidades, ciudades, planes)
"$PHP" artisan migrate --force
"$PHP" artisan db:seed --class=CatalogSeeder --force
"$PHP" artisan db:seed --class=PlanSeeder --force

# 4. Fotos públicas
"$PHP" artisan storage:link || echo "   (storage:link falló; ver DESPLIEGUE_HOSTINGER.md, plan B)"

# 5. Conectar public_html con la carpeta public/ de Laravel
if [ -n "$PUBLIC_HTML" ]; then
    if [ -L "$PUBLIC_HTML" ]; then
        echo "→ public_html ya es un enlace simbólico: $(readlink "$PUBLIC_HTML")"
    else
        if [ -d "$PUBLIC_HTML" ]; then
            BACKUP="${PUBLIC_HTML}_respaldo_$(date +%Y%m%d%H%M%S)"
            echo "→ Respaldando public_html en $BACKUP"
            mv "$PUBLIC_HTML" "$BACKUP"
        fi
        ln -s "$APP_DIR/public" "$PUBLIC_HTML"
        echo "→ public_html → $APP_DIR/public"
    fi
fi

# 6. Permisos y cachés
chmod -R 775 storage bootstrap/cache
"$PHP" artisan optimize

echo
echo "✅ Listo. Falta configurar el Cron Job en hPanel (cada minuto):"
echo "   cd $APP_DIR && $PHP artisan schedule:run >> /dev/null 2>&1"
