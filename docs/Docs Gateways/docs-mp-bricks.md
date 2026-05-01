**Nota (esta aplicação):** O pagamento com **cartão** via Mercado Pago nesta aplicação utiliza **checkout transparente** (formulário próprio + tokenização via API/SDK do MP), e não o Card Payment Brick. PIX e boleto continuam podendo usar Bricks ou a API conforme a integração.

---

Pré-requisitos
Para realizar a integração dos Bricks, é preciso atender aos requisitos listados abaixo.

Atenção
Os Bricks não possuem suporte oficial quando utilizados em fluxos WebView nas plataformas Android e iOS.
Requisitos	Descrição
Aplicação	As aplicações são as diferentes integrações contidas em uma ou mais lojas. Você pode criar uma aplicação para cada solução que implementar, a fim de ter tudo organizado e manter um controle que facilite a gestão. Veja Suas integrações para mais informações sobre como criar uma aplicação.
Conta de vendedor Mercado Pago ou Mercado Livre	Para integrar os Bricks é preciso uma conta de vendedor no Mercado Pago ou Mercado Livre. Caso não tenha, clique aqui para criá-la gratuitamente.
Credenciais	Senhas únicas com as quais identificamos uma integração na sua conta. Para realizar as integrações, serão necessárias a Public key e o Access Token. Clique aqui para mais informações.
Instale o SDK do Mercado Pago	Instale os SDKs oficiais para simplificar sua integração com nossas APIs. Para mais informações, clique aqui.
Se todos os pré-requisitos foram atendidos, você poderá realizar a integração dos Bricks.

Inicialização comum
Para configurar a integração dos Bricks e ter um checkout responsivo, otimizado e configurável, disponibilizamos nos passos abaixo o processo de configuração inicial comum para todos os Bricks.

Incluir biblioteca Mercado Pago
Client-Side

Utilize as nossas bibliotecas oficiais para acessar as funcionalidades do Mercado Pago com segurança desde seu frontend.

// O código JS pode ser incluído em uma tag < script > ou um arquivo JS separado.
<script src="https://sdk.mercadopago.com/js/v2"></script>
Inicializar biblioteca Mercado Pago
Em seguida, inicialize a biblioteca Mercado Pago para utilizar Checkout Bricks.

const mp = new MercadoPago('YOUR_PUBLIC_KEY');
const bricksBuilder = mp.bricks();
Escolher o Brick
Com a biblioteca do Mercado Pago adicionada e configurada em seu projeto, você já está pronto para adicionar os Bricks em seu site. Para isso, escolha o Brick que melhor atende sua necessidade e siga os passos detalhados na seção correspondente ao Brick escolhido.

Bricks
Conheça todos os módulos do Checkout Bricks e as suas disponibilidades.
Payment Brick
Ofereça diversos métodos de pagamento para os clientes escolherem, com a capacidade de salvar dados do cartão para compras futuras. Teste a demonstração do Brick antes de integrá-lo.
Saiba mais
Acessar demonstração
Wallet Brick
Vincule a conta Mercado Pago e permita pagamentos registrados. Teste a demonstração do Brick antes de integrá-lo.
Saiba mais
Acessar demonstração
Status Screen Brick
Informe os clientes dos resultados do processo de compra após efetuar o pagamento. Teste a demonstração do Brick antes de integrá-lo.
Saiba mais
Acessar demonstração
Card Payment Brick
Ofereça pagamentos com cartão de crédito e débito. Teste a demonstração do Brick antes de integrá-lo.
Saiba mais
Acessar demonstração
Anterior
Pré-requisitos
Conheça os pré-requisitos necessários para integrar o Checkout Bricks.
Próximo

Payment Brick
O Payment Brick é uma solução modular e personalizável que permite adicionar vários métodos de pagamento à sua loja com apenas um Brick, permitindo que você salve os dados do cartão para compras futuras. Ao utilizar o Payment Brick, você terá diferentes métodos de pagamento à sua disposição e poderá escolher quais habilitar para o seu site.

Você poderá dar aos seus clientes a possibilidade de efetuarem pagamentos através de cartões de crédito, cartão de débito virtual Caixa, Pix, boleto, utilizar a Conta Mercado Pago ou Parcelamento sem cartão.

A possibilidade de guardar os dados dos cartões que já foram debitados em compras anteriores torna o processo de pagamento mais eficiente e rápido. Para o comprador, não é mais necessário recarregar os dados cada vez que entrar no checkout.

Experimente nosso Brick
Construa experiências visuais em tempo real. Quando estiver tudo pronto, baixe ou copie o código gerado para adicionar no seu site ou compartilhar com um desenvolvedor.

Element for view
Demo
Construir seu Payment Brick
Por sua vez, nosso processador cumpre todas as garantias de segurança para oferecer aos usuários a máxima proteção ao salvar seus dados. Essa é uma das grandes vantagens de adicionar o Checkout Bricks ao seu site: a tranquilidade de oferecer uma solução segura, respaldada pelo Mercado Pago, mas customizada para as necessidades da sua empresa.

Layout
O layout do Payment Brick foi construído com base nas melhores práticas de UX para que seja possível entregar ao comprador a melhor experiência sem que você precise se preocupar com detalhes de design.

payment-Brick-layout-mlb

Atenção
Os Bricks foram criados para atender não somente necessidades técnicas de implementação e segurança, mas também para prover a melhor experiência ao comprador. Customizar um Brick pode mudar drasticamente a experiência do comprador. Nossa recomendação é que você sempre faça uso do Brick com a menor quantidade possível de customizações adicionais para garantir sempre a melhor experiência.

Renderização padrão
Antes de realizar a renderização do Payment Brick, primeiro execute os passos de inicialização compartilhados entre todos os Bricks. A partir disso, veja abaixo as informações necessárias para você configurar e renderizar o Payment Brick.

