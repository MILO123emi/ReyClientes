# ReyPass — guía de despliegue y recuperación de acceso

Droplet de DigitalOcean de $6 (1 vCPU, 1 GB) · Ubuntu 24.04 LTS · Nginx + PHP-FPM 8.4 · SQLite · HTTPS con certbot.
Los archivos que usa esta guía están en [deploy/](deploy/).

En toda la guía hay que cambiar:

| Marcador         | Qué es                                                            |
| ---------------- | ----------------------------------------------------------------- |
| `TU_DOMINIO`     | El dominio o subdominio de la app, p. ej. `reypass.superelrey.hn` |
| `IP_DEL_DROPLET` | La IP pública del Droplet                                         |

**Si solo necesitas recuperar la cuenta de la administradora, ve directo a la [sección 9](#9-recuperar-el-acceso-de-la-administradora).**

---

## 0. Antes de empezar

1. **Dominio** con un registro DNS tipo `A` de `TU_DOMINIO` → `IP_DEL_DROPLET`. Sin proxy de Cloudflare (nube gris): la app limita intentos por IP y necesita ver la IP real.
2. **Cuenta de DigitalOcean** con verificación en 2 pasos activada y sus códigos de respaldo guardados. Es la última puerta de entrada al servidor (sección 9, nivel 3).
3. **Correo saliente** — una de dos:
    - **Resend** (recomendado): crear cuenta, agregar `TU_DOMINIO`, poner los registros DNS que indica y crear una API key.
    - **Brevo**: crear cuenta, verificar el remitente y copiar el usuario y la clave SMTP.
4. **Backblaze B2**: crear un bucket **privado** y una application key limitada a ese bucket.
5. **Gestor de contraseñas** donde guardar: `APP_KEY`, `DNI_HASH_KEY`, la llave privada de `age` y las claves de Resend/Brevo y B2.
6. En la Mac: que `php artisan test` pase y que puedas entrar por SSH con tu llave (`ssh-add -l` la muestra).

## 1. Crear el Droplet

En el panel de DigitalOcean: Ubuntu 24.04 LTS, plan Basic de $6, región más cercana a Honduras (ATL1 o NYC; DigitalOcean no tiene Miami), **autenticación por llave SSH** (no contraseña) y **backups semanales activados**.

## 2. Preparar el servidor (una sola vez, como `root`)

```bash
ssh root@IP_DEL_DROPLET
```

```bash
# Sistema, zona horaria y 2 GB de swap
apt update && apt -y upgrade
timedatectl set-timezone America/Tegucigalpa
fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab
echo 'vm.swappiness=10' > /etc/sysctl.d/99-swap.conf && sysctl --system

# PHP 8.4, Nginx y herramientas
add-apt-repository -y ppa:ondrej/php && apt update
apt -y install nginx certbot python3-certbot-nginx \
  php8.4-fpm php8.4-cli php8.4-sqlite3 php8.4-gd php8.4-mbstring php8.4-xml \
  php8.4-curl php8.4-intl php8.4-bcmath php8.4-zip php8.4-opcache \
  composer sqlite3 age rclone rsync unzip ufw unattended-upgrades

# Firewall
ufw allow OpenSSH && ufw allow 'Nginx Full' && ufw --force enable

# Usuario de la app (sin contraseña, entra solo con tu llave SSH)
adduser --disabled-password --gecos "" reypass
install -d -m 700 -o reypass -g reypass /home/reypass/.ssh
install -m 600 -o reypass -g reypass /root/.ssh/authorized_keys /home/reypass/.ssh/authorized_keys
install -d -o reypass -g reypass /var/www/reypass
echo 'reypass ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.4-fpm' > /etc/sudoers.d/reypass
chmod 440 /etc/sudoers.d/reypass && visudo -c

# SSH solo con llave
sed -i 's/^#\?PasswordAuthentication.*/PasswordAuthentication no/' /etc/ssh/sshd_config
systemctl reload ssh
```

Deja esta sesión de `root` abierta y, en otra terminal, comprueba que entras como `reypass`:

```bash
ssh reypass@IP_DEL_DROPLET 'whoami && php -v | head -1'
```

## 3. Primer envío del código (desde la Mac)

```bash
cd ReyClientes
./deploy/deploy.sh reypass@IP_DEL_DROPLET
```

Corre los tests, compila los assets, sube el código e instala las dependencias. La primera vez termina con "Falta crear .env": es lo esperado.

## 4. Crear el `.env` y las llaves (como `reypass`)

```bash
ssh reypass@IP_DEL_DROPLET
cd /var/www/reypass
cp deploy/env.production.example .env && chmod 600 .env

php artisan key:generate --force
echo "DNI_HASH_KEY=$(openssl rand -base64 48)"    # copia la línea que imprime
nano .env
```

En `nano`: pega el valor de `DNI_HASH_KEY`, cambia `TU_DOMINIO` (en `APP_URL` y `MAIL_FROM_ADDRESS`) y completa el correo (`RESEND_API_KEY`, o descomenta el bloque de Brevo y comenta el de Resend).

> **Guarda `APP_KEY` y `DNI_HASH_KEY` en el gestor de contraseñas ahora mismo.** Con `APP_KEY` se cifran las identidades y las fotos; con `DNI_HASH_KEY` se busca por identidad. Si se pierden o se cambian, los datos quedan ilegibles y **no hay forma de recuperarlos**. Nunca vuelvas a correr `key:generate` en este servidor.

```bash
grep -E '^(APP_KEY|DNI_HASH_KEY)=' .env          # para copiarlas al gestor
touch database/database.sqlite && chmod 600 database/database.sqlite
php artisan migrate --force
php artisan optimize
```

No se corre `db:seed`: el seeder es solo para desarrollo.

## 5. Nginx, PHP-FPM y HTTPS (como `root`)

```bash
cp /var/www/reypass/deploy/php-fpm-reypass.conf /etc/php/8.4/fpm/pool.d/reypass.conf
cp /var/www/reypass/deploy/nginx-reypass.conf /etc/nginx/sites-available/reypass
sed -i 's/TU_DOMINIO/el.dominio.real/' /etc/nginx/sites-available/reypass     # pon el dominio real
ln -s /etc/nginx/sites-available/reypass /etc/nginx/sites-enabled/reypass
rm -f /etc/nginx/sites-enabled/default

php-fpm8.4 -t && systemctl restart php8.4-fpm
nginx -t && systemctl reload nginx

certbot --nginx --redirect --hsts -d TU_DOMINIO
systemctl list-timers | grep certbot      # la renovación automática ya queda programada
```

Comprobación: `curl -I https://TU_DOMINIO/up` debe responder `200`.

## 6. Correo, administradora y tareas programadas (como `reypass`)

```bash
cd /var/www/reypass

# 1. El correo tiene que funcionar ANTES de crear la cuenta: de él depende la recuperación.
php artisan mail:test tu-correo@example.com -v

# 2. La administradora (una sola vez). Pregunta nombre, correo y contraseña.
php artisan admin:create --owner

# 3. Limpieza diaria de códigos vencidos
crontab -e
```

Línea para el crontab:

```
* * * * * cd /var/www/reypass && php artisan schedule:run >> /dev/null 2>&1
```

Los empleados los crea ella desde la app, en **Usuarios → Nuevo empleado**.

Después prueba en el navegador, con la cuenta de ella:

- [ ] Entrar y crear un cliente con una foto tomada con el celular (5–8 MB).
- [ ] Agregar una persona autorizada, buscarla por nombre y por identidad.
- [ ] Cerrar sesión → "¿Olvidaste tu contraseña?" → llega el código y se puede entrar con la contraseña nueva.
- [ ] Entrar como empleado: ve todo y no puede cambiar nada.

## 7. Respaldos

### 7.1 Configurar (una sola vez)

En la **Mac** (la llave privada nunca vive en el servidor):

```bash
brew install age
age-keygen -o reypass-age.txt        # imprime "Public key: age1..."
```

Guarda **todo el contenido** de `reypass-age.txt` en el gestor de contraseñas. Sin esa llave los respaldos no se pueden abrir.

En el servidor, como `reypass`:

```bash
rclone config        # n) nuevo remoto → nombre: b2 → tipo: b2 → account = keyID, key = applicationKey
cat > ~/backup.conf <<'EOF'
AGE_RECIPIENT="age1...la-llave-publica..."
RCLONE_REMOTE="b2:NOMBRE-DEL-BUCKET/reypass"
EOF
chmod 600 ~/backup.conf

/var/www/reypass/deploy/backup.sh     # debe terminar con "respaldo ok"
crontab -e
```

Línea para el crontab (todos los días a las 2:30 a. m.):

```
30 2 * * * /var/www/reypass/deploy/backup.sh >> /home/reypass/backup.log 2>&1
```

Cada respaldo lleva la base de datos, las fotos de cédula y el `.env`, cifrados. Se conservan 30 días.

### 7.2 Probar una restauración (obligatorio antes de cargar datos reales)

En la Mac (`brew install rclone` y el mismo `rclone config`, o descarga el archivo desde la web de Backblaze):

```bash
rclone lsf b2:NOMBRE-DEL-BUCKET/reypass
rclone copy b2:NOMBRE-DEL-BUCKET/reypass/reypass-AAAAMMDD-HHMMSS.tar.gz.age .
./deploy/restore.sh --test reypass-AAAAMMDD-HHMMSS.tar.gz.age reypass-age.txt
```

Debe decir "Respaldo válido: N usuarios, N clientes, N archivos de fotos". Con `--test` no cambia nada.

### 7.3 Restaurar de verdad (servidor nuevo o datos dañados)

1. Si el servidor es nuevo: pasos 1, 2, 3 y 5 de esta guía (el paso 4 no: el `.env` viene en el respaldo).
2. Sube el respaldo y la llave, restaura y borra la llave del servidor:

```bash
scp reypass-AAAAMMDD-HHMMSS.tar.gz.age reypass-age.txt reypass@IP_DEL_DROPLET:~
ssh reypass@IP_DEL_DROPLET
/var/www/reypass/deploy/restore.sh ~/reypass-AAAAMMDD-HHMMSS.tar.gz.age ~/reypass-age.txt
shred -u ~/reypass-age.txt
```

## 8. Actualizar la app (cada vez que cambie el código)

Desde la Mac:

```bash
cd ReyClientes
./deploy/deploy.sh reypass@TU_DOMINIO
```

Hace todo: tests, build, subida, `composer install --no-dev`, migraciones, `optimize` y recarga de PHP-FPM. No toca el `.env`, la base de datos ni las fotos.

Si cambiaste `deploy/nginx-reypass.conf` o `deploy/php-fpm-reypass.conf`, vuelve a copiarlos como en el paso 5 (ojo: certbot editó el de Nginx; aplica el cambio a mano en `/etc/nginx/sites-available/reypass`).

## 9. Recuperar el acceso de la administradora

No existe una cuenta oculta de respaldo: sería una segunda puerta a las cédulas de los clientes. En su lugar hay cuatro niveles; cada uno funciona aunque falle el anterior.

### Nivel 1 — Ella sola, desde el login

"¿Olvidaste tu contraseña?" → escribe su correo → le llega un código de 6 dígitos (dura 10 minutos) → escribe el código y la contraseña nueva.

Si cambió de correo, lo actualiza ella misma **mientras tenga sesión**: menú de la cuenta → "Cambiar correo".

### Nivel 2 — No tiene acceso a su correo, o el correo no llega (tú, por SSH)

```bash
ssh reypass@TU_DOMINIO
cd /var/www/reypass

# Asignarle una contraseña nueva. No hace falta saber su correo: lo muestra.
php artisan admin:reset-password --owner

# Si además perdió el correo, cambiárselo para que el nivel 1 vuelva a servir.
php artisan admin:set-email --owner correo.nuevo@example.com
```

Ambos comandos cierran sus sesiones abiertas y quedan en el Registro de cambios. Dile que cambie la contraseña al entrar (menú de la cuenta → "Cambiar contraseña").

Para un empleado: `php artisan admin:reset-password correo@del.empleado` (aunque lo normal es que ella se la asigne desde **Usuarios**).

### Nivel 3 — Perdiste tu llave SSH

Panel de DigitalOcean → el Droplet → **Access → Launch Droplet Console**. Abre una terminal como `root` desde el navegador, sin llave. Ahí:

```bash
# Los comandos del nivel 2, como el usuario de la app
sudo -u reypass -H bash -c 'cd /var/www/reypass && php artisan admin:reset-password --owner'

# Y autorizar tu llave SSH nueva
echo 'ssh-ed25519 AAAA...tu-llave-publica-nueva' >> /home/reypass/.ssh/authorized_keys
```

Por eso la cuenta de DigitalOcean lleva verificación en 2 pasos y códigos de respaldo guardados.

### Nivel 4 — El servidor ya no existe

Restaurar un backup semanal del Droplet desde el panel de DigitalOcean, o crear un Droplet nuevo y seguir la sección 7.3. Después, el nivel 2.

### Lo único que no se puede recuperar

| Si se pierde…                                                      | Consecuencia                                                                      |
| ------------------------------------------------------------------ | --------------------------------------------------------------------------------- |
| `APP_KEY` **y** todos los respaldos                                | Identidades y fotos ilegibles para siempre                                        |
| La llave privada de `age`                                          | Los respaldos de B2 no se pueden abrir (los backups del Droplet siguen sirviendo) |
| La cuenta de DigitalOcean (sin 2FA de respaldo) **y** la llave SSH | No hay forma de entrar al servidor                                                |

Guarda las tres en el gestor de contraseñas y deja una copia con alguien de confianza.

## 10. Diagnóstico rápido

| Síntoma                          | Qué mirar                                                                                     |
| -------------------------------- | --------------------------------------------------------------------------------------------- |
| Error 500                        | `tail -50 /var/www/reypass/storage/logs/laravel-$(date +%F).log`                              |
| Error 502                        | `systemctl status php8.4-fpm` y `tail /var/log/nginx/error.log`                               |
| "La foto ha fallado" o error 413 | El pool y Nginx del paso 5 no están aplicados (`client_max_body_size`, `upload_max_filesize`) |
| No llega el código               | `php artisan mail:test correo@example.com -v` muestra el motivo                               |
| Cambié el `.env` y no pasa nada  | `php artisan optimize && sudo systemctl reload php8.4-fpm`                                    |
| ¿Corrió el respaldo?             | `tail ~/backup.log` y `rclone lsf b2:NOMBRE-DEL-BUCKET/reypass \| tail -3`                    |
| Poco espacio                     | `df -h /` y `du -sh /var/www/reypass/storage/app/private`                                     |
| Mantenimiento                    | `php artisan down` / `php artisan up`                                                         |
