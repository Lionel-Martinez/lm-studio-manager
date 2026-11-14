# Publicar en un VPS (Debian 13 + Nginx + PHP 8.4 + MariaDB)

Resultado: `https://panel.tudominio.com.ar` con el frontend y la API en el **mismo dominio** (lo más seguro para la sesión y el CSRF).

> Reemplazá `panel.tudominio.com.ar` por tu subdominio y `deploy` por el usuario que crees. Los comandos con `#` los corrés como root; los que tienen `$` como tu usuario.

## 0. Antes de empezar
- Un VPS con **Debian 13 (Trixie)**: ya trae PHP 8.4. Con 1 GB de RAM alcanza.
- Acceso por SSH al servidor y la IP pública.
- El repo en GitHub. Si es privado, el servidor necesita una *deploy key* (paso 4).
- Guardá una copia de `backend/.env` y de la `APP_KEY` en un lugar seguro: sin ella no se pueden leer las sesiones ni los secretos de la verificación en dos pasos.

## 1. DNS
Creá un registro **A** `panel` → IP del VPS (en el DNS donde administrás tu `.com.ar`: nic.ar o Cloudflare). Si lo hacés en Cloudflare, dejalo en **"solo DNS" (nube gris)** para empezar: con la nube naranja hay que configurar `TRUSTED_PROXIES` (ver paso 10).

## 2. Servidor: usuario y seguridad básica
```bash
# adduser deploy && usermod -aG sudo deploy
# mkdir -p /home/deploy/.ssh && cp ~/.ssh/authorized_keys /home/deploy/.ssh/ && chown -R deploy:deploy /home/deploy/.ssh
```
Probá entrar como `deploy` por SSH **antes** de seguir. Después:
```bash
# sed -i 's/^#\?PasswordAuthentication.*/PasswordAuthentication no/; s/^#\?PermitRootLogin.*/PermitRootLogin no/' /etc/ssh/sshd_config
# systemctl restart ssh
# apt update && apt -y upgrade
# apt -y install ufw fail2ban unattended-upgrades
# ufw allow OpenSSH && ufw allow 80 && ufw allow 443 && ufw --force enable
```
(Solo SSH con clave, sin root, firewall con tres puertos, fail2ban contra fuerza bruta y actualizaciones de seguridad automáticas.)

## 3. Software
```bash
# apt -y install nginx git unzip composer mariadb-server mariadb-client rsync \
    php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring php8.4-xml php8.4-curl php8.4-zip php8.4-bcmath php8.4-intl
# mysql_secure_installation
```
Node **no hace falta** en el servidor: el frontend se compila en tu máquina.

Base de datos con usuario propio (no root):
```bash
# mariadb
CREATE DATABASE lm_studio_manager CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'lm_manager'@'localhost' IDENTIFIED BY 'UNA_CONTRASEÑA_LARGA_Y_UNICA';
GRANT ALL PRIVILEGES ON lm_studio_manager.* TO 'lm_manager'@'localhost';
FLUSH PRIVILEGES;
```
MariaDB escucha solo en `localhost` por defecto: no lo expongas a internet.

## 4. Código
```bash
# mkdir -p /var/www/lm-studio-manager && chown deploy:deploy /var/www/lm-studio-manager
$ git clone https://github.com/Lionel-Martinez/lm-studio-manager.git /var/www/lm-studio-manager
```
Si el repo es privado: `ssh-keygen -t ed25519` como `deploy`, y cargá la clave pública en GitHub → repo → *Settings → Deploy keys* (solo lectura); cloná con la URL `git@github.com:...`.

## 5. Backend
```bash
$ cd /var/www/lm-studio-manager/backend
$ cp ../deploy/env.production.example .env
$ nano .env          # completá APP_URL, DB_PASSWORD y los datos BUSINESS_*
$ composer install --no-dev --optimize-autoloader
$ php artisan key:generate --force
$ php artisan migrate --force
$ php artisan db:seed --force                       # países, provincias y departamentos
$ php artisan app:import-localities --province=Salta   # localidades (opcional, ~1 min por provincia)
$ php artisan app:create-user tu@correo.com --name="Tu nombre"
$ chmod 600 .env
# chown -R deploy:www-data storage bootstrap/cache && chmod -R ug+rwX storage bootstrap/cache
$ php artisan config:cache && php artisan route:cache && php artisan view:cache
```
**No corras `app:seed-demo` en producción.** Si lo usaste en tu máquina, no copies esa base.

## 6. Frontend (se compila en TU máquina)
```bash
cd frontend
npm ci && npm run build
rsync -az --delete dist/ deploy@panel.tudominio.com.ar:/var/www/lm-studio-manager/frontend/dist/
```
Más cómodo: `./deploy/upload-frontend.sh deploy@panel.tudominio.com.ar` (necesita Node 22.18+ en tu máquina, que ya tenés para desarrollar). En el servidor, antes: `mkdir -p /var/www/lm-studio-manager/frontend/dist`.

