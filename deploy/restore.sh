#!/usr/bin/env bash
# Restaurar un respaldo:  ./restore.sh reypass-AAAAMMDD-HHMMSS.tar.gz.age /ruta/llave-age.txt
# Con --test solo descifra y verifica en una carpeta temporal, sin tocar la app.
set -euo pipefail

TEST=0
if [[ "${1:-}" == "--test" ]]; then TEST=1; shift; fi
ARCHIVE="${1:?Uso: restore.sh [--test] respaldo.tar.gz.age llave-age.txt}"
IDENTITY="${2:?Falta el archivo con la llave privada de age}"
APP_DIR="${APP_DIR:-/var/www/reypass}"

umask 077
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

age -d -i "$IDENTITY" "$ARCHIVE" | tar -xzf - -C "$WORK"
[[ "$(sqlite3 "$WORK/database.sqlite" 'PRAGMA integrity_check;')" == "ok" ]] || { echo "La base de datos del respaldo está dañada" >&2; exit 1; }

USERS="$(sqlite3 "$WORK/database.sqlite" 'select count(*) from users;')"
CLIENTS="$(sqlite3 "$WORK/database.sqlite" 'select count(*) from clients;')"
PHOTOS="$(find "$WORK/private" -type f | wc -l | tr -d ' ')"
echo "Respaldo válido: ${USERS} usuarios, ${CLIENTS} clientes, ${PHOTOS} archivos de fotos."

if [[ "$TEST" == "1" ]]; then
    echo "Modo prueba: no se cambió nada."
    exit 0
fi

read -r -p "Esto REEMPLAZA la base de datos, las fotos y el .env de ${APP_DIR}. Escribe RESTAURAR para seguir: " ANSWER
[[ "$ANSWER" == "RESTAURAR" ]] || { echo "Cancelado."; exit 1; }

cd "$APP_DIR"
php artisan down || true
SAVED="$HOME/antes-de-restaurar-$(date -u +%Y%m%d-%H%M%S)"
mkdir -p "$SAVED"
[[ -f database/database.sqlite ]] && mv database/database.sqlite* "$SAVED"/ || true
[[ -d storage/app/private ]] && mv storage/app/private "$SAVED"/private || true
[[ -f .env ]] && cp .env "$SAVED"/env || true

mkdir -p database storage/app
cp "$WORK/database.sqlite" database/database.sqlite
cp -R "$WORK/private" storage/app/private
cp "$WORK/env" .env
chmod 600 .env database/database.sqlite

php artisan migrate --force
php artisan optimize
php artisan up
echo "Restaurado. Lo anterior quedó en $SAVED (bórralo cuando confirmes que todo está bien)."
