#!/usr/bin/env bash
# Respaldo de la base de datos y de los archivos subidos (fotos, etc.) en ./backups/
set -euo pipefail
cd "$(dirname "$0")/.."
set -a; . ./.env; set +a

stamp="$(date +%Y%m%d-%H%M%S)"
mkdir -p backups

docker compose exec -T db mariadb-dump -u root -p"$DB_ROOT_PASSWORD" "$DB_NAME" | gzip > "backups/db-$stamp.sql.gz"
docker compose exec -T wordpress tar -C /var/www/html/wp-content -czf - uploads > "backups/uploads-$stamp.tar.gz"

echo "Respaldo creado en backups/ ($stamp)"
