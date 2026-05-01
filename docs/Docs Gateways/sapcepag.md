Documentação API PIX

A Spacepag é uma API de pagamentos PIX robusta, projetada para simplificar o processamento de pagamentos para empresas de todos os portes. Nossa API oferece uma integração perfeita para o processamento de pagamentos instantâneos por meio do sistema PIX do Brasil.

Esta documentação destina-se a desenvolvedores que desejam integrar pagamentos PIX em seus aplicativos.

Introdução
Para começar a usar a API Spacepag, você precisará:

Cadastrar-se na Spacepag clicando aqui fazer seu cadastro e em seguida utilizar suas credenciais de API.
Siga o processo de autenticação descrito abaixo
URL Base
https://api.spacepag.com.br/v1

Autenticação
Todas as solicitações de API exigem autenticação usando tokens JWT. Para obter um token, faça uma solicitação POST para o endpoint de autenticação.

POST
/auth
Request Headers
Headers
Copiar
{
    "Content-Type":"application/json"
}
Request Body
JSON
Copiar
              
{
  "public_key": "pk_live_12345678912345678",
  "secret_key": "sk_live_123456789123456789123456789",
}

            
Response
JSON
Copiar
{
    "access_token": "e1f6as5f99e8f4e98f1e981fa98ef198e1wfa981wa98f1ef19a8ewf1...",
    "type":"Bearer",
    "expireIn": 1800
}
Usando o Token
Inclua o token no cabeçalho de Autorização para todas as solicitações subsequentes:

Headers
Copiar
Authorization: Bearer YOUR_JWT_TOKEN
Criar um pagamento
Crie um novo pagamento PIX com as informações necessárias.

POST
/cob
Request Headers
Headers
Copiar
{
    "Content-Type":"application/json",
    "Authorization":"Bearer YOUR_JWT_TOKEN"
}
Request Body
Copiar
O campo split não é obrigatório
Em split o seu username encontra-se no painel em Chaves API > Username. Deve conter o @ no inicio
Em split o percentageSplit é definido em % e deve ser do tipo Decimal com valor entre 0.1 e 99.9
{
    "amount": 50,
    "consumer": {
        "name": "Matheus Shovinsk",
        "document": "333.444.555-66",
        "email": "matheusshov@gmail.com"
    },
    "external_id": "7df8df7a98d7",
    "postback": "https://webhook.site/#!/view/364cff6f-c585-4437-a909-c68da8908064",
    "split": {
        "username": "@username1234",
        "percentageSplit": 10
    }
}
Success Response
JSON
Copiar
{
    "event": "order.created",
    "status": "pending",
    "transaction_id": "1f65sdf1sd15f1sdf817df8asd",
    "pix": {
        "amount": 50,
        "taxes": 0.5,
        "liquid": 49.50,
        "copy_and_paste": "00020126330014br.gov.bcb.pix2568...",
        "qrcode": "data:image/png;base64 ... png;base64,iVBORw0KGgoAAAANSUh...",
    },
    "consumer": {
        "name": "Matheus Shovinsk",
        "document": "333.444.555-66",
        "email": "matheusshov@gmail.com"
    },
    "external_id": "7df8df7a98d7",
    "postback": "https://webhook.site/#!/view/364cff6f-c585-4437-a909-c68da8908064"
}
Error Response
JSON
Copiar
{
    "statusCode": 403,
    "message": "Erro ao gerar transação."
}
Code Examples
cURL
JavaScript
PHP
Python
Java
Ruby
cURL
Copiar
curl -X POST 'https://api.spacepag.com.br/v1/cob' \
-H 'Content-Type: application/json' \
-H 'Authorization: Bearer YOUR_JWT_TOKEN' \
-d '{
    "amount": 50,
    "consumer": {
        "name": "Matheus Shovinsk",
        "document": "333.444.555-66",
        "email": "matheusshov@gmail.com"
    },
    "external_id": "7df8df7a98d7",
    "postback": "https://webhook.site/#!/view/364cff6f-c585-4437-a909-c68da8908064"
}'
Solicitar um saque
Crie uma nova solicitação de saque para transferir fundos para uma chave PIX.

POST
/pixout
Request Headers
Headers
Copiar
{
    "Content-Type":"application/json",
    "Authorization":"Bearer YOUR_JWT_TOKEN"
}
Request Body
JSON
Copiar
{
    "amount": 25,
    "pix_key": "000000000000",
    "pix_key_type": "cpf",
    "receiver": {
        "name": "Matheus Shovinsk",
        "document": "123.456.789-00",
        "email": "matheusshov@gmail.com"
    },
    "external_id": "123456",
    "postback": "https://webhook.site/#!/view/364cff6f-c585-4437-a909-c68da8908064"
}
Success Response
JSON
Copiar
{
    "event": "order.created",
    "status": "pending",
    "transaction_id": "1f65sdf1sd15f1sdf817df8asd",
    "payment": {
        "amount": 25,
        "taxes": 0.5,
        "liquid": 24.50,
        "pix_key": "000000000000",
        "pix_key_type": "cpf"
    },
    "receiver": {
        "name": "Matheus Shovinsk",
        "document": "333.444.555-66",
        "email": "matheusshov@gmail.com"
    },
    "external_id": "7df8df7a98d7",
    "postback": "https://webhook.site/#!/view/364cff6f-c585-4437-a909-c68da8908064"
}
Error Response
JSON
Copiar
{
    "statusCode": 403,
    "message": "Saldo insuficiente"                
}
Webhooks
Os webhooks permitem que você receba notificações em tempo real sobre alterações no status de pagamento. Configure seu endpoint de webhook para receber essas notificações automaticamente.

