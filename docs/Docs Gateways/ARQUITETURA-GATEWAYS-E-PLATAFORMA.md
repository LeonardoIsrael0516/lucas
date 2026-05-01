# Plataforma e arquitetura de gateways de pagamento

Este documento descreve a plataforma (Laravel, checkout, pedidos, assinaturas) e **como os gateways de pagamento funcionam**, para facilitar a **integração de um novo gateway**.

---

## 1. Visão geral da plataforma

- **Stack:** Laravel (PHP), frontend com Inertia + Vue.
- **Multi-tenant:** pedidos e credenciais são por `tenant_id`.
- **Checkout:** por slug (oferta, plano de assinatura ou produto). Suporta PIX, cartão, boleto e PIX automático (recorrente).
- **Fluxo:** usuário escolhe método → cria `Order` (status `pending`) → gateway gera cobrança → `gateway` e `gateway_id` gravados no pedido → webhook do gateway notifica → job `ProcessPaymentWebhook` atualiza pedido e dispara eventos (ex.: `OrderCompleted`).

### Modelos principais

| Modelo | Uso |
|--------|-----|
| `Order` | Pedido: `tenant_id`, `user_id`, `product_id`, `status`, `amount`, **`gateway`**, **`gateway_id`**, `metadata`, `subscription_plan_id`, etc. |
| `GatewayCredential` | Credenciais criptografadas por tenant e `gateway_slug`. |
| `Product` / `ProductOffer` / `SubscriptionPlan` | Definem valor, moeda e, opcionalmente, qual gateway/método usar. |
| `Subscription` | Assinatura ativa (pix_auto/recorrente): criada quando o primeiro pagamento é confirmado. |

---

## 2. Arquitetura de gateways

### 2.1 Contrato (interface)

Todo gateway implementa **`App\Gateways\Contracts\GatewayDriver`**:

```php
interface GatewayDriver
{
    public function testConnection(array $credentials): bool;

    public function createPixPayment(
        array $credentials,
        float $amount,
        array $consumer,      // name, document, email
        string $externalId,   // order id
        string $postbackUrl   // webhook URL
    ): array;  // transaction_id, qrcode?, copy_paste?, raw?

    public function getTransactionStatus(string $transactionId, array $credentials): ?string;
    // Retorno: 'paid' | 'pending' | 'cancelled' | null

    public function createCardPayment(...): array;   // opcional; pode throw
    public function createBoletoPayment(...): array;  // opcional; pode throw
}
```

- **PIX:** obrigatório retornar `transaction_id` e, quando fizer sentido, `qrcode` (base64) e `copy_paste` (código EMV).
- **Cartão/Boleto:** só quem suporta implementa; os demais podem lançar exceção.

### 2.2 Registro de gateways

- **Config:** `config/gateways.php` → chave `gateways` (array slug → definição).
- **Extensões:** `GatewayRegistry::register($gateway)` (ex.: plugins).
- **Listagem:** `GatewayRegistry::all()` e `GatewayRegistry::get($slug)`.
- **Driver:** `GatewayRegistry::driver($slug)` retorna a instância do `GatewayDriver`.

Cada definição no config contém:

- `slug`, `name`, `image`, `signup_url`
- **`methods`:** `['pix']`, `['pix','card']`, `['pix','pix_auto']`, etc.
- **`driver`:** classe que implementa `GatewayDriver`
- **`credential_keys`:** lista de `['key' => '...', 'label' => '...', 'type' => 'text'|'password'|'boolean'|'file']`
- Opcional: `certificate_key`, `country`, `country_flag`, `scope`, etc.

Ordem padrão por método (redundância) está em `config/gateways.php` → `default_order` (ex.: `pix` => `['spacepag','efi','mercadopago','pushinpay']`). O tenant pode sobrescrever via `Setting::get('gateway_order', ...)` e o produto pode fixar um gateway em `checkout_config['payment_gateways']`.

### 2.3 Fluxo de criação de pagamento (ex.: PIX)

1. **CheckoutController** (ou fluxo de checkout) chama `PaymentService::createPixPayment($order, $product, $consumer)`.
2. **PaymentService** obtém a ordem de gateways para o método (`getGatewayOrderForMethod`) e, para cada slug:
   - Busca `GatewayCredential` do tenant para esse slug (conectada).
   - Obtém o driver: `GatewayRegistry::driver($gatewaySlug)`.
   - Monta a URL de webhook: `route('webhooks.' . $gatewaySlug)` ou `/webhooks/gateways/{slug}`.
   - Chama `$driver->createPixPayment(...)`.
   - Em sucesso: atualiza `Order` com `gateway` e `gateway_id` e retorna os dados (qrcode, copy_paste, etc.).
3. Se nenhum gateway conseguir, lança exceção.

Para **cartão** e **boleto** o fluxo é análogo, usando `createCardPayment` e `createBoletoPayment`.

### 2.4 Webhooks

