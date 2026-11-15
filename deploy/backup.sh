#!/usr/bin/env bash
# Respaldo diario cifrado (cron del usuario reypass). Guarda la base de datos,
# las fotos de cédula y el .env en un archivo cifrado con age y lo sube a
# Backblaze B2 con rclone. Conserva 30 días.
#
# Configuración en /home/reypass/backup.conf:
#   AGE_RECIPIENT="age1..."        # llave PÚBLICA; la privada NO vive en el servidor
#   RCLONE_REMOTE="b2:mi-bucket/reypass"
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/reypass}"
CONF="${BACKUP_CONF:-$HOME/backup.conf}"
# shellcheck disable=SC1090
source "$CONF"
: "${AGE_RECIPIENT:?Falta AGE_RECIPIENT en $CONF}"
: "${RCLONE_REMOTE:?Falta RCLONE_REMOTE en $CONF}"
KEEP_DAYS="${KEEP_DAYS:-30}"

umask 077
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
STAMP="$(date -u +%Y%m%d-%H%M%S)"
FILE="reypass-${STAMP}.tar.gz.age"

# Copia consistente aunque la app esté escribiendo (modo WAL).
sqlite3 "$APP_DIR/database/database.sqlite" ".timeout 10000" ".backup '$WORK/database.sqlite'"
[[ "$(sqlite3 "$WORK/database.sqlite" 'PRAGMA integrity_check;')" == "ok" ]] || { echo "La copia de la base de datos está dañada" >&2; exit 1; }
cp "$APP_DIR/.env" "$WORK/env"

tar -czf - -C "$WORK" database.sqlite env -C "$APP_DIR/storage/app" private \
    | age -r "$AGE_RECIPIENT" -o "$WORK/$FILE"

rclone copy "$WORK/$FILE" "$RCLONE_REMOTE" --quiet
rclone lsf "$RCLONE_REMOTE" --include "$FILE" | grep -q . || { echo "El respaldo no llegó al almacenamiento" >&2; exit 1; }
rclone delete "$RCLONE_REMOTE" --min-age "${KEEP_DAYS}d" --include 'reypass-*.tar.gz.age' --quiet

echo "$(date -u +%FT%TZ) respaldo ok: $FILE ($(du -h "$WORK/$FILE" | cut -f1))"
