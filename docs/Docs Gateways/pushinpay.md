https://app.theneo.io/pushinpay/pix/pix

Pix
Endpoints para realizar cobranças via PIX.

POST /pix/cashIn → Gera uma cobrança PIX, retornando um código de pagamento e um QR Code para que o cliente possa efetuar o pagamento.
GET /transaction/{id}→ Utilizado para consultar o STATUS de uma transação PIX.
GET /cashOut→ Realiza saque via API.
Exemplo da resposta do Webhook:
A resposta será enviada apenas se o campo webhook_url estiver configurado nos endpoints informados.

JSON
{
"id" = string,
"value" = number,
"status" = "created" | "paid" | "canceled",
"end_to_end_id" = string,
}


Criar PIX
Esse endpoint cria um código PIX para pagamento.

Pontos de atenção

Tenha sua conta CRIADA e Aprovada.
Caso não tenha conta realizar no link https://app.pushinpay.com.br/register
Para utilização do ambiente SANDBOX fazer o cadastro primeiramente no ambiente de produção (acima) e depois no suporte solicitar a liberação do ambiente SANDBOX;
Valores sempre em CENTAVOS.
Valor mínimo de 50 centavos;
Percentual máximo de 50% para SPLIT entre contas;
Checar o limite de valor máximo em sua conta;
Caso não tenha um servidor para receber as notificações da transação não preencha o campo webhook_url;
Obrigatoriedade de Aviso sobre o Papel da PUSHIN PAY

Item 4.10 do nosso Termos de Uso https://pushinpay.com.br/termos-de-uso;
É de responsabilidade do usuário da plataforma PUSHIN PAY, titular da conta, informar de maneira clara, destacada e acessível em seus canais de venda (sites, redes sociais, aplicativos, plataformas, entre outros), que:
“A PUSHIN PAY atua exclusivamente como processadora de pagamentos e não possui qualquer responsabilidade pela entrega, suporte, conteúdo, qualidade ou cumprimento das obrigações relacionadas aos produtos ou serviços oferecidos pelo vendedor.”
Esse aviso deve constar no momento da oferta e antes da finalização do pagamento, preferencialmente na página de checkout, nos termos de compra e/ou nas comunicações automáticas relacionadas à transação.
O não cumprimento pode gerar penalizações e até bloqueio da conta;
Exemplo de Resposta - Endpoint
JSON
{
  "id": "9c29870c-9f69-4bb6-90d3-2dce9453bb45",
  "qr_code": "00020101021226770014BR.GOV.BCB.PIX2555api...",
  "status": "created",
  "value": 35,
  "webhook_url": "http://teste.com",
  "qr_code_base64": "data:image/png;base64,iVBORw0KGgoAA.....",
  "webhook": null,
  "split_rules": [],
  "end_to_end_id": null,
  "payer_name": null,
  "payer_national_registration": null
}

📝 Descrição dos Campos

Campo

Tipo

Descrição

id

string

Identificador único da transação gerada. Salve a mesma para consultar o status da mesma.

qr_code

string

Código PIX completo no padrão EMV para ser copiado e pago manualmente.

status

string

Status atual da transação (created | paid | expired).

value

integer

Valor da cobrança em centavos de reais.

webhook_url

string

URL informada para receber notificações de pagamento.

qr_code_base64

string

Imagem do QR Code no formato base64, ideal para exibição.

webhook

string/null

Retorno interno do processamento da notificação enviada, se houver.

split_rules

array

Lista com as regras de divisão de valores (caso existam splits configurados).

end_to_end_id

string/null

Código identificador do PIX gerado pelo Banco Central (aparece após o pagamento).

payer_name

string/null

Nome do pagador, retornado após o pagamento.

payer_national_registration

string/null

CPF ou CNPJ do pagador, retornado após o pagamento.

Webhook de retorno
Ao adicionar o campo webhook_url na criação do qrcode pix, quando o status for alterados e caso falhe a tentativa nós tentaremos enviar 3x, e caso as 3x falhe, via painel administrativo será possível retomar os envios do mesmo. Também é possível adicionar um header customizado que iremos enviar para você em todos os webhooks, essa configuração está disponível em seu menu de configurações de nosso painel

Não recomendamos a pratica de scrap, por isso atente-se a usar os nossos webhooks para receber alterações de status

Erros de Limite e Validação

Valor acima do limite permitido: Quando o valor enviado para geração do QR Code PIX ultrapassa o limite máximo configurado na conta , será retornada a mensagem informando o valor máximo permitido ;
Valor do split maior que o valor total: Se o valor definido para o split for maior do que o valor total da transação, será retornado um erro indicando que o valor da transação não pode ser menor que o valor do split.
Split + taxa maior que o valor total: Quando a soma do valor do split com a taxa de transação for maior que o valor total da transação, o sistema retorna uma mensagem indicando que isso não é permitido.
Conta de split não encontrada: Caso o account_id informado em algum dos splits não corresponda a uma conta válida no banco de dados, será exibida uma mensagem de erro informando que a conta não foi encontrada.
Valor total dos splits excede o valor da transação: Se a soma dos valores dos splits (incluindo a taxa) for maior que o valor total da transação, um erro será retornado informando que a soma não pode exceder o valor da transação.
Splits do token inválidos: A mesma validação anterior se aplica ao caso em que os splits vêm de um token usado na geração da transação. Se os valores forem inconsistentes, o erro indicará que a soma dos splits vinculados ao token não pode exceder o valor da transação.
Header Parameters
Authorization
string
Required
Colocar no formato Bearer TOKEN

Accept
string
Required
application/json

Content-Type
string
Required
application/json

Body Parameters
value
number
Required
Adicione o valor em centavos. O mínimo deve ser 50 centavos

webhook_url
string
Caso tenha um servidor para receber as informações de pagamento ou estorno informe aqui sua URL

split_rules
array
Utilizado para realizar SPLIT para várias contas já cadastradas na PUSHINPAY { "value": 50, "account_id": "9C3XXXXX3A043" }