- Cada gateway tem uma rota dedicada, por exemplo:
  - `POST /webhooks/gateways/pushinpay` → `PushinPayWebhookController::handle`
  - `POST /webhooks/gateways/efi/pix` → `EfiWebhookController::pix`
- O controller do webhook:
  - Lê o ID da transação do payload.
  - Localiza o `Order` por `gateway` + `gateway_id`.
  - (Opcional) Valida assinatura usando credenciais do tenant (ex.: `webhook_secret`).
  - Mapeia o status do gateway para um evento interno (`order.paid`, `order.cancelled`, etc.).
  - Despacha o job **`ProcessPaymentWebhook`** com: `gatewaySlug`, `transactionId`, `event`, `status`, `payload`.
- Resposta ao gateway: em geral `200` com `{ "received": true }` para evitar retentativas desnecessárias.

### 2.5 Job ProcessPaymentWebhook

- Busca o pedido por `gateway` e `gateway_id`.
- **order.paid:** antes de marcar como pago, chama `reconfirmPaidWithGateway()` (usa `getTransactionStatus` do driver). Depois: `Order::status = 'completed'`, vincula usuário ao produto, cria `Subscription` se for plano e primeiro pagamento (ou renova período se for renewal). Dispara `OrderCompleted` (e eventualmente `SubscriptionCreated` / `SubscriptionRenewed`).
- **order.cancelled:** se pedido ainda estiver `pending`, marca `cancelled` e dispara `OrderCancelled`.
- **order.rejected / payment.rejected:** marca `rejected`, dispara `OrderRejected`.
- **order.refunded / payment.refunded:** marca `refunded`, dispara `OrderRefunded`.

Para **pix_auto** (recorrente), o pedido pode ter em `metadata`:
- `efi_pix_auto_id_rec` (Efí) ou `pushinpay_subscription_id` (Pushin Pay), usados para criar/renovar `Subscription` e, no caso da Efí, para gerar a próxima cobrança recorrente.

---

## 3. Gateways já integrados (resumo)

| Gateway    | Métodos     | Driver / Observações |
|-----------|-------------|----------------------|
| Spacepag  | pix | `SpacepagDriver`; split opcional via credenciais `split_username` (com @) e `split_percentage` (0,1–99,9). |
| Sapcepag  | pix, card, boleto | `SapcepagDriver` |
| Efí       | pix, card, boleto, pix_auto | `EfiDriver`; PIX recorrente via `EfiPixRecorrenteService` |
| Stripe    | card        | `StripeDriver` |
| Mercado Pago | pix, card, boleto | `MercadoPagoDriver` |
| **Pushin Pay** | pix, pix_auto | `PushinPayDriver`; PIX recorrente via `PushinPayPixRecorrenteService` |

Documentação da API Pushin Pay está em **`Docs Gateways/pushinpay.md`** (PIX avulso, consulta transação, PIX recorrente, cancelamento, webhook com `id`, `value`, `status`: created | paid | canceled).

---

## 4. Como integrar um novo gateway

Siga estes passos para um gateway genérico (ex.: “MeuGateway”) com PIX.

### 4.1 Backend – Driver

1. Criar a classe do driver em `app/Gateways/MeuGateway/MeuGatewayDriver.php` implementando `GatewayDriver`:
   - `testConnection($credentials)`: chamada leve à API (ex.: listar transações ou health).
   - `createPixPayment($credentials, $amount, $consumer, $externalId, $postbackUrl)`: criar cobrança PIX; valor em reais (a API pode exigir centavos – converter). Retornar `['transaction_id' => ..., 'qrcode' => ..., 'copy_paste' => ...]`.
   - `getTransactionStatus($transactionId, $credentials)`: retornar `'paid'`, `'pending'`, `'cancelled'` ou `null`.
   - `createCardPayment` / `createBoletoPayment`: implementar se o gateway suportar; senão, `throw new \RuntimeException('...');`.
2. Tratar sandbox/produção conforme as credenciais (ex.: `$credentials['sandbox']` e URLs diferentes).
3. Logar falhas (status HTTP, mensagem) e lançar `\RuntimeException` com mensagem amigável em caso de erro.

### 4.2 Backend – Config

4. Em **`config/gateways.php`**:
   - Adicionar entrada em `gateways`, por exemplo:
     ```php
     'meugateway' => [
         'slug' => 'meugateway',
         'name' => 'Meu Gateway',
         'image' => 'images/gateways/meugateway.png',
         'methods' => ['pix'],  // ou ['pix','card']
         'scope' => 'national',
         'country' => 'br',
         'country_name' => 'Brasil',
         'country_flag' => 'brasil.png',
         'signup_url' => 'https://...',
         'driver' => \App\Gateways\MeuGateway\MeuGatewayDriver::class,
         'credential_keys' => [
             ['key' => 'api_token', 'label' => 'API Token', 'type' => 'password'],
             ['key' => 'sandbox', 'label' => 'Usar sandbox', 'type' => 'boolean'],
             // Opcional: ['key' => 'webhook_secret', 'label' => 'Webhook Secret', 'type' => 'password'],
         ],
     ],
     ```
   - Incluir o slug em `default_order` no método desejado, por exemplo em `'pix' => [..., 'meugateway']`.

