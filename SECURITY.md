# Seguridad — LM Studio Client Manager

## Qué protege la aplicación
- **Acceso privado:** toda la API (`/api/*`) exige sesión iniciada. No hay registro público; los usuarios se crean con `php artisan app:create-user correo@dominio.com`.
- **Sesión por cookie HttpOnly** (el navegador nunca maneja tokens) + **protección CSRF** (cookie `XSRF-TOKEN` / encabezado `X-XSRF-TOKEN`).
- **Contraseñas** con hash (bcrypt), mínimo 12 caracteres con mayúsculas, minúsculas, números y símbolos.
- **Login a prueba de fuerza bruta:** 5 intentos fallidos por correo+IP bloquean 15 min. El error es el mismo exista o no el correo.
- **Sesión nueva al entrar y destruida al salir**; vence tras 60 min sin actividad; el contenido de la sesión se guarda cifrado.
- **Verificación en dos pasos (TOTP, opcional):** se activa en *Cuenta* con una app como Google Authenticator, Authy o 1Password. Con ella activa, la contraseña sola no inicia sesión: pide un código de 6 dígitos (5 intentos y se bloquea; un mismo código no sirve dos veces). Incluye 8 códigos de recuperación de un solo uso (guardados con hash). Activar, renovar códigos y desactivar piden la contraseña. El secreto se guarda cifrado con `APP_KEY`: si cambiás `APP_KEY`, las cuentas con dos pasos quedan bloqueadas (hay que desactivarlo desde la base o con tinker).
- **Cambio de contraseña** exige la actual y cierra las demás sesiones.
- **Registro de actividad** (`audit_logs`): accesos, fallos, bloqueos, cambios de contraseña, alta/edición/baja de clientes. Se ve en *Cuenta*.
- **Cabeceras:** `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, HSTS (por HTTPS), `Cache-Control: no-store` en la API.
- **Validación** de todo dato entrante (FormRequests) y **baja lógica** de clientes (recuperable).

## Antes de publicar en internet (checklist)
1. `APP_ENV=production`, `APP_DEBUG=false` (con debug activo se filtran datos internos).
2. Servir **solo por HTTPS** y poner `SESSION_SECURE_COOKIE=true`.
3. Frontend y API bajo el **mismo dominio** (p. ej. `/api` detrás del mismo servidor); si no, revisar CORS y `SESSION_DOMAIN`/`SESSION_SAME_SITE`.
4. Usuario de base de datos **con permisos mínimos** (no `root`), con contraseña propia; MySQL no expuesto a internet.
5. `.env` fuera del directorio público y nunca en Git. Generar `APP_KEY` propia (`php artisan key:generate`).
6. **Backups** periódicos de la base y probar restaurarlos.
7. `composer audit` y `npm audit` de vez en cuando; mantener PHP y dependencias actualizadas.
8. Si usás un proxy/hosting, configurar `TrustProxies` para que las IP y HTTPS se detecten bien.

## Copias de seguridad
- Manual: `php artisan app:backup` → guarda `backup-AAAAMMDD-HHMMSS.sql.gz` en `backend/storage/app/private/backups/` (fuera de la parte pública) y conserva las últimas `BACKUP_KEEP` (14 por defecto).
- Usa `mysqldump`. Si no está en el PATH (XAMPP en Linux), poné en `.env`: `BACKUP_MYSQLDUMP_PATH=/opt/lampp/bin/mysqldump`. La contraseña de la base no viaja en la línea de comandos.
- Automático: ya está programada a las 03:00. Hace falta una sola entrada de cron: `* * * * * cd /ruta/al/proyecto/backend && php artisan schedule:run >> /dev/null 2>&1`.
- **Una copia que vive en el mismo servidor no alcanza:** copiá la carpeta `backups/` a otro lugar (disco externo o nube) de vez en cuando, y probá restaurar: `gunzip -c backup-….sql.gz | mysql -u usuario -p base_de_pruebas`.

## Exportación de datos
- Botones "Exportar (CSV)" en Clientes, Proyectos y Pagos. Requieren sesión, tienen límite de uso y quedan en la actividad de la cuenta. Los CSV abren bien en Excel en español (separador `;`, acentos) y neutralizan textos que empiezan con `=`, `+`, `-` o `@`.

## Pendiente / ideas
- Bloqueo por IP a nivel servidor (fail2ban / WAF).
