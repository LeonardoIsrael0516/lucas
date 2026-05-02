#!/usr/bin/env bash
set -euo pipefail

REPO_URL="${GETFY_REPO_URL:-https://github.com/getfy-opensource/getfy.git}"
BRANCH="${GETFY_BRANCH:-main}"
INSTALL_DIR="${GETFY_DIR:-/opt/getfy}"
HTTP_PORT="${GETFY_HTTP_PORT:-80}"
SWAP_MODE="${GETFY_SWAP_MODE:-auto}"

if [ "$(uname -s)" != "Linux" ]; then
  echo "Este instalador é para Linux." >&2
  exit 1
fi

if ! command -v bash >/dev/null 2>&1; then
  echo "bash não encontrado." >&2
  exit 1
fi

if ! command -v apt-get >/dev/null 2>&1; then
  echo "Distribuição não suportada (precisa de apt-get, ex.: Ubuntu/Debian)." >&2
  exit 1
fi

SUDO=""
if [ "$(id -u)" -ne 0 ]; then
  if command -v sudo >/dev/null 2>&1; then
    SUDO="sudo"
  else
    echo "Rode como root ou instale sudo." >&2
    exit 1
  fi
fi

# Perguntas no teclado: com "curl ... | bash" o stdin é o pipe (não é TTY) e [ -t 0 ] falha —
# as perguntas eram ignoradas mesmo quando você digitava noutro passo. Ler de /dev/tty corrige.
GETFY_INSTALL_TTY=""
if [ -r /dev/tty ] && [ -w /dev/tty ]; then
  GETFY_INSTALL_TTY=/dev/tty
elif [ -t 0 ]; then
  GETFY_INSTALL_TTY=/dev/stdin
fi

if [ -z "${GETFY_DOMAIN:-}" ] || [ -z "${GETFY_ACME_EMAIL:-}" ]; then
  if [ -n "$GETFY_INSTALL_TTY" ]; then
    echo ""
    echo "Instalação Getfy — HTTPS na origem (Let's Encrypt via Caddy)"
    echo "  • Recomendado se usar Cloudflare em modo SSL/TLS \"Full\" ou \"Full (strict)\"."
    echo "    Nesses modos a Cloudflare exige HTTPS válido na VPS; o Let's Encrypt cumpre isso."
    echo "  • O modo Cloudflare \"Flexible\" funciona sem certificado na origem (HTTP na VPS) —"
    echo "    não use \"Flexible\" se quiser \"Full (strict)\" com esta stack."
    echo "Para só HTTP (ex.: testes por IP), deixe o domínio em branco (Enter)."
    if [ -z "${GETFY_DOMAIN:-}" ]; then
      read -r -p "Domínio público (ex.: loja.seudominio.com) [Enter = sem HTTPS automático]: " _getfy_domain_input <"$GETFY_INSTALL_TTY"
      GETFY_DOMAIN="$(echo "$_getfy_domain_input" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')"
    fi
    if [ -n "${GETFY_DOMAIN:-}" ] && [ -z "${GETFY_ACME_EMAIL:-}" ]; then
      while true; do
        read -r -p "E-mail para Let's Encrypt (ACME / contato do certificado): " _getfy_acme_input <"$GETFY_INSTALL_TTY"
        GETFY_ACME_EMAIL="$(echo "$_getfy_acme_input" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')"
        if [ -n "$GETFY_ACME_EMAIL" ]; then
          break
        fi
        echo "Informe um e-mail para continuar com HTTPS automático (ou Ctrl+C para cancelar)." >&2
      done
    fi
  else
    echo "Aviso: sem terminal (ex.: CI/cron) e sem GETFY_DOMAIN/GETFY_ACME_EMAIL no ambiente — a instalar só HTTP." >&2
    echo "      Para HTTPS na origem (Cloudflare Full strict), exporte antes de correr o instalador:" >&2
    echo "      export GETFY_DOMAIN=loja.seudominio.com GETFY_ACME_EMAIL=voce@email.com" >&2
  fi
fi

TLS_ACTIVE=false
case "${GETFY_TLS:-}" in 1|true|yes|on|ON|True|YES) TLS_ACTIVE=true ;; esac
if [ -n "${GETFY_DOMAIN:-}" ]; then
  TLS_ACTIVE=true
fi

if [ "$TLS_ACTIVE" = true ]; then
  if [ -z "${GETFY_DOMAIN:-}" ]; then
    echo "HTTPS automático: defina GETFY_DOMAIN (ex.: loja.seudominio.com) ou use as variáveis de ambiente." >&2
    exit 1
  fi
  if [ -z "${GETFY_ACME_EMAIL:-}" ]; then
    echo "HTTPS automático: defina GETFY_ACME_EMAIL (e-mail para Let's Encrypt)." >&2
    exit 1
  fi
