#!/bin/sh
# Diagnóstico rápido de HTTP 500 após update (rodar na pasta da instalação, ex.: /opt/getfy).
set -e

ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT_DIR"

ENV_FILE=".docker/stack.env"
PROFILE_FILE=".docker/compose-profile"
COMPOSE_FILES="docker-compose.yml"
if [ -f "$PROFILE_FILE" ] && [ "$(tr -d '\r\n' < "$PROFILE_FILE")" = "caddy" ]; then
  COMPOSE_FILES="docker-compose.caddy.yml"
elif [ -f "$ENV_FILE" ] && grep -q '^GETFY_COMPOSE_PROFILE=caddy' "$ENV_FILE" 2>/dev/null; then
  COMPOSE_FILES="docker-compose.caddy.yml"
fi

APP_CONTAINER="${GETFY_APP_CONTAINER:-getfy-app-1}"

echo "=== Container app (últimas 40 linhas) ==="
docker logs "$APP_CONTAINER" --tail 40 2>&1 || true

echo ""
echo "=== Laravel log (últimas 60 linhas) ==="
docker exec "$APP_CONTAINER" sh -c 'ls -t storage/logs/laravel*.log 2>/dev/null | head -1 | xargs tail -60 2>/dev/null' || echo "(sem log ou container indisponível)"

echo ""
echo "=== Status das migrations ==="
docker exec "$APP_CONTAINER" php artisan migrate:status 2>&1 | tail -25 || true

echo ""
echo "=== Tentar migrate --force ==="
docker exec "$APP_CONTAINER" php artisan migrate --force 2>&1 || true

echo ""
echo "=== Limpar caches ==="
docker exec "$APP_CONTAINER" php artisan optimize:clear 2>&1 || true

echo ""
echo "Se migrate falhar em product_recommended_products (tabela pela metade), rode:"
echo "  docker exec -i getfy-mysql-1 mysql -u\"\$GETFY_DB_USER\" -p\"\$GETFY_DB_PASS\" getfy -e 'DROP TABLE IF EXISTS product_recommended_products;'"
echo "  docker exec $APP_CONTAINER php artisan migrate --force"
