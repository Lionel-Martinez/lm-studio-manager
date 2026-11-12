# Seguridad — LM Studio Client Manager

## Qué protege la aplicación
- **Acceso privado:** toda la API (`/api/*`) exige sesión iniciada. No hay registro público; los usuarios se crean con `php artisan app:create-user correo@dominio.com`.
- **Sesión por cookie HttpOnly** (el navegador nunca maneja tokens) + **protección CSRF** (cookie `XSRF-TOKEN` / encabezado `X-XSRF-TOKEN`).
- **Contraseñas** con hash (bcrypt), mínimo 12 caracteres con mayúsculas, minúsculas, números y símbolos.
- **Login a prueba de fuerza bruta:** 5 intentos fallidos por correo+IP bloquean 15 min. El error es el mismo exista o no el correo.
- **Sesión nueva al entrar y destruida al salir**; vence tras 60 min sin actividad; el contenido de la sesión se guarda cifrado.
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

## Pendiente / ideas
- Verificación en dos pasos (TOTP) — requiere decidir librería.
- Bloqueo por IP a nivel servidor (fail2ban / WAF).
