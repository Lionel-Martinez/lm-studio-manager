# LM Studio Client Manager

CRM privado para gestionar clientes, proyectos, pagos, comprobantes y notas de un estudio de desarrollo.

- **Backend:** Laravel 12 (PHP 8.4), MySQL/MariaDB, sesión + CSRF, API en `/api`.
- **Frontend:** Vue 3 + TypeScript, Pinia, Vue Router, Tailwind CSS 4, PrimeVue 4, Chart.js.

## Funciones
Clientes (con ubicación, baja lógica y desactivación por motivo) · Proyectos con estados y fechas · Pagos con vencimientos · Comprobantes internos (sin validez fiscal) · Notas en Markdown · Resumen con datos reales · Reportes por período · Agenda de entregas, cobros y recordatorios · Exportación a CSV · Copias de seguridad · Login con verificación en dos pasos opcional.

Pantallas todavía sin construir: **Servicios** y **Geografía** (muestran un aviso de "próximamente").

## Requisitos
PHP 8.4 (con `pdo_mysql`; `sqlite3` para los tests) · Composer · Node 20+ · MySQL o MariaDB.

## Puesta en marcha

### Backend (`backend/`)
```bash
composer install
cp .env.example .env          # luego editá DB_DATABASE, DB_USERNAME, DB_PASSWORD, BUSINESS_*
php artisan key:generate
php artisan migrate
php artisan db:seed           # países, provincias y departamentos
php artisan app:create-user tu@correo.com --name="Tu nombre"
php artisan serve             # http://127.0.0.1:8000
```
> Guardá una copia de tu `.env` (sobre todo `APP_KEY`): con la clave perdida las sesiones y el secreto de la verificación en dos pasos no se pueden leer.

### Frontend (`frontend/`)
```bash
npm install
npm run dev                   # Vite redirige /api al backend en 127.0.0.1:8000
```

## Pruebas y chequeos
```bash
# backend  (usa SQLite en memoria; nunca apuntes los tests a tu base real)
php artisan test

# frontend
npm run type-check && npx oxlint src && npm run build-only
```

## Copias de seguridad
`php artisan app:backup` (también programada a las 03:00). Detalles, restauración y cron en [SECURITY.md](SECURITY.md).

## Seguridad
Ver [SECURITY.md](SECURITY.md): qué protege la aplicación y la lista de verificación antes de publicar en internet.

## Estructura
```
backend/   API Laravel (app/Http/Controllers, app/Models, app/Support, database/migrations, tests/Feature)
frontend/  Vue 3 (src/views, src/components, src/stores, src/lib, src/types)
SECURITY.md
```
