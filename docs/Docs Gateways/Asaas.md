Introdução
Aprenda o passo a passo para criar cobranças no Asaas.

Com nossa API você pode automatizar seus processos de cobrança, recebimento e pagamento de forma fácil e segura, utilizando várias formas de pagamento: PIX, boleto bancário, cartão de crédito e débito e TED

Para iniciar no processo de criação de cobranças, siga esses passos iniciais
Crie um cliente, a partir dele você terá acesso ao ID do customer, essencial para criação de cobranças;
Crie sua cobrança, no formato que desejar, confira os guias:
Cobranças via boleto
Cobranças via Pix
Cobranças via cartão de crédito
Você também pode criar cobranças onde o seu cliente escolhe a forma de pagamento
Você pode utilizar nossa integração de cobranças como checkout transparente, enviando todas as informações via back-end para a API, utilizando a tela de Fatura (assim podendo aceitar também pagamento com Cartão de Débito) ou utilizando a criação de Link de Pagamento.
Você também pode utilizar o redirecionamento automático após o pagamento em Faturas e Links de Pagamentos
Notificações de cobranças
Como um passo opcional, você pode configurar as notificações que seu cliente irá receber. É possível enviar notificações por e-mail, SMS e WhatsApp. Sendo elas:

Aviso de cobrança recebida
Aviso 10 dias antes do vencimento
Aviso no dia do vencimento
Aviso de cobrança vencida
Aviso a cada 7 dias após vencimento
Aviso de cobrança atualizada
Linha digitável no dia do vencimento
Updated 6 months ago

Aprovação de contas
Cadastro de clientes
Did this page help you?

Introdução
Nossa API permite controle completo das funcionalidades do Pix, como geração de chave, recebimento através de QR Code dinâmico e estático, além de envio de dinheiro (pagamentos) através de chave pix, dados dados bancários e QR Codes.


Esta sessão de nossa documentação foca em orientá-lo no recebimento através de Pix. Para entende como fazer o envio de dinheiro (pagamentos), verifique a sessão de transferências.

Existem três maneiras de receber via Pix:

Gerar uma cobrança com a forma de recebimento "PIX" que realiza as notificações de cobrança automaticamente para o seu cliente e que também permite recuperar o QrCode Dinâmico após a sua criação;
Cadastrando uma chave e informando-a ao seu cliente;
Criando um QrCode estático.
📘
Ao receber um pagamento através da chave Pix ou um QrCode estático, um cliente e uma cobrança serão criados automaticamente para registro desse pagamento em sua conta.

Recebendo cobranças e transferências via Pix
O primeiro passo para receber cobranças ou transferências via Pix é criar uma chave Pix em sua conta.

🚧
Esta é uma funcionalidade que só estará habilitada após a sua conta estar 100% aprovada e a prova de vida ter sido realizada.

❗️
ATENÇÃO: Sem uma chave PIX cadastrada em sua conta, pode haver lentidão no processamento dos pagamentos via PIX.
Se sua conta não tiver uma chave PIX cadastrada, nossa aplicação precisará gerar uma chave temporária para processar o recebimento. Esse processo pode causar demora na conclusão da cobrança, impactando a experiência do seu cliente.

Por isso, recomendamos cadastrar uma chave PIX na sua conta para garantir que os pagamentos sejam processados de forma instantânea.

Criando uma chave Pix
No momento, através da API é possível criar somente chaves aleatórias (EVP). Lembramos que há uma restrição no número de chaves que cada conta pode criar: 5 para pessoa física e 20 para pessoa jurídica.

POST/v3/pix/addressKeys
Confira a referência completa deste endpoint

JSON

{
  "type": "EVP",
}
📘
Por limitações aplicadas pelo Banco Central, deve-se aguardar 1 minuto entre cada chave criada para uma conta.

O Banco Central também aplica os limites de chaves por conta informados acima.


Cobranças via Pix / QR Code dinâmico
A forma mais rápida de ter o dinheiro na conta.

Ofereça o Pix como forma de pagamento, aumente suas vendas e ainda receba o dinheiro em segundos, direto na sua conta digital. Conheça mais.

Criando uma cobrança por Pix
Ao escolher a forma de pagamento por PIX e ter uma chave Pix configurada, um QRCode único é gerado para você.

POST/v3/lean/payments
Confira a referência completa deste endpoint

JSON

{
      "customer": "cus_000005219613",
      "billingType": "PIX",
      "value": 100.90,
      "dueDate": "2023-07-21"
}
Para recuperar a imagem do QRCode e a chave copia e cola, basta enviar o ID dessa cobrança que você acabou de criar no endpoint para recuperar os dados.

GET/v3/payments/id/pixQrCode
Confira a referência completa deste endpoint

A partir desse endpoint você terá acesso a 3 informações, a imagem encodada em Base64 encodedImage, o código copia e cola payload e a data de expiração expirationDate.

