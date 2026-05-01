# Getfy Cloud — API pública de status de cobrança (para banner na plataforma instalada)

Esta API permite que a plataforma instalada (ex.: WordPress Cloud / n8n Cloud “original”) consulte se a cobrança daquela instalação está em dia na Getfy Cloud, para exibir um banner de aviso no header quando necessário.

## Visão geral

- A Getfy Cloud gera um **token por instalação** (por `Instance`).
- Esse token é **injetado automaticamente** como variável de ambiente no container da plataforma instalada:
  - `GETFY_CLOUD_INSTALL_TOKEN`
- A plataforma instalada chama um endpoint público no Orchestrator usando esse token.
- A resposta informa se o pagamento está vencido e se está em período de carência.

## Endpoint

**GET** `/v1/public/billing/status`

### Autenticação

Enviar o token da instalação via header:

`Authorization: Bearer <GETFY_CLOUD_INSTALL_TOKEN>`

Sem token (ou token inválido) a API retorna `401`.

### Rate limit

O endpoint tem rate limit (configurado no servidor). Faça cache na plataforma instalada para evitar chamadas excessivas.

## Resposta (200)

```json
{
  "instanceId": "ckv.../cuid",
  "customerId": "ckv.../cuid",
  "fqdn": "subdominio.getfy.app",
  "paymentRequired": false,
  "inGracePeriod": false,
  "cycleDays": 30,
  "graceDays": 3,
  "lastPaidAt": "2026-03-16T12:34:56.789Z",
  "paidThrough": "2026-04-15T12:34:56.789Z",
  "overdueSince": null,
  "serverNow": "2026-03-16T12:35:01.123Z",
  "portalUrl": "https://SEU-FRONTEND/dashboard"
}
```

### Campos

- `paymentRequired`:
  - `true` quando **não existe pagamento** para a instância ou quando ela está **após** `paidThrough`.
- `inGracePeriod`:
  - `true` quando está vencido (`paymentRequired=true`) e ainda dentro de `graceDays`.
- `lastPaidAt`:
  - Data do último `BillingOrder` com status `COMPLETED` associado à instância.
- `paidThrough`:
  - `lastPaidAt + cycleDays`.
- `overdueSince`:
  - Igual a `paidThrough` quando vencido.
- `portalUrl`:
  - Link sugerido para o usuário ir pagar/gerenciar no painel da Getfy Cloud.

## Erros

### 401

```json
{ "error": "unauthorized" }
```

## Como integrar na plataforma instalada

### 1) Ler o token do ambiente

O token fica disponível como:

- `GETFY_CLOUD_INSTALL_TOKEN`

Se estiver vazio/não existir, trate como “não integrado” (não exiba banner ou exiba aviso interno de configuração).

### 2) Chamar a API

Exemplo com `curl`:

```bash
curl -sS "https://orch.getfy.cloud/v1/public/billing/status" \
  -H "Authorization: Bearer $GETFY_CLOUD_INSTALL_TOKEN"
```

Exemplo com `fetch` (Node/JS):

```js
const res = await fetch(`${process.env.ORCH_API_BASE_URL}/v1/public/billing/status`, {
  headers: {
    Authorization: `Bearer ${process.env.GETFY_CLOUD_INSTALL_TOKEN}`,
  },
})

if (res.status === 401) {
  throw new Error('Token inválido ou ausente')
}

const status = await res.json()

if (status.paymentRequired) {
  // exibir banner
  // pode usar status.portalUrl como CTA ("Pagar agora")
}
```

### 3) Cache recomendado

Para evitar excesso de requests:

- Cache de 5 a 15 minutos em memória (ou storage) na plataforma instalada.
- Em caso de erro de rede, reutilize o último status por um curto período (ex.: 30–60 min) para evitar “piscar” o banner.

## Regras de cobrança (ciclo e carência)

O cálculo é:

- `paidThrough = lastPaidAt + BILLING_CYCLE_DAYS`
- `paymentRequired = now > paidThrough` (ou não há `lastPaidAt`)
- `inGracePeriod = paymentRequired && now <= overdueSince + BILLING_GRACE_DAYS`

Variáveis no Orchestrator:

- `BILLING_CYCLE_DAYS` (padrão: `30`)
- `BILLING_GRACE_DAYS` (padrão: `3`)

## Observações operacionais

- Para instalações antigas (já provisionadas antes dessa feature), o token é gerado no próximo provision/update da instância.
- O token é verificado no Orchestrator via **hash SHA-256** (não precisa consultar o token “em claro” para autenticar).
- O token “em claro” é mantido criptografado no banco apenas para permitir reinjeção automática no container.

