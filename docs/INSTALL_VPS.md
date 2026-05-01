# Instalação em VPS (1 comando)

Executa:

- Instala Docker + Compose (plugin) em Ubuntu/Debian via apt
- Clona o repositório do Getfy
- Sobe o stack com Docker Compose

## Comando (HTTP na origem)

```bash
bash -c "$(curl -fsSL https://raw.githubusercontent.com/getfy-opensource/getfy/main/install.sh)"
```

## HTTPS automático (Let's Encrypt + Cloudflare Full)

Em **SSH interativo**, o `install.sh` pergunta no **início** (antes de instalar Docker / clonar) o **domínio público** e o **e-mail ACME**. Se não quiser HTTPS automático na origem, deixe o domínio em branco (Enter) e continuará só com HTTP.

Ou passe tudo **por variáveis** (recomendado em scripts ou `curl ... | bash`, onde não há terminal interativo):

```bash
GETFY_DOMAIN=loja.seudominio.com \
GETFY_ACME_EMAIL=voce@seudominio.com \
bash -c "$(curl -fsSL https://raw.githubusercontent.com/getfy-opensource/getfy/main/install.sh)"
```

Também pode definir `GETFY_TLS=1` explicitamente; se `GETFY_DOMAIN` estiver definido, o modo HTTPS é ativado automaticamente.

### Cloudflare

1. Crie um registo **A** (ou **AAAA**) para o seu hostname apontando para o IP público da VPS (proxy “laranja” pode ficar ativado).
2. Em **SSL/TLS**, escolha **Full** ou **Full (strict)**. Com Let's Encrypt na origem, **Full (strict)** é o ideal.
3. Na firewall da VPS, permita **TCP 80** e **TCP 443** na origem (porta 80 costuma ser necessária para renovação HTTP-01 do Let's Encrypt).

### Variáveis opcionais (HTTP)

```bash
GETFY_DIR=/opt/getfy \
GETFY_BRANCH=main \
GETFY_HTTP_PORT=80 \
bash -c "$(curl -fsSL https://raw.githubusercontent.com/getfy-opensource/getfy/main/install.sh)"
```

Com HTTPS automático, as portas **80** e **443** na origem são fixas (requisito típico do ACME e do modo Full).

### Atualização (`update.sh`)

O script grava o perfil do Compose em `.docker/compose-profile` e `GETFY_COMPOSE_PROFILE` em `.docker/stack.env`. Assim, **`update.sh`** continua a usar o mesmo stack (com ou sem Caddy) sem precisar de flags.

Instalações antigas só com **`update-caddy.sh`** sem esse marcador: rode uma vez `update-caddy.sh` ou defina `GETFY_COMPOSE_PROFILE=caddy` em `.docker/stack.env`.

### Se o HTTP-01 falhar (desafio Let's Encrypt)

Em cenários raros (bloqueio da porta 80 na origem, políticas muito restritivas), o desafio **HTTP-01** pode falhar. Nesses casos use **DNS-01** (por exemplo módulo DNS do Caddy ou plugin `certbot-dns-cloudflare` com um token de API Cloudflare com permissão DNS). Consulte a documentação do Caddy ou do Certbot para o seu caso.

## Depois de rodar

- Com HTTPS: `https://SEU_DOMINIO/docker-setup`
- Só HTTP: `http://SEU_IP:PORTA/docker-setup`

Em seguida, o fluxo segue para o primeiro administrador conforme o README.

## Stack sem Redis

O instalador `install-no-redis.sh` **não** suporta o modo HTTPS automático descrito acima (deteta `docker-compose.no-redis.yml` e aborta). Para HTTPS automático use o `install.sh` padrão (com Redis).