📘
O QRCode gerado é do tipo dinâmico com vencimento.* O QRCode expira 12 meses após a data de vencimento.* Pode ser impresso ou disponibilizado em documentos, pois os valores são consultados na hora da leitura do QRCode. Por exemplo: imprimir em um boleto ou carnês de pagamento.* Só pode ser pago uma vez.
🚧
Atenção
Atualmente é possível gerar QR Code Pix dinâmico de pagamento imediato sem possuir uma chave Pix Cadastrada no Asaas. Esse QR Code será vinculado a uma instituição parceira onde o Asaas tem uma chave cadastrada. Todo QR Code obtido desta maneira pode ser pago até 23:59 do mesmo dia. A cada atualização em sua cobrança, é necessário obter um novo QR Code. Entretanto essa funcionalidade será descontinuada no futuro, será enviando um comunicado com 30 dias de antecedência, portanto já indicamos fazer o cadastro da sua chave Pix em Criar uma chave Pix.

Criando um QR Code estático
O QR Code estático funciona da mesma forma que um link de pagamento. Ele só expira se você definir uma data de expiração e pode receber quantos pagamentos você quiser.

Este é o caso ideal para deixar exposto em um restaurante, por exemplo, para receber pagamentos via Pix e você conseguir identificar de onde eles vieram.

Receber pagamentos por QR Codes estáticos
Você pode criar um QrCode estático com um valor pré-definido para a sua chave. O primeiro passo é você ter em mãos qual chave irá receber este pagamento, vamos usar neste exemplo a chave aleatória. Depois, é só chamar o endpoint para gerar um QrCode estático.

POST/v3/pix/qrCodes/static
Confira a referência completa deste endpoint

JSON

{
  "addressKey": "b6295ee1-f054-47d1-9e90-ee57b74f60d9",
  "description": "Churrasco",
  "value": 50.00,
  "format": "ALL",
  "expirationDate": "2023-05-05 14:20:50",
  "expirationSeconds": null
}
No retorno você terá acesso ao id desse QrCode e também ao payload onde terá a imagem do QrCode encodado em Base64.

Ao usar um QrCode estático, nao é necessário que você crie uma cobrança ou defina qual o cliente, pois ao ser pago por alguém, irá automaticamente importar os dados do cliente e criar uma cobrança.

Para que você saiba que um QrCode estático foi pago, você precisa observar os eventos do Webhook para cobranças. Quando receber um evento de pagamento recebido, o campo pixQrCodeId terá o ID do seu QR Code.

Verificar cobranças geradas por um QR Code estático
Com o pixQrCodeId você chama o endpoint de listas cobranças para ter mais dados sobre as cobranças criadas a partir deste QR Code.

GET/v3/payments?pixQrCodeId=9bea9bcd226b45c7980065f598be54d5
Confira a referência completa deste endpoint

Introdução
Assinaturas devem ser utilizadas quando a cobrança é feita periodicamente de forma recorrente, como por exemplo cobrar o cliente mensalmente pelo uso do seu software, cobrança mensal de aluguéis, etc. Além de mensal é possível escolher outras periodicidades como trimestral, semestral, entre outras.


Diferença entre assinaturas e parcelamentos
Assinaturas são diferentes de cobranças parceladas: ao gerar uma cobrança parcelada, todas as parcelas são geradas de uma só vez. Já no caso da assinatura, uma cobrança será a cada mês (ou conforme a periodicidade selecionada) e enviada para o cliente. Caso a forma de pagamento da assinatura seja cartão de crédito, o cartão do cliente será cobrado automaticamente no data de vencimento da cobrança.

Assinaturas e parcelamentos diferem também quando são pagos com cartão de crédito: no caso de parcelamento, o valor total da compra é cobrado no cartão do cliente de uma só vez, parcelando conforme especificado. No caso de assinaturas, uma nova transação é lançada mensalmente (ou de acordo com a periodicidade selecionada) no cartão do cliente, até que a assinatura seja removida ou o cartão sendo utilizado se torne inválido (no caso expiração, cancelamento, etc).

Fluxo de criação de cobranças de uma assinatura
Cobranças recorrentes pertencentes a uma assinatura são geradas 40 dias antes do vencimento (dueDate). Dessa forma, uma assinatura que foi configurada para vencer 5 dias após sua criação, com vencimento mensal, já terá duas cobranças pertencentes a ela no sistema.


No infográfico acima o cliente está com a configuração padrão de notificação de 10 dias antes do vencimento ativada. Dessa forma ao criar a assinatura, duas cobranças são criadas, mas somente as notificações do vencimento da primeira são enviadas ao cliente. A notificação da cobrança seguinte será enviada apenas 10 dias antes de seu vencimento.

Prazo para geração de cobranças em assinaturas
As cobranças são geradas, por padrão, 40 dias antes do vencimento, para permitir maior liberdade a você oferecer ao seu cliente a cobrança quando achar mais viável. Porém, caso deseje, é possível alterar o prazo de geração 14 ou 7 dias antes da cobrança vencer. Nesse caso, basta entrar em contato com o seu Gerente de Contas e fazer a solicitação.

Updated 6 months ago

FAQ
Criando uma assinatura
Did this page help you?

Criando uma assinatura
Para criar uma assinatura, basta chamar o endpoint de assinaturas.

POST /v3/subscriptions
Confira a referência completa deste endpoint

JSON