Nota
Para consultar tipagens e especificações dos parâmetros e respostas de funções do Brick, consulte a documentação técnica.
Configurar o Brick
Crie a configuração de inicialização do Brick.

const renderPaymentBrick = async (bricksBuilder) => {
 const settings = {
   initialization: {
     /*
      "amount" é o valor total a ser pago por todos os meios de pagamento
    com exceção da Conta Mercado Pago e Parcelamento sem cartão de crédito, que tem seu valor de processamento determinado no backend através do "preferenceId"
     */
     amount: 100,
     preferenceId: "<PREFERENCE_ID>",
   },
   customization: {
     paymentMethods: {
       ticket: "all",
       bankTransfer: "all",
       creditCard: "all",
       prepaidCard: "all",
       debitCard: "all",
       mercadoPago: "all",
     },
   },
   callbacks: {
     onReady: () => {
       /*
        Callback chamado quando o Brick estiver pronto.
        Aqui você pode ocultar loadings do seu site, por exemplo.
       */
     },
     onSubmit: ({ selectedPaymentMethod, formData }) => {
       // callback chamado ao clicar no botão de submissão dos dados
       return new Promise((resolve, reject) => {
         fetch("/process_payment", {
           method: "POST",
           headers: {
             "Content-Type": "application/json",
           },
           body: JSON.stringify(formData),
         })
           .then((response) => response.json())
           .then((response) => {
             // receber o resultado do pagamento
             resolve();
           })
           .catch((error) => {
             // lidar com a resposta de erro ao tentar criar o pagamento
             reject();
           });
       });
     },
     onError: (error) => {
       // callback chamado para todos os casos de erro do Brick
       console.error(error);
     },
   },
 };
 window.paymentBrickController = await bricksBuilder.create(
   "payment",
   "paymentBrick_container",
   settings
 );
};
renderPaymentBrick(bricksBuilder);
Atenção
Sempre que o usuário sair da tela onde algum Brick é exibido, é necessário destruir a instância atual com o comando window.paymentBrickController.unmount(). Ao entrar novamente, uma nova instância deve ser gerada.
Para utilizar o método de pagamento (paymentMethods) do tipo "mercadoPago" é preciso enviar uma preferência durante a inicialização do Brick, substituindo o valor <PREFERENCE_ID> pelo ID da preferência criada. As instruções para criação da preferência estão na seção Habilitar pagamento com Mercado Pago.

Renderizar o Brick
Uma vez criadas as configurações, insira o código abaixo para renderizar o Brick.

Importante
O id paymentBrick_container da div html abaixo, deve corresponder ao valor enviado dentro do método create() da etapa anterior.
<div id="paymentBrick_container"></div>
O resultado de renderizar o Brick deve ser como na imagem abaixo.

payment-Brick-layout-mlb

Habilitar pagamento com Mercado Pago
Para utilizar o método de pagamento (paymentMethods) do tipo "mercadoPago" é preciso enviar uma preferência durante a inicialização do Brick, substituindo o valor <PREFERENCE_ID> pelo ID da preferência criada.

Para criar uma preferência em seu backend, adicione o SDK do Mercado Pago e as credenciais necessárias ao seu projeto para habilitar o uso de preferências.

<?php
// SDK do Mercado Pago
require __DIR__ .  '/vendor/autoload.php';
// Adicione as credenciais
MercadoPago\SDK::setAccessToken('PROD_ACCESS_TOKEN');
?>
Em seguida, configure a preferência de acordo com o seu produto ou serviço.

Os exemplos de código abaixo configuram o purpose da preferência como wallet_purchase, mas também é possível configurá-lo como onboarding_credits. Entenda a diferença entre os dois:

wallet_purchase: o usuário deverá fazer login quando for redirecionado para sua conta do Mercado Pago.

onboarding_credits: após fazer login, o usuário verá a opção de pagamento com crédito pré-selecionada em sua conta do Mercado Pago.

<?php
// Cria um objeto de preferência
$preference = new MercadoPago\Preference();
 
// Cria um item na preferência
$item = new MercadoPago\Item();
$item->title = 'Meu produto';
$item->quantity = 1;
$item->unit_price = 75.56;
$preference->items = array($item);
 
// o $preference->purpose = 'wallet_purchase'; permite apenas pagamentos logados
// para permitir pagamentos como guest, você pode omitir essa propriedade
$preference->purpose = 'wallet_purchase';
$preference->create();
?>
Importante
Para saber mais detalhes de como configurá-la, acesse a seção Preferências.



Considere que quando um usuário opta por fazer o pagamento utilizando a Conta Mercado Pago, este será redirecionado para a página do Mercado Pago para concluir o pagamento. Por isso, é necessário configurar as back_urls se você quiser retornar ao seu site ao final do pagamento. Para mais informações, visite a seção Redirecione o comprador para o seu site.

Cartões
Server-Side

Com todas as informações coletadas no backend , envie um POST com os atributos necessários ao endpoint /v1/payments e execute a requisição ou, se preferir, faça o envio das informações utilizando nossos SDKs.