Response
200
Object
{ "id": "9e6e0...", "qr_code": "000201...", "status": "created" | "paid" | "canceled", "value": 50, "webhook_url": "https://seu-site.com", "qr_code_base64": "data:image/png;base64,iVBOR...", "webhook": {}, "split_rules": [], "end_to_end_id": {}, "payer_name": {}, "payer_national_registration": {} }
400
Object
Bad Request -- Composição do request inválido
401
Object
Unauthorized -- Chave TOKEN inválida
403
Object
Forbidden -- Apenas administradores
404
Object
Not Found -- Pedido não existe
405
Object
Method Not Allowed -- Método não permitido
406
Object
Not Acceptable -- Formato JSON inválido
410
Object
Gone -- Essa requisição não existe mais
418
Object
I'm a teapot.
422
Object
{ "message": "O campo value deve ser no mínimo 50.", "errors": { "value": [ "O campo value deve ser no mínimo 50." ] } }
429
Object
Too Many Requests -- Muitas requisições em um curto espaço de tempo
500
Object
Internal Server Error -- Favor tente mais tarde
503
Object
Service Unavailable -- Estamos temporariamente inativos, favor aguardar.
Was this section helpful?
Yes
No

Previous

Pix

Next

Consultar PIX




Base URL

Produção:

https://api.pushinpay.com.br/api

SandBox (Homolog):

https://api-sandbox.pushinpay.com.br/api

Language Box

cURL
Ruby
Ruby
Python
Python
PHP
PHP
Java
Java
Node.js
Node.js
Go
Go
.NET
.NET
POST

/pix/cashIn

PHP


<?php
$client = new Client();
$headers = [
  'Authorization' => 'Bearer',
  'Accept' => 'application/json',
  'Content-Type' => 'application/json'
];
$body = '{
  "value": 51,
  "webhook_url": "https://seu-site.com",
  "split_rules": []
}';
$request = new Request('POST', 'https://api.pushinpay.com.br/api/pix/cashIn', $headers, $body);
$res = $client->sendAsync($request)->wait();
echo $res->getBody();
Response

200
400
401
403
404
405
406
410
418
422
429
500
503