{
  "customer": "cus_0T1mdomVMi39",
  "billingType": "BOLETO",
  "nextDueDate": "2023-10-15",
  "value": 19.9,
  "cycle": "MONTHLY",
  "description": "Assinatura Plano Pró",
}
O campo nextDueDate define quando será feita a primeira cobrança da assinatura, que irá seguir o ciclo conforme configurado. Os ciclos disponíveis são:

WEEKLY - Semanal
BIWEEKLY - Quinzenal (2 semanas)
MONTHLY - Mensal
QUARTERLY - Trimestral
SEMIANNUALLY - Semestral
YEARLY - Anual
A assinatura funciona como um agendador de criação de cobranças. No exemplo acima, uma nova cobrança do tipo boleto será criada mensalmente e enviada ao seu cliente, conforme configurações de notificação.

Depois de criada, você terá em mãos o ID da assinatura que segue um padrão semelhante a este: sub_VXJBYgP2u0eO.

Verificando se uma assinatura foi paga
Para saber se uma assinatura foi paga, você deve acompanhar o webhook para cobranças. Quando uma nova cobrança é criada referente a sua assinatura, você receberá um evento PAYMENT_CREATED e o campo subscription conterá o ID da sua assinatura.

Assim que a cobrança relacionada a assinatura, você receberá o evento PAYMENT_RECEIVED em caso de pagamento por boleto, como no exemplo.

Você também poderá verificar as cobranças criadas de uma assinatura através do endpoint:

GET /v3/subscriptions/{id}/payments
Confira a referência completa deste endpoit

Editar assinatura
É possível alterar todas as informações de uma assinatura do tipo BOLETO ou PIX.

POST /v3/subscriptions/{id}
Veja a referência completa deste endpoint.

Ao atualizar o valor da assinatura ou forma de pagamento somente serão afetadas mensalidade futuras. Para atualizar as mensalidades já criadas mas não pagas com a nova forma de pagamento e/ou novo valor, é necessário passar o parâmetro updatePendingPayments: true.

Recuperar cobranças da assinatura
Diferente de um parcelamento, em que no retorno da criação é devolvido o id da primeira cobrança, no caso de assinaturas, a cobrança é criada apenas depois da assinatura, e não junto, e por isso não é possível recuperar esse id no ato da criação.

Para ter acesso à primeira cobrança criada da assinatura, é necessário consumir a API uma segunda vez no endpoint:

GET /v3/subscriptions/{id}/payments
Veja a referência completa deste endpoint.

Esse endpoint irá retornar todas as cobranças já criadas nesta assinatura, assim como seus status.

Updated 6 months ago

Introdução
Criando assinatura com cartão de crédito
Did this page help you?

Criando assinatura com cartão de crédito
Assim como na cobrança, os dados do cartão e do portador podem ser enviados na requisição de criação da assinatura para que o pagamento já seja processado. A diferença é que no caso da cobrança o cartão do cliente é cobrado no momento da criação da mesma, já no caso da assinatura, o cartão será validado no momento da criação, porém a cobrança será feita somente no vencimento da primeira mensalidade. É importante ressaltar que a validação feita no momento a criação não garante que cobrança ocorrerá com sucesso no vencimento, pois neste meio-tempo o cartão pode ter sido cancelado, expirado, não ter limite, entre outros.

Para tal, ao executar a requisição de criação da assinatura, basta enviar os dados do cartão de crédito juntamente com os dados do titular através dos objetos creditCard e creditCardHolderInfo. Se a transação for autorizada a assinatura será criada e a API retornará HTTP 200. Caso contrário a assinatura não será persistida e será retornado HTTP 400.

📘
Dica!
Caso você queira criar uma assinatura que a primeira cobrança será cobrada no ato da criação, informe o nextDueDate como a data atual.

Uma vez criada a assinatura com cartão de crédito, a cobrança será feita mensalmente (ou outra periodicidade definida) no cartão do cliente até que ele se torne inválido ou você remova a assinatura.

🚧
Atenção
Caso você opte por capturar na interface do seu sistema os dados do cartão do cliente, é obrigatório o uso de SSL (HTTPS), caso contrário sua conta pode ser bloqueada para transações via cartão de crédito.
Para se evitar timeouts e decorrentemente duplicidades na captura, recomendamos a configuração de um timeout mínimo de 60 segundos para este request.
POST /v3/subscriptions
Confira a referência completa deste endpoint

JSON

{
  "customer": "cus_0T1mdomVMi39",
  "billingType": "CREDIT_CARD",
  "nextDueDate": "2023-10-15",
  "value": 19.9,
  "cycle": "MONTHLY",
  "description": "Assinatura Plano Pró",
  "creditCard": {
    "holderName": "marcelo h almeida",
    "number": "5162306219378829",
    "expiryMonth": "05",
    "expiryYear": "2021",
    "ccv": "318"
  },
  "creditCardHolderInfo": {
    "name": "Marcelo Henrique Almeida",
    "email": "marcelo.almeida@gmail.com",
    "cpfCnpj": "24971563792",
    "postalCode": "89223-005",
    "addressNumber": "277",
    "addressComplement": null,
    "phone": "4738010919",
    "mobilePhone": "47998781877"
  },
}
Como alterar a data de vencimento ou o valor?
Para conseguir alterar o valor ou vencimento de uma assinatura, você precisa obrigatoriamente ter a tokenização ativa em sua conta.

