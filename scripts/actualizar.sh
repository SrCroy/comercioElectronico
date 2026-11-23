#!/usr/bin/env bash
# Actualiza el servidor con la última versión de GitHub.
# Uso (en el VPS):  ./scripts/actualizar.sh
set -euo pipefail
cd "$(dirname "$0")/.."

echo "==> Respaldo antes de actualizar"
./scripts/backup.sh

echo "==> Descargando cambios de GitHub"
git pull --ff-only

echo "==> Reconstruyendo y reiniciando"
docker compose up -d --build

echo "==> Aplicando configuración de Mechanical Bull"
docker compose exec -T -u www-data wordpress wp mb instalar
docker compose exec -T -u www-data wordpress wp rewrite flush
docker compose exec -T -u www-data wordpress wp cache flush >/dev/null 2>&1 || true

echo "Listo."