fi

export DEBIAN_FRONTEND=noninteractive

$SUDO apt-get update -y
$SUDO apt-get install -y ca-certificates curl git gnupg lsb-release

if [ "$SWAP_MODE" != "off" ]; then
  MEM_KB="$(awk '/^MemTotal:/ {print $2}' /proc/meminfo 2>/dev/null || echo 0)"
  SWAP_KB="$(awk '/^SwapTotal:/ {print $2}' /proc/meminfo 2>/dev/null || echo 0)"
  MEM_GB=$(( (MEM_KB + 1048575) / 1048576 ))

  SHOULD_CREATE_SWAP=0
  if [ "$SWAP_MODE" = "on" ]; then
    SHOULD_CREATE_SWAP=1
  elif [ "$SWAP_MODE" = "auto" ]; then
    if [ "$SWAP_KB" -eq 0 ] && [ "$MEM_GB" -gt 0 ] && [ "$MEM_GB" -le 8 ]; then
      SHOULD_CREATE_SWAP=1
    fi
  fi

  if [ "$SHOULD_CREATE_SWAP" -eq 1 ]; then
    if ! swapon --show 2>/dev/null | awk 'NR>1 {print $1}' | grep -q '^/swapfile$'; then
      if [ ! -f /swapfile ]; then
        SWAP_GB=4
        if [ "$MEM_GB" -le 2 ]; then
          SWAP_GB=2
        fi

        if command -v fallocate >/dev/null 2>&1; then
          $SUDO fallocate -l "${SWAP_GB}G" /swapfile
        else
          $SUDO dd if=/dev/zero of=/swapfile bs=1M count=$((SWAP_GB * 1024)) status=progress
        fi
        $SUDO chmod 600 /swapfile
        $SUDO mkswap /swapfile >/dev/null
      fi

      $SUDO swapon /swapfile || true
      if ! grep -Eq '^\s*/swapfile\s+' /etc/fstab; then
        echo "/swapfile none swap sw 0 0" | $SUDO tee -a /etc/fstab >/dev/null
      fi
    fi
  fi
fi

if ! command -v docker >/dev/null 2>&1; then
  $SUDO install -m 0755 -d /etc/apt/keyrings
  $SUDO rm -f /etc/apt/keyrings/docker.gpg
  curl -fsSL https://download.docker.com/linux/ubuntu/gpg | $SUDO gpg --dearmor -o /etc/apt/keyrings/docker.gpg
  $SUDO chmod a+r /etc/apt/keyrings/docker.gpg

  CODENAME="$(. /etc/os-release && echo "${VERSION_CODENAME:-}")"
  if [ -z "$CODENAME" ]; then
    CODENAME="$(lsb_release -cs 2>/dev/null || true)"
  fi
  if [ -z "$CODENAME" ]; then
    echo "Não foi possível detectar o codename do Ubuntu/Debian." >&2
    exit 1
  fi

  ARCH="$(dpkg --print-architecture)"
  echo "deb [arch=$ARCH signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $CODENAME stable" | $SUDO tee /etc/apt/sources.list.d/docker.list >/dev/null

  $SUDO apt-get update -y
  $SUDO apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
  $SUDO systemctl enable --now docker >/dev/null 2>&1 || true
fi

if [ -n "${SUDO_USER:-}" ] && id -nG "$SUDO_USER" 2>/dev/null | grep -qw docker; then
  :
elif [ -n "${SUDO_USER:-}" ]; then
  $SUDO usermod -aG docker "$SUDO_USER" || true
fi

if [ -e "$INSTALL_DIR" ] && [ ! -d "$INSTALL_DIR" ]; then
  echo "Destino existe e não é diretório: $INSTALL_DIR" >&2
  exit 1
fi

