#!/usr/bin/env bash
# Actualiza el BACKEND en el servidor: ./deploy/deploy.sh
# El frontend se compila en tu máquina y se sube con deploy/upload-frontend.sh (el servidor no necesita Node).
# Se corre desde /var/www/lm-studio-manager con el usuario que es dueño del proyecto (no root).
set -euo pipefail

cd "$(dirname "$0")/.."

echo "→ Bajando cambios"
git pull --ff-only origin main

echo "→ Backend"
cd backend
composer install --no-dev --optimize-autoloader --no-interaction
# Copia antes de tocar la base, por si una migración sale mal.
php artisan app:backup || echo "⚠ La copia previa falló; revisá BACKUP_MYSQLDUMP_PATH"
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "→ Recargando PHP-FPM"
sudo systemctl reload php8.4-fpm

echo "✓ Listo"