Importante
Além disso, você deverá enviar obrigatoriamente o atributo X-Idempotency-Key. Seu preenchimento é importante para garantir a execução e reexecução de requisições de forma segura, sem o risco de realizar a mesma ação mais de uma vez por engano. Para fazé-lo, atualize nossa biblioteca de SDK ou gere um UUID V4 e envie-o no header de suas chamadas.
<?php
  use MercadoPago\Client\Payment\PaymentClient;
  use MercadoPago\Client\Common\RequestOptions;
  use MercadoPago\MercadoPagoConfig;

  MercadoPagoConfig::setAccessToken("YOUR_ACCESS_TOKEN");

  $client = new PaymentClient();
  $request_options = new RequestOptions();
  $request_options->setCustomHeaders(["X-Idempotency-Key: <SOME_UNIQUE_VALUE>"]);

  $payment = $client->create([
    "transaction_amount" => (float) $_POST['<TRANSACTION_AMOUNT>'],
    "token" => $_POST['<TOKEN>'],
    "description" => $_POST['<DESCRIPTION>'],
    "installments" => $_POST['<INSTALLMENTS>'],
    "payment_method_id" => $_POST['<PAYMENT_METHOD_ID'],
    "issuer_id" => $_POST['<ISSUER>'],
    "payer" => [
      "email" => $_POST['<EMAIL>'],
      "identification" => [
        "type" => $_POST['<IDENTIFICATION_TYPE'],
        "number" => $_POST['<NUMBER>']
      ]
    ]
  ], $request_options);
  echo implode($payment);
?>
Resposta
{
    "status": "approved",
    "status_detail": "accredited",
    "id": 3055677,
    "date_approved": "2019-02-23T00:01:10.000-04:00",
    "payer": {
        ...
    },
    "payment_method_id": "visa",
    "payment_type_id": "credit_card",
    "refunds": [],
    ...
}
O callback de onSubmit do Brick contém todos os dados necessários para a criação de um pagamento, porém, caso deseje, é possível incluir detalhes adicionais, o que pode facilitar o reconhecimento da compra por parte do comprador e aumentar a taxa de aprovação dos pagamentos.

Para fazer isso, adicione campos relevantes ao objeto enviado, que vem na resposta do callback onSubmit do Brick. Alguns desses campos são: description (esse campo pode ser exibido nos boletos emitidos) e external_reference (id da compra no seu site, que permite o reconhecimento da compra mais fácil). Também é possível adicionar dados complementares sobre o comprador.

Importante
Recomendamos a adesão do protocolo 3DS 2.0, tendo em vista ampliar a probabilidade de aprovação de seus pagamentos, o qual pode ser feito como descrito aqui.
Conheça todos os campos disponíveis para realizar um pagamento completo nas Referências de API.

Teste sua integração
Com a integração finalizada, você poderá testar o recebimento de pagamentos. Para mais informações, acesse a seção Realizar compra teste.

Pix
Server-Side

Ao finalizar a inclusão do formulário de pagamento, é preciso enviar o e-mail do comprador, o tipo e o número do documento, o meio de pagamento utilizado (pix) e o detalhe do valor.

Importante
Além disso, você deverá enviar obrigatoriamente o atributo X-Idempotency-Key. Seu preenchimento é importante para garantir a execução e reexecução de requisições de forma segura, sem o risco de realizar a mesma ação mais de uma vez por engano. Para fazé-lo, atualize nossa biblioteca de SDK ou gere um UUID V4 e envie-o no header de suas chamadas.
Para configurar pagamento com Pix, envie um POST ao endpoint /v1/payments e execute a requisição ou, se preferir, faça a requisição utilizando nossos SDKs.

<?php
  use MercadoPago\Client\Payment\PaymentClient;
  use MercadoPago\Client\Common\RequestOptions;
  use MercadoPago\MercadoPagoConfig;

  MercadoPagoConfig::setAccessToken("YOUR_ACCESS_TOKEN");

  $client = new PaymentClient();
  $request_options = new RequestOptions();
  $request_options->setCustomHeaders(["X-Idempotency-Key: <SOME_UNIQUE_VALUE>"]);

  $payment = $client->create([
 "transaction_amount" => (float) $_POST['<TRANSACTION_AMOUNT>'],
    "payment_method_id" => $_POST['<PAYMENT_METHOD_ID>'],
    "payer" => [
      "email" => $_POST['<EMAIL>']
    ]
  ], $request_options);
  echo implode($payment);
?>
A resposta mostrará o estado pendente do pagamento e todas as informações que você precisa para mostrar ao comprador. O valor transaction_data retornará os dados para código QR.

{
  ...,
  "id": 5466310457,
  "status": "pending",
  "status_detail": "pending_waiting_transfer",
  ...,
  "transaction_details": {
      "net_received_amount": 0,
      "total_paid_amount": 100,
      "overpaid_amount": 0,
      "external_resource_url": null,
      "installment_amount": 0,
      "financial_institution": null
  },
  "point_of_interaction": {
      "type": "PIX",
      "sub_type": null,
      "application_data": {
        "name": "NAME_SDK",
        "version": "VERSION_NUMBER"
      },
      "transaction_data": {
        "qr_code_base64": "iVBORw0KGgoAAAANSUhEUgAABRQAAAUUCAYAAACu5p7oAAAABGdBTUEAALGPC/xhBQAAAAFzUkdCAK7OHOkAAAAgY0hSTQAAeiYAAICEAAD6AAAAgOgAAHUwAADqYAAAOpgAABdwnLpRPAAAIABJREFUeJzs2luO3LiWQNFmI+Y/Zd6vRt36KGNXi7ZOBtcagHD4kNLeiLX33v8DAAAAABD879sDAAAAAAA/h6AIAAAAAGSCIgAAAACQCYoAAAAAQCYoAgAAAACZoAgAAAAAZIIiAAAAAJAJigAAAABAJigCAAAAAJmgCAAAAABkgiIAAAAAkAmKAAAAAEAmKAIAAAAAmaAIAAAAAGSCIgAAAACQCYoAAAAAQCYoAgAAAACZoAgAAAAAZIIiAAAAAJAJigAAAABAJigCA...",
        "qr_code": "00020126600014br.gov.bcb.pix0117john@yourdomain.com0217additional data520400005303986540510.005802BR5913Maria Silva6008Brasilia62070503***6304E2CA",
        "ticket_url": "https://www.mercadopago.com.br/payments/123456789/ticket?caller_id=123456&hash=123e4567-e89b-12d3-a456-426655440000"
      }
  }
  ...,
}
Mostre o status do pagamento
Após criar o pagamento pelo backend utilizando a SDK do Mercado Pago, utilize o id recebido na resposta para instanciar o Status Screen Brick e mostrar para o comprador.