Essa funcionalidade permite você cobrar de seus clientes recorrentemente sem a necessidade deles informarem todos os dados de cartão de crédito novamente. Tudo isso de forma segura por meio de um token.

🚧
Atenção
A funcionalidade de tokenização está previamente habilitada em Sandbox e você já pode testá-la. Para uso em produção, é necessário solicitar a habilitação da funcionalidade ao seu gerente de contas. A habilitação da funcionalidade está sujeita a análise prévia, podendo ser aprovada ou negada de acordo com os riscos da operação.
O token é armazenado por cliente, não podendo ser utilizado em transações de outros clientes.
Para editar a assinatura você não precisa informar o token, mas precisa que ele esteja ativado em sua conta.

POST /v3/subscriptions/{id}
Veja a referência completa deste endpoint.

Além disso, ao atualizar o valor da assinatura ou forma de pagamento somente serão afetadas mensalidade futuras. Para atualizar as mensalidades já criadas mas não pagas com a nova forma de pagamento e/ou novo valor, é necessário passar o parâmetro updatePendingPayments: true.

Como alterar o cartão de crédito de uma assinatura?
Você pode atualizar o cartão de crédito de uma assinatura sem realizar uma cobrança imediata! Essa é a maneira recomendada para atualizar os dados do cartão em uma assinatura recorrente.

Atualizar sem cobrança imediata:

PUT /v3/subscriptions/{id}/creditCard
Veja a referência completa deste endpoint.

JSON

{
  "creditCard": {
    "holderName": "John Doe",
    "number": "1234567890123456",
    "expiryMonth": "4",
    "expiryYear": "2025",
    "ccv": "123"
  },
  "creditCardHolderInfo": {
    "name": "John Doe",
    "email": "john.doe@asaas.com",
    "cpfCnpj": "12345678901",
    "postalCode": "12345678",
    "addressNumber": "123",
    "addressComplement": null,
    "phone": null,
    "mobilePhone": null
  },
  "creditCardToken": "a75a1d98-c52d-4a6b-a413-71e00b193c99",
  "remoteIp": "116.213.42.532"
}
Como poderia fazer upgrade de um plano de assinatura?
Pode acontecer de você ter um cliente que fez uma assinatura mensal, mas no meio do período quer mudar o plano para um superior, mais caro, por exemplo ou migrar para o plano anual. Se você tiver a tokenização ativa na sua conta, poderá alterar o valor da assinatura e/ou data, caso contrário, o recomendado é remover a assinatura atual e criar uma nova em seguida.

Caso o seu cliente tenha valores proporcionais para acertar, recomendamos verificar as cobranças em aberto, calcular qual seria o valor extra, gerar uma nova cobrança do valor poporcional e depois editar sua assinatura para os novos valores e/ou data.

Cobranças via boleto
Comece a aceitar pagamentos de boletos online com o Asaas.

As cobranças são a principal forma de receber dinheiro em sua conta no Asaas. Com elas você pode receber pagamentos por Boleto, Cartão de crédito, Cartão de débito e Pix. Este primeiro guia irá te mostrar como criar um fluxo para boletos. Conheça mais.


Criando uma cobrança por boleto
Ao criar um cobrança, automaticamente um boleto será criado. Lembrando que a taxa referente ao pagamento de um boleto só é descontada da sua conta em caso de pagamento do mesmo.

POST/v3/lean/payments
Confira a referência completa deste endpoint

JSON

{
      "customer": "cus_000005219613",
      "billingType": "BOLETO",
      "value": 100.00,
      "dueDate": "2023-07-21"
}
Olhando para o objeto retornado, temos acesso a propriedade bankSlipUrl que é o arquivo PDF do boleto que acabou de ser gerado.

Cobrança parcelada
Você também pode facilmente criar uma cobrança parcelada e recuperar o carnê desta cobrança com todos os boletos do parcelamento.

Primeiro, vamos criar nossa cobrança parcelada em 10 vezes.

POST/v3/lean/payments
Confira a referência completa deste endpoint

JSON

{
  "customer": "cus_000005219613",
  "billingType": "BOLETO",
  "value": 2000.00,
  "dueDate": "2023-07-21",
  "installmentCount": 10,
  "installmentValue": 200.00
}
No retorno feito pela API já podemos ver que o campo installment veio preenchido com o ID do parcelamento: 24ef7e81-7961-41b7-bd28-90e25ad2c3d7.

Carnê de pagamentos
Para gerar o carnê você só precisa fazer uma chamada GET para o seguinte endpoint:

GET/v3/installments/24ef7e81-7961-41b7-bd28-90e25ad2c3d7/paymentBook

Confira a referência completa deste endpoint

Note que foi usado o ID do parcelamento que acabamos de receber ao criar o mesmo, este endpoint retorna um arquivo em PDF com todos os boletos gerados.

