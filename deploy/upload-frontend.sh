#!/usr/bin/env bash
# Compila el frontend en TU máquina y lo sube al servidor.
# Uso: ./deploy/upload-frontend.sh usuario@panel.tudominio.com.ar
set -euo pipefail

SERVER="${1:?Falta el destino. Ejemplo: ./deploy/upload-frontend.sh deploy@panel.tudominio.com.ar}"
REMOTE_DIR="${REMOTE_DIR:-/var/www/lm-studio-manager/frontend/dist}"

cd "$(dirname "$0")/../frontend"

echo "→ Compilando"
npm ci
npm run build

echo "→ Subiendo a $SERVER:$REMOTE_DIR"
rsync -az --delete dist/ "$SERVER:$REMOTE_DIR/"

echo "✓ Listo"