Além de exibir o status do pagamento, o Status Screen Brick também exibirá o código Pix para copiar e colar e o QR Code para o comprador escanear e pagar. Saiba como é simples integrar clicando aqui.

Importante
Caso você tenha utilizado as credenciais de produção de um usuário de teste para gerar o pagamento com Pix, ocorrerá um erro de visualização ao clicar no botão que leva a página do QR Code. Para visualizá-la corretamente, remova o trecho /sandbox da URL da página aberta.
payment-submission-pix-status

Teste sua integração

Outros meios de pagamento
Server-Side

Para configurar pagamentos com boleto bancário, envie um POST com os seguintes parâmetros ao endpoint /v1/payments e execute a requisição ou, se preferir, utilize um de nossos SDKs abaixo.

Importante
Lembre-se que o Brick já resolve a maioria dos parâmetros para enviar o POST. O retorno das informações vem no callback onSubmit, dentro do objeto formData, onde você poderá encontrar parâmetros como: payment_method_id, payer.email e amount.

Além disso, você deverá enviar obrigatoriamente o atributo X-Idempotency-Key. Seu preenchimento é importante para garantir a execução e reexecução de requisições de forma segura, sem o risco de realizar a mesma ação mais de uma vez por engano. Para fazé-lo, atualize nossa biblioteca de SDK ou gere um UUID V4 e envie-o no header de suas chamadas.
Tipo de pagamento	Parâmetro	Valor
Boleto	payment_method_id	bolbradesco
<?php
  use MercadoPago\Client\Payment\PaymentClient;
  use MercadoPago\Client\Common\RequestOptions;
  use MercadoPago\MercadoPagoConfig;

  MercadoPagoConfig::setAccessToken("YOUR_ACCESS_TOKEN");

  $client = new PaymentClient();
  $request_options = new RequestOptions();
  $request_options->setCustomHeaders(["X-Idempotency-Key: <SOME_UNIQUE_VALUE>"]);

  $payment = $client->create([
    "transaction_amount" => (float) $_POST['transactionAmount'],
    "token" => $_POST['token'],
    "description" => $_POST['description'],
    "installments" => $_POST['installments'],
    "payment_method_id" => $_POST['paymentMethodId'],
    "issuer_id" => $_POST['issuer'],
    "payer" => [
      "email" => $_POST['email'],
      "first_name" => $_POST['payerFirstName'],
      "last_name" => $_POST['payerLastName'],
      "identification" => [
        "type" => $_POST['identificationType'],
        "number" => $_POST['number']
      ]
    ]
  ], $request_options);
  echo implode($payment);
?>
A resposta mostrará o status pendente até que o comprador realize o pagamento. Além disso, na resposta à requisição, o parâmetro external_resource_url retornará uma URL que contém as instruções para que o comprador realize o pagamento. Você pode redirecioná-lo para este mesmo link para conclusão do fluxo de pagamento. Veja abaixo um exemplo de retorno.

[
 {
    ...,
    "id": 5466310457,
    "status": "pending",
    "status_detail": "pending_waiting_payment",
    ...,
    "transaction_details": {
        "net_received_amount": 0,
        "total_paid_amount": 100,
        "overpaid_amount": 0,
        "external_resource_url": "https://www.mercadopago.com/mlb/payments/ticket/helper?payment_id=123456789&payment_method_reference_id= 123456789&caller_id=123456",
        "installment_amount": 0,
        "financial_institution": null,
        "payment_method_reference_id": "1234567890"
    }
 }
]
Mostre o status do pagamento
Após criar o pagamento pelo backend utilizando a SDK do Mercado Pago, utilize o id recebido na resposta para instanciar o Status Screen Brick e mostrar para o comprador.

Além de exibir o status do pagamento, o Status Screen Brick também exibirá o código de barras para o comprador copiar e colar, ou escanear e assim fazer o pagamento. Saiba como é simples integrar clicando aqui.

payment-submission-other-payment-methods-status-mlb

Importante
A data de vencimento do boleto pode ser configurada através do envio de requisição POST com parâmetro data_of_expiration ao endpoint /v1/payments. Após o vencimento, o boleto será cancelado.

O prazo de aprovação do boleto é de até 2h úteis. Por isso, configure a data de expiração com no mínimo 3 dias para garantir que o pagamento seja abonado.


Inicializar dados nos Bricks
Client-Side

Cartões
No formulário exibido para pagamento com cartões, é possível inicializar com os campos de documento e e-mail já preenchidos. Para isso, é necessário passar a seguinte configuração no objeto de inicialização do Brick.

settings = {
  ...,
  initialization: {
    ...,
    payer: {
      ...,
      email: '<PAYER_EMAIL_HERE>',
      identification: {
          type: 'string',
          number: 'string',
      },
    }
  }
}
Pix
No formulário exibido para pagamento com Pix, é possível inicializar com o campo de e-mail já preenchido. Para isso, é necessário passar a seguinte configuração no objeto de inicialização do Brick.

