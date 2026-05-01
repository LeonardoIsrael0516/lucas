# Guia de Gateways de Pagamento

Este documento descreve a arquitetura de gateways da plataforma e como adicionar novos gateways (no core ou via plugins).

## Visão geral

- **GatewayRegistry**: lista todos os gateways disponíveis (core + plugins). Cada gateway tem slug, nome, imagem, métodos aceitos (pix, card, boleto), escopo (national/international), URL de cadastro e driver.
- **Credenciais**: armazenadas na tabela `gateway_credentials` por tenant, criptografadas. Nunca expor valores no frontend.
- **Redundância**: a ordem de tentativa por método (pix, card, boleto) é configurável por tenant no Setting `gateway_order`. No checkout, em caso de falha, o próximo gateway da lista é tentado.
- **Driver**: cada gateway possui uma classe que implementa `App\Gateways\Contracts\GatewayDriver` (testConnection, createPixPayment, etc.).

## Estrutura de pastas e arquivos

```
config/gateways.php              # Definição dos gateways core
app/Gateways/
  GatewayRegistry.php            # Registro e listagem de gateways
  Contracts/GatewayDriver.php    # Interface do driver
  Drivers/
    SpacepagDriver.php           # Driver Spacepag (PIX)
app/Models/GatewayCredential.php
app/Services/PaymentService.php
app/Http/Controllers/
  GatewaysController.php         # Configurações (credenciais, teste, ordem)
  Webhooks/
    SpacepagWebhookController.php
database/migrations/
  xxxx_create_gateway_credentials_table.php
public/images/gateways/          # Imagens dos gateways (ex.: spacepag.png)
routes/web.php                   # Rotas de configuração e webhooks
```

## Como adicionar um gateway no core

1. **Criar o driver** em `app/Gateways/Drivers/` implementando `App\Gateways\Contracts\GatewayDriver`:
   - `testConnection(array $credentials): bool` — testar autenticação.
   - `createPixPayment(array $credentials, float $amount, array $consumer, string $externalId, string $postbackUrl): array` — criar cobrança PIX; retornar `transaction_id`, `qrcode`, `copy_paste` (e opcionalmente `raw`).

2. **Registrar em** `config/gateways.php` no array `gateways`:
   - `slug`: identificador único (ex.: `spacepag`).
   - `name`: nome exibido.
   - `image`: path relativo em `public/` (ex.: `images/gateways/spacepag.png`).
   - `methods`: array com `pix`, `card`, `boleto` conforme o que o gateway suporta.
   - `scope`: `national` ou `international`.
   - `signup_url`: link para o infoprodutor criar conta no gateway.
   - `driver`: classe do driver (FQCN).
   - `credential_keys`: array de `{ key, label, type }` (type: `text` ou `password`) para os campos de credencial.

3. **Adicionar imagem** em `public/images/gateways/{slug}.png` (ou outro formato).

4. **Se houver webhook**: criar controller em `app/Http/Controllers/Webhooks/` e registrar rota pública (ex.: `POST /webhooks/gateways/{slug}`). No handler, localizar o pedido por `gateway_id` (transaction_id), atualizar status e disparar `OrderCompleted` quando pago. Em `PaymentService`, a URL de postback para gateways não-Spacepag pode usar `webhookUrlForGateway($slug)` (que usa a rota nomeada `webhooks.{slug}` ou fallback `/webhooks/gateways/{slug}`).

5. **Atualizar** `config/gateways.php` em `default_order` se quiser que o novo gateway entre na ordem padrão de redundância (ex.: `'pix' => ['spacepag', 'novo_gateway']`).

## Como adicionar um gateway via plugin

1. **No plugin**, criar a classe do driver (pode ficar na pasta do plugin, ex.: `plugins/meu-plugin/Drivers/MeuGatewayDriver.php`) implementando `App\Gateways\Contracts\GatewayDriver`.

2. **No `bootstrap.php` do plugin**, registrar o gateway:
   ```php
   use App\Gateways\GatewayRegistry;

   return function ($app, Dispatcher $events): void {
       GatewayRegistry::register([
           'slug' => 'meu_gateway',
           'name' => 'Meu Gateway',
           'image' => 'plugins/meu-plugin/assets/logo.png', // ou URL
           'methods' => ['pix'],
           'scope' => 'national',
           'signup_url' => 'https://...',
           'driver' => \MeuPlugin\Drivers\MeuGatewayDriver::class,
           'credential_keys' => [
               ['key' => 'api_key', 'label' => 'API Key', 'type' => 'password'],
           ],
       ]);
   };
   ```