### 4.3 Backend – Webhook

5. Criar **`App\Http\Controllers\Webhooks\MeuGatewayWebhookController`**:
   - Receber o POST do gateway.
   - Extrair ID da transação do body.
   - Buscar `Order::where('gateway', 'meugateway')->where('gateway_id', $transactionId)`.
   - Se não achar pedido, responder `200` com `['received' => true]`.
   - (Recomendado) Validar assinatura com credencial do tenant (ex.: header ou campo no body), usando `GatewayCredential::forTenant($order->tenant_id)->where('gateway_slug', 'meugateway')...` e `getDecryptedCredentials()`.
   - Mapear status da API para `order.paid` / `order.cancelled` / etc.
   - `ProcessPaymentWebhook::dispatch('meugateway', $transactionId, $event, $mappedStatus, $request->all())`.
   - Retornar `response()->json(['received' => true])`.

6. Em **`routes/web.php`** (no mesmo grupo das outras rotas de webhook):
   ```php
   Route::post('/webhooks/gateways/meugateway', [\App\Http\Controllers\Webhooks\MeuGatewayWebhookController::class, 'handle'])->name('webhooks.meugateway');
   ```

### 4.4 PaymentService – URL de webhook

7. O **PaymentService** já monta a URL por convenção: `webhookUrlForGateway($gatewaySlug)` usa `route('webhooks.' . $slug)` ou `url('/webhooks/gateways/' . $slug)`. Nada mais é necessário se a rota estiver nomeada como `webhooks.meugateway`. Para Efí/Spacepag há exceções (rotas específicas); só replicar esse padrão se o novo gateway tiver mais de uma URL (ex.: PIX vs boleto).

### 4.5 Frontend – Checkout (método padrão)

8. Em **`resources/js/components/checkout/gateways/registry.js`**:
   - Adicionar no objeto `gatewayMethodComponents` a entrada do novo slug, por exemplo:
     ```js
     meugateway: {
         pix: DefaultMethodCard,   // usa o card genérico (só exibe “PIX”)
         card: DefaultMethodCard,
         boleto: DefaultMethodCard,
     },
     ```
   - Se precisar de UI específica (ex.: campos extras), criar componente em `gateways/meugateway/Pix.vue` (e outros) e referenciar aqui.

### 4.6 PIX automático (recorrente) – opcional

9. Se o gateway tiver **PIX recorrente/assinatura** (como Pushin Pay e Efí):
   - Criar um service análogo a `PushinPayPixRecorrenteService` / `EfiPixRecorrenteService` que chame a API de assinatura/recorrência.
   - No **CheckoutController**, no bloco `if ($paymentMethod === 'pix_auto')`, após `getFirstAvailableGatewayForMethod(..., 'pix_auto', ...)`:
     - Se `$gatewaySlug === 'meugateway'`, buscar credenciais, criar pedido, chamar o service de recorrente, gravar `gateway`, `gateway_id` e em `metadata` o ID da assinatura no gateway (ex.: `meugateway_subscription_id`).
   - No **ProcessPaymentWebhook**, ao criar `Subscription`, ler desse `metadata` e preencher `gateway_subscription_id`; se o gateway exigir cobrança manual da próxima parcela (como Efí), implementar lógica semelhante a `createEfiPixAutoCobrForNextPeriod` para o novo gateway.
   - Incluir `pix_auto` em `methods` do gateway em `config/gateways.php` e em `default_order['pix_auto']`.

### 4.7 Imagem e painel

10. Adicionar imagem do gateway (ex.: `public/images/gateways/meugateway.png`) para exibição no painel de integrações.
11. No painel (Integrações), a listagem usa `GatewayRegistry::all()` e o formulário usa `credential_keys`; ao salvar, `GatewaysController::update` grava em `GatewayCredential` (criptografado). Nenhuma alteração extra é necessária se o novo gateway estiver em `config/gateways.php`.

---

## 5. Resumo rápido para novo gateway (checklist)

- [ ] Criar `App\Gateways\MeuGateway\MeuGatewayDriver` implementando `GatewayDriver` (PIX + opcional cartão/boleto).
- [ ] Registrar em `config/gateways.php` em `gateways` e em `default_order` para o(s) método(s).
- [ ] Criar `MeuGatewayWebhookController`, mapear status e despachar `ProcessPaymentWebhook`.
- [ ] Registrar rota `POST /webhooks/gateways/meugateway` com nome `webhooks.meugateway`.
- [ ] Registrar componente no frontend em `gateways/registry.js` (DefaultMethodCard ou componente custom).
- [ ] Se tiver PIX recorrente: service de assinatura + trecho no CheckoutController (pix_auto) + metadata e `ProcessPaymentWebhook` para Subscription.

Com isso, a plataforma passa a usar o novo gateway na ordem configurada (tenant/produto) e a processar notificações via webhook até concluir ou cancelar o pedido e, quando aplicável, criar/renovar assinatura.