settings = {
  ...,
  initialization: {
   ...,
  payer: {
  email: '<PAYER_EMAIL_HERE>'
   }
}
Outros meios de pagamento
No formulário exibido para pagamento com boleto bancário, é possível inicializar com as informações já preenchidas. Para isso, é necessário passar a seguinte configuração no objeto de inicialização do Brick.

settings = {
  ...,
  initialization: {
 ...,
 payer: {
   firstName: '<PAYER_FIRST_NAME_HERE>',
   lastName: '<PAYER_LAST_NAME_HERE>',
   identification: {
    "type": "<PAYER_DOC_TYPE_HERE>",
    "number": "<PAYER_DOC_NUMBER_HERE>",
   },
   email: '<PAYER_EMAIL_HERE>',
   address: {
     zipCode: '<PAYER_ZIP_CODE_HERE>',
     federalUnit: '<PAYER_FED_UNIT_HERE>',
     city: '<PAYER_CITY_HERE>',
     neighborhood: '<PAYER_NEIGHBORHOOD_HERE>',
     streetName: '<PAYER_STREET_NAME_HERE>',
     streetNumber: '<PAYER_STREET_NUMBER_HERE>',
     complement: '<PAYER_COMPLEMENT_HERE>',
   }
 }
}

# Configurações de preferência

Você pode adaptar a integração do Payment Brick ao seu modelo negócio configurando [atributos de preferência](/developers/pt/reference/preferences/_checkout_preferences/post).

Se você oferece compras de valores altos, por exemplo, pode aceitar [pagamentos com dois cartões de crédito](#bookmark_aceite_pagamentos_com_2_cartões_de_crédito) ou ainda [excluir meios de pagamento](#bookmark_defina_os_meios_de_pagamento_desejados) indesejados para a sua operação.

## Exemplo de preferência completa

```json
{
  "items": [
  {
  "id": "item-ID-1234",
  "title": "Meu produto",
  "currency_id": "BRL",
  "picture_url": "https://www.mercadopago.com/org-img/MP3/home/logomp3.gif",
  "description": "Descrição do Item",
  "category_id": "art",
  "quantity": 1,
  "unit_price": 75.76
  }
  ],
  "payer": {
  "name": "<PAYER_NAME_HERE>",
  "surname": "<PAYER_SURNAME_HERE>",
  "email": "<PAYER_EMAIL_HERE>",
  "phone": {
  "area_code": "<PAYER_AREA_CODE_HERE>",
  "number": "<PAYER_PHONE_NUMBER_HERE>"
  },
  "identification": {
  "type": "<PAYER_DOC_TYPE_HERE>",
  "number": "<PAYER_DOC_NUMBER_HERE>"
  },
  "address": {
  "street_name": "Street",
  "street_number": 123,
  "zip_code": "<PAYER_ZIP_CODE_HERE>"
  }
  },
  "back_urls": {
  "success": "https://www.success.com",
  "failure": "http://www.failure.com",
  "pending": "http://www.pending.com"
  },
  "auto_return": "approved",
  "payment_methods": {
  "excluded_payment_methods": [
  {
  "id": "master"
  }
  ],
  "excluded_payment_types": [
  {
  "id": "ticket"
  }
  ],
  "installments": 12
  },
  "notification_url": "https://www.your-site.com/ipn",
  "statement_descriptor": "MEUNEGOCIO",
  "external_reference": "Reference_1234",
  "expires": true,
  "expiration_date_from": "2016-02-01T12:00:00.000-04:00",
  "expiration_date_to": "2016-02-28T12:00:00.000-04:00"
}
```

## Defina os meios de pagamento desejados

Por meio da preferência de pagamento, você pode configurar um meio de pagamento padrão para ser renderizado, excluir algum indesejado, ou ainda escolher um número máximo de parcelas a serem ofertadas.

| Atributo de preferência | Descrição |
| --- | --- |
| `payment_methods` | Classe que descreve os atributos e métodos de meios de pagamento do Payment Brick. |
| `excluded_payment_types` | Método que exclui meios de pagamento indesejados, como cartão de crédito, ticket (boleto), entre outros. |
| `excluded_payment_methods` | Método que exclui bandeiras específicas de cartões de crédito e débito, como Visa, Mastercard, American Express, entre outros. |
| `installments` | Método que define o número máximo de parcelas a serem ofertadas. |
| `purpose` | Ao indicar o valor `wallet_purchase` neste método, o Payment Brick apenas aceitará pagamentos de usuários cadastrados no Mercado Pago, com cartão e saldo em conta. |

Por exemplo:

[[[
```php
<?php
$preference = new MercadoPago\Preference();
// ...
$preference->payment_methods = array(
  "excluded_payment_methods" => array(
  array("id" => "master")
  ),
  "excluded_payment_types" => array(
  array("id" => "ticket")
  ),
  "installments" => 12
);
// ...
?>
```
```node
var preference = {}
preference = {
//...
"payment_methods": {
  "excluded_payment_methods": [
  {
  "id": "master"
  }
  ],
  "excluded_payment_types": [
  {
  "id": "ticket"
  }
  ],
  "installments": 12
	}
//...
}
```
```java
PreferenceClient client = new PreferenceClient();
//...
List<PreferencePaymentMethodRequest> excludedPaymentMethods = new ArrayList<>();
excludedPaymentMethods.add(PreferencePaymentMethodRequest.builder().id("master").build());
excludedPaymentMethods.add(PreferencePaymentMethodRequest.builder().id("amex").build());

List<PreferencePaymentTypeRequest> excludedPaymentTypes = new ArrayList<>();
excludedPaymentTypes.add(PreferencePaymentTypeRequest.builder().id("ticket").build());

PreferencePaymentMethodsRequest paymentMethods =
  PreferencePaymentMethodsRequest.builder()
  .excludedPaymentMethods(excludedPaymentMethods)
  .excludedPaymentTypes(excludedPaymentTypes)
  .installments(12)
  .build();

PreferenceRequest request = PreferenceRequest.builder().paymentMethods(paymentMethods).build();

client.create(request);
//...
```
```ruby
#...
preference_data = {
  # ...
  payment_methods: {
  excluded_payment_methods: [
  { id: 'master' }
  ],
  excluded_payment_types: [
  { id: 'ticket' }
  ],
  installments: 12
  }
  # ...
}
#...
```
```csharp
var paymentMethods = new PreferencePaymentMethodsRequest
{
  ExcludedPaymentMethods = new List<PreferencePaymentMethodRequest>
  {
  new PreferencePaymentMethodRequest
  {
  Id = "master",
  },
  },
  ExcludedPaymentTypes = new List<PreferencePaymentTypeRequest>
  {
  new PreferencePaymentTypeRequest
  {
  Id = "ticket",
  },
  },
  Installments = 12,
};

var request = new PreferenceRequest
{
  // ...
  PaymentMethods = paymentMethods,
};
```
```python
#...
preference_data = {
  "excluded_payment_methods": [
  { "id": "master" }
  ],
  "excluded_payment_types": [
  { "id": "ticket" }
  ],
  "installments": 12
}
#...
```
]]]

## Aceite pagamentos com 2 cartões de crédito

Você pode ativar a opção de oferecer pagamentos com dois cartões de crédito a partir da conta do Mercado Pago. 

Para ativar essa opção de pagamento, acesse "[Opcões de negócio](https://www.mercadopago.com.br/settings/my-business)" e selecione a opção "Receber pagamentos com 2 cartões de crédito".

## Aceite pagamentos somente de usuários cadastrados

Você pode aceitar pagamentos com a carteira do Mercado Pago apenas de usuários cadastrados, com cartão, saldo disponível e Linha de Crédito.

Isto permite que seus clientes tenham suas informações de conta disponíveis no ato do pagamento, tais como seus cartões e endereços salvos. 

> WARNING
>
> Importante
>
> Ao adicionar esta opção, você não poderá receber pagamentos de usuários que não possuem uma conta Mercado Pago ou Mercado Livre, assim como não poderá receber pagamentos via dinheiro ou transferência.

Para aceitar pagamentos somente de usuários cadastrados, adicione o seguinte atributo as suas preferências:

```json
"purpose": "wallet_purchase"
```

Ao completar a ação, sua preferência teria estrutura similar a do exemplo abaixo:

```json
{
  "purpose": "wallet_purchase",
  "items": [
  {
  "title": "Meu produto",
  "quantity": 1,
  "unit_price": 75.76
  }
  ],
}
```

## Altere a data de vencimento de pagamentos em dinheiro

É possível alterar a data de vencimento padrão de pagamentos em dinheiro enviando o campo `date_of_expiration` na requisição de criação da preferência. A data configurada por você deve ser entre 1 dia e 30 dias a partir da data de emissão do pagamento.

Por exemplo:

[[[
```json
===
A data usa o formato ISO 8601: yyyy-MM-dd'T'HH:mm:ssz
===
"date_of_expiration": "2020-05-30T23:59:59.000-04:00"
```
]]]

> NOTE
>
> Nota
>
> O prazo de creditação está entre 1 dia e 2 dias úteis de acordo com o meio de pagamento escolhido. Por isso, recomendamos que você defina a data de expiração com no mínimo 3 dias de intervalo para garantir a realização do pagamento.

Revise os [tempos de creditação por meio de pagamento](https://www.mercadopago.com.br/ajuda/_265) para executar a configuração corretamente.

> WARNING
>
> Importante
>
> Caso o pagamento seja realizado depois da data de expiração, o valor será estornado na conta Mercado Pago do pagador.

## Ative o modo binário

Você pode ativar o modo binário se o modelo de negócios exigir que a aprovação do pagamento seja instantânea. Dessa forma, o pagamento só poderá ser aprovado ou recusado. Se o modo binário estiver desativado, o pagamento poderá ficar pendente (no caso de exigir qualquer ação do comprador) ou em processo (se for necessária uma revisão manual).

Para ativá-lo, basta definir o atributo `binary_mode` da preferência de pagamento como `true`:

```json
"binary_mode": true
```

> WARNING
>
> Importante
>
> A ativação do modo binário simplifica a integração com o Payment Brick, mas pode acarretar no decréscimo da taxa de porcentagem de pagamentos aprovados. Isto porque, para manter o fluxo instantâneo, pagamentos pendentes ou ainda sendo processados serão por padrão automáticamente rejeitados. 

## Defina a vigência das suas preferências

Defina um período de validade para as suas preferências de pagamento a partir dos atributos `expires`, `expiration_date_from` e `expiration_date_to`:

```json
"expires": true,
"expiration_date_from": "2017-02-01T12:00:00.000-04:00",
"expiration_date_to": "2017-02-28T12:00:00.000-04:00"
```

Note que a data deve seguir o formato `ISO 8601: yyyy-MM-dd'T'HH:mm:ssz`.

## Envie descrição na fatura do cartão comprador

Você pode adicionar uma descrição para o seu negócio através do atributo `statement_descriptor` das preferências de pagamento, como mostra o exemplo abaixo: 

```json
"statement_descriptor": "MEUNEGOCIO"
```

Dependendo da bandeira do cartão, a descrição (valor do atributo) aparecerá na fatura do cartão do comprador. 

## Defina uma preferência para diversos itens

Se você precisar criar uma preferência para mais de um item, deverá adicioná-los como uma lista, como mostra o exemplo abaixo:

[[[
```php
<?php
  # Criar um objeto preferência
  $preference = new MercadoPago\Preference();
  # Cria itens na preferência
  $item1 = new MercadoPago\Item
  $item1->title = "Item de Teste 1";
  $item1->quantity = 2;
  $item1->unit_price = 11.96;

  $item2= new MercadoPago\Item
  $item2->title = "Item de Teste 2";
  $item2->quantity = 1;
  $item2->unit_price = 11.96;

  $preference->items = array($item1,$item2);
  # Salvar e postar a preferência
  $preference->save();
?>
```
```node
// Configura sua preferência
var preference = {
  items: [
  { title: 'Meu produto',
  quantity: 1,
  currency_id: '[FAKER][CURRENCY][ACRONYM]',
  unit_price: 75.56 },
	{ title: 'Meu produto 2’,
  quantity: 2,
  currency_id: '[FAKER][CURRENCY][ACRONYM]',
  unit_price: 96.56 }
  ]
};
// Cria um botão de pagamento no seu site
mercadopago.preferences.create(preference)
.then(function(preference){
  // Este valor substituirá o string "$$init_point$$" no seu HTML
  global.init_point = preference.body.init_point;
}).catch(function(error){
  console.log(error);
});
```
```java
// Cria um objeto preferência
PreferenceClient client = new PreferenceClient();
// Cria itens na preferência
PreferenceClient client = new PreferenceClient();

List<PreferenceItemRequest> items = new ArrayList<>();

PreferenceItemRequest item1 =
  PreferenceItemRequest.builder()
  .id("1234")
  .title("Produto 1")
  .quantity(2)
  .currencyId("BRL")
  .unitPrice(new BigDecimal("100"))
  .build(); 
PreferenceItemRequest item2 =
  PreferenceItemRequest.builder()
  .id("12")
  .title("Produto 2")
  .quantity(1)
  .currencyId("BRL")
  .unitPrice(new BigDecimal("100"))
  .build();

items.add(item1);
items.add(item2);

PreferenceRequest request = PreferenceRequest.builder().items(items).build();
// Salvar e postar a preferência
client.create(request);
```
```ruby
sdk = Mercadopago::SDK.new('ENV_ACCESS_TOKEN')
# Create preference data with items
preference_data = {
  items: [
  {
  title: 'Meu produto 1',
  quantity: 1,
  currency_id: '[FAKER][CURRENCY][ACRONYM]',
  unit_price: 75.56
  },
  {
  title: 'Meu produto 2',
  quantity: 2,
  currency_id: '[FAKER][CURRENCY][ACRONYM]',
  unit_price: 96.56
  }
  ]
}

preference_response = sdk.preference.create(preference_data)
preference = preference_response[:response]
```
```python
# Cria itens na preferência
preference_data = {
  "items": [
  {
  "title": "Mi producto",
  "quantity": 1,
  "unit_price": 75.56
  },
  {
  "title": "Mi producto2",
  "quantity": 2,
  "unit_price": 96.56
  }
  ]
}

# Cria a preferência
preference_response = sdk.preference().create(preference_data)
preference = preference_response["response"]
```
```csharp
// Cria o request com múltiplos itens
var request = new PreferenceRequest
{
  Items = new List<PreferenceItemRequest>
  {
  new PreferenceItemRequest
  {
  Title = "Meu produto 1",
  Quantity = 1,
  CurrencyId = "[FAKER][CURRENCY][ACRONYM]",
  UnitPrice = 75.56m,
  },
  new PreferenceItemRequest
  {
  Title = "Meu produto 2",
  Quantity = 2,
  CurrencyId = "[FAKER][CURRENCY][ACRONYM]",
  UnitPrice = 96.56m,
  },
  // ...
  },
};

// Cria um objeto client
var client = new PreferenceClient();

// Cria a preferência
Preference preference = await client.CreateAsync(request);
```
```curl
curl -X POST \
  'https://api.mercadopago.com/checkout/preferences' \
  -H 'Content-Type: application/json' \
  -H 'cache-control: no-cache' \
  -H 'Authorization: Bearer PROD_ACCESS_TOKEN' \
  -d '{
	"items": [
	{
		"id_product":1,
		"quantity":1,
		"unit_price": 234.33,
		"titulo":"Meu produto"
	},
	{
		"id_product":2,
		"quantity":2,
		"unit_price": 255.33,
		"titulo":"Meu produto 2"
	}
]
}'
```
]]]

Lembre-se de que o valor total da preferência será a soma do valor do preço unitário de cada item listado.

## Mostre o valor do envio 

Se você já possui o envio estimado pelo seu site, pode definir o valor do mesmo e mostrá-lo separadamente do valor total no momento do pagamento. 

Para configurar tal cenário, adicione o item `shipments` com o valor que quiser cobrar no atributo `cost` e o valor `not_specified` no atributo `mode`:

```json
{
  "shipments":{
  "cost": 1000,
  "mode": "not_specified",
  }
}
```

## Redirecione o comprador para o seu site

No final do processo de pagamento, você tem a opção de redirecionar o comprador para o seu _site_ novamente. Para isso, adicione o atributo `back_urls` e defina, segundo o status do pagamento, a página desejada para redirecionar o seu comprador quando ele clicar no botão de retorno ao site.

Se deseja que o redirecionamento para os pagamentos aprovados seja automático, sem a renderização de um botão de retorno, é preciso adicionar também o atributo `auto_return` com valor `approved`. 

| Atributo |	Descrição |
| ------------ 	|	-------- | 
| `auto_return` | Os compradores são redirecionados automaticamente para o _site_ quando o pagamento é aprovado. O valor padrão é `approved`. |
| `back_urls` | URL de retorno ao site. Possíveis cenários são:<br/><br/>`success`: URL de retorno perante pagamento aprovado.<br/><br/>`pending`: URL de retorno perante pagamento pendente.<br/><br/>`failure`: URL de retorno perante pagamento rejeitado.

Através das `back_urls`, serão retornados os seguintes parâmetros:

| Parâmetro |	Descrição |
| --- | --- | 
| `payment_id` | ID (identificador) do pagamento do Mercado Pago. |
| `status` | Estado do pagamento. Ex.: `approved` para um pagamento aprovado ou `pending` para um pagamento pendente. |
| `external_reference` | Valor enviado no momento da criação da preferência de pagamento. |
| `merchant_order_id` | ID (identificador) da ordem de pagamento gerada no Mercado Pago. |

> NOTE
> 
> Nota
> 
> Alguns dos parâmetros guardam informações de compra apenas se o comprador completou todo o pagamento no Payment Brick e não abandonou o fluxo antes de retornar ao seu site por meio da `back_urls` de `failure`.

Por exemplo:

[[[
```php
<?php
$preference = new MercadoPago\Preference();
//...
$preference->back_urls = array(
  "success" => "https://www.seu-site/success",
  "failure" => "http://www.seu-site/failure",
  "pending" => "http://www.seu-site/pending"
);
$preference->auto_return = "approved";
// ...
?>
```
```node
var preference = {}
preference = {
  // ...
  "back_urls": {
  "success": "https://www.seu-site/success",
  "failure": "http://www.seu-site/failure",
  "pending": "http://www.seu-site/pending"
  },
  "auto_return": "approved",
  // ...
}
```
```java
PreferenceBackUrlsRequest backUrls =
// ...
PreferenceBackUrlsRequest.builder()
  .success("https://www.seu-site/success")
  .pending("https://www.seu-site/pending")
  .failure("https://www.seu-site/failure")
  .build();

PreferenceRequest request = PreferenceRequest.builder().backUrls(backUrls).build();
// ...
```
```ruby
# ...
preference_data = {
  # ...
  back_urls = {
  success: 'https://www.tu-sitio/success',
  failure: 'https://www.tu-sitio/failure',
  pending: 'https://www.tu-sitio/pendings'
  },
  auto_return: 'approved'
  # ...
}
# ...
```
```csharp
var request = new PreferenceRequest
{
  // ...
  BackUrls = new PreferenceBackUrlsRequest
  {
  Success = "https://www.tu-sitio/success",
  Failure = "http://www.tu-sitio/failure",
  Pending = "http://www.tu-sitio/pendings",
  },
  AutoReturn = "approved",
};
```
```python
preference_data = {
  "back_urls": {
  "success": "https://www.tu-sitio/success",
  "failure": "https://www.tu-sitio/failure",
  "pending": "https://www.tu-sitio/pendings"
  },
  "auto_return": "approved"
}
```
]]]

Ocultar elemento
Veja abaixo como ocultar elementos do Payment Brick.

Ocultar título
Client-Side

-	Brick
Momento de customização	Ao renderizar Brick
Propriedade	customization.hideFormTitle
Tipo	Boolean
Observações	Quando true, oculta a linha de título.
const settings = {
   ...,
   customization: {
       visual: {
           hideFormTitle: true
       }
   }
}
Ocultar botão de pagamento
Client-Side

-	Brick
Momento de customização	Ao renderizar Brick
Propriedade	customization.visual.hidePaymentButton
Tipo	Boolean
Observações	Quando true não mostra o botão de enviar o formulário e passa a ser necessário utilizar a função getFormData para obter os dados do formulário (veja exemplo abaixo).
const settings = {
    ...,
    callbacks: {
        onReady: () => {
            // callback chamado quando o Brick estiver pronto
        },
        onError: (error) => { 
            // callback chamado para todos os casos de erro do Brick
        },
    },
    customization: {
        visual: {
            hidePaymentButton: true
        }
    }
}
Visto que o botão de pagamento padrão foi oculto, será necessário adicionar alguma substituição. Os blocos de código a seguir exemplificam como implementar seu botão de pagamento customizado.

<button type="button" onclick="createPayment();">Custom Payment Button</button>
function createPayment(){
    window.paymentBrickController.getFormData()
        .then(({ formData }) => {
            console.log('formData received, creating payment...');
            fetch("/process_payment", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify(formData),
            })
        })
        .catch((error) => {
            // tratamento de erros ao chamar getFormData()
        });
};


Alterar textos
Client-Side

É possível alterar os textos que vêm carregados no Brick. Para isso, no objeto de inicialização do Brick, é preciso enviar o objeto customization.visual.texts com os valores de textos desejados.

Caso os textos customizados sejam maiores do que o espaço disponível, o texto apresentado será interrompido até o tamanho máximo permitido e o excedente será substituído pelo símbolo "...". Se atente também ao fato que os textos customizados ignoram valores vazios.

Atenção
Os textos alterados sobrescrevem as configurações de idioma passadas para o Brick.
Os textos customizáveis estão indicados abaixo.

const settings = {
  customization: {
    visual: {
      texts: {
        formTitle: "string",
        emailSectionTitle: "string",
        installmentsSectionTitle: "string",
        cardholderName: {
          label: "string",
          placeholder: "string",
        },
        email: {
          label: "string",
          placeholder: "string",
        },
        cardholderIdentification: {
          label: "string",
        },
        cardNumber: {
          label: "string",
          placeholder: "string",
        },
        expirationDate: {
          label: "string",
          placeholder: "string",
        },
        securityCode: {
          label: "string",
          placeholder: "string",
        },
        entityType: {
          placeholder: "string",
          label: "string",
        },
        financialInstitution: {
          placeholder: "string",
          label: "string",
        },
        selectInstallments: "string",
        selectIssuerBank: "string",
        formSubmit: "string",
        paymentMethods: {
          newCreditCardTitle: "string",
          creditCardTitle: "string",
          creditCardValueProp: " string",
          newDebitCardTitle: "string",
          debitCardTitle: "string",
          debitCardValueProp: "string",
          ticketTitle: "string",
          ticketValueProp: "string",
        },
      },
    },
  },
};
Para alterar os textos dos meios de pagamento offline (tickets, Pix e ATM, por exemplo), dentro do objeto de paymentMethods utilize o padrão {paymentMethodId}{ValueProp/Title} .

Importante
Somente serão exibidos os textos customizados caso estes valores apareçam no Brick. Por exemplo, caso o pagamento com ticket não esteja habilitado, não serão exibidos os textos referentes a ticket no Brick.
Anterior

https://www.mercadopago.com.br/developers/pt/docs/checkout-bricks/overview