{
  "id": "9e6e0...",
  "qr_code": "000201...",
  "status": "created" | "paid" | "canceled",
  "value": 50,
  "webhook_url": "https://seu-site.com",
  "qr_code_base64": "data:image/png;base64,iVBOR...",
  "webhook": {},
  "split_rules": [],
  "end_to_end_id": {},
  "payer_name": {},
  "payer_national_registration": {}



  Consultar PIX
Esse endpoint para consultar o STATUS de uma transação PIX.
Pontos de atenção
Recomendamos que seja feita apenas a consulta quando o cliente FINAL identifique por alguma ação que foi pago.
Consultas diretas são autorizadas a cada 1 minuto. Caso seja feito requisições abaixo desse tempo sua conta poderá ser bloqueada;
O retorno do ENDPOINT é igual ao de criar PIX, favor verificar.
Consultar PIX: esta faltado o Response 200 para ok e 404 se nao encontrado e retornado um array vazio
Header Parameters
Authorization
string
Required
Colocar no formato Bearer TOKEN

Accept
string
Required
application/json

Content-Type
string
Required
application/json

Path Parameters
id
string
Response
200
Object
{ "id": "9FF86...", "status": "created" | "paid" | "canceled", "value": "1333", "description": "Pagamento PIX", "payment_type": "pix", "created_at": "2025-09-26T13:15:48.027000Z", "updated_at": "2025-09-26T13:15:48.027000Z", "webhook_url": null, "split_rules": [], "fee": null, "total": null, "end_to_end_id": null, "payer_name": null, "payer_national_registration": null, "webhook": null, "emails": null, "pix_details": { "id": "9FF868...", "expiration_date": "2081-06-23 02:31:34.997", "emv": "00020126850014br.gov.bcb.pix2563pix.pushinpay.com.br/qr/v3/at/b08a3a09-311a-4665-bfaf-8bb2cc5d75a5PUSHIN_PAY_LTDA6007LIMEIRA62070503***63046895", "created_at": "2025-09-26T13:15:48.020000Z", "updated_at": "2025-09-26T13:15:48.020000Z" }, "transaction_product": [] }
404
Object
se nao encontrado e retornado um []
Was this section helpful?
Yes
No

Previous

Criar PIX

Next

Saque PIX




Base URL

Produção:

https://api.pushinpay.com.br/api

SandBox (Homolog):

https://api-sandbox.pushinpay.com.br/api

Language Box

cURL
Ruby
Ruby
Python
Python
PHP
PHP
Java
Java
Node.js
Node.js
Go
Go
.NET
.NET
GET

/transactions/{id}

PHP


<?php
$client = new Client();
$headers = [
  'Authorization' => 'Bearer',
  'Accept' => 'application/json',
  'Content-Type' => 'application/json'
];
$request = new Request('GET', 'https://api.pushinpay.com.br/api/transactions/{id}', $headers);
$res = $client->sendAsync($request)->wait();
echo $res->getBody();
Response

200
404
{
    "id": "9FF86...",
    "status": "created" | "paid" | "canceled",
    "value": "1333",
    "description": "Pagamento PIX",
    "payment_type": "pix",
    "created_at": "2025-09-26T13:15:48.027000Z",
    "updated_at": "2025-09-26T13:15:48.027000Z",
    "webhook_url": null,
    "split_rules": [],
    "fee": null,
    "total": null,
    "end_to_end_id": null,
    "payer_name": null,
    "payer_national_registration": null,
    "webhook": null,
    "emails": null,
    "pix_details": {
        "id": "9FF868...",




        Criar Pix Recorrente
Esse endpoint cria uma cobrança Pix recorrente para pagamento.

Exemplo de Resposta - Endpoint
JSON
{
  "id": "a10df814-5f5d-4a7f-8277-e7d6906faac9",
    "qr_code": "00020101021226810014br.gov.bcb.pix2559qr.woovi.com/qr/v2/cob/7fcb1fca-dcc2-421e-9b8a-6e9a0340dd185204000053039865802BR5909PushinPay6008CAMPINAS62070503***80810014br.gov.bcb.pix2559qr.woovi.com/qr/v2/rec/2345c1b4-1bca-4a…",
    "status": "created",
    "value": 200,
    "webhook_url": "https://oriented-lamprey-inviting.ngrok-free.app/api",
    "qr_code_base64": "Ry95NfG5XD9Vg+/pN8Sls3/wEMRlrpJjzkt5Ge0qCDqCI7Xp/atxTMkSjTp3TZC+jxS821cpl4KCxBgTUTqRiK4uBA+rOruLjiPDgPU3Enn0/Py4wvaA0IrnuHpDMfE/JEguMTd5K+xwkbfpJ9IzyjYftLCaQCDW0x+IdCmu6TRo6inDu9g/gnp1S1YZ9…",
    "webhook": null,
    "split_rules": [],
    "end_to_end_id": null,
    "payer_name": "João Silva",
    "payer_national_registration": "99999999999",
    "subscription_id": "6134421-fe50-4c2f-86ce-2411243385bd"
}

📝 Descrição dos Campos

Campo

Tipo

Descrição

id

string

Identificador único da transação gerada.

qr_code

string

Código PIX completo no padrão EMV para ser copiado e pago manualmente.

status

string

Status atual da transação (created).

value

integer

Valor da cobrança em centavos de reais.

webhook_url

string

URL informada para receber notificações de pagamento.

qr_code_base64

string

Imagem do QR Code no formato base64, ideal para exibição.

webhook

string/null

Retorno interno do processamento da notificação enviada, se houver.

split_rules

array

Lista com as regras de divisão de valores (caso existam splits configurados).

end_to_end_id

string/null

Código identificador do PIX gerado pelo Banco Central (aparece após o pagamento).

payer_name

string/null

Nome do pagador, retornado após o pagamento.

payer_national_registration

string/null

CPF ou CNPJ do pagador, retornado após o pagamento.

subscription_id

string/null

Identificador único da cobrança recorrente.

Header Parameters
Authorization
string
Required
Colocar no formato Bearer TOKEN

Accept
string
Required
application/json

Content-Type
string
Required
application/json

Body ParametersExpand all
value
number
Required
Adicione o valor em centavos. O mínimo deve ser 50 centavos

frequency
number
name
string
comment
string
pix_recurring_retry_policy
number
customer
object
Show child attributes

webhook_url
string
Caso tenha um servidor para receber as informações de pagamento ou estorno informe aqui sua URL

promo_value
integer
Adicione o valor em centavos. O valor deve ser inferior ao 'value'

Response
200
Object
{ "id": "a10df814-5f5d-4a7f-8277-e7d6906faac9",     "qr_code": "00020101021226810014br.gov.bcb.pix2559qr.woovi.com/qr/v2/cob/7fcb1fca-dcc2-421e-9b8a-6e9a0340dd185204000053039865802BR5909PushinPay6008CAMPINAS62070503***80810014br.gov.bcb.pix2559qr.woovi.com/qr/v2/rec/2345c1b4-1bca-4a…",     "status": "created",     "value": 200,     "webhook_url": "https://oriented-lamprey-inviting.ngrok-free.app/api",     "qr_code_base64": "Ry95NfG5XD9Vg+/pN8Sls3/wEMRlrpJjzkt5Ge0qCDqCI7Xp/atxTMkSjTp3TZC+jxS821cpl4KCxBgTUTqRiK4uBA+rOruLjiPDgPU3Enn0/Py4wvaA0IrnuHpDMfE/JEguMTd5K+xwkbfpJ9IzyjYftLCaQCDW0x+IdCmu6TRo6inDu9g/gnp1S1YZ9…",     "webhook": null,     "split_rules": [],     "end_to_end_id": null,     "payer_name": "João Silva",     "payer_national_registration": "99999999999",     "subscription_id": "6134421-fe50-4c2f-86ce-2411243385bd" }
Was this section helpful?
Yes
No

Previous

Pix Recorrente

Next

Cancelar Pix Recorrente




Base URL

Produção:

https://api.pushinpay.com.br/api

SandBox (Homolog):

https://api-sandbox.pushinpay.com.br/api

Language Box

cURL
Ruby
Ruby
Python
Python
PHP
PHP
Java
Java
Node.js
Node.js
Go
Go
.NET
.NET
POST

/pix/cashIn/subscription

PHP


<?php
$client = new Client();
$headers = [
  'Authorization' => 'Bearer',
  'Accept' => 'application/json',
  'Content-Type' => 'application/json'
];
$body = '{
  "value": 2000,
  "frequency": 2,
  "name": "Assinatura Plano",
  "comment": "Assinatura Plano ",
  "pix_recurring_retry_policy": 2,
  "customer": {
    "name": "joao silva",
    "email": "joao.silva@example.com",
    "phoneNumber": "+5511999999999",
    "document": {
      "type": "CPF",
      "number": "999999999999"
    },
    "address": {
      "street": "Av. Paulista",
      "streetNumber": "1000",
      "zipCode": "01310930",
      "state": "SP",
      "city": "São Paulo",
      "district": "Bela Vista",
      "complement": "Conjunto 101"
    }
  },
  "webhook_url": "https://suaurl/api",
  "promo_value": 200
}';
$request = new Request('POST', 'https://api.pushinpay.com.br/api/pix/cashIn/subscription', $headers, $body);
$res = $client->sendAsync($request)->wait();
echo $res->getBody();
Response

200
{
  "id": "a10df814-5f5d-4a7f-8277-e7d6906faac9",
    "qr_code": "00020101021226810014br.gov.bcb.pix2559qr.woovi.com/qr/v2/cob/7fcb1fca-dcc2-421e-9b8a-6e9a0340dd185204000053039865802BR5909PushinPay6008CAMPINAS62070503***80810014br.gov.bcb.pix2559qr.woovi.com/qr/v2/rec/2345c1b4-1bca-4a…",
    "status": "created",
    "value": 200,
    "webhook_url": "https://oriented-lamprey-inviting.ngrok-free.app/api",
    "qr_code_base64": "Ry95NfG5XD9Vg+/pN8Sls3/wEMRlrpJjzkt5Ge0qCDqCI7Xp/atxTMkSjTp3TZC+jxS821cpl4KCxBgTUTqRiK4uBA+rOruLjiPDgPU3Enn0/Py4wvaA0IrnuHpDMfE/JEguMTd5K+xwkbfpJ9IzyjYftLCaQCDW0x+IdCmu6TRo6inDu9g/gnp1S1YZ9…",
    "webhook": null,
    "split_rules": [],
    "end_to_end_id": null,
    "payer_name": "João Silva",
    "payer_national_registration": "99999999999",
    "subscription_id": "6134421-fe50-4c2f-86ce-2411243385bd"
}


Cancelar Pix Recorrente
Esse endpoint é usado para cancelar uma cobrança de Pix recorrente.
Exemplo de response:
JSON
{
    "success": true,
    "message": "Assinatura cancelada com sucesso.",
    "data": {
        "status": true,
        "message": "Assinatura cancelada com sucesso.",
        "data": false
    }
}

Header Parameters
Authorization
string
Required
Colocar no formato Bearer TOKEN

Accept
string
Required
application/json

Content-Type
string
Required
application/json

Path Parameters
id
string
Response
200
Object
{     "success": true,     "message": "Assinatura cancelada com sucesso.",     "data": {         "status": true,         "message": "Assinatura cancelada com sucesso.",         "data": false     } }
Was this section helpful?
Yes
No

Previous

Criar Pix Recorrente

Next

Buscar Pix Recorrente




Base URL

Produção:

https://api.pushinpay.com.br/api

SandBox (Homolog):

https://api-sandbox.pushinpay.com.br/api

Language Box

cURL
Ruby
Ruby
Python
Python
PHP
PHP
Java
Java
Node.js
Node.js
Go
Go
.NET
.NET
GET

/pix/cashIn/subscription/{id}/cancel

PHP


<?php
$client = new Client();
$headers = [
  'Authorization' => 'Bearer',
  'Accept' => 'application/json',
  'Content-Type' => 'application/json'
];
$request = new Request('GET', 'https://api.pushinpay.com.br/api/pix/cashIn/subscription/{id}/cancel', $headers);
$res = $client->sendAsync($request)->wait();
echo $res->getBody();
Response

200
{
    "success": true,
    "message": "Assinatura cancelada com sucesso.",
    "data": {
        "status": true,
        "message": "Assinatura cancelada com sucesso.",
        "data": false
    }
}

Buscar Pix Recorrente
Esse endpoint é usado para buscar Pix Recorrente retonando uma listagem com paginação

Exemplo de response:
JSON
{
    "data": [
        {
            "id": 40123,
            "account_id": "9D12365D-3DEB-4271-9E0D-86D2C123D370",
            "customer_id": 20014,
            "global_id": "UGF5bWVudFN1YnNjcmlwdGlvbjo2OThjNzhmNzNlY123c2RiMWQyNDI4Nzc=",
            "correlation_id": "61123421-fe40-4c1c-86ce-241b11235bd",
            "value": 200,
            "frequency": 2,
            "name": "Assinatura Plano",
            "comment": "Assinatura Plano",
            "pix_recurring_retry_policy": 2,
            "status": "ACTIVE",
            "created_at": "2026-02-11T12:41:27.380000Z",
            "updated_at": "2026-02-11T12:41:27.877000Z",
            "webhook_url": "https://oriented-lamprey-inviting.ngrok-free.app/api",
            "subscription_pix_transaction": [
                {
                    "id": 40024,
                    "subscription_pix_id": 40123,
                    "transaction_id": "A1012314-5F5D-4A7F-8277-E7D6123AAC9",
                    "installment_status": null,
                    "cobr_status": null,
                    "cobr_try_status": null,
                    "created_at": "2026-02-11T12:41:27.397000Z",
                    "updated_at": "2026-02-11T12:41:27.397000Z",
                    "installment_number": "1",
                    "transaction": {
                        "id": "A1123814-5F5D-4A7F-8277-E7D6123AAC9",
                        "status": "pending",
                        "amount": "200",
                        "original_amount": "200",
                        "description": "Pagamento PIX Automatico",
                        "external_id": "6112321-fe40-4c1c-86ce-241b12385bd",
                        "payment_type": "pix_automatico",
                        "refunded": "0",
                        "voided": "0",
                        "captured": "0",
                        "account_id": "9D12365D-3DEB-4271-9E0D-86D2C123D370",
                        "deleted_at": null,
                        "created_at": "2026-02-11T12:41:27.317000Z",
                        "updated_at": "2026-02-11T12:41:27.903000Z",
                        "webhook_url": "https://oriented-lamprey-inviting.ngrok-free.app/api",
                        "transactions_pix_cash_in_id": "A1123815-3FD4-4FF4-8B65-65B8C1231100",
                        "end_to_end_id": null,
                        "payer_name": "João Silva",
                        "payer_national_registration": "06322593021",
                        "payer_type": null,
                        "gateway": "11",
                        "transactions_bank_slip_id": null,
                        "transaction_type": "7",
                        "payment_link_id": null,
                        "payer_email": null,
                        "in_infraction": false,
                        "token_used": null,
                        "refund_id": null,
                        "is_threeds": false,
                        "bank_ispb": null,
                        "end_to_end_refund_id": null
                    }
                }
            ],
            "customer": {
                "id": 20123,
                "external_id": "d31233d-d873-431b-84d1-bb141239edff",
                "name": "João Silva",
                "email": null,
                "phone_number": null,
                "document_type": 1,
                "document_number": "06322593020",
                "created_at": "2026-02-06T16:35:48.587000Z",
                "updated_at": "2026-02-06T16:35:48.587000Z",
                "anex": null,
                "prefer_contact": null,
                "status": 1
            }
        },
        {
            "id": 30017,
            "account_id": "9D51235D-3DEB-4271-9E0D-86D2123D370",
            "customer_id": 20123,
            "global_id": "UGF5bWVudFchYnNjcmlwdG6hbjo2123TkxNTUxMWVmOG4MDM1YjhhZmY=",
            "correlation_id": "076123816-3fef-46f8-8db6-5147d1234745",
            "value": 200,
            "frequency": 2,
            "name": "Assinatura Plano",
            "comment": "Assinatura Plano",
            "pix_recurring_retry_policy": 2,
            "status": "ACTIVE",
            "created_at": "2026-02-06T16:38:45.023000Z",
            "updated_at": "2026-02-06T16:38:45.380000Z",
            "webhook_url": "https://oriented-lamprey-inviting.ngrok-free.app/api",
            "subscription_pix_transaction": [
                {
                    "id": 30025,
                    "subscription_pix_id": 30017,
                    "transaction_id": "A1233E05-AC7A-4B4E-A58A-6A6F123A32E",
                    "installment_status": null,
                    "cobr_status": null,
                    "cobr_try_status": null,
                    "created_at": "2026-02-06T16:38:45.030000Z",
                    "updated_at": "2026-02-06T16:38:45.030000Z",
                    "installment_number": "1",
                    "transaction": {
                        "id": "A1123E05-AC7A-4B4E-A58A-6A6F123FA32E",
                        "status": "pending",
                        "amount": "200",
                        "original_amount": "200",
                        "description": "Pagamento PIX Automatico",
                        "external_id": "0767c816-3fef-46f8-8db6-5147dfda4745",
                        "payment_type": "pix_automatico",
                        "refunded": "0",
                        "voided": "0",
                        "captured": "0",
                        "account_id": "9D5123D-3DEB-4271-9E0D-86D2C0912370",
                        "deleted_at": null,
                        "created_at": "2026-02-06T16:38:45.003000Z",
                        "updated_at": "2026-02-06T16:38:45.397000Z",
                        "webhook_url": "https://oriented-lamprey-inviting.ngrok-free.app/api",
                        "transactions_pix_cash_in_id": "A1012306-422C-4BB3-9F3E-9B6123E2748D",
                        "end_to_end_id": null,
                        "payer_name": "Maria Santos",
                        "payer_national_registration": "06322593021",
                        "payer_type": null,
                        "gateway": "11",
                        "transactions_bank_slip_id": null,
                        "transaction_type": "7",
                        "payment_link_id": null,
                        "payer_email": null,
                        "in_infraction": false,
                        "token_used": null,
                        "refund_id": null,
                        "is_threeds": false,
                        "bank_ispb": null,
                        "end_to_end_refund_id": null
                    }
                }
            ],
            "customer": {
                "id": 20078,
                "external_id": "d31233d-d873-431b-84d1-bb146123edff",
                "name": "Maria Santos",
                "email": null,
                "phone_number": null,
                "document_type": 1,
                "document_number": "06322593020",
                "created_at": "2026-02-06T16:35:48.587000Z",
                "updated_at": "2026-02-06T16:35:48.587000Z",
                "anex": null,
                "prefer_contact": null,
                "status": 1
            }
        },
    ],
    "links": {
        "first": "http://localhost:8061/api/pix/cashIn/subscription?page=1",
        "last": null,
        "prev": null,
        "next": "http://localhost:8061/api/pix/cashIn/subscription?page=2"
    },
    "meta": {
        "current_page": 1,
        "from": 1,
        "path": "http://localhost:8061/api/pix/cashIn/subscription",
        "per_page": 15,
        "to": 15
    }
}

Todos os status possíveis:

ACTIVE: Assinatura ativa e criando novas parcelas nas datas pré definidas.
COMPLETED: Assinatura concluída e não criará novas parcelas. (Especificadamente quando a assinatura tem uma data final).
EXPIRED: Possui uma parcela que foi expirada
INACTIVE: Assinartura cancelada
Header Parameters
Authorization
string
Required
Colocar no formato Bearer TOKEN

Accept
string
Required
application/json

Content-Type
string
Required
application/json

Response
200
Object
{     "data": [         {             "id": 40123,             "account_id": "9D12365D-3DEB-4271-9E0D-86D2C123D370",             "customer_id": 20014,             "global_id": "UGF5bWVudFN1YnNjcmlwdGlvbjo2OThjNzhmNzNlY123c2RiMWQyNDI4Nzc=",             "correlation_id": "61123421-fe40-4c1c-86ce-241b11235bd",             "value": 200,             "frequency": 2,             "name": "Assinatura Plano",             "comment": "Assinatura Plano",             "pix_recurring_retry_policy": 2,             "status": "ACTIVE",             "created_at": "2026-02-11T12:41:27.380000Z",             "updated_at": "2026-02-11T12:41:27.877000Z",             "webhook_url": "https://oriented-lamprey-inviting.ngrok-free.app/api",             "subscription_pix_transaction": [                 {                     "id": 40024,                     "subscription_pix_id": 40123,                     "transaction_id": "A1012314-5F5D-4A7F-8277-E7D6123AAC9",                     "installment_status": null,                     "cobr_status": null,                     "cobr_try_status": null,                     "created_at": "2026-02-11T12:41:27.397000Z",                     "updated_at": "2026-02-11T12:41:27.397000Z",                     "installment_number": "1",                     "transaction": {                         "id": "A1123814-5F5D-4A7F-8277-E7D6123AAC9",                         "status": "pending",                         "amount": "200",                         "original_amount": "200",                         "description": "Pagamento PIX Automatico",                         "external_id": "6112321-fe40-4c1c-86ce-241b12385bd",                         "payment_type": "pix_automatico",                         "refunded": "0",                         "voided": "0",                         "captured": "0",                         "account_id": "9D12365D-3DEB-4271-9E0D-86D2C123D370",                         "deleted_at": null,                         "created_at": "2026-02-11T12:41:27.317000Z",                         "updated_at": "2026-02-11T12:41:27.903000Z",                         "webhook_url": "https://oriented-lamprey-inviting.ngrok-free.app/api",                         "transactions_pix_cash_in_id": "A1123815-3FD4-4FF4-8B65-65B8C1231100",                         "end_to_end_id": null,                         "payer_name": "João Silva",                         "payer_national_registration": "06322593021",                         "payer_type": null,                         "gateway": "11",                         "transactions_bank_slip_id": null,                         "transaction_type": "7",                         "payment_link_id": null,                         "payer_email": null,                         "in_infraction": false,                         "token_used": null,                         "refund_id": null,                         "is_threeds": false,                         "bank_ispb": null,                         "end_to_end_refund_id": null                     }                 }             ],             "customer": {                 "id": 20123,                 "external_id": "d31233d-d873-431b-84d1-bb141239edff",                 "name": "João Silva",                 "email": null,                 "phone_number": null,                 "document_type": 1,                 "document_number": "06322593020",                 "created_at": "2026-02-06T16:35:48.587000Z",                 "updated_at": "2026-02-06T16:35:48.587000Z",                 "anex": null,                 "prefer_contact": null,                 "status": 1             }         },         {             "id": 30017,             "account_id": "9D51235D-3DEB-4271-9E0D-86D2123D370",             "customer_id": 20123,             "global_id": "UGF5bWVudFchYnNjcmlwdG6hbjo2123TkxNTUxMWVmOG4MDM1YjhhZmY=",             "correlation_id": "076123816-3fef-46f8-8db6-5147d1234745",             "value": 200,             "frequency": 2,             "name": "Assinatura Plano",             "comment": "Assinatura Plano",             "pix_recurring_retry_policy": 2,             "status": "ACTIVE",             "created_at": "2026-02-06T16:38:45.023000Z",             "updated_at": "2026-02-06T16:38:45.380000Z",             "webhook_url": "https://oriented-lamprey-inviting.ngrok-free.app/api",             "subscription_pix_transaction": [                 {                     "id": 30025,                     "subscription_pix_id": 30017,                     "transaction_id": "A1233E05-AC7A-4B4E-A58A-6A6F123A32E",                     "installment_status": null,                     "cobr_status": null,                     "cobr_try_status": null,                     "created_at": "2026-02-06T16:38:45.030000Z",                     "updated_at": "2026-02-06T16:38:45.030000Z",                     "installment_number": "1",                     "transaction": {                         "id": "A1123E05-AC7A-4B4E-A58A-6A6F123FA32E",                         "status": "pending",                         "amount": "200",                         "original_amount": "200",                         "description": "Pagamento PIX Automatico",                         "external_id": "0767c816-3fef-46f8-8db6-5147dfda4745",                         "payment_type": "pix_automatico",                         "refunded": "0",                         "voided": "0",                         "captured": "0",                         "account_id": "9D5123D-3DEB-4271-9E0D-86D2C0912370",                         "deleted_at": null,                         "created_at": "2026-02-06T16:38:45.003000Z",                         "updated_at": "2026-02-06T16:38:45.397000Z",                         "webhook_url": "https://oriented-lamprey-inviting.ngrok-free.app/api",                         "transactions_pix_cash_in_id": "A1012306-422C-4BB3-9F3E-9B6123E2748D",                         "end_to_end_id": null,                         "payer_name": "Maria Santos",                         "payer_national_registration": "06322593021",                         "payer_type": null,                         "gateway": "11",                         "transactions_bank_slip_id": null,                         "transaction_type": "7",                         "payment_link_id": null,                         "payer_email": null,                         "in_infraction": false,                         "token_used": null,                         "refund_id": null,                         "is_threeds": false,                         "bank_ispb": null,                         "end_to_end_refund_id": null                     }                 }             ],             "customer": {                 "id": 20078,                 "external_id": "d31233d-d873-431b-84d1-bb146123edff",                 "name": "Maria Santos",                 "email": null,                 "phone_number": null,                 "document_type": 1,                 "document_number": "06322593020",                 "created_at": "2026-02-06T16:35:48.587000Z",                 "updated_at": "2026-02-06T16:35:48.587000Z",                 "anex": null,                 "prefer_contact": null,                 "status": 1             }         },     ],     "links": {         "first": "http://localhost:8061/api/pix/cashIn/subscription?page=1",         "last": null,         "prev": null,         "next": "http://localhost:8061/api/pix/cashIn/subscription?page=2"     },     "meta": {         "current_page": 1,         "from": 1,         "path": "http://localhost:8061/api/pix/cashIn/subscription",         "per_page": 15,         "to": 15     } }
Was this section helpful?
Yes
No

Previous

Cancelar Pix Recorrente

Next

Boleto




Base URL

Produção:

https://api.pushinpay.com.br/api

SandBox (Homolog):

https://api-sandbox.pushinpay.com.br/api

Language Box

cURL
Ruby
Ruby
Python
Python
PHP
PHP
Java
Java
Node.js
Node.js
Go
Go
.NET
.NET
POST

/pix/cashIn/subscription/

PHP


<?php
$client = new Client();
$headers = [
  'Authorization' => 'Bearer',
  'Accept' => 'application/json',
  'Content-Type' => 'application/json'
];
$request = new Request('POST', 'https://api.pushinpay.com.br/api/pix/cashIn/subscription/', $headers);
$res = $client->sendAsync($request)->wait();
echo $res->getBody();
Response

200
{
    "data": [
        {
            "id": 40123,
            "account_id": "9D12365D-3DEB-4271-9E0D-86D2C123D370",
            "customer_id": 20014,
            "global_id": "UGF5bWVudFN1YnNjcmlwdGlvbjo2OThjNzhmNzNlY123c2RiMWQyNDI4Nzc=",
            "correlation_id": "61123421-fe40-4c1c-86ce-241b11235bd",
            "value": 200,
            "frequency": 2,
            "name": "Assinatura Plano",
            "comment": "Assinatura Plano",
            "pix_recurring_retry_policy": 2,
            "status": "ACTIVE",
            "created_at": "2026-02-11T12:41:27.380000Z",
            "updated_at": "2026-02-11T12:41:27.877000Z",
            "webhook_url": "https://oriented-lamprey-inviting.ngrok-free.app/api",
            "subscription_pix_transaction": [
                {


                    MED:
                    Criar Pix Recorrente
Esse endpoint responde uma infração.

Exemplo de Resposta - Endpoint
JSON
{
    "success": true,
    "data": {
        "id": 135,
        "status": 8,
        "description": null,
        "external_id": "A11239D0-EC64-432C-AB66-E36123BA584A",
        "account_id": "A012336B-CB36-4ED7-B7C8-30AF12373CD6",
        "transaction_id": "A13123D0-EC64-432C-AB66-E36123BA584A",
        "created_at": "2026-03-02T18:21:51.627000Z",
        "updated_at": "2026-03-02T18:22:43.486000Z",
        "statement_id": null,
        "analysis_result": null,
        "gateway": "11",
        "defense": "Sua defesa",
        "infraction_created": "2026-03-02T18:21:51.627000Z",
        "origin": 2,
        "manual_description": "Teste de api de defesa",
        "payer_email": null,
        "payer_phone": null,
        "admin_id": "11",
        "auto_defense_sent": false,
        "compliance_analysis_required": "0",
        "compliance_analysis_send": null
    }
}

Header Parameters
Authorization
string
Required
Colocar no formato Bearer TOKEN

Accept
string
Required
application/json

Content-Type
string
Required
application/json

Path Parameters
id
string
Body ParametersExpand all
files
array
Arquivos das provas

Show child attributes

defense
string
Sua defesa

Response
200
Object
{     "success": true,     "data": {         "id": 135,         "status": 8,         "description": null,         "external_id": "A11239D0-EC64-432C-AB66-E36123BA584A",         "account_id": "A012336B-CB36-4ED7-B7C8-30AF12373CD6",         "transaction_id": "A13123D0-EC64-432C-AB66-E36123BA584A",         "created_at": "2026-03-02T18:21:51.627000Z",         "updated_at": "2026-03-02T18:22:43.486000Z",         "statement_id": null,         "analysis_result": null,         "gateway": "11",         "defense": "Sua defesa",         "infraction_created": "2026-03-02T18:21:51.627000Z",         "origin": 2,         "manual_description": "Teste de api de defesa",         "payer_email": null,         "payer_phone": null,         "admin_id": "11",         "auto_defense_sent": false,         "compliance_analysis_required": "0",         "compliance_analysis_send": null     } }
Was this section helpful?
Yes
No

Previous

Infração

Next

Infração Busca




Base URL

Produção:

https://api.pushinpay.com.br/api

SandBox (Homolog):

https://api-sandbox.pushinpay.com.br/api

Language Box

cURL
Ruby
Ruby
Python
Python
PHP
PHP
Java
Java
Node.js
Node.js
Go
Go
.NET
.NET
POST

/infractions/{id}/med

PHP


<?php
$client = new Client();
$headers = [
  'Authorization' => 'Bearer',
  'Accept' => 'application/json',
  'Content-Type' => 'application/json'
];
$body = '{
  "files": [
    ""
  ],
  "defense": "Minha defesa"
}';
$request = new Request('POST', 'https://api.pushinpay.com.br/api/infractions/{id}/med', $headers, $body);
$res = $client->sendAsync($request)->wait();
echo $res->getBody();
Response

200
{
    "success": true,
    "data": {
        "id": 135,
        "status": 8,
        "description": null,
        "external_id": "A11239D0-EC64-432C-AB66-E36123BA584A",
        "account_id": "A012336B-CB36-4ED7-B7C8-30AF12373CD6",
        "transaction_id": "A13123D0-EC64-432C-AB66-E36123BA584A",
        "created_at": "2026-03-02T18:21:51.627000Z",
        "updated_at": "2026-03-02T18:22:43.486000Z",
        "statement_id": null,
        "analysis_result": null,
        "gateway": "11",
        "defense": "Sua defesa",
        "infraction_created": "2026-03-02T18:21:51.627000Z",
        "origin": 2,
        "manual_description": "Teste de api de defesa",
        "payer_email": null,
        "payer_phone": null,

        Esse endpoint é usado para buscar infrações.
Exemplo de Resposta - Endpoint
JSON
{
    "data": [
        {
            "id": 135,
            "created_at": "2026-03-02T18:21:51.627000Z",
            "description": null,
            "updated_at": "2026-03-02T18:22:43.487000Z",
            "transaction": {
                "id": "A134A9D0-EC64-432C-AB66-E363123A584A",
                "status": "paid",
                "value": "2869",
                "description": "Pagamento PIX",
                "payment_type": "pix",
                "created_at": "2026-03-02T18:19:54.137000Z",
                "updated_at": "2026-03-02T18:21:51.643000Z",
                "webhook_url": null,
                "split_rules": [],
                "fee": null,
                "total": null,
                "end_to_end_id": "Eed3d98fe558347cca0eb1233f531ceee",
                "payer_name": "Cliente",
                "payer_national_registration": "447*********01",
                "infraction": {
                    "defense": "Minha defesa",
                    "description": null,
                    "infraction_created": "2026-03-02T18:21:51.627000Z",
                    "status_text": "Aguardando instituição",
                    "status": 8,
                    "analysis_result": null,
                    "manual_description": "Teste de api de defesa"
                }
            },
            "status": 8,
            "status_text": "Aguardando instituição",
            "analysis_result": null,
            "account_id": "A03CF36B-CB36-4ED7-B7C8-30AF95123CD6",
            "defense": "Minha defesa",
            "account": {
                "businessName": "João da Silva",
                "email": "exemplo@pushinpay.com.br",
                "contactNumber": "+5531971439894"
            },
            "origin": 2,
            "manual_description": "Teste de api de defesa",
            "infraction_defense": null,
            "auto_defense_sent": false,
            "file": [
                {
                    "id": 199,
                    "account_id": "A03CF36B-CB36-4ED7-B7C8-30AF12373CD6",
                    "infraction_id": "135",
                    "path": "infractions/A03CF36B-CB36-4ED7-B7C8-301235473CD6/135/a79bae81-8c7d-4091-9157-ee4b88123f84.pdf",
                    "url": null,
                    "created_at": "2026-03-02T18:22:42.873000Z",
                    "updated_at": "2026-03-02T18:22:42.873000Z",
                    "deleted_at": null,
                    "url_external": null,
                    "path_url": "https://pushinpay-sandbox.s3.us-east-1.amazonaws.com/pushinpay-sandbox/infractions/A03CF36B-CB36-4ED7-B7C8-30AF95473CD6/135/a79bae81-8c7d-4091-9157-ee4b88e2ff84.pdf?X-Amz-Content-Sha256=UNSIGNED-PAYLOAD&X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Credential=AKIASTHLPGZBYNEFHM6V%2F20260302%2Fus-east-1%2Fs3%2Faws4_request&X-Amz-Date=20260302T182853Z&X-Amz-SignedHeaders=host&X-Amz-Expires=60&X-Amz-Signature=03bc7b8aa4c350b30bd9601314e1dd8103a1231c4fb9341fd077718d50f3bd57"
                }
            ]
        },
    ]
}

Header Parameters
Authorization
string
Required
Colocar no formato Bearer TOKEN

Accept
string
Required
application/json

Content-Type
string
Required
application/json

Response
200
Object
{     "data": [         {             "id": 135,             "created_at": "2026-03-02T18:21:51.627000Z",             "description": null,             "updated_at": "2026-03-02T18:22:43.487000Z",             "transaction": {                 "id": "A134A9D0-EC64-432C-AB66-E363123A584A",                 "status": "paid",                 "value": "2869",                 "description": "Pagamento PIX",                 "payment_type": "pix",                 "created_at": "2026-03-02T18:19:54.137000Z",                 "updated_at": "2026-03-02T18:21:51.643000Z",                 "webhook_url": null,                 "split_rules": [],                 "fee": null,                 "total": null,                 "end_to_end_id": "Eed3d98fe558347cca0eb1233f531ceee",                 "payer_name": "Cliente",                 "payer_national_registration": "447*********01",                 "infraction": {                     "defense": "Minha defesa",                     "description": null,                     "infraction_created": "2026-03-02T18:21:51.627000Z",                     "status_text": "Aguardando instituição",                     "status": 8,                     "analysis_result": null,                     "manual_description": "Teste de api de defesa"                 }             },             "status": 8,             "status_text": "Aguardando instituição",             "analysis_result": null,             "account_id": "A03CF36B-CB36-4ED7-B7C8-30AF95123CD6",             "defense": "Minha defesa",             "account": {                 "businessName": "João da Silva",                 "email": "exemplo@pushinpay.com.br",                 "contactNumber": "+5531971439894"             },             "origin": 2,             "manual_description": "Teste de api de defesa",             "infraction_defense": null,             "auto_defense_sent": false,             "file": [                 {                     "id": 199,                     "account_id": "A03CF36B-CB36-4ED7-B7C8-30AF12373CD6",                     "infraction_id": "135",                     "path": "infractions/A03CF36B-CB36-4ED7-B7C8-301235473CD6/135/a79bae81-8c7d-4091-9157-ee4b88123f84.pdf",                     "url": null,                     "created_at": "2026-03-02T18:22:42.873000Z",                     "updated_at": "2026-03-02T18:22:42.873000Z",                     "deleted_at": null,                     "url_external": null,                     "path_url": "https://pushinpay-sandbox.s3.us-east-1.amazonaws.com/pushinpay-sandbox/infractions/A03CF36B-CB36-4ED7-B7C8-30AF95473CD6/135/a79bae81-8c7d-4091-9157-ee4b88e2ff84.pdf?X-Amz-Content-Sha256=UNSIGNED-PAYLOAD&X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Credential=AKIASTHLPGZBYNEFHM6V%2F20260302%2Fus-east-1%2Fs3%2Faws4_request&X-Amz-Date=20260302T182853Z&X-Amz-SignedHeaders=host&X-Amz-Expires=60&X-Amz-Signature=03bc7b8aa4c350b30bd9601314e1dd8103a1231c4fb9341fd077718d50f3bd57"                 }             ]         }, ] }
Was this section helpful?
Yes
No

Previous

Infração (MED)





Base URL

Produção:

https://api.pushinpay.com.br/api

SandBox (Homolog):

https://api-sandbox.pushinpay.com.br/api

Language Box

cURL
Ruby
Ruby
Python
Python
PHP
PHP
Java
Java
Node.js
Node.js
Go
Go
.NET
.NET
GET

/transactions

PHP


<?php
$client = new Client();
$headers = [
  'Authorization' => 'Bearer',
  'Accept' => 'application/json',
  'Content-Type' => 'application/json'
];
$request = new Request('GET', 'https://api.pushinpay.com.br/api/transactions', $headers);
$res = $client->sendAsync($request)->wait();
echo $res->getBody();
Response

200
{
    "data": [
        {
            "id": 135,
            "created_at": "2026-03-02T18:21:51.627000Z",
            "description": null,
            "updated_at": "2026-03-02T18:22:43.487000Z",
            "transaction": {
                "id": "A134A9D0-EC64-432C-AB66-E363123A584A",
                "status": "paid",
                "value": "2869",
                "description": "Pagamento PIX",
                "payment_type": "pix",
                "created_at": "2026-03-02T18:19:54.137000Z",
                "updated_at": "2026-03-02T18:21:51.643000Z",
                "webhook_url": null,
                "split_rules": [],
                "fee": null,
                "total": null,
                "end_to_end_id": "Ee