if [ -d "$INSTALL_DIR/.git" ]; then
  GIT_BASE=(git -c safe.directory="$INSTALL_DIR" -C "$INSTALL_DIR")
  if [ -n "${GETFY_REPO_URL:-}" ]; then
    $SUDO "${GIT_BASE[@]}" remote set-url origin "$GETFY_REPO_URL" >/dev/null 2>&1 || true
  fi
  HAS_LOCAL_CHANGES=0
  STATUS_OUT="$($SUDO "${GIT_BASE[@]}" status --porcelain 2>/dev/null || true)"
  if [ -n "$STATUS_OUT" ]; then
    HAS_LOCAL_CHANGES=1
    if ! $SUDO "${GIT_BASE[@]}" stash push -u -m "getfy-install" >/dev/null 2>&1; then
      echo "Falha ao aplicar stash automaticamente. Resolva manualmente em: $INSTALL_DIR" >&2
      exit 1
    fi
  fi
  $SUDO "${GIT_BASE[@]}" fetch --all --prune
  if ! $SUDO "${GIT_BASE[@]}" checkout -B "$BRANCH" "origin/$BRANCH"; then
    echo "Falha ao atualizar código (checkout). Se você tem alterações locais, rode:" >&2
    echo "  cd \"$INSTALL_DIR\" && git stash push -u -m getfy-install" >&2
    exit 1
  fi
  $SUDO "${GIT_BASE[@]}" reset --hard "origin/$BRANCH"
  if [ "$HAS_LOCAL_CHANGES" -eq 1 ]; then
    if ! $SUDO "${GIT_BASE[@]}" stash pop >/dev/null 2>&1; then
      echo "Aviso: havia alterações locais. O instalador fez stash, mas não conseguiu reaplicar automaticamente." >&2
      echo "Para ver e resolver manualmente: cd \"$INSTALL_DIR\" && git stash list && git stash show -p" >&2
    fi
  fi
else
  $SUDO mkdir -p "$(dirname "$INSTALL_DIR")"
  $SUDO git clone --depth 1 --branch "$BRANCH" "$REPO_URL" "$INSTALL_DIR"
fi

cd "$INSTALL_DIR"

# Caminhos absolutos: `sudo sh docker/…` pode correr com cwd em /root (não é a pasta da app).
DOCKER_UP_SH="$INSTALL_DIR/docker/up.sh"
DOCKER_CHECK_TLS_SH="$INSTALL_DIR/docker/check-caddy-tls.sh"

$SUDO chmod +x "$DOCKER_UP_SH" >/dev/null 2>&1 || true
$SUDO chmod +x "$DOCKER_CHECK_TLS_SH" >/dev/null 2>&1 || true

if [ "$TLS_ACTIVE" = true ]; then
  if ss -ltn 2>/dev/null | awk '{print $4}' | grep -qE '(^|:)80$'; then
    echo "Aviso: porta 80 parece estar em uso (Let's Encrypt / Cloudflare costumam precisar dela na origem)." >&2
  fi
  if ss -ltn 2>/dev/null | awk '{print $4}' | grep -qE '(^|:)443$'; then
    echo "Aviso: porta 443 parece estar em uso." >&2
  fi
  $SUDO env GETFY_DOMAIN="$GETFY_DOMAIN" GETFY_ACME_EMAIL="$GETFY_ACME_EMAIL" GETFY_TLS=1 \
    GETFY_HTTP_PORT=80 GETFY_HTTPS_PORT=443 \
    sh "$DOCKER_UP_SH"
  echo ""
  echo "--- Verificação Caddy / SSL (erros do Let's Encrypt aparecem abaixo) ---"
  if [ -f "$DOCKER_CHECK_TLS_SH" ]; then
    $SUDO sh "$DOCKER_CHECK_TLS_SH" || true
  else
    echo "Aviso: não encontrado $DOCKER_CHECK_TLS_SH (repositório incompleto ou GETFY_DIR errado?)." >&2
  fi
else
  echo ""
  echo "Nota: sem HTTPS automático na origem. Cloudflare \"Full\" / \"Full (strict)\" exige certificado"
  echo "válido na VPS — instale de novo com GETFY_DOMAIN + GETFY_ACME_EMAIL ou veja docs/INSTALL_VPS.md."
  if ss -ltn 2>/dev/null | awk '{print $4}' | grep -qE "(^|:)${HTTP_PORT}$"; then
    echo "Aviso: porta $HTTP_PORT parece estar em uso. Se o compose falhar, mude GETFY_HTTP_PORT." >&2
  fi
  $SUDO env GETFY_HTTP_PORT="${HTTP_PORT}" sh "$DOCKER_UP_SH"
fi

IP="$(curl -fsSL https://api.ipify.org 2>/dev/null || true)"
if [ -z "$IP" ]; then
  IP="$(hostname -I 2>/dev/null | awk '{print $1}' || true)"
fi
if [ -z "$IP" ]; then
  IP="SEU_IP"
fi

echo ""
echo "Getfy iniciado via Docker."
if [ "$TLS_ACTIVE" = true ]; then
  echo "Abra: https://${GETFY_DOMAIN}/docker-setup"
  echo "SSL na origem: Caddy + Let's Encrypt. No Cloudflare use SSL/TLS \"Full (strict)\" (recomendado)."
  echo "Se a página não abrir em HTTPS, veja os erros ACME acima ou: sudo sh \"$DOCKER_CHECK_TLS_SH\""
else
  echo "Abra: http://$IP:$HTTP_PORT/docker-setup"
fi
echo ""
echo "Se você adicionou seu usuário ao grupo docker, reabra o SSH para aplicar."