Boleto com descontos para pagamento antecipado
Para que o Asaas cobre juros e multa na hora que um boleto for pago em atraso, você deve informar isso na criação da cobrança. Por exemplo, se você desejar dar um desconto de 10% para quem pagar 5 dias antes do vencimento, basta enviar a criação da cobrança dessa forma:

POST/v3/lean/payments
Confira a referência completa deste endpoint

JSON

{
  "customer": "cus_000005219613",
  "billingType": "BOLETO",
  "value": 2000.00,
  "dueDate": "2023-07-21",
  "discount": {
     "value": 10,
     "dueDateLimitDays": 5,
     "type": "PERCENTAGE"
}
Após a cobrança ser paga, se você fizer uma busca pela mesma, poderá ver que existirá um campo originalValue, indicando que o campo value está diferente do valor definido originalmente. Essa informação também estará presente no retorno do Webhook.

Boleto com juros e multas
Da mesma forma que você pode adicionar descontos para pagamentos antecipados, você pode definir juros e multas para pagamentos em atraso.

POST/v3/lean/payments
Confira a referência completa deste endpoint

JSON

{
  "customer": "cus_000005219613",
  "billingType": "BOLETO",
  "value": 2000.00,
  "dueDate": "2023-07-21",
  "interest": {
     "value": 1,
  },
  "fine": {
     "value": 2,
  },
}
Isso irá adicionar 1% de juros ao mês e 2% de multa em caso de atraso. A mesma informação sobre o originalValue se encaixa nesse formato também.

📘
Após o boleto ser pago, no retorno do Webhook você terá acesso ao campo interestValue, que mostra a soma dos juros e multa que foram aplicadas na cobrança.

Obter linha digitável do boleto
Se você precisar da linha digitável para exibir na tela ao seu cliente, é necessário fazer uma nova chamada na API.

GET/v3/lean/payments/{id}/identificationField
Confira a referência completa deste endpoint

JSON

{
  "identificationField": "00190000090275928800021932978170187890000005000",
  "nossoNumero": "6543",
  "barCode": "00191878900000050000000002759288002193297817"
}
🚧
Caso a cobrança seja atualizada, a linha digitável também sofrerá alterações. O indicado é que a cada nova atualização da cobrança a linha digitável seja novamente recuperada, garantindo que você sempre estará exibindo a linha digitável atualizada.

Como adicionar o QRCode do Pix no PDF do boleto?
Para que um QRCode de Pix apareça em todos os PDFs de boletos gerados pelo Asaas, basta você ter cadastrado uma chave Pix na sua conta.

Referência da API
📘
Confira a referência completa do endpoint Cobranças(/v3/lean/payments)
Acesse nossa referêncida da API

Cobranças via cartão de crédito
Segurança e praticidade nas cobranças online.

O Asaas aceita diversas bandeiras de cartão de forma fácil e sem mensalidade. Você pode fazer vendas à vista, parceladas e recorrentes. Conheça mais.


Criando uma cobrança por cartão de crédito
É possível seguir dois passos, um deles é criar uma cobrança do tipo cartão de crédito e redirecionar o usuário para a tela de fatura para fazer o pagamento.

POST/v3/lean/payments
Confira a referência completa deste endpoint

JSON

{
      "customer": "cus_000005219613",
      "billingType": "CREDIT_CARD",
      "value": 109.90,
      "dueDate": "2023-07-21"
}
Ao criar uma cobrança com a forma de pagamento cartão de crédito, você redireciona o cliente para a URL da fatura (invoiceUrl) afim de que ele informe os dados do cartão através da interface do Asaas.

É possível gerar uma cobrança que aceite cartão de débito?
Enviando os dados do cartão pela API, infelizmente não.

Mas você pode enviar o cliente para a invoiceUrl como descrito acima, se o billingType for CREDIT_CARD ou UNDEFINED a opção de Cartão de Débito estará habilitada na fatura.

Criar uma cobrança com cartão de crédito e já realizar o pagamento
O segundo passo é já enviar os dados do cartão de crédito na hora da criação da cobrança. Dessa forma é possível processar o pagamento na hora da criação da cobrança.

Para tal, ao executar a requisição de criação da cobrança, basta enviar os dados do cartão de crédito juntamente com os dados do titular através dos objetos creditCard e creditCardHolderInfo. É importante que os dados do titular sejam exatamente os mesmos cadastrados no banco emissor do cartão, caso contrário a transação poderá ser negada por suspeita de fraude.

Se a transação for autorizada a cobrança será criada e a API retornará HTTP 200. Caso contrário a cobrança não será persistida e será retornado HTTP 400.

Se estiver em Sandbox, você pode usar números de cartão de crédito para teste.

POST/v3/lean/payments
Confira a referência completa deste endpoint

JSON

{
      "customer": "cus_000005219613",
      "billingType": "CREDIT_CARD",
      "value": 100.00,
      "dueDate": "2023-07-21",
      "creditCard": {
        "holderName": "marcelo h almeida",
        "number": "5162306219378829",
        "expiryMonth": "05",
        "expiryYear": "2024",
        "ccv": "318"
      },
      "creditCardHolderInfo": {
        "name": "Marcelo Henrique Almeida",
        "email": "marcelo.almeida@gmail.com",
        "cpfCnpj": "24971563792",
        "postalCode": "89223-005",
        "addressNumber": "277",
        "addressComplement": null,
        "phone": "4738010919",
        "mobilePhone": "47998781877"
      },
      "remoteIp": "116.213.42.532"
}
📘
Independente da data de vencimento informada, a captura (cobrança no cartão do cliente) será efetuada no momento da criação da cobrança.* Caso você opte por capturar na interface do seu sistema os dados do cartão do cliente, é obrigatório o uso de SSL (HTTPS), caso contrário sua conta pode ser bloqueada para transações via cartão de crédito.* Para se evitar timeouts e decorrentemente duplicidades na captura, recomendamos a configuração de um timeout mínimo de 60 segundos para este request.
Tokenização de cartão de crédito
Ao realizar uma primeira transação para o cliente com cartão de crédito, a resposta da API lhe devolverá o atributo creditCardToken.

Em posse dessa informação, nas próximas transações, o atributo creditCardToken pode substituir os objetos creditCard e creditCardHolderInfo e ser informado diretamente na raiz da requisição, não necessitando assim que os objetos sejam informados novamente.

POST/v3/lean/payments
Confira a referência completa deste endpoint

JSON

{
      "customer": "cus_000005219613",
      "billingType": "CREDIT_CARD",
      "value": 100.00,
      "dueDate": "2023-07-21",
      "creditCardToken": "76496073-536f-4835-80db-c45d00f33695",
      "remoteIp": "116.213.42.532"
}
Você também pode criar um token a qualquer momento. Tendo em mão os dados dos clientes, basta enviar para o endpoint de tokenização e você receberá o creditCardToken.

POST/v3/creditCard/tokenize
Confira a referência completa deste endpoint

JSON

{
      "customer": "cus_000005219613",
      "creditCard": {
        "holderName": "marcelo h almeida",
        "number": "5162306219378829",
        "expiryMonth": "05",
        "expiryYear": "2024",
        "ccv": "318"
      },
      "creditCardHolderInfo": {
        "name": "Marcelo Henrique Almeida",
        "email": "marcelo.almeida@gmail.com",
        "cpfCnpj": "24971563792",
        "postalCode": "89223-005",
        "addressNumber": "277",
        "addressComplement": null,
        "phone": "4738010919",
        "mobilePhone": "47998781877"
      },
      "remoteIp": "116.213.42.532"
}
A API retornará para você os últimos 4 dígitos do cartão creditCardNumber e a bandeira creditCardBrand do cartão (caso você queira exibir em tela, por exemplo), além do creditCardToken.

Essa funcionalidade é interessante caso você desenvolva uma funcionalidade de "Salvar dados de pagamentos" na sua aplicação.

🚧
A funcionalidade de tokenização está previamente habilitada em Sandbox e você já pode testá-la. Para uso em produção, é necessário solicitar a habilitação da funcionalidade ao seu gerente de contas. A habilitação da funcionalidade está sujeita a análise prévia, podendo ser aprovada ou negada de acordo com os riscos da operação.* O token é armazenado por cliente, não podendo ser utilizado em transações de outros clientes.
Parcelamento no cartão
Você também pode facilmente criar uma cobrança parcelada diretamente no cartão de crédito do cliente.

POST/v3/lean/payments
Confira a referência completa deste endpoint

JSON

{
  "customer": "cus_000005219613",
  "billingType": "CREDIT_CARD",
  "value": 2000.00,
  "dueDate": "2023-07-21",
  "installmentCount": 10,
  "installmentValue": 200,
  "creditCard": {
      "holderName": "marcelo h almeida",
      "number": "5162306219378829",
      "expiryMonth": "05",
      "expiryYear": "2024",
      "ccv": "318"
    },
    "creditCardHolderInfo": {
      "name": "Marcelo Henrique Almeida",
      "email": "marcelo.almeida@gmail.com",
      "cpfCnpj": "24971563792",
      "postalCode": "89223-005",
      "addressNumber": "277",
      "addressComplement": null,
      "phone": "4738010919",
      "mobilePhone": "47998781877"
    },
    "remoteIp": "116.213.42.532"
}

🚧
Atenção
É permitido a criação de parcelamentos no cartão de crédito em até 21x para cartões de bandeira Visa e Master.
Anteriormente, era suportado parcelamentos de até 12 parcelas para todas as bandeiras.
Para outras bandeiras, exceto Visa e Master, o limite continua sendo de 12 parcelas.

❗️
Importante
Para cobranças avulsas (1x) não deve-se usar os atributos do parcelamento: installmentCount, installmentValue e totalValue. Se for uma cobrança em 1x, usa-se apenas o value.

Somente cobranças com 2 ou mais parcelas usa-se os atributos do parcelamento.

Retorno de erros para pagamentos e tokenização de cartão de créditos.
Por padrão, caso não haja nada de errado com os dados informados do cartão e ocorra algum problema na transação, a API retornará um erro genérico para você.

JSON

{
    "errors": [
        {
            "code": "invalid_creditCard",
            "description": "Transação não autorizada. Verifique os dados do cartão de crédito e tente novamente."
        }
    ]
}
Atuamos dessa forma por motivos de segurança para que pessoas mal intencionadas não usem o Asaas para testar cartões de crédito extraviados

🚧
Você pode ter acesso ao erro real que as transações apresentam solicitando ao seu gerente de contas que essa funcionalidade seja habilitada. Será feito uma análise prévia para a liberação. A recomendação é que esse erro real nunca seja mostrado para o usuário final.

Referência da API
📘
Confira a referência completa do endpoint Cobranças(/v3/lean/payments)
Acesse nossa referêncida da API

Updated 6 months ago

Cobranças via boleto
Criar uma cobrança parcelada
Did this page help you?

Criar uma cobrança parcelada
Para criar uma cobrança parcelada, ao invés de enviar o parâmetro value, envie installmentCount
e installmentValue, que representam o número de parcelas e o valor da cada parcela respectivamente.

Request
Response

{
  "customer": "{CUSTOMER_ID}",
  "billingType": "BOLETO",
  "installmentCount": 6,
  "installmentValue": 20,
  "dueDate": "2017-06-10",
  "description": "Pedido 056984",
  "externalReference": "056984",
  "discount": {
    "value": 10,
    "dueDateLimitDays": 0
  },
  "fine": {
    "value": 1
  },
  "interest": {
    "value": 2
  }
}
Caso prefira informar apenas o valor total do parcelamento, envie o campo totalValue no lugar do installmentValue com o valor desejado. Se não for possível a divisão exata dos valores de cada parcela, a diferença sera compensada na última parcela.

Por exemplo, um parcelamento com o valor total de R$ 350,00 divido em 12 vezes geraria 11 parcelas no valor de R$: 29,16, sendo a décima segunda parcela no valor de R$: 29,24, totalizando R$: 350.00.

A resposta em caso de sucesso será a primeira cobrança do parcelamento. Caso queira recuperar todas as parcelas basta executar a seguinte requisição com o installment retornado :

>

Outras ações sobre o parcelamento podem ser encontradas em nossa seção de parcelamentos.

🚧
Atenção
É permitido a criação de parcelamentos no cartão de crédito em até 21x para cartões de bandeira Visa e Master.
Anteriormente, era suportado parcelamentos de até 12 parcelas para todas as bandeiras.
Para outras bandeiras, exceto Visa e Master, o limite continua sendo de 12 parcelas.

❗️
Importante
Para cobranças avulsas (1x) não deve-se usar os atributos do parcelamento: installmentCount, installmentValue e totalValue. Se for uma cobrança em 1x, usa-se apenas o value.

Somente cobranças com 2 ou mais parcelas usa-se os atributos do parcelamento.

Redirecionamento após o pagamento
Redirecione o cliente de volta para a sua aplicação depois de um pagamento bem sucedido em nossa fatura


Utilizando a URL de Retorno, é possível que o pagamento seja processado completamente na interface do Asaas, com seu cliente sendo redirecionado de volta para o seu site após a conclusão do pagamento.


A URL de Retorno funciona com cobranças, links de pagamento e assinaturas, sendo possível escolher entre redirecionamento automático autoRedirect ou não. Caso não seja escolhido o redirecionamento automático, após a conclusão do pagamento pelo seu cliente, um botão com o texto “Ir para o site” será mostrado.

O autoRedirect funciona para pagamentos via cartão de crédito, cartão de débito (somente na fatura) e Pix, pois são os meios de pagamentos que permitem confirmação de pagamento instantânea.

A URL informada deve ser obrigatoriamente do mesmo domínio cadastrado em seus dados comerciais, que você encontra em "Configurações da conta" na aba "Informações".


Criando uma fatura com redirecionamento automático
A forma de criação de cobrança é a mesma, sendo apenas necessário um atributo adicional, o callback. Caso ele seja informado, sua cobrança estará configurada para enviar o cliente de volta ao seu site após o pagamento.

POST/v3/lean/payments
Confira a referência completa deste endpoint

JSON

{
  "customer": "cus_000005219613",
  "billingType": "PIX",
  "value": 2000.00,
  "dueDate": "2023-07-21",
  "callback":{
    "successUrl": "https://seusite.com/redirect",
    "autoRedirect": false // somente enviar em caso de desativação do redirect automatico
  }
}
📘
Caso você tenha definido o autoRedirect comofalse um botão com o texto "Ir para o site" será exibido para o seu cliente após a conclusão do pagamento.

Após criar uma cobrança com URL de Retorno, você pode redirecionar seu cliente para a URL no atributo invoiceUrl do JSON de resposta. No momento que o pagamento for concluído, ele será enviado para a URL que você definiu.

Caso o cliente acesse novamente o link da fatura (invoiceUrl) em outro momento, ele não será mais redirecionado para o seu site pois o pagamento já terá sido concluído anteriormente. Neste caso, ele verá apenas uma fatura paga.

📘
Você pode informar o parâmetro ?autoRedirect=true na URL da fatura caso queira que o usuário seja sempre redirecionado quando acessar o invoiceUrl.

Você também poderá atualizar uma Cobrança enviando os mesmos atributos no endpoint de atualização de cobrança.

Uma tela com um carregamento de 5 segundos é mosrada ao cliente ao realizar o pagamento com sucesso.
Uma tela com um carregamento de 5 segundos é mosrada ao cliente ao realizar o pagamento com sucesso.

Criando um link de pagamento com redirecionamento automático
Da mesma forma, é possível criar um link de pagamento que, ao sucesso do pagamento, redireciona o cliente ao link informado.

POST/v3/paymentLinks
Confira a referência completa deste endpoint

JSON

{
  "name": "Meu link da pagamento",
  "billingType": "UNDEFINED",
  "value": 2000.00,
  "chargeType": "DETACHED",
  "callback":{
    "successUrl": "https://seusite.com/redirect",
    "autoRedirect": false // somente enviar em caso de desativação do redirect automatico
  }
}
📘
Da mesma forma que na fatura, caso você tenha definido o autoRedirect comofalse um botão com a mensagem "ir para o site" será mostrado na tela de pagamento aprovado.

Após criar o Link de Pagamento com URL de sucesso, você pode redirecionar seu cliente a url retornada. No momento que o pagamento for confirmado, ele será enviado para a URL que você definiu.

Exemplo de botão de retorno no link de pagamento quando o `autoRedirect `é desativado.
Exemplo de botão de retorno no link de pagamento quando o autoRedirect é desativado.

Você também poderá atualizar um Link de Pagamento enviando os mesmos atributos no endpoint de atualização de link de pagamento.

Chargeback
Quando uma cobrança sofre chargeback, algumas informações são retornadas:

O campo chargeback pode possuir no atributo status:

REQUESTED, IN_DISPUTE, DISPUTE_LOST, REVERSED e DONE
O campo chargeback pode possuir no atributo reason:

ABSENCE_OF_PRINT - Ausência de impressão
ABSENT_CARD_FRAUD - Fraude em ambiente de cartão não presente
CARD_ACTIVATED_PHONE_TRANSACTION - Transação telefônica ativada por cartão
CARD_FRAUD - Fraude em ambiente de cartão presente
CARD_RECOVERY_BULLETIN - Boletim de negativação de cartões
COMMERCIAL_DISAGREEMENT - Desacordo comercial
COPY_NOT_RECEIVED - Cópia não atendida
CREDIT_OR_DEBIT_PRESENTATION_ERROR - Erro de apresentação de crédito / débito
DIFFERENT_PAY_METHOD - Pagamento por outros meios
FRAUD - Sem autorização do portador do cartão
INCORRECT_TRANSACTION_VALUE - Valor da transação é diferente
INVALID_CURRENCY - Moeda inválida
INVALID_DATA - Dados inválidos
LATE_PRESENTATION - Apresentação tardia
LOCAL_REGULATORY_OR_LEGAL_DISPUTE - Contestação regulatória / legal local
MULTIPLE_ROCS - ROCs múltiplos
ORIGINAL_CREDIT_TRANSACTION_NOT_ACCEPTED - Transação de crédito original não aceita
OTHER_ABSENT_CARD_FRAUD - Outras fraudes - Cartão ausente
PROCESS_ERROR - Erro de processamento
RECEIVED_COPY_ILLEGIBLE_OR_INCOMPLETE - Cópia atendida ilegível / incompleta
RECURRENCE_CANCELED - Recorrência cancelada
REQUIRED_AUTHORIZATION_NOT_GRANTED - Autorização requerida não obtida
RIGHT_OF_FULL_RECOURSE_FOR_FRAUD - Direito de regresso integral por fraude
SALE_CANCELED - Mercadoria / serviços cancelado
SERVICE_DISAGREEMENT_OR_DEFECTIVE_PRODUCT - Mercadoria / serviço com defeito ou em desacordo
SERVICE_NOT_RECEIVED - Mercadoria / serviços não recebidos
SPLIT_SALE - Desmembramento de venda
TRANSFERS_OF_DIVERSE_RESPONSIBILITIES - Transf. de responsabilidades diversas
UNQUALIFIED_CAR_RENTAL_DEBIT - Débito de aluguel de carro não qualificado
USA_CARDHOLDER_DISPUTE - Contestação do portador de cartão (EUA)
VISA_FRAUD_MONITORING_PROGRAM - Programa Visa de monitoramento de fraude
WARNING_BULLETIN_FILE - Arquivo boletim de advertência
Para saber mais sobre Chargeback, clique aqui.

Estornos
Após uma cobrança ter estornos, o atributo refunds é retornado no objeto da mesma. Um exemplo retornado:

JSON

"refunds": [
  {
    "dateCreated": "2022-02-21 10:28:40",
    "status": "DONE",
    "value": 2.00,
    "description": "Pagamento a mais",
    "endToEndIdentifier": null,
    "transactionReceiptUrl": "https://www.asaas.com/comprovantes/6677732109104548",
    "refundedSplits": [
      {
        "id": "cff860dd-148e-48ca-ac8e-849684175158",
        "value": 10,
        "done": true
      }
    ]
  }
]
Os status disponíveis no retorno do campo refunds são:

PENDING, CANCELLED e DONE
https://docs.asaas.com/docs/visao-geral