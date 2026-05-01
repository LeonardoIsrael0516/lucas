# Comandos para produção — Getfy

Este documento reúne todos os comandos necessários para a aplicação Getfy rodar em produção.

---

## 1. Requisitos

- **PHP** 8.2 ou superior
- **Composer** 2.x
- **Node.js** e **npm** (para build do frontend)
- **MySQL** 8.0 (ou compatível)
- **Redis** (opcional, recomendado para fila, cache e sessão em produção)

---

## 2. Primeira implantação

```bash
# Clone do repositório (ou upload do código)
git clone https://github.com/getfy-opensource/getfy.git
cd getfy

# Dependências PHP
composer install --no-interaction --no-dev --optimize-autoloader

# Ambiente
cp .env.example .env
php artisan key:generate

# Banco de dados: configure DB_* no .env e execute
php artisan migrate --force

# Frontend
npm ci
npm run build

# Link simbólico para storage (se usar public disk para arquivos públicos)
php artisan storage:link

# PWA: gerar chaves VAPID para notificações push do painel (opcional)
php artisan pwa:vapid
# Depois preencha PWA_VAPID_PUBLIC e PWA_VAPID_PRIVATE no .env

# Otimizações para produção
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

**Script rápido (composer):**

```bash
composer run setup
```

Isso executa: `composer install`, cópia de `.env.example` para `.env` (se não existir), `key:generate`, `migrate --force`, `npm install`, `npm run build`. Ajuste o `.env` antes de rodar se precisar de DB e outras variáveis.

---

## 3. Fila (queue)

O sistema usa filas para webhooks, e-mail marketing, processamento de pagamentos e outros jobs. O worker precisa estar rodando em produção.

**Comando recomendado:**

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=0
```

- `--sleep=3`: segundos de espera quando não há jobs
- `--tries=3`: tentativas antes de mover para `queue:failed`
- `--timeout=0`: sem limite de tempo por job (ajuste se necessário)

**Com Redis** (recomendado em produção): defina no `.env`:

```env
QUEUE_CONNECTION=redis
```

**Supervisor (recomendado em servidor Linux):** crie um arquivo `/etc/supervisor/conf.d/getfy-worker.conf`:

```ini
[program:getfy-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /caminho/para/getfy/artisan queue:work --sleep=3 --tries=3 --timeout=0
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/caminho/para/getfy/storage/logs/worker.log
```

Depois: `sudo supervisorctl reread`, `sudo supervisorctl update`, `sudo supervisorctl start getfy-worker:*`.

---

## 4. Cron / Schedule

O Laravel Schedule executa tarefas periódicas (lembretes de assinatura, carrinho abandonado, campanhas de e-mail, heartbeats). É necessário chamar `schedule:run` **uma vez por minuto**.

**Crontab (Linux):**

```bash
* * * * * cd /caminho/para/getfy && php artisan schedule:run >> /dev/null 2>&1
```

Substitua `/caminho/para/getfy` pelo diretório real do projeto.

**Alternativa via URL (quando o cron do servidor não está disponível):**

1. Defina no `.env` um token secreto:

   ```env
   CRON_SECRET=seu_token_secreto_aqui
   ```

2. Chame a URL uma vez por minuto (serviço externo, cron em outro servidor, etc.):

   ```
   GET https://seu-dominio.com/cron?token=seu_token_secreto_aqui
   ```

A URL é exibida em **E-mail Marketing > Configuração** quando `CRON_SECRET` está definido.

---

## 5. O que o schedule executa

| Frequência   | Tarefa |
|------------|--------|
| Diário 09:00 | Lembretes de assinatura (`SendSubscriptionRemindersJob`) |
| A cada 10 minutos | Webhooks de carrinho abandonado (`checkout:fire-abandoned-cart-webhooks`, após 10 min da interação no formulário) |
| A cada minuto | Processamento de campanhas de e-mail (`email-campaign:process`) |
| A cada minuto | Heartbeat do schedule (`schedule:heartbeat`) — indica que o cron está ativo |
| A cada minuto | Heartbeat da fila (`QueueHeartbeatJob`) — indica que o worker está ativo |

Sem o cron/schedule, campanhas de e-mail agendadas e lembretes de assinatura não rodam. O painel em E-mail Marketing mostra se o schedule e a fila estão OK (com base nesses heartbeats).

### Webhooks de integração (pedido pago, pendente, etc.)