## 7. Nginx y HTTPS
```bash
# cp /var/www/lm-studio-manager/deploy/nginx.conf.example /etc/nginx/sites-available/panel
# nano /etc/nginx/sites-available/panel      # poné tu dominio
# ln -s /etc/nginx/sites-available/panel /etc/nginx/sites-enabled/panel && rm -f /etc/nginx/sites-enabled/default
# nginx -t && systemctl reload nginx
# apt -y install certbot python3-certbot-nginx
# certbot --nginx -d panel.tudominio.com.ar --redirect
```
Certbot instala el certificado, redirige HTTP a HTTPS y lo renueva solo. Comprobá la renovación: `# certbot renew --dry-run`.

## 8. Tareas programadas (copias y limpieza)
Como `deploy`: `crontab -e` y agregá una línea:
```
* * * * * cd /var/www/lm-studio-manager/backend && php artisan schedule:run >> /dev/null 2>&1
```
Eso dispara la copia de seguridad (03:00) y la limpieza del registro de actividad (03:30).

## 9. Verificación (no te saltees esto)
1. `curl -I https://panel.tudominio.com.ar/up` → `200`.
2. Abrí `https://panel.tudominio.com.ar`, entrá con tu usuario.
3. **Activá la verificación en dos pasos** (Cuenta) y guardá los códigos de recuperación.
4. Cargá un cliente de prueba, un proyecto con plan de pago, marcá un cobro, emití un comprobante. Después eliminalo si querés.
5. Revisá que el `.env` no sea accesible: `curl -I https://panel.tudominio.com.ar/.env` → `404` o `403`, nunca `200`.
6. Copia: `php artisan app:backup` y verificá que aparezca el archivo en `backend/storage/app/private/backups/`.
7. Con `APP_DEBUG=false`, un error nunca debe mostrar rutas ni datos internos.
8. Probá en [securityheaders.com](https://securityheaders.com) y [ssllabs.com/ssltest](https://www.ssllabs.com/ssltest/): deberías ver notas A o similares.

## 10. Si usás Cloudflare con la nube naranja
Poné en `backend/.env` `TRUSTED_PROXIES=*` **solo si** el firewall del servidor acepta 80/443 únicamente desde las IP de Cloudflare (lista en cloudflare.com/ips). Después `php artisan config:cache`. Sin esto, el límite de intentos de login vería siempre la IP de Cloudflare. En Cloudflare, usá SSL/TLS en modo **Full (strict)**.

## 11. Copias fuera del servidor
Una copia que vive en el mismo servidor no sirve si el servidor se pierde. Elegí una:
- Desde tu máquina, cada tanto: `rsync -az deploy@panel.tudominio.com.ar:/var/www/lm-studio-manager/backend/storage/app/private/backups/ ~/backups-lm-studio/`
- O `rclone` a Google Drive / Backblaze desde el servidor, con otra entrada de cron.

Probá restaurar una copia en una base de pruebas al menos una vez: `gunzip -c backup-….sql.gz | mysql -u usuario -p base_de_pruebas`.

## 12. Actualizar después
1. En tu máquina: `git push` a `main`.
2. Backend: en el servidor, `./deploy/deploy.sh` (baja cambios, instala, **hace una copia**, migra y recarga PHP).
3. Frontend (si cambió): `./deploy/upload-frontend.sh deploy@panel.tudominio.com.ar`.

Para que `deploy.sh` pueda recargar PHP sin pedir contraseña: `# echo 'deploy ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.4-fpm' > /etc/sudoers.d/deploy-php && chmod 440 /etc/sudoers.d/deploy-php`.

## Problemas típicos
| Síntoma | Causa probable |
|---|---|
| `500` en `/api/...` | Permisos de `storage/` o `bootstrap/cache/`; mirá `backend/storage/logs/laravel-AAAA-MM-DD.log`. |
| `419` al iniciar sesión | `APP_URL` distinto del dominio real, o `SESSION_SECURE_COOKIE=true` entrando por HTTP. |
| Entra pero se desloguea enseguida | `SESSION_DOMAIN` mal puesto (debe ser `null`) o reloj del servidor desfasado. |
| Pantalla en blanco | No se subió `frontend/dist`, o falta el `try_files … /index.html` de Nginx. |
| Al recargar `/clientes` da 404 | Falta el bloque `location /` con `try_files` en Nginx. |
| Los códigos 2FA no andan | Reloj del servidor desfasado: `timedatectl set-ntp true`. |
| `app:backup` falla | `mariadb-client` no instalado o `BACKUP_MYSQLDUMP_PATH` incorrecto. |
