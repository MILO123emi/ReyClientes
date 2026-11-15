#!/usr/bin/env bash
# Despliegue desde la Mac:  ./deploy/deploy.sh reypass@TU_DOMINIO
# Compila los assets aquí (nunca en el servidor de 1 GB), sube el código con
# rsync y actualiza la app. No toca .env, storage/ ni la base de datos.
set -euo pipefail

REMOTE="${1:?Uso: deploy/deploy.sh usuario@servidor}"
APP_DIR="${APP_DIR:-/var/www/reypass}"
cd "$(dirname "$0")/.."

if [[ "${SKIP_CHECKS:-0}" != "1" ]]; then
    echo "==> Tests"
    php artisan test --compact
fi

echo "==> Compilando assets"
npm run build

echo "==> Subiendo código a ${REMOTE}:${APP_DIR}"
rsync -az --delete \
    --exclude='.git/' --exclude='.github/' --exclude='.idea/' \
    --exclude='.env' --exclude='.env.*' \
    --exclude='node_modules/' --exclude='vendor/' --exclude='tests/' \
    --exclude='/storage/' --exclude='/bootstrap/cache/*.php' \
    --exclude='/database/*.sqlite*' \
    --exclude='/public/hot' --exclude='/public/fonts-manifest.dev.json' --exclude='/public/storage' \
    --exclude='.DS_Store' --exclude='.agents/' --exclude='.claude/' --exclude='.codex/' --exclude='.junie/' \
    --exclude='.mcp.json' --exclude='AGENTS.md' --exclude='boost.json' \
    ./ "${REMOTE}:${APP_DIR}/"

echo "==> Actualizando la app en el servidor"
ssh "$REMOTE" "APP_DIR='${APP_DIR}' bash -s" <<'REMOTE_SCRIPT'
set -euo pipefail
cd "$APP_DIR"
mkdir -p storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
composer install --no-dev --optimize-autoloader --no-interaction --quiet
if [[ ! -f .env ]]; then
    echo "Código subido. Falta crear .env (guía, paso 4); después vuelve a correr el despliegue."
    exit 0
fi
php artisan migrate --force
php artisan optimize
sudo /usr/bin/systemctl reload php8.4-fpm
php artisan about --only=environment
REMOTE_SCRIPT

echo "==> Listo"