Os envios para as URLs em **Integrações > Webhooks** usam o job `DispatchWebhookJob`.

- **`pedido_pago` (`OrderCompleted`) e `pedido_pendente` (`OrderPending`)**: por padrão o POST é feito **no mesmo request** que processa o evento (sem enfileirar), para não atrasar quando a fila está cheia. Isso é controlado por `config/getfy.php` → `webhooks.sync_critical_payment_events` (variável `.env`: `GETFY_WEBHOOKS_SYNC_CRITICAL_PAYMENT`, default `true`).
- **Demais eventos** (PIX gerado, carrinho abandonado, assinatura, etc.): se a fila estiver `redis`/`database` e o cache `queue_heartbeat` existir e for **recente** (últimos 3 minutos), o envio vai para a **fila**; caso contrário cai em modo síncrono para não “sumir” quando não há worker.
- O `queue_heartbeat` é atualizado pelo job `QueueHeartbeatJob` (precisa de **worker** rodando `php artisan queue:work`). Heartbeat recente **não** força atraso nos eventos críticos acima; só afeta os demais.

Variáveis opcionais no `.env`:

- `GETFY_WEBHOOKS_SYNC_CRITICAL_PAYMENT=true|false` — desativar só se souber o impacto (pedido pago/pendente passam a depender da fila como os outros).
- `GETFY_WEBHOOKS_DISPATCH_ALL_SYNC=true` — todos os webhooks de integração síncronos (pode alongar requests; uso avançado).

**Diagnóstico de atraso:** em **Integrações > Webhooks > logs**, compare o horário do primeiro POST com o horário do pedido. Atraso grande com fila ativa costuma ser backlog do worker ou muitos jobs à frente; para eventos críticos isso deixa de aplicar com o padrão atual.

---

## 6. PWA (notificações push)

Para notificações push no painel (vendas, PIX gerado, etc.):

1. Gere as chaves VAPID:

   ```bash
   php artisan pwa:vapid
   ```

2. O comando escreve no `.env` as linhas `PWA_VAPID_PUBLIC` e `PWA_VAPID_PRIVATE`. Se não escrever, gere em https://vapidkeys.com/ e adicione manualmente.

3. Reinicie o PHP ou limpe o cache de config:

   ```bash
   php artisan config:clear
   ```

---

## 7. Otimização para produção

```bash
# Cache de configuração, rotas e views
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Composer sem dependências de desenvolvimento
composer install --no-dev --optimize-autoloader
```

No `.env` use:

- `APP_ENV=production`
- `APP_DEBUG=false`

---

## 8. Atualização da aplicação

**Pelo painel:** Configurações > Atualização. O sistema executa: `git pull`, `composer install --no-dev`, `npm ci && npm run build`, `migrate --force`, `config:cache`. Requer repositório Git e (opcional) `GETFY_PHP_PATH` no `.env` se o PHP não estiver no PATH do servidor web.

**Manual:**

```bash
cd /caminho/para/getfy
git pull origin main
composer install --no-interaction --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache
```

Reinicie o worker da fila e o PHP (ou servidor web) após a atualização.

---

## 9. Comandos úteis

| Comando | Descrição |
|---------|-----------|
| `php artisan migrate:status` | Lista status das migrations |
| `php artisan queue:failed` | Lista jobs que falharam |
| `php artisan queue:retry all` | Reprocessa todos os jobs falhos |
| `php artisan queue:retry <id>` | Reprocessa um job falho pelo ID |
| `php artisan config:clear` | Limpa cache de configuração |
| `php artisan route:clear` | Limpa cache de rotas |
| `php artisan view:clear` | Limpa cache de views |
| `php artisan cache:clear` | Limpa cache da aplicação |
| `php artisan schedule:list` | Lista tarefas agendadas |

---

## Resumo mínimo para produção

1. **Servidor web** apontando para a pasta `public` e PHP 8.2+.
2. **Worker da fila** rodando: `php artisan queue:work --sleep=3 --tries=3 --timeout=0`.
3. **Cron** executando a cada minuto: `cd /caminho/getfy && php artisan schedule:run`.
4. **Variáveis de ambiente** configuradas no `.env` (DB, APP_KEY, fila/cache/sessão, CRON_SECRET e PWA_VAPID se usar).

Com isso, checkout, webhooks, e-mail marketing, assinaturas e notificações push do painel funcionam em produção.