3. **Webhook**: o plugin pode registrar sua própria rota (ex.: em `routes.php` do plugin) para um endpoint público e tratar o postback. Informar no hub do gateway a URL completa (ex.: `https://seusite.com/webhooks/gateways/meu_gateway`). O `PaymentService` usa `route('webhooks.' . $gatewaySlug)` se existir, senão `url('/webhooks/gateways/' . $gatewaySlug)`.

4. **Imagem**: colocar no plugin ou usar URL externa no registro.

## Redundância

- A ordem por método é salva no Setting `gateway_order` (por tenant): `{ "pix": ["spacepag", "outro"], "card": [], "boleto": [] }`.
- Na aba Gateways das Configurações, a ordem atual é exibida (e pode ser editada no futuro com UI de reordenação).
- O `PaymentService::createPixPayment` percorre a lista `gateway_order['pix']`, obtém credenciais e driver de cada gateway, tenta criar o PIX; em exceção, tenta o próximo até esgotar.

## Boas práticas

- **Credenciais**: nunca logar nem retornar em JSON/API. Usar `Crypt::encryptString` ao salvar e `getDecryptedCredentials()` apenas no backend quando for chamar o driver.
- **Webhooks**: validar origem quando a documentação do gateway indicar (header, assinatura). Responder com 200 e corpo mínimo para o gateway não reenviar.
- **Erros**: em falha do gateway, logar (sem credenciais) e deixar o PaymentService tentar o próximo da lista. Mensagem ao usuário genérica se todos falharem.
- **API futura**: o `PaymentService` é o ponto único de criação de pagamento; uma futura API de plataforma deve chamá-lo em vez de duplicar lógica.

## Efí (Efí Bank)

- **Métodos**: PIX (cobrança imediata), cartão e boleto (estrutura preparada para implementação futura).
- **Credenciais**: Client ID, Client Secret, Chave PIX, **Identificador de conta (payee_code)** — para pagamento com cartão (tokenização no frontend; obter em: conta Efí > API > Introdução > Identificador de conta) —, ambiente (sandbox/produção) e **certificado P12**.
- **Certificado**: o certificado P12 é enviado por upload nas configurações do gateway (Configurações > Gateways > Efí) e armazenado em `storage/app/gateway_certs/{tenant_id}/efi.p12`. Nunca é exposto no frontend; apenas um indicador "Certificado enviado" é exibido.
- **Webhook PIX**: rota `POST /webhooks/gateways/efi/pix`. Na conta Efí, cadastre a URL de notificação (a Efí pode anexar `/pix` à URL; use `https://seusite.com/webhooks/gateways/efi/pix` diretamente se preferir). Documentação: [Efí – Credenciais e certificado](https://dev.efipay.com.br/docs/api-pix/credenciais).
- **Gateway com certificado**: em `config/gateways.php` use `certificate_key` (ex.: `'certificate_key' => 'certificate'`) e inclua em `credential_keys` um item com `type => 'file'` para o upload.

## Formato do array de registro (gateway)

| Chave            | Tipo   | Obrigatório | Descrição                                      |
|------------------|--------|-------------|------------------------------------------------|
| slug             | string | sim         | Identificador único (ex.: spacepag).           |
| name             | string | sim         | Nome exibido nos cards e no sidebar.           |
| image            | string | não         | Path em public/ ou URL da imagem.              |
| methods          | array  | sim         | Lista: pix, card, boleto.                      |
| scope            | string | não         | national ou international (default: national). |
| signup_url       | string | não         | Link para criar conta no gateway.              |
| driver           | string | sim         | FQCN da classe que implementa GatewayDriver.   |
| credential_keys  | array  | sim         | [{ key, label, type }] para o formulário.     |

## Referência rápida – Spacepag

- **Documentação**: `Docs Gateways/sapcepag.md`
- **Criar conta**: https://hub.spacepag.com.br/auth/jwt/sign-up?ref=4a5d0212320748719ee818cffdb93248
- **Só PIX**. Nacional.
- **Credenciais**: public_key, secret_key. Autenticação: POST https://api.spacepag.com.br/v1/auth. Criar PIX: POST /v1/cob. Webhook: evento `order.paid`, status `paid`.
