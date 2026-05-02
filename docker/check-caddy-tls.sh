#!/bin/sh
# Diagnóstico rápido: Caddy, portas 80/443 e logs Let's Encrypt.
# Dentro da pasta do clone: sh docker/check-caddy-tls.sh
# Por caminho: sudo sh /opt/getfy/docker/check-caddy-tls.sh
# Sem o ficheiro no disco (fork antigo): defina onde está o clone, ex.:
#   curl -fsSL https://raw.githubusercontent.com/getfy-opensource/getfy/main/docker/check-caddy-tls.sh -o /tmp/check-caddy-tls.sh
#   sudo GETFY_DIR=/opt/getfy sh /tmp/check-caddy-tls.sh

if [ -n "${GETFY_DIR:-}" ]; then
  ROOT_DIR="$GETFY_DIR"
else
  ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
fi
cd "$ROOT_DIR" || { echo "getfy: não foi possível aceder à pasta: $ROOT_DIR (defina GETFY_DIR?)." >&2; exit 1; }

ENV_FILE=".docker/stack.env"
if [ ! -f "$ENV_FILE" ]; then
  echo "Falta $ROOT_DIR/$ENV_FILE" >&2
  exit 1
fi

echo "=== Portas em escuta (80 e 443 no host) ==="
if command -v ss >/dev/null 2>&1; then
  ss -tlnp 2>/dev/null | grep -E ':(80|443)\b' || echo "(nada ouvindo em 80/443 — firewall ou Caddy não subiu)"
else
  netstat -tlnp 2>/dev/null | grep -E ':(80|443)' || true
fi

echo ""
echo "=== Variáveis TLS no stack.env ==="
grep -E '^(GETFY_CADDY_HOST|GETFY_APP_URL|GETFY_ACME_EMAIL|GETFY_DOMAIN|GETFY_COMPOSE_PROFILE|GETFY_HTTP_PORT|GETFY_HTTPS_PORT)=' "$ENV_FILE" 2>/dev/null || true

echo ""
echo "=== Containers (docker-compose.caddy.yml) ==="
docker compose -f docker-compose.caddy.yml --env-file "$ENV_FILE" ps -a 2>&1 || true

echo ""
echo "=== Logs do Caddy (certificado Let's Encrypt / erros ACME) ==="
docker compose -f docker-compose.caddy.yml --env-file "$ENV_FILE" logs caddy --tail 120 2>&1 || echo "(sem serviço caddy — não está a usar o stack com Caddy?)"

echo ""
echo "=== Pedido HTTP local (deve responder o Caddy na 80) ==="
if command -v curl >/dev/null 2>&1; then
  curl -fsSI -m 10 http://127.0.0.1/ 2>&1 | head -20 || echo "curl falhou — nada na porta 80 ou Caddy em erro."
else
  echo "(instale curl para este teste)"
fi

echo ""
echo "=== Certificado na 443 (Let's Encrypt = subject com o seu domínio; Full strict na Cloudflare) ==="
CADDY_HOST=""
if [ -f "$ENV_FILE" ]; then
  CADDY_HOST="$(grep -E '^GETFY_CADDY_HOST=' "$ENV_FILE" | head -1 | cut -d= -f2- | tr -d '\r')"
fi
case "$CADDY_HOST" in
  :*|"") echo "(GETFY_CADDY_HOST não é FQDN — stack sem TLS automático neste env)" ;;
  *)
    if command -v openssl >/dev/null 2>&1; then
      echo | openssl s_client -connect 127.0.0.1:443 -servername "$CADDY_HOST" 2>/dev/null \
        | openssl x509 -noout -subject -issuer -dates 2>/dev/null || echo "Sem certificado válido na 443 ainda (ACME a correr ou falhou — ver logs do Caddy acima)."
    else
      echo "(instale openssl para inspecionar o certificado)"
    fi
  ;;
esac
