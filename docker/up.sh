#!/bin/sh
set -e

_SCRIPT_DIR="$(dirname "$0")"
case "$_SCRIPT_DIR" in
  /*) ;;
  *)
    if [ ! -d "$_SCRIPT_DIR" ]; then
      echo "getfy: docker/up.sh com cwd errado ou caminho relativo inválido (ex.: sudo desde /root)." >&2
      echo "    Use caminho absoluto (ex.: sh /opt/getfy/docker/up.sh) ou: cd pasta-do-getfy && sh docker/up.sh" >&2
      exit 1
    fi
  ;;
esac
ROOT_DIR="$(cd "$_SCRIPT_DIR/.." && pwd)"
cd "$ROOT_DIR"

mkdir -p .docker

ENV_FILE=".docker/stack.env"
PROFILE_FILE=".docker/compose-profile"

tls_requested=false
case "${GETFY_TLS:-}" in 1|true|yes|True|YES|on|ON) tls_requested=true ;; esac
if [ -n "${GETFY_DOMAIN:-}" ]; then
  tls_requested=true
fi

if [ "$tls_requested" = true ]; then
  if [ -z "${GETFY_DOMAIN:-}" ]; then
    echo "getfy: HTTPS automático requer GETFY_DOMAIN (FQDN público)." >&2
    exit 1
  fi
  if [ -z "${GETFY_ACME_EMAIL:-}" ]; then
    echo "getfy: HTTPS automático requer GETFY_ACME_EMAIL (contato Let's Encrypt)." >&2
    exit 1
  fi
fi

if [ "$tls_requested" = true ]; then
  case "${GETFY_COMPOSE_FILES:-}" in *no-redis*)
    echo "getfy: Stack sem Redis (docker-compose.no-redis.yml) não suporta HTTPS automático neste instalador. Use install.sh padrão ou TLS manual." >&2
    exit 1
  ;; esac
fi

merge_kv() {
  k="$1"
  v="$2"
  f="$3"
  tmp="$(mktemp)"
  awk -v k="$k" -v v="$v" '
    BEGIN { replaced = 0 }
    index($0, k "=") == 1 { print k "=" v; replaced = 1; next }
    { print }
    END { if (!replaced) print k "=" v }
  ' "$f" > "$tmp" && mv "$tmp" "$f"
}

if [ ! -f "$ENV_FILE" ]; then
  HTTP_PORT="${GETFY_HTTP_PORT:-80}"
  HTTPS_PORT="${GETFY_HTTPS_PORT:-443}"
  APP_URL="${GETFY_APP_URL:-http://localhost}"
  CADDY_HOST="${GETFY_CADDY_HOST:-:80}"
  ACME_EMAIL="${GETFY_ACME_EMAIL:-getfy-acme-placeholder@invalid.local}"

  if [ "$tls_requested" = true ]; then
    HTTP_PORT=80
    HTTPS_PORT=443
    APP_URL="https://${GETFY_DOMAIN}"
    CADDY_HOST="${GETFY_DOMAIN}"
    ACME_EMAIL="${GETFY_ACME_EMAIL}"
  fi

  U="getfy_$(tr -dc 'a-z0-9' < /dev/urandom | head -c 8)"
  P="$(tr -dc 'A-Za-z0-9' < /dev/urandom | head -c 32)"
  R="$(tr -dc 'A-Za-z0-9' < /dev/urandom | head -c 32)"

  DOMAIN_LINE=""
  PROFILE_LINE="GETFY_COMPOSE_PROFILE=default"
  if [ "$tls_requested" = true ]; then
    DOMAIN_LINE="GETFY_DOMAIN=${GETFY_DOMAIN}"
    PROFILE_LINE="GETFY_COMPOSE_PROFILE=caddy"
  fi

  cat > "$ENV_FILE" <<EOF
GETFY_DB_DATABASE=getfy
GETFY_DB_USERNAME=$U
GETFY_DB_PASSWORD=$P
GETFY_APP_URL=$APP_URL
GETFY_HTTP_PORT=$HTTP_PORT
GETFY_HTTPS_PORT=$HTTPS_PORT
GETFY_MYSQL_DATABASE=getfy
GETFY_MYSQL_USER=$U
GETFY_MYSQL_PASSWORD=$P
GETFY_MYSQL_ROOT_PASSWORD=$R
GETFY_QUEUE_CONNECTION=${GETFY_QUEUE_CONNECTION:-redis}
GETFY_CACHE_STORE=${GETFY_CACHE_STORE:-redis}
GETFY_SESSION_DRIVER=${GETFY_SESSION_DRIVER:-file}
GETFY_MYSQL_INNODB_BUFFER_POOL_SIZE=${GETFY_MYSQL_INNODB_BUFFER_POOL_SIZE:-256M}
GETFY_MYSQL_INNODB_LOG_FILE_SIZE=${GETFY_MYSQL_INNODB_LOG_FILE_SIZE:-64M}
GETFY_MYSQL_INNODB_BUFFER_POOL_INSTANCES=${GETFY_MYSQL_INNODB_BUFFER_POOL_INSTANCES:-1}
GETFY_MYSQL_MAX_CONNECTIONS=${GETFY_MYSQL_MAX_CONNECTIONS:-50}
GETFY_MYSQL_TABLE_OPEN_CACHE=${GETFY_MYSQL_TABLE_OPEN_CACHE:-200}
GETFY_MYSQL_THREAD_CACHE_SIZE=${GETFY_MYSQL_THREAD_CACHE_SIZE:-16}
GETFY_MYSQL_SKIP_TZINFO=${GETFY_MYSQL_SKIP_TZINFO:-1}
GETFY_REDIS_MAXMEMORY=${GETFY_REDIS_MAXMEMORY:-128mb}
GETFY_REDIS_MAXMEMORY_POLICY=${GETFY_REDIS_MAXMEMORY_POLICY:-allkeys-lru}
GETFY_QUEUE_WORKER_MEMORY=${GETFY_QUEUE_WORKER_MEMORY:-128}
GETFY_QUEUE_WORKER_MAX_TIME=${GETFY_QUEUE_WORKER_MAX_TIME:-3600}
GETFY_QUEUE_WORKER_MAX_JOBS=${GETFY_QUEUE_WORKER_MAX_JOBS:-1000}
GETFY_CADDY_HOST=$CADDY_HOST
GETFY_ACME_EMAIL=$ACME_EMAIL
$PROFILE_LINE
$DOMAIN_LINE
EOF
else
  if grep -Eq '^\s*GETFY_DB_USERNAME\s*=\s*$' "$ENV_FILE" || grep -Eq '^\s*GETFY_DB_PASSWORD\s*=\s*$' "$ENV_FILE" \
    || grep -Eq '^\s*GETFY_DB_USERNAME\s*=\s*getfy\s*$' "$ENV_FILE" || grep -Eq '^\s*GETFY_DB_PASSWORD\s*=\s*getfy\s*$' "$ENV_FILE"; then
    U="getfy_$(tr -dc 'a-z0-9' < /dev/urandom | head -c 8)"
    P="$(tr -dc 'A-Za-z0-9' < /dev/urandom | head -c 32)"
    R="$(tr -dc 'A-Za-z0-9' < /dev/urandom | head -c 32)"
    TMP="$(mktemp)"
    awk -v U="$U" -v P="$P" -v R="$R" '
      BEGIN { u=0; p=0; r=0; mu=0; mp=0; mr=0 }
      $0 ~ /^GETFY_DB_USERNAME=/ { print "GETFY_DB_USERNAME=" U; u=1; next }
      $0 ~ /^GETFY_DB_PASSWORD=/ { print "GETFY_DB_PASSWORD=" P; p=1; next }
      $0 ~ /^GETFY_MYSQL_USER=/ { print "GETFY_MYSQL_USER=" U; mu=1; next }
      $0 ~ /^GETFY_MYSQL_PASSWORD=/ { print "GETFY_MYSQL_PASSWORD=" P; mp=1; next }
      $0 ~ /^GETFY_MYSQL_ROOT_PASSWORD=/ { print "GETFY_MYSQL_ROOT_PASSWORD=" R; mr=1; next }
      { print }
      END {
        if (!u) print "GETFY_DB_USERNAME=" U
        if (!p) print "GETFY_DB_PASSWORD=" P
        if (!mu) print "GETFY_MYSQL_USER=" U
        if (!mp) print "GETFY_MYSQL_PASSWORD=" P
        if (!mr) print "GETFY_MYSQL_ROOT_PASSWORD=" R
      }
    ' "$ENV_FILE" > "$TMP"
    mv "$TMP" "$ENV_FILE"
  fi
fi

if [ -f "$ENV_FILE" ]; then
  if [ "$tls_requested" = true ]; then
    merge_kv GETFY_HTTP_PORT 80 "$ENV_FILE"
    merge_kv GETFY_HTTPS_PORT 443 "$ENV_FILE"
    merge_kv GETFY_APP_URL "https://${GETFY_DOMAIN}" "$ENV_FILE"
    merge_kv GETFY_CADDY_HOST "${GETFY_DOMAIN}" "$ENV_FILE"
    merge_kv GETFY_ACME_EMAIL "${GETFY_ACME_EMAIL}" "$ENV_FILE"
    merge_kv GETFY_DOMAIN "${GETFY_DOMAIN}" "$ENV_FILE"
  else
    if [ -n "${GETFY_HTTP_PORT:-}" ]; then
      merge_kv GETFY_HTTP_PORT "${GETFY_HTTP_PORT}" "$ENV_FILE"
    fi
    if [ -n "${GETFY_APP_URL:-}" ]; then
      merge_kv GETFY_APP_URL "${GETFY_APP_URL}" "$ENV_FILE"
    fi
  fi
  if ! grep -q '^GETFY_HTTPS_PORT=' "$ENV_FILE"; then
    merge_kv GETFY_HTTPS_PORT "${GETFY_HTTPS_PORT:-443}" "$ENV_FILE"
  fi
  if ! grep -q '^GETFY_ACME_EMAIL=' "$ENV_FILE"; then
    merge_kv GETFY_ACME_EMAIL "${GETFY_ACME_EMAIL:-getfy-acme-placeholder@invalid.local}" "$ENV_FILE"
  fi
fi

COMPOSE_FILES=""
if [ "$tls_requested" = true ]; then
  COMPOSE_FILES="docker-compose.caddy.yml"
elif [ -n "${GETFY_COMPOSE_FILES:-}" ]; then
  COMPOSE_FILES="$GETFY_COMPOSE_FILES"
elif [ -f "$PROFILE_FILE" ]; then
  prof="$(tr -d '\r\n' < "$PROFILE_FILE")"
  case "$prof" in
    caddy) COMPOSE_FILES="docker-compose.caddy.yml" ;;
    *) COMPOSE_FILES="docker-compose.yml" ;;
  esac
elif [ -f "$ENV_FILE" ]; then
  stack_prof="$(grep -E '^GETFY_COMPOSE_PROFILE=' "$ENV_FILE" | head -1 | cut -d= -f2- | tr -d '\r')"
  case "$stack_prof" in
    caddy) COMPOSE_FILES="docker-compose.caddy.yml" ;;
    *) COMPOSE_FILES="docker-compose.yml" ;;
  esac
else
  COMPOSE_FILES="docker-compose.yml"
fi

case "$COMPOSE_FILES" in *docker-compose.caddy.yml*)
  echo caddy > "$PROFILE_FILE"
  if [ -f "$ENV_FILE" ]; then
    merge_kv GETFY_COMPOSE_PROFILE caddy "$ENV_FILE"
  fi
;; *)
  echo default > "$PROFILE_FILE"
  if [ -f "$ENV_FILE" ]; then
    merge_kv GETFY_COMPOSE_PROFILE default "$ENV_FILE"
  fi
;; esac

COMPOSE_ARGS=""
OLD_IFS="$IFS"
IFS=';'
for f in $COMPOSE_FILES; do
  if [ -n "$f" ]; then
    COMPOSE_ARGS="$COMPOSE_ARGS -f $f"
  fi
done
IFS="$OLD_IFS"

docker compose $COMPOSE_ARGS --env-file "$ENV_FILE" up --build -d