Eventos do Webhook
Você receberá notificações para os seguintes eventos:

order.created: Um novo pagamento foi gerado
order.paid: Um pagamento foi pago
order.cancelled: Um pagamento foi cancelado
order.med: Um pagamento recebeu MED PIX
payment.created: Uma solicitação de saque foi gerada
payment.paid: Uma solicitação de saque foi paga
payment.cancelled: Uma solicitação de saque foi cancelada
Webhook Payload Pagamento
JSON
Copiar
{
    "event": "order.paid",
    "status": "paid",
    "transaction_id": "1f65sdf1sd15f1sdf817df8asd",
    "pix": {
        "amount": 50,
        "taxes": 0.5,
        "liquid": 49.50,
        "copy_and_paste": "00020126330014br.gov.bcb.pix2568...",
        "qrcode": "data:image/png;base64 ... png;base64,iVBORw0KGgoAAAANSUh...",
    },
    "consumer": {
        "name": "Matheus Shovinsk",
        "document": "333.444.555-66",
        "email": "matheusshov@gmail.com"
    },
    "external_id": "7df8df7a98d7",
    "postback": "https://webhook.site/#!/view/364cff6f-c585-4437-a909-c68da8908064"
}
Webhook Payload Saque
JSON
Copiar
{
    "event": "payment.paid",
    "status": "paid",
    "transaction_id": "1f65sdf1sd15f1sdf817df8asd",
    "payment": {
        "amount": 25,
        "taxes": 0.5,
        "liquid": 24.50,
        "pix_key": "000000000000",
        "pix_key_type": "cpf"
    },
    "receiver": {
        "name": "Matheus Shovinsk",
        "document": "333.444.555-66",
        "email": "matheusshov@gmail.com"
    },
    "external_id": "7df8df7a98d7",
    "postback": "https://webhook.site/#!/view/364cff6f-c585-4437-a909-c68da8908064"
}
Consultar Transações
Transação de pagamento
Verificar o status de um pagamento.

GET
/transactions/cob/:transaction_id
Request Headers
Headers
Copiar
{
    "Content-Type":"application/json",
    "Authorization":"Bearer YOUR_JWT_TOKEN"
}
Success Response
JSON
Copiar
{
    "event": "order.paid",
    "status": "paid",
    "transaction_id": "1f65sdf1sd15f1sdf817df8asd",
    "pix": {
        "amount": 50,
        "taxes": 0.5,
        "liquid": 49.50,
        "copy_and_paste": "00020126330014br.gov.bcb.pix2568...",
        "qrcode": "data:image/png;base64 ... png;base64,iVBORw0KGgoAAAANSUh...",
    },
    "consumer": {
        "name": "Matheus Shovinsk",
        "document": "333.444.555-66",
        "email": "matheusshov@gmail.com"
    },
    "external_id": "7df8df7a98d7",
    "postback": "https://webhook.site/#!/view/364cff6f-c585-4437-a909-c68da8908064"
}
Error Response
JSON
Copiar
{
    "statusCode": 404,
    "message": "Transação não encontrada."                
}
Transação de saque
Verificar o status de uma solicitação de saque.

GET
/transactions/payment/:transaction_id
Request Headers
Headers
Copiar
{
    "Content-Type":"application/json",
    "Authorization":"Bearer YOUR_JWT_TOKEN"
}
Success Response
JSON
Copiar
{
    "event": "payment.paid",
    "status": "paid",
    "transaction_id": "1f65sdf1sd15f1sdf817df8asd",
    "payment": {
        "amount": 25,
        "taxes": 0.5,
        "liquid": 24.50,
        "pix_key": "000000000000",
        "pix_key_type": "cpf"
    },
    "receiver": {
        "name": "Matheus Shovinsk",
        "document": "333.444.555-66",
        "email": "matheusshov@gmail.com"
    },
    "external_id": "7df8df7a98d7",
    "postback": "https://webhook.site/#!/view/364cff6f-c585-4437-a909-c68da8908064"
}
Error Response
JSON
Copiar
{
    "statusCode": 404,
    "message": "Transação não encontrada."                
}
Important Notes
Registro Automático
Todas as tentativas de pagamento são registradas automaticamente para fins de auditoria. Isso inclui transações bem-sucedidas e malsucedidas.

Erros Comuns
Número de documento inválido: Certifique-se de que o número do documento é válido e está formatado corretamente.
Valor inválido: O valor deve ser um número positivo em formato decimal sem símbolos de moeda.
Token expirado: Se você receber um erro de autenticação, solicite um novo token.
Boas Práticas
Sempre valide o status da resposta e trate os erros adequadamente.
Armazene o external_id para referência futura.
Implemente mecanismos adequados de tratamento de erros e repetição.