Credenciais, Certificado e Autorização
Nesta página você encontra informações sobre credenciais, certificado e autorização da API Pix.


A API Pix Efí oferece recursos avançados para integração com sua aplicação, permitindo que você crie soluções personalizadas e ofereça opções de pagamento inovadoras aos seus clientes. Com nossa API é possível criar cobranças, verificar os Pix recebidos, devolver e enviar Pix.

Para integrar a API Pix Efí ao seu sistema ou sua plataforma, é necessário ter uma Conta Digital Efí. Uma vez com acesso, você poderá obter as credenciais e o certificado necessários para a comunicação com a API Pix Efí.

Veja a seguir como obter as credenciais, certificados e detalhes sobre a autorização e segurança da sua integração com a Efí.


Segurança no gerenciamento de credenciais
Dentro dos sistemas integrados à nossa API, é importante que as operações de login e a alteração das chaves de integração sejam realizadas com segurança. Sugerimos a implementação de autenticação de dois fatores e outras práticas de segurança.


Obtendo as credenciais da aplicação
Um integrador pode criar quantas aplicações desejar. Para cada aplicação são gerados 2 pares de chaves Client_Id e Client_Secret, sendo um par para utilização em ambiente de Produção (?) e outro para Homologação (?).

Utilizando a API Pix Efí, o integrador pode gerar transações Pix (pagamentos e recebimentos), configurar Webhooks para o recebimento de notificações via callbacks e acessar as funcionalidades exclusivas da Conta Digital Efí. Para isso, é necessário ativar os escopos necessários em sua aplicação.

Entendendo os escopos de aplicação
Ao criar ou editar uma aplicação em sua Conta Efí, você precisará configurar os escopos que a aplicação terá acesso. A escolha desses escopos define quais ações uma aplicação estará autorizada a realizar via API.

Os escopos disponíveis na API Pix Efí estão listados abaixo com suas respectivas descrições de permissão:

cob.write - Alterar cobranças;
cob.read - Consultar cobranças;
pix.write - Alterar Pix;
pix.read - Consultar Pix;
pix.send - Enviar Pix;
gn.pix.send.read - Consultar pix enviado;
webhook.write - Alterar Webhooks;
webhook.read - Consultar Webhooks;
payloadlocation.write - Alterar Payloads;
payloadlocation.read - Consultar Payloads;
gn.pix.evp.write - Alterar Chaves aleatórias;
gn.pix.evp.read - Consultar Chaves aleatórias;
gn.balance.read - Consultar saldo;
gn.settings.write - Alterar Configurações da API;
gn.settings.read - Consultar Configurações da API;
gn.reports.write - Solicitar relatórios;
gn.reports.read - Consultar relatórios;
cobv.write - Alterar cobranças com vencimento;
cobv.read - Consultar cobranças com vencimento;
gn.split.read - Consultar configuração de venda (split pix);
gn.split.write - Criar configuração de venda (split pix);
lotecobv.read - Alterar lote de cobranças com vencimento;
lotecobv.write - Consultar lote de cobranças com vencimento;
gn.infractions.write - Submeter defesa de infração MED;
gn.infractions.read - Listar as infrações MED da conta;
gn.qrcodes.pay - Pagar QR Code Pix;
gn.receipts.read - Baixar comprovante Pix;
rec.read - Consultar recorrências de Pix Automático;
rec.write - Alterar recorrências de Pix Automático;
solicrec.read - Consultar solicitações de Pix Automático;
solicrec.write - Alterar solicitações de Pix Automático;
cobr.read - Consultar cobranças de Pix Automático;
cobr.write - Alterar cobranças de Pix Automático;
payloadlocationrec.read - Consultar payloads de Pix Automático;
payloadlocationrec.write - Alterar payloads de Pix Automático;
webhookrec.read - Consultar webhook recorrências de Pix Automático;
webhookrec.write - Alterar webhook de recorrências de Pix Automático;
webhookcobr.read - Consultar webhook de cobranças de Pix Automático;
webhookcobr.write - Alterar webhook de cobranças de Pix Automático;

Criar uma aplicação ou configurar uma já existente
Veja como criar uma aplicação ou aproveitar uma aplicação já existente para integrar com a API Pix Efí.

Criar uma aplicação
Aproveitar uma aplicação existente
Para criar uma aplicação para utilização da API Pix siga os passos abaixo:

Acesse sua conta e clique no item "API" na parte inferior do menu à esquerda da conta Efí;
Clique em "Criar aplicação"
Habilite a API Pix e escolha os escopos que deseja liberar em ambiente de Produção e Homologação (você pode editá-los no futuro);
Com os escopos selecionados, clique em "Continuar".
banner
Ilustração dos passos para a criação de uma nova aplicação integrada à API Pix


Gerando um certificado P12
Todas as requisições devem conter um certificado de segurança que será fornecido pela Efí dentro da sua conta, no formato PFX(.p12). Essa exigência está descrita na íntegra no manual de segurança do PIX.


Atenção!
O download do certificado é feito imediatamente após a sua criação. Não será possível realizar o download do mesmo certificado em outro momento, por isso, armazene-o em local seguro em seu computador.


Para gerar o seu certificado, basta seguir os passos abaixo:

Acesse o item "API" no menu inferior a esquerda da conta Efí;
No menu à esquerda, clique em "Meus Certificados";
Na nova janela selecione o ambiente ao qual pertencerá o certificado (Produção ou Homologação)
Clique em "Novo Certificado" (botão azul);
Atribua uma descrição ao certificado para identificá-lo no futuro;
Confirme a criação do certificado;
Por fim, baixe o certificado e clique em prosseguir.
Os passos para a criação de um certificado estão ilustrados na imagem a seguir.

banner
Passos para a criação do certificado

banner
Janela para a criação do certificado

banner
Janela de download do certificado gerado

Vale ressaltar que um mesmo certificado pode ser usado por diversas aplicações da sua conta digital. Ainda assim, você pode gerar até cinco certificados para cada ambiente (Produção ou Homologação).


Conversão de certificado P12 para o formato PEM

Informação
Em algumas linguagens as chaves precisarão ser convertidas para o formato .pem. Utilize as informações desta seção apenas se esse for o seu caso.


Caso precise converter o certificado utilizando um sistema operacional Windows, você pode utilizar o nosso conversor disponível no GitHub.

Para gerar o seu certificado com este conversor, basta seguir os passos abaixo:

Clone ou baixe o conversor pelo repositório no GitHub.;
Certifique-se de que o arquivo .p12 esteja no mesmo diretório que o script;
Execute o arquivo conversor_p12_para_pem.bat;
Se o arquivo .p12 estiver protegido por senha, o script solicitará que você insira a senha do certificado. Se você não inserir uma senha, o script considerará uma senha vazia "".
O script irá converter o arquivo .p12 para .pem no mesmo diretório e o arquivo .pem gerado terá o mesmo nome do arquivo .p12, com a extensão .pem.

Caso precise de separar a chave Privada do seu certificado, após a conversão, o script perguntará se você deseja separar a chave privada em um arquivo separado. Responda "S" ou "s" para sim.
Assim a chave privada será exportada para um arquivo separado com o mesmo nome do arquivo .p12, mas com a extensão _key.pem.


Atenção!
É importante destacar que você pode usar um único certificado para várias aplicações na sua conta digital. No entanto, você tem a opção de gerar até cinco certificados para cada ambiente, seja ele de Produção ou Homologação.


Conversão de certificado com OpenSSL
É possível também converter o certificado utilizando o comando o OpenSSL para realizar essa conversão de formato entre as chaves:

Shell
 # Gerar certificado e chave em único arquivo
openssl pkcs12 -in certificado.p12 -out certificado.pem -nodes -password pass:""


Se for necessário separar a chave privada do certificado durante a conversão, use o comando abaixo, também com o OpenSSL:

Shell
# Gerar certificado e chave separadas
openssl pkcs12 -in path.p12 -out newfile.crt.pem -clcerts -nokeys -password pass:"" #certificado
openssl pkcs12 -in path.p12 -out newfile.key.pem -nocerts -nodes -password pass:"" #chave privada



Informação
O processo de conversão do certificado pode pedir a senha do certificado. Se isso ocorrer, informe vazio.


Rotas base
Nesta documentação você perceberá referências à Rotas base ou URL's base para ambientes de Produção ou Homologação. Essas rotas são, na verdade, a URL na qual a API Pix Efí se encontra. Assim, quando nos referirmos aos endpoints, fica implícito que esses trechos de URL também compõem a rota final do recurso desejado.

Utilize as rotas abaixo para realizar a comunicação da sua aplicação com os ambientes de produção e homologação oferecidos pela Efí.

Ambiente	Rota base
Produção	https://pix.api.efipay.com.br
Homologação	https://pix-h.api.efipay.com.br

Autorização com OAuth2
O mecanismo de permissão das solicitações feitas à API Pix Efí é compatível com o protocolo OAuth2. Isso significa que ele segue um conjunto de regras e padrões para autorizar as requisições feitas à API.

O objetivo do OAuth2
Para autorizar todas as chamadas feitas à API, é necessário obter um token de acesso (access_token). Esse token é usado para verificar se uma determinada aplicação tem permissão para utilizar o endpoint solicitado na API.

Como é feita a autenticação das requisições
A autenticação é realizada usando HTTP Basic Auth, que requer o Client_Id e Client_Secret da aplicação que você criou na sua conta Efí. Com essa autenticação, o OAuth pode fornecer as informações sobre as permissões concedidas à aplicação, permitindo autorizar ou negar as solicitações com base nessa informação.


Atenção!
O Certificado P12/PEM gerado nos passos anteriores é obrigatório em todas as requisições feitas à API Pix, inclusive na requisição de autorização.



Configuração do Header Accept-Encoding
Pode ser necessário definir o header Accept-Encoding conforme a necessidade de obter o tamanho do Response Body antes do processamento:

Accept-Encoding: gzip - Caso não seja necessário verificar o tamanho do Response Body antes de processá-lo:
Response Body < 1000 bytes: não haverá compressão e o Content-Length será retornado.
Response Body >= 1000 bytes: haverá compressão, o header Transfer-Encoding: chunked será retornado e o Content-Length não será retornado.
Accept-Encoding: * ou Accept-Encoding: identity - Caso seja necessário verificar o tamanho do Response Body antes de processá-lo:
Response Body de qualquer tamanho: o Content-Length será retornado.


Collection Postman API PIX
Este é o link da nossa Collection que manteremos atualizada com os endpoints da API Pix Efí.

Executar no Postman


Configurando o Postman para testes

Dica
O uso do software Postman é opcional. Os próximos parágrafos explicam como configurá-lo. Caso não deseje usar o Postman para testes, você pode avançar para o tópico Obter Autorização.


Antes de prosseguir com a configuração do Postman, você deverá ter:

Um par de credenciais chamado Client_Id e Client_Secret de uma aplicação que você cadastrou na sua Conta Efí;
Um certificado P12/PEM que você gerou conforme mostrado nos passos anteriores;
O software Postman instalado no seu computador. Caso não o tenha, você pode baixá-lo aqui.

1. Criando um Environment
A criação de um Environment no Postman é necessária para que algumas automações embutidas na collection funcionem. Essas automações foram desenvolvidas fecilitar os testes para os desenvolvedores.

Com essas automações, você só precisa solicitar autorização uma vez e o access_token será armazenado como uma variável de ambiente (environment) no Postman, pronto para ser utilizado nas próximas requisições.

Para criar um Environment, siga os seguinte passos:

Pressione Ctrl+N e, ao abrir o atalho, escolha "Environment";
Atribua um nome preferencialmente especificando se esse Environment será apontado para o ambiente de Produção ou Homologação;
Crie a variável efi-pix-api e como valor inicial (Initial value) insira a URL da API Pix de Produção ou Homologação;
Salve o seu Environment;
Selecione o Environment desejado para que o Postman reconheça a variável criada.
No exemplo a seguir, foi criado um Environment apontado para o ambiente de Homologação da API Pix.


Dica
Repita os passos acima para dessa vez ter um Environment apontado para o ambiente de Produção. Assim você poderá simplesmente alternar entre os Environments e suas requisições já estarão apontadas corretamente.


banner
Criando um novo environment

banner
Configurações do environment


2. Configurando o certificado no Postman
Todas as requisições feitas à API Pix Efí precisam do certificado gerado em sua conta Efí. Portanto, para facilitar seus testes utilizando o Postman, siga os passos a seguir para configurar o uso do certificado durante as requisições de maneira automática:

Clique no ícone de engrenagem no canto superior direito do Postman;
Depois, clique em "Settings" para abrir as configurações;
Na aba superior, clique em "Certificates";
Em seguida, clique em "Add Certificate";
Na janela de configuração do novo certificado, preencha o campo "Host" com a Rota base do ambiente ao qual o certificado pertence (Produção ou Homologação);
Utilize o campo "PFX File" para indicar ao Postman onde está localizado o arquivo do seu certificado .p12. Fique atento ao formato do arquivo, aqui deve ser usado o certificado em .p12;
Finalize clicando em "Add" para salvar suas configurações.
Seguindo esses passos, o Postman usará o certificado para quaisquer requisições feitas ao Host do ambiente configurado.


Dica
É ideal que você configure o certificado do ambiente de homologação, mas você também pode repetir os passos acima para configurar o Postman com um certificado para o ambiente de Produção.


As imagens abaixo ilustram o passo a passo da configuração do certificado.

banner
Acessando as configurações do Postman

banner
Adicionando um novo certificado no Postman

banner
Configurações do certificado


3. Atribuindo o Client_Id e Client_Secret no Postman
Para configurar o Postman corretamente, você precisa adicionar as credenciais da sua aplicação da conta Efí. Essas credenciais são usadas para o Basic Auth e para obter o access_token usando o OAuth.

Siga os passos a seguir para incluir as credenciais e realizar o seu primeiro teste na API Pix:

Na collection importada, localize a rota /oauth/token e clique duas vezes para abri-la;
Acesse o menu "Authorization" e verifique se o "Type" (tipo de autorização) está selecionado como "Basic Auth";
Nos campos "username" e "password" preencha com as credenciais da sua aplicação, Client_Id e Client_Secret, respectivamente;
Para testar, clique no botão "Send" para enviar a requisição
Após esses passos, uma resposta em formato JSON será exibida, contendo o access_token, token_type, expires_in e scope (como na imagem abaixo).

banner
Uso das credenciais de uma aplicação para autorização de requisições


Obter Autorização
POST /oauth/token

O endpoint POST /oauth/token é usado para autorizar as credenciais de uma aplicação e obter os acessos necessários para utilizar os outros recursos da API.


Atenção!
É necessário incluir o certificado P12/PEM na requisição de autorização para que o servidor da API crie uma conexão segura.



Exemplos de autorização utilizando o certificado .P12
Para a utilização do Pix, é necessário que o cliente e o servidor se comuniquem através de uma conexão verificada. Essa verificação é feita pelo certificado bidirecional (.PEM ou .P12), onde tanto o servidor quanto o cliente possuem uma chave privada e uma chave pública para garantir a identidade um do outro.

Portanto, para fazer qualquer requisição HTTP à API Pix, incluindo a solicitação de autorização pelo OAuth2, é necessário que o certificado .P12 ou .PEM esteja presente nos cabeçalhos da requisição.

A seguir, apresentamos exemplos de como realizar a autorização na API Pix Efí, incorporando esse certificado na requisição:

PHP
Node
Python
.Net
Ruby
Java
Go
//Desenvolvido pela Consultoria Técnica da Efí
<?php 
  $config = [
    "certificado" => "./certificado.pem",
    "client_id" => "YOUR-CLIENT-ID",
    "client_secret" => "YOUR-CLIENT-SECRET"
  ];
  $autorizacao =  base64_encode($config["client_id"] . ":" . $config["client_secret"]);

  $curl = curl_init();

  curl_setopt_array($curl, array(
      CURLOPT_URL => "https://pix-h.api.efipay.com.br/oauth/token", // Rota base, homologação ou produção
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING => "",
      CURLOPT_MAXREDIRS => 10,
      CURLOPT_TIMEOUT => 0,
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST => "POST",
      CURLOPT_POSTFIELDS => '{"grant_type": "client_credentials"}',
      CURLOPT_SSLCERT => $config["certificado"], // Caminho do certificado
      CURLOPT_SSLCERTPASSWD => "",
      CURLOPT_HTTPHEADER => array(
          "Authorization: Basic $autorizacao",
          "Content-Type: application/json"
      ),
  ));

  $response = curl_exec($curl);

  curl_close($curl);

  echo "<pre>";
  echo $response;
  echo "</pre>";
?>



Exemplo de resposta da autorização
A seguir, o trecho de código representa um exemplo de resposta do OAuth à sua requisição de autorização:

Resposta
{
   "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiIxMjM0NTY3ODkwIiwibmFtZSI6IkpvaG4gRG9lIiwiaWF0IjoxNTE2MjM5MDIyfQ.SflKxwRJSMeKKF2QT4fwpMeJf36POk6yJV_adQssw5c",
   "token_type": "Bearer",
   "expires_in": 3600,
   "scope": "cob.read cob.write pix.read pix.write"
}



A tabela abaixo descreve os atributos presentes no JSON retornado.

Atributo	Descrição	Tipo
access_token	Token de autorização a ser usado nas outras requisições feitas à API.	string
token_type	Tipo de autorização na qual o access_token deve ser usado
Padrão: "Bearer"	string
expires_in	Tempo de expiração do access_token em segundos.
Padrão 3600	Integer (int32)
scope	Lista de escopos aos quais a aplicação autorizada possui acesso. Os escopos estão separados por espaço.	string


Pix Automático
O conjunto de endpoints a seguir é responsável pela gestão de cobranças recorrentes de Pix Automático. As cobranças, no contexto da API Pix representam uma transação financeira entre um pagador e um recebedor, cuja forma de pagamento é o Pix.



Atenção!
Para utilizar as funcionalidades do Pix Automático, é necessário ter uma Conta Digital Efí Empresas.



Informação
A configuração dos webhooks do Pix Automático está descrita nesta página.


Gerenciamento de recorrências de Pix Automático
Esta seção reúne endpoints destinados a lidar com gerenciamento de recorrências de Pix Automático.

Criar recorrência de Pix Automático
Endpoint para criar recorrência de Pix Automático.

Geralmente, o txid é criado pelo usuário recebedor e está sob sua responsabilidade. No entanto, este endpoint é uma exceção a essa regra, e, nesse caso, o txid será definido pela Efí.

POST /v2/rec

Consultar recorrência de Pix Automático
Endpoint para consultar recorrência de Pix Automático.

GET /v2/rec/:idRec

Revisar recorrência de Pix Automático
Endpoint para revisar recorrência de Pix Automático.

PATCH /v2/rec/:idRec

Consultar lista de recorrências de Pix Automático
Endpoint para consultar lista de recorrências de Pix Automático.

Este endpoint possui filtros para afunilar os resultados da busca, tais como CPF/CNPJ e status. Dentre todos os filtros disponíveis, os filtros inicio e fim são obrigatórios e representam o intervalo de datas em que as cobranças consultadas devem estar compreendidas.

GET /v2/rec

Gerenciamento de solicitações de recorrências de Pix Automático
Esta seção reúne endpoints destinados a lidar com gerenciamento de solicitações de recorrências de Pix Automático.

Criar solicitação de confirmação de recorrência de Pix Automático
Endpoint para criar solicitação de confirmação de recorrência de Pix Automático.

POST /v2/solicrec

Consultar solicitação de confirmação de recorrência de Pix Automático
Endpoint para consultar solicitação de recorrência de Pix Automático.

GET /v2/solicrec/:idSolicRec

Revisar solicitação de confirmação de recorrência de Pix Automático
Endpoint para revisar solicitação de confirmação de recorrência de Pix Automático.

PATCH /v2/solicrec/:idSolicRec

Gerenciamento de cobranças associadas a uma recorrência de Pix Automático
Esta seção reúne endpoints destinados a lidar com gerenciamento de cobranças associadas a uma recorrência de Pix Automático.

Criar cobrança de Pix Automático (com txid)
Endpoint para criar uma cobrança de Pix Automático com um identificador de transação (txid).

PUT /v2/cobr/:txid

Revisar cobrança de Pix Automático
Endpoint para revisar uma cobrança de Pix Automático.

PATCH /v2/cobr/:txid

Consultar cobrança de Pix Automático
Endpoint para consultar uma cobrança de Pix Automático através de um determinado txid.

GET /v2/cobr/:txid

Criar cobrança de Pix Automático (sem txid)
Endpoint para criar uma cobrança de Pix Automático, neste caso, o txid deve ser definido pelo PSP.

Geralmente, o txid é criado pelo usuário recebedor e está sob sua responsabilidade. No entanto, este endpoint é uma exceção a essa regra, e, nesse caso, o txid será definido pela Efí.

POST /v2/cobr

Consultar lista de cobranças de Pix Automático
Endpoint para consultar cobranças de Pix Automático através de parâmetros como início, fim, idRec, cpf, cnpj, status e convênio.

Este endpoint possui filtros para afunilar os resultados da busca, tais como CPF/CNPJ e status. Dentre todos os filtros disponíveis, os filtros inicio e fim são obrigatórios e representam o intervalo de datas em que as cobranças consultadas devem estar compreendidas.

GET /v2/cobr

Solicitar retentativa de Pix Automático
Endpoint para solicitar retentativa de uma cobrança de Pix Automático.

POST /v2/cobr/:txid/retentativa/:data

Jornadas de contratação
A adesão ao Pix Automático pode ser feita por meio de quatro jornadas diferentes. Veja a seguir como funciona a emissão em cada uma delas:

banner
Jornadas de contratação


Jornada 1 - via notificação push
Essa jornada foi pensada para negociações iniciadas fora do ambiente do Pix — pelo WhatsApp, por exemplo.

Passos para emitir:

1. Criar a recorrência: POST /v2/rec.
O location não deve ser enviado no body da requisição.

2. Criar a solicitação da recorrência: POST /v2/solicrec.
Informe o idRec obtido no Passo 1.

3. Criar a cobrança recorrente associada à recorrência aceita: PUT /v2/cobr/:txid ou POST /v2/cobr.

Jornada 2 - com QR Code para cobranças recorrentes futuras
Essa jornada foi pensada para empresas que não precisam receber o pagamento imediatamente, mas desejam cobrar de forma automática e periódica a partir do mês seguinte.

Passos para emitir:

1. Criar o location: POST /v2/locrec.
2. Criar a recorrência: POST /v2/rec.
Informe o location obtido no Passo 1.

3. Consultar a recorrência para obter o copia e cola: GET /v2/rec/:idRec.
Informe o idRec obtido no passo 2, como parâmetro.

4. Criar a cobrança recorrente associada à recorrência aceita: PUT /v2/cobr/:txid ou POST /v2/cobr.

Jornada 3 - com QR Code para pagamento imediato + recorrência em uma única autorização
Essa jornada foi pensada para quando o cliente deseja começar a usar o serviço imediatamente e a empresa precisa receber o primeiro pagamento à vista.

Passos para emitir:

1. Criar o location: POST /v2/locrec.
2. Criar a cobrança imediata: POST /v2/cob ou PUT /v2/cob/:txid.
3. Criar a recorrência: POST /v2/rec.
Informe o location obtido no Passo 1 e o txid da cobrança obtido no passo 2.

4. Consultar a recorrência para obter o copia e cola: GET /v2/rec/:idRec.
Informe o idRec obtido no passo 3, como parâmetro e o txid da cobrança imediata obtido no passo 2, como query params.

5. Criar a cobrança recorrente associada à recorrência aceita: PUT /v2/cobr/:txid ou POST /v2/cobr.

Jornada 4 - via fatura com QR Code
Essa jornada foi pensada para quando uma empresa já envia faturas mensais com QR Code para seus clientes e quer oferecer a proposta de ativação do Pix Automático, simplificando os pagamentos futuros.

Passos para emitir:

1. Criar o location: POST /v2/locrec.
2. Criar a cobrança com vencimento: PUT /v2/cobv/:txid.
3. Criar a recorrência: POST /v2/rec.
Informe o location obtido no Passo 1.

4. Consultar a recorrência para obter o copia e cola: GET /v2/rec/:idRec.
Informe o idRec obtido no passo 3, como parâmetro e o txid da cobrança com vencimento obtido no passo 2, como query params.

5. Criar a cobrança recorrente associada à recorrência aceita: PUT /v2/cobr/:txid ou POST /v2/cobr.Webhooks
Esta seção reúne endpoints para gerenciamento de notificações por parte do PSP recebedor a pessoa usuária recebedora.


Entendendo o padrão mTLS
Por norma do Banco Central, será necessário a inserção de uma chave pública da Efí em seu servidor para que a comunicação obedeça o padrão mTLS. No domínio que representa o seu servidor, você deverá configurar a exigência da chave pública (mTLS) que estamos disponibilizando, para que ocorra a autenticação mútua.

A Efí irá fazer 2 requisições para o seu domínio (servidor):

Primeira Requisição: Vamos certificar que seu servidor esteja exigindo uma chave pública da Efí. Para isso, enviaremos uma requisição sem certificado e seu servidor não deverá aceitar a requisição. Caso seu servidor responda com recusa, enviaremos a 2ª requisição.
Segunda Requisição: Seu servidor, que deve conter a chave pública disponibilizada, deverá realizar o "Hand-Shake" para que a comunicação seja estabelecida.
É necessário que o seu servidor tenha a versão mínima do TLS 1.2.

Em seu servidor, configure uma rota 'POST' com uma resposta padrão como uma string "200". Inclua o nosso certificado de produção ou homologação em seu servidor, a seguir temos alguns exemplos.


Servidores dedicados
Recomenda-se que você tenha um servidor dedicado para conseguir realizar as configurações do webhook, pois é necessário ter acesso a alguns arquivos para realizar as configurações, como nos exemplos abaixo.


Exemplos de configurações de servidor
Para configurar seu servidor, você precisará das chaves públicas da Efí. Abaixo estão os endereços das chaves para os ambientes de Produção e Homologação. Essas chaves devem ser baixadas e dispostas em seu servidor.


Atributo	URL da chave pública
Produção	https://certificados.efipay.com.br/webhooks/certificate-chain-prod.crt
Homologação	https://certificados.efipay.com.br/webhooks/certificate-chain-homolog.crt

Os trechos de código abaixo buscam exemplificar as configurações necessárias em seu servidor para que seja possível realizar o hand-shake com nossos servidores.

Python
Nginx
Node
Apache
PHP
from flask import Flask, jsonify, request
import ssl
import json
app = Flask(__name__)

@app.route("/", methods=["POST"])
def imprimir():
  response = {"status": 200}
  return jsonify(response)


@app.route("/pix", methods=["POST"])
def imprimirPix():
  imprime = print(request.json)
  data = request.json
  with open('data.txt', 'a') as outfile:
      outfile.write("\n")
      json.dump(data, outfile)
  return jsonify(imprime)

if __name__ == "__main__":
  context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
  context.verify_mode = ssl.CERT_REQUIRED
  context.load_verify_locations('caminho-certificados/certificado-público.crt')
  context.load_cert_chain(
      'caminho-certificados/server_ssl.crt.pem',
      'caminho-certificados/server_ssl.key.pem')
  app.run(ssl_context=context, host='0.0.0.0')
#Desenvolvido pela Consultoria Técnica da Efí

Para ter um ter um SSL válido, você deve entrar em contato com uma Autoridade Certificadora e gerar a chave privada server_ssl.key.pem e uma pública server_ssl.crt.pem, assim você valida a integridade da conexão. Você consegue realizar isso de forma gratuita utilizando um utilitário como o Certbot por exemplo.

Skip-mTLS
Para hospedagem em servidores compartilhados, pode haver restrições em relação à inserção de certificados gerados por outra entidade, como o nosso CA, por exemplo. Por isso, disponibilizamos a opção skip mTLS, que permite o cadastro do webhook sem a necessidade de validação mTLS.


Atenção!
É importante destacar que sempre enviaremos o certificado nos webhooks, seja no cadastro ou na notificação de Pix. No entanto, quando o skip-mTLS é utilizado, você, pessoa integradora, fica responsável por validar o nosso certificado.
Caso opte por utilizar o atributo skip mTLS, ou seja, sem a validação mTLS no seu servidor, você deverá implementar medidas para garantir que quem está enviando os webhooks ao seu servidor é, de fato, a Efí.



Sugerimos as duas formas de validação a seguir, mas recomendamos fortemente que as utilize em conjunto:

Verifique o IP de comunicação: Você pode restringir a comunicação ao domínio do webhhook cadastrado para aceitar apenas mensagens do IP utilizado pela Efí.
IP utilizado atualmente em nossas comunicações: '34.193.116.226'.
Adicione uma hash à URL cadastrada no webhook: Crie um hmac (uma identificação própria) que será acrescentado ao final da URL no momento do cadastro do webhook. Essa hash será utilizada para validar a origem da notificação. Assim, todos os webhooks enviados ao seu servidor terão essa identificação final e sua aplicação deve validar a presença da mesma.
Exemplo:
URL de notificação original: https://seu_dominio.com.br/webhook
Como deverá ser cadastrada com a hash: https://seu_dominio.com.br/webhook?hmac=xyz&ignorar=. O termo ignorar= servirá para tratar a adição do /pix no final da URL.
Como cadastrar o skip-mTLS:
Para configurar o webhook Pix, você deve utilizar o endpoint específico e passar no cabeçalho da requisição o parâmetro x-skip-mtls-checking com o valor true ou false dependendo se deseja habilitar ou desabilitar essa funcionalidade.

A imagem abaixo mostra como este parâmetro deve ser informado:



Gerenciamento de notificações
Esta seção reúne endpoints para gerenciamento de notificações por parte do PSP recebedor ao usuário recebedor.

Configurar o webhook Pix
Endpoint para configuração do serviço de notificações acerca de Pix recebidos. Pix oriundos de cobranças estáticas só serão notificados se estiverem associados a um txid.


Lembrete
Uma URL de webhook pode estar associada a várias chaves Pix.

Por outro lado, uma chave Pix só pode estar vinculada a uma única URL de webhook.



Informação
Ao cadastrar seu webhook, enviaremos uma notificação de teste para a URL cadastrada, porém quando de fato uma notificação for enviada, o caminho /pix será acrescentado ao final da URL cadastrada. Para não precisar de duas rotas distintas, você poder adicionar um parâmetro ?ignorar= ao final da URL cadastrada, para que o /pix não seja acrescentado na rota da sua URL.


PUT /v2/webhook/:chave

Exibir informações do webhook Pix
Endpoint para recuperação de informações sobre o webhook pix.

GET /v2/webhook/:chave

Consultar lista de webhooks
Endpoint para consultar webhooks associados a chaves através de parâmetros como início e fim. Os atributos são inseridos como query params.

GET /v2/webhook

Cancelar o webhook Pix
Endpoint para cancelamento do webhook pix.

DELETE /v2/webhook/:chave

Gerenciamento de notificações de recorrências de Pix Automático
Esta seção reúne endpoints para gerenciamento de notificações de de recorrências de Pix Automático por parte do PSP recebedor ao usuário recebedor.

Configurar o webhook de recorrência de Pix Automático
Endpoint para configuração do serviço de notificações acerca de recorrências de Pix Automático. Somente recorrências associadas a chave e conta serão notificadas.


Informação
Ao cadastrar seu webhook, enviaremos uma notificação de teste para a URL cadastrada, porém quando de fato uma notificação for enviada, o caminho /rec será acrescentado ao final da URL cadastrada. Para não precisar de duas rotas distintas, você poder adicionar um parâmetro ?ignorar= ao final da URL cadastrada, para que o /rec não seja acrescentado na rota da sua URL.


PUT /v2/webhookrec

Exibir informações do webhook de recorrência de Pix Automático
Endpoint para recuperação de informações sobre o webhook de recorrência de Pix Automático.

GET /v2/webhookrec

Cancelar o webhook de recorrência de Pix Automático
Endpoint para cancelamento do webhook de recorrência de Pix Automático.

DELETE /v2/webhookrec

Gerenciamento de notificações de cobranças de Pix Automático
Esta seção reúne endpoints para gerenciamento de notificações de cobranças recorrentes de Pix Automático por parte do PSP recebedor ao usuário recebedor.

Configurar o webhook de cobrança de Pix Automático
Endpoint para configuração do serviço de notificações acerca de cobranças recorrentes de Pix Automático. Somente cobranças recorrentes de Pix Automático associadas ao usuário recebedor serão notificadas.


Informação
Ao cadastrar seu webhook, enviaremos uma notificação de teste para a URL cadastrada, porém quando de fato uma notificação for enviada, o caminho /cobr será acrescentado ao final da URL cadastrada. Para não precisar de duas rotas distintas, você poder adicionar um parâmetro ?ignorar= ao final da URL cadastrada, para que o /cobr não seja acrescentado na rota da sua URL.


PUT /v2/webhookcobr

Exibir informações do webhook de cobrança de Pix Automático
Endpoint para recuperação de informações sobre o webhook de cobrança de Pix Automático.

GET /v2/webhookcobr

Cancelar o webhook de cobrança de Pix Automático
Endpoint para cancelamento do webhook de cobrança de Pix Automático.

DELETE /v2/webhookcobr

Reenviar webhook Pix
Endpoint que permite reenviar webhook pix.


Atenção!
O endpoint de reenvio de webhook Pix não se aplica aos webhooks do Pix Automático.



Atenção!
É possível solicitar o reenvio de Webhooks para transações que ocorreram a partir do dia 27/12 às 10:00 da manhã.

O reenvio de webhook para uma transação fica disponível por um prazo máximo de 30 dias.

A tentativa de reenvio ocorre uma vez para cada webhook, NÃO existe reagendamentos como ocorre no envio normal. Caso o servidor do cliente esteja inoperante, o cliente terá que solicitar novamente o reenvio.

Nos casos de webhooks de devoluções (recebimento e envio) ocorre o reenvio de um webhook com todo o array de devolução ao invés de um webhook por devolução. Por exemplo, se você realizar duas devoluções relacionadas a um mesmo endToEndId, no envio, você receberá dois webhooks distintos. Porém, ao solicitar o reenvio, receberá apenas um webhook.


POST /v2/gn/webhook/reenviar

Recebendo Callbacks
Esse serviço está protegido por uma camada de autenticação mTLS. Os callbacks são enviados pela Efí via POST url-webhook-cadastrada​/pix quando há uma alteração no status do Pix.


Informação
Para testar os endpoints de cobrança Pix Cob e Pix CobV em ambiente de homologação, é possível simular todos os status retornados pela nossa API e webhook.

Cobranças com valor entre R$ 0.01 à R$ 10.00 são confirmadas, e você receberá a informação via Webhook.
Cobranças com valor acima de R$ 10.00 permanecem ativas, sem confirmação, e não há webhook nesses casos.


Requisição
Quando ocorre uma alteração no status de uma transação Pix associada à chave cadastrada, a Efí envia uma requisição POST para a URL de webhook que você definiu. Um objeto JSON (como os exemplos abaixo) será enviado ao seu servidor. Cada requisição de callback possui um timeout de 60 segundos, ou seja, é interrompida se não houver resposta em 60 segundos.

Exemplos:

Credenciais e Autorização
Nesta página, você encontra informações sobre credenciais e autorização da API Cobranças Efí.


A API Cobranças Efí oferece recursos avançados que permitem emitir diferentes tipos de cobranças, tais como Boleto, Cartão de crédito, Carnê, Links de pagamento, Assinaturas (Recorrência) e Marketplace (Split de pagamento).

Para integrar a API Cobranças Efí ao seu sistema ou sua plataforma, é necessário ter uma Conta Digital Efí. Após obter o acesso à conta, você poderá adquirir as credenciais necessárias para estabelecer a comunicação com a API Cobranças Efí.

A seguir, veja como obter as credenciais e detalhes sobre a autorização e segurança da sua integração com a Efí.


Segurança no gerenciamento de credenciais
Dentro dos sitemas integrados à nossa API, é importante que as operações de login e a alteração das chaves de integração sejam realizadas com segurança. Sugerimos a implementação de autenticação de dois fatores e outras práticas de segurança.


Obtendo as credenciais da aplicação
Para obter as credenciais da aplicação, a pessoa integradora pode criar quantas aplicações desejar. Cada aplicação é associada a 2 pares de chaves: Client_Id e Client_Secret, sendo um par destinado ao ambiente de Produção (?) e outro para o ambiente de Homologação (?). É fundamental ativar o escopo em sua aplicação para poder utilizar a API Cobranças da Efí.

Criar uma aplicação ou configurar uma já existente
Veja como criar uma aplicação ou aproveitar uma aplicação já existente para integrar com a API Cobranças Efí.

Criar uma aplicação
Aproveitar uma aplicação existente
Para criar uma aplicação e utilizar a API Cobranças siga os passos abaixo:

Acesse sua conta Efí e clique "API", no menu à esquerda;
Clique em "Criar aplicação"
Habilite a API de Emissões e escolha o escopo para liberar os ambientes de Produção/Homologação;
Com o escopo selecionados, clique em "Continuar".
banner
Ilustração dos passos para a criação de uma nova aplicação integrada à API Cobranças

Rota base
Nesta documentação, você encontrará referências às Rotas base ou URL's base para ambientes de Produção ou Homologação. Essas rotas representam o endereço da API Cobranças Efí. Quando mencionarmos os endpoints, essas partes de URL também fazem parte do caminho para acessar o recurso desejado.

Para comunicar sua aplicação com os ambientes de produção e homologação da Efí, utilize as seguintes rotas:

Ambiente	Rota base
Produção	https://cobrancas.api.efipay.com.br
Homologação	https://cobrancas-h.api.efipay.com.br

Autenticação com OAuth2
O processo de autenticação na API Cobranças segue o protocolo OAuth2. As requisições são autenticadas usando HTTP Basic Auth.

Collection Postman API Cobranças
Este é o link da nossa Collection que manteremos atualizada com os endpoints da API Cobranças Efí.

Executar no Postman


Configurando o Postman para testes

Dica
O uso do software Postman é opcional. A seguir, explicaremos como configurá-lo. Caso não deseje usar o Postman para testes, você pode avançar para o tópico: Obter Autorização.


Antes de prosseguir com a configuração do Postman, certifique-se de ter:

Um par de credenciais Client_Id e Client_Secret de uma aplicação cadastrada em sua Conta Efí;
O software Postman instalado em seu computador (Caso não tenha, baixe aqui);

1. Criando um Environment
A criação de um Environment no Postman é necessária para que algumas automações embutidas na collection funcionem. Essas automações foram projetadas para dar mais facilidade aos desenvolvedores durante os testes.

Isso permitirá que você solicite a autorização apenas uma vez, gravando o access_token como uma variável de ambiente (environment) do Postman, disponível para uso em outras requisições subsequentes.

Para criar um Environment siga os passos a seguir:

Presione Ctrl+N e selecione "Environment";
Atribua um nome preferencialmente especificando se esse Environment será apontado para o ambiente de Produção ou Homologação;
Crie a variável efi-cob-api e como valor inicial (Initial value) insira a URL da API Cobranças de Produção ou Homologação;
Salve o seu Environment;
Selecione o Environment desejado para que o Postman entenda a variável criada.
As imagens a seguir mostram os passos ilustrados acima. Neste exemplo, foi criado um Environment para o ambiente de Produção da API Cobranças da Efí.

banner
Criando um novo environment


banner
Configurações do environment


2. Atribuindo o Client_Id e Client_Secret no Postman
Para finalizar a configuração do seu Postman é necessário configurar as credenciais de uma aplicação da sua conta Efí. Essas credenciais são usadas para o Basic Auth e para obter o access_token junto ao OAuth.

Siga os passos a seguir para incluir as credenciais e realizar o seu primeiro teste na API Cobranças.

Na collection importada, vá até a rota /v1/authorize e clique duas vezes para abrir;
Acesse o menu "Authorization" e verifique se o "Type" (tipo de autorização) está selecionado como "Basic Auth";
Preencha os campos "username" e "password" com as credenciais da sua aplicação, ou seja, o Client_Id e o Client_Secret, respectivamente;
Para testar, clique no botão "Send" para enviar a requisição.
A imagem abaixo ilustra os passos acima. Se tudo foi seguido corretamente, você deve obter uma resposta em formato JSON, contendo o access_token, token_type, expires_in e scope (como na imagem abaixo).

banner
Uso das credenciais de uma aplicação para autorização de requisições


Obter Autorização
POST /v1/authorize

O endpoint POST /v1/authorize é usado para autorizar as credenciais de uma aplicação e obter os acessos necessários para utilizar os outros recursos da API.

Exemplos de autorização
A seguir, apresentamos exemplos de como realizar a autorização na API Cobranças:

PHP
Node
Python
.Net
Ruby
Java
Go
//Desenvolvido pela Consultoria Técnica da Efí
<?php 
  $config = [
    "client_id" => "YOUR-CLIENT-ID",
    "client_secret" => "YOUR-CLIENT-SECRET"
  ];
  $autorizacao =  base64_encode($config["client_id"] . ":" . $config["client_secret"]);

  $curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://cobrancas-h.api.efipay.com.br/v1/authorize',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS =>'{ "grant_type": "client_credentials"}',
  CURLOPT_HTTPHEADER => array(
          "Authorization: Basic $autorizacao",
          "Content-Type: application/json"
      ),
  ));

  $response = curl_exec($curl);

  curl_close($curl);

  echo "<pre>";
  echo $response;
  echo "</pre>";
?>


Exemplo de resposta da autorização
A seguir, o trecho de código representa um exemplo de resposta do OAuth à sua requisição de autorização:

Resposta
{
   "access_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpYXQiOjE3MTYyOTY2NTYsImV4cCI6MTcxNjI5NzI1NiwiZGF0YSI6eyJrZXlfaWQiOjUyOTU2MSwidHlwZSI6ImFjY2Vzc1Rva2VuIn19._d22EAjlsmuCKxTCtYDMd2ZVK04fS7xWNWSjE-JWEpc",
   "refresh_token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpYXQiOjE3MTYyOTY2NTYsImV4cCI6MTcxNjI5Nzg1NiwiZGF0YSI6eyJrZXlfaWQiOjUyOTU2MSwidHlwZSI6InJlZnJlc2hUb2tlbiJ9fQ.4txXqR4g5FMQvCU3jL8LnrQ002xfEAK1EwKaJjlyCOU",
   "expires_in": 600,
   "expire_at": "1690986856033",
   "token_type": "Bearer"
}



A tabela abaixo descreve os atributos presentes no JSON retornado.

Atributo	Descrição	Tipo
access_token	Token de autorização a ser usado nas outras requisições feitas à API.	string
refresh_token	Token de autorização que será utilizado para atualizar um access token expirado.	string
expires_in	Tempo de expiração do access_token em segundos.
Padrão 600	Integer (int32)
expire_at	Tempo de expiração do access_token em Timestamp ISO 8601	string
token_type	Tipo de autorização na qual o access_token deve ser usado
Padrão: "Bearer"	string


Boleto
Passo a passo para gerar boleto bancário na API Efí


Atualmente disponibilizamos dois procedimentos para a criação de uma transação do tipo Boleto bancário. Na primeira delas o titulo é criado em um passo único, assim fora convencionado como One Step. A segunda opção de criação da transação se da em dois passos, sendo assim convencionada como Two Steps.

Criação de Boleto (Bolix) em One Step (Um passo)
Nesta opção é necessário que o body da requisição contenha todos os atributos mínimos obrigatórios para a emissão do titulo.

Permite criar uma transação já associando um método de pagamento, podendo ser boleto bancário ou cartão de crédito, em apenas uma etapa.


Importante!
Para garantir que a criação de transações via One Step ocorra sem problemas, é necessário atualizar a sua SDK. Todos os arquivos necessários para essa atualização estão disponíveis através de nosso repositório e na nossa documentação.



Como definir a baixa automática de boletos após vencimento
Agora você pode definir o prazo para baixa de boletos vencidos de acordo com suas necessidades. Ateriormente, o prazo era fixo em 89 dias e não permitia alterações, agora você pode escolher um prazo que varia de 0 a 120 dias.

Para configurar o prazo, basta definir o número de dias no atributo days_to_write_off. Por exemplo, se você indicar 0 e a cobrança vencer em 28/02/2024, o pagamento não será mais possível a partir de 29/02/2024.

Caso nao tenha nenhum pagamento identificado até a data maxima estipulada neste atributo, o status atribuido à cobrança será de expired (expirado).



Defina juros mensais ou diários
Você pode definir se os juros serão calculados mensalmente ou diariamente, de acordo com suas necessidades e preferências específicas. Basta informar o tipo de juros desejado no momento da transação como monthly.

Caso opte por não especificar o tipo de juros, por padrão os juros serão calculados diariamente.


Estrutura hierárquica dos atributos do Schema que podem ser utilizados:
POST /v1/charge/one-step


Informação
Ao usar o atributo message, deve-se utilizar o operador \n para efetuar a "quebra" da linha. No código que disponibilizamos já incluímos este operador.


Criação de Boleto em Two Steps (Dois passos)
Nesta opção é necessário seguir dois passos, enviando o body da requisição com todos os atributos mínimos obrigatórios para a emissão da cobrança.

Crie a transação, informando o item/produto/serviço, valor, quantidade, etc;
Associe à forma de pagamento via boleto, informando o charge_id da transação e os dados do cliente.
O restante desta página apresenta os procedimentos detalhados, mas você precisa instalar uma de nossas bibliotecas em seu servidor para executar os códigos de exemplo. Certifique-se de que a SDK da Efí foi instalada.

1. Criar transação
Para começar, o primeiro passo é gerar a transação, também conhecida como “cobrança”. Nesse momento, você informará o nome do item, produto ou serviço, o valor da transação, a quantidade e outras informações relevantes.

Após a criação da cobrança, você receberá um charge_idque é um identificador único para essa transação. Esse será usado para associar a forma de pagamento.

Inicialmente, a transação terá o status new, o que significa que a cobrança foi gerada e está aguardando a definição da forma de pagamento. O status será atualizado somente quando o método de pagamento for escolhido pela pessoa integradora.

Para gerar uma transação, você deve enviar uma requisição POST para a rota /v1/charge.

Estrutura hierárquica dos atributos do Schema que podem ser utilizados:
POST /v1/charge

2. Associar à forma de pagamento via boleto
Após gerar a transação com sucesso, o próximo passo é associá-la à forma de pagamento desejada - neste caso, será o banking_billet(boleto bancário). Para isso, você precisará informar o charge_id que foi obtido ao criar a transação.

Ao selecionar o boleto bancário como forma de pagamento, o status da transação será alterado de new para waitingEssa mudança indica que a forma de pagamento foi escolhida e está aguardando a confirmação do pagamento.

Para associar à forma de pagamento, você deve enviar uma requisição POST para a rota /v1/charge/:id/pay.

Estrutura hierárquica dos atributos do Schema que podem ser utilizados:
POST /v1/charge/:id/pay


Pagamento realizado como Pessoa Jurídica (PJ)
O cliente associado à transação pode ser uma Pessoa Jurídica. Nesse caso, é necessário informar a Razão Social e o CNPJ da empresa pagadora no atributo juridical_person.



Relação de todos os possíveis status de uma transação
Todas as transações possuem um status que representa sua “situação”. É importante conhecer os possíveis status das transações na API para lidar adequadamente com elas em seu sistema.

Confira aqui todos os detalhes dos possíveis status das transações.



Callbacks (notificações) das transações da API para seu sistema
As notificações permitem que você seja informado quando uma transação tiver seu status alterado. Dessa forma, você poderá identificar quando um boleto for pago, por exemplo.

Confira aqui todos os detalhes sobre como implementar a sua URL de notificação.


Retornar informações de cobrança existente
Para retornar informações de uma transação (como um boleto, por exemplo), você deve enviar uma requisição GET para a rota /v1/charge/:id.

GET /v1/charge/:id

Retornar lista de cobranças
Para retornar informações de cobranças emitidas em uma aplicação, você deve enviar uma requisição GET para a rota /v1/charges.

Este endpoint possui filtros para afunilar os resultados da busca, tais como CPF/CNPJ e status. Dentre todos os filtros disponíveis, os filtros charge_type, begin_date e end_date são obrigatórios e representam o tipo da transação e o intervalo de datas em que as cobranças consultadas devem estar compreendidas.


Importante!
Atualmente este recurso está em versão beta. Estamos entusiasmados em compartilhar essa ferramenta com você, porém, é essencial lembrar que ela está em desenvolvimento ativo e pode passar por alterações durante este período.
Valorizamos profundamente seu feedback durante esta fase e queremos ouvir suas experiências e sugestões para aprimorar continuamente nossos serviços. Sinta-se à vontade para entrar em contato conosco por meio de nossa comunidade do Discord ou outros canais de suporte.



GET /v1/charges


Limite de consumo
Assim como todos os endpoints de nossa API, a listagem de cobranças também possui um limite diário, que pode ser conferido na aba Limites de Consumo.

Caso as consultas excedam estes valores, recomendamos abrir um ticket em sua conta, solicitando a liberação.


Incluir "notification_url" e "custom_id" em uma transação existente
Você pode definir ou modificar as informações enviadas na propriedade metadata da transação a qualquer momento. Este endpoint é de extrema importânciapara atualizar a URL de notificação vinculada às transações ou modificar o custom_id associado anteriormente.

Para alterar a notification_url e/ou custom_id de uma transação, você deve enviar uma requisição PUT para a rota /v1/charge/:id/metadata.

Casos de uso deste endpoint:
A pessoa integradora alterou o IP do servidor que estava associado à URL de notificação das transações;
A pessoa integradora atualizou a URL de notificação para as novas transações criadas (createCharge), mas precisa atualizar as transações anteriores (updateChargeMetadata) que foram geradas e que estão associadas com a URL incorreta/desatualizada;
Foi instalado SSL (https) no servidor do cliente e mesmo que o cliente defina uma regra de redirecionamento 301 ou 302, será necessário definir a nova URL nas transações que estão usando a URL "antiga";
A pessoa integradora gerou cobranças sem informar a URL de notificação ao enviar a requisição de criação da transação;
Modificar ou acrescentar uma informação junto ao atributo custom_id associado às transações geradas previamente;
Dentre outros possíveis cenários.
PUT /v1/charge/:id/metadata

Alterar data de vencimento de uma transação existente
Possibilita alterar a data de vencimento de uma transação cuja forma de pagamento seja banking_billet (boleto bancário) e que ainda não foi paga.

Para tal, é necessário que você informe o charge_id da transação desejada e a nova data de vencimento em formato YYYY-MM-DD dentro do atributo expire_at. Deve-se enviar uma requisição PUT para a rota /v1/charge/:id/billet.


Importante!
A nova data de vencimento deve ser pelo menos maior que a data atual.


PUT /v1/charge/:id/billet

Cancelar uma transação existente
Uma transação pode ser cancelada apenas se ela possuir o status new, waiting, unpaid ou link.

Quando uma transação é cancelada, há apenas uma condição para que o status seja alterado novamente: se o cliente imprimir o boleto antes do integrador cancelar a transação, ele poderá efetuar o pagamento normalmente em uma agência bancária. Nesse caso, tanto a pessoa integradora quanto a pessoa pagadora receberão a confirmação do pagamento como de costume, e o status da cobrança será alterado de canceled para paid.

Para cancelar uma transação (por exemplo, cancelar um boleto), você deve enviar uma requisição PUT para a rota /v1/charge/:id/cancel.

PUT /v1/charge/:id/cancel

Reenvio do boleto bancário para o email desejado
Se a transação for um boleto bancário banking_billet e estiver com o status waiting ou unpaid, é possível reenviar o boleto por e-mail.

Basta enviar o charge_id da transação e o endereço de e-mail válido para o qual deseja enviar o boleto.

Para reenviar um boleto por e-mail, você deve enviar uma requisição POST para a rota /v1/charge/:id/billet/resend.

POST /v1/charge/:id/billet/resend

Acrescentar descrição ao histórico de uma transação
O histórico de uma transação mostra todas as ações que ocorreram até o momento, mas as mensagens personalizadas não afetam a transação em si, apenas aparecem no histórico. Você pode visualizar o histórico da transação na interface ou usando o endpoint de detalhes da transação.

Para isso, basta enviar o identificador charge_id e a mensagem que deseja adicionar ao histórico da transação. A descrição deve ter entre 1 e 255 caracteres.

Para adicionar mensagens personalizadas no histórico de uma transação, você deve enviar uma requisição POST para a rota /v1/charge/:id/history.

POST /v1/charge/:id/history

Definir que a transação será do tipo boleto balancete
Após a criação da transação, será o momento de definirmos que o boleto a ser gerado será do tipo balancete.

Para isso, você deve enviar uma requisição POST para a rota /v1/charge/:id/balance-sheet.


NOTA
É importante destacar que não há um padrão fixo para os itens exibidos no boleto balancete. A própria pessoa integradora poderá definir, através dos atributos adequados, a quantidade de colunas (até 4), linhas, textos e valores que serão mostrados no boleto. De forma resumida, a pessoa integradora trabalha com uma tabela construída em HTML, mas em formato JSON.

POST /v1/charge/:id/balance-sheet


Importante!
As informações contidas no balancete não são utilizadas pela Efí. Recebemos o conteúdo da requisição do seu sistema/aplicação e apenas montamos a cobrança da forma que a pessoa integradora espera, seguindo o layout de exemplo acima. Ou seja, a Efí não valida as informações nem faz cálculos no balancete; apenas processa e organiza os dados dentro do layout especificado pela sua requisição à rota POST /charge/:id/balance-sheet.



Importante!
As requisições para o endpoint de balancete não devem exceder 300 KB (body da requisição).

Marcar como pago (baixa manual) uma determinada transação
Às vezes, os clientes pagam as cobranças de outras maneiras, como em dinheiro ou por depósito bancário. Na conta Efí, somente é possível confirmar manualmente as cobranças feitas por boletos ou carnês. As cobranças realizadas através de links de pagamento, mesmo que o pagamento seja feito por boleto, não podem ser confirmadas manualmente.


Importante!
Quando uma transação é marcada como paga, nenhum valor de pagamento é retornado via API. Descontos, multas e moras não serão aplicados automaticamente.

Conheça as duas maneiras de confirmar manualmente o pagamento de uma cobrança na Efí:

1. Por meio do painel Efí:
Faça login em sua conta Efí;
Acesse o menu “Receber” e, logo em seguida, “Gestão de cobranças”;
Selecione a opção “Boletos”;
Escolha a cobrança que deseja confirmar;
Em seguida, clique no botão azul "Marcar como pago".
Esta operação não possui cobrança de tarifas.


Observação
A confirmação manual de pagamento só é permitida para cobranças com status Aguardando (waiting) ou Inadimplente (unpaid). Para os demais status — Pago (paid), Identificado (identified), Cancelado (canceled) e Expirado (expired) — não é possível marcar a cobrança como paga manualmente.

Após marcar como paga, a cobrança é automaticamente baixada em até 2 (dois) dias úteis. A partir desse momento, ela não estará mais disponível para pagamento.


2. Por meio de requisição via API:
Apenas transações com status waiting ou unpaid podem ser confirmadas manualmente. Existem dois endpoints responsáveis pelas confirmações manuais de pagamento:

settleCharge : permite marcar como pago (baixa manual) uma determinada transação;
settleCarnetParcel : permite marcar como pago (baixa manual) uma determinada parcela de um carnê.

NOTA
As confirmações de pagamento podem ser:

Confirmações Automáticas: é o mecanismo padrão oferecido pela API por meio da URL de notificação. Ou seja, disparamos um POST para sua URL de notificação assim que houver uma mudança no status da transação, seu sistema recepciona essa informação e realiza as tratativas para as quais foi designado. Em outras palavras, o status paid estará contido na notificação que enviamos. Logo, o status da transação será paid.
Confirmações Manuais: representada pelos endpoints settleCharge e settleCarnetParcel. É quando o pagamento foi realizado por formas de pagamento alternativas (ex: pagamento em mãos) e o integrador efetuou a confirmação manual pelo painel Efí, via requisição à API ou pelo seu próprio sistema de gestão. Neste caso, o status da transação será settled.


Marcar como pago determinada transação
Permite marcar como pago (baixa manual) uma determinada transação.

Para marcar uma transação como paga (baixa manual), você deve enviar uma requisição PUT para a rota /v1/charge/:id/settle.

PUT /v1/charge/:id/settle


Atenção!
Transações marcadas como pagas não geram movimentações financeiras em uma conta Efí, uma vez que o fluxo financeiro não ocorre sob controle da Efí.

Cartão
Passo a passo para gerar uma cobrança de cartão de crédito na API Efí


Introdução
As transações online via cartão de crédito exigem apenas a numeração de face e o código no verso do cartão, o que pode resultar em transações suspeitas. Por isso, é importante adotar procedimentos de segurança para evitar prejuízos financeiros, como o Chargeback.

Quando uma transação com cartão de crédito é realizada, ela passa por três etapas: autorização da operadora, análise de segurança e captura. Cada transação é analisada para identificar possíveis riscos. Se for aprovada, o valor é debitado na fatura do cliente. Caso contrário, o valor fica reservado até que a comunicação reversa seja concluída e o limite do cartão seja reestabelecido.

Confira a lista de cartões de crédito aceitos pela Efí
Visa
Master
AmericanExpress
Elo

Atenção!
Para fazer o pagamento com cartão de crédito,é necessário obter o payment_token da transação. Portanto, é imprescindível seguir os procedimentos para obter o payment_token conforme descrito no documento antes de criar a cobrança com cartão de crédito.

Outra informação importante é você precisa cadastrar o ramo de atividade em sua conta. Confira mais detalhes aqui.


Obtenção do payment_token
Um payment_token é um conjunto de caracteres gerado pela API Efí, que representa os dados do cartão da pessoa pagadora. Ele pode ser configurado para uso único ou reutilização recorrente.

Para transações com cartão de crédito, é realizada uma etapa prévia à criação da cobrança, onde ocorre a geração do payment_token. Isso pode ser feito transmitindo os dados do cartão, podendo utilizar a biblioteca JavaScript, ou o módulo jQuery.


Tokenização de cartão
Se você precisa reutilizar o payment_token para fins de recorrência, utilize o atributo reuse com o valor booleano true. Dessa forma, o payment_token pode ser usado em mais de uma transação de forma segura, sem a necessidade de salvar os dados do cartão



Simulação em Ambiente de Homologação
A simulação de cobranças de cartão em ambiente de Homologação funciona com base na análise imediata de acordo com o último dígito do número do cartão de crédito utilizado:

Cartão com final 1 retorna: "reason":"Dados do cartão inválidos."
Cartão com final 2 retorna: "reason":"Transação não autorizada por motivos de segurança."
Cartão com final 3 retorna: "reason":"Transação não autorizada, tente novamente mais tarde."
Demais finais têm transação aprovada.


Biblioteca JavaScript
Esta biblioteca JavaScript permite a criptografia dos dados do cartão diretamente no navegador do cliente, gerando o payment_token, identificando a bandeira do cartão e obtendo informações de parcelamento.

Demonstração
Para visualizar como essa biblioteca é utilizada em um exemplo prático, você pode conferir uma demonstração aqui.

Veja mais detalhes no repositório da biblioteca no GitHub.

Instalação
Abaixo, fornecemos algumas opções de instalação da biblioteca para atender a projetos web que utilizam JavaScript puro ou frameworks modernos. Veja os detalhes a seguir:

a) Aplicação Web(Browser)
Disponibilizamos duas formas de instalação da biblioteca em aplicações Web.

Importação por CDN
Importação através do link do CDN da biblioteca.

script
  <script src="https://cdn.jsdelivr.net/gh/efipay/js-payment-token-efi/dist/payment-token-efi-umd.min.js"></script>


Importação local
Realizando o download da biblioteca localizada em /dist/payment-token-efi.min.js, adicione-a localmente em seu projeto.

script
  <script src="./dist/payment-token-efi-umd.min.js"></script>

b) Gerenciador de pacote (NPM ou Yarn)
Se você estiver utilizando um gerenciador de pacotes como npm ou yarn, instale a biblioteca diretamente:

shell
      npm install payment-token-efi
      // ou
      yarn add payment-token-efi

Após a instalação, você pode importar a biblioteca conforme o ambiente que estiver utilizando:

Universal Module Definition (UMD)
Para ambientes que suportam Universal Module Definition:

script
  import EfiPay from "payment-token-efi";

ECMAScript Modules (ESM)
Para ambientes que suportam ES Modules:

script
  import EfiPay from "payment-token-efi";

CommonJS (CJS)
Para ambientes que utilizam o padrão CommonJS:

script
  const EfiPay = require("payment-token-efi");

Obs: Esta biblioteca não é compatível no backend em Node.js

Framework React Native e outros (WebView)
Para aplicações que não possuem DOM nativo, como React Native, Ionic, Swift, e outros frameworks similares, é necessário utilizar um componente WebView para executar a biblioteca. O WebView permite que a biblioteca funcione corretamente, pois fornece um ambiente DOM para sua execução. Disponibilizamos aqui um exemplo de demonstração com React Native.

Tipagens TypeScript
Se você estiver utilizando TypeScript, quando você instalar a biblioteca payment-token-efi, o TypeScript deve ser capaz de encontrar os tipos automaticamente localizados em types/payment-token-efi.d.ts

Utilização
Este script traz quatro funções que ajudam no processamento de dados de cartão de crédito:

A primeira função permite verificar se o script foi bloqueado por alguma extensão ou configuração do navegador.

A segunda função permite identificar a bandeira do cartão a partir do número digitado.

A terceira função busca informações de parcelamento de acordo com as configurações de recebimento via cartão em sua conta.

Por fim, a quarta função gera o token de pagamento (payment_token) e a máscara do cartão (card_mask) com base nos dados do cartão.

Para utilizar esse script, é necessário fornecer o código Identificador de conta (payee_code) como parâmetro para gerar o payment_token dos dados do cartão de crédito. Você pode obter essa informação em sua conta digital, no menu API > Introdução > Identificador de conta. Veja onde encontrá-lo. Certifique-se de ter essa informação disponível ao utilizar as funções do script.

a) Verificar bloqueio do script
Verificar bloqueio do script
b) Identificar a bandeira
Identificar a bandeira
c) Buscar as informações de parcelamento
Buscar as informações de parcelamento
d) Gerar o payment_token e card_mask
Gerar o payment_token e card_mask
e) Ativar debbuger
Ativar debbuger

Exemplos práticos
Disponibilizamos alguns exemplos de utilização para as principais linguaguagens de programação front-end. Acesse aqui.


Módulo jQuery
Uma opção para gerar o payment_token a partir dos dados do cartão é usar o front-end. Para isso, você precisa de um código JavaScript específico da sua conta Efí. Para gerá-lo, siga estes passos:

Efetue login em sua conta Efí e acesse API.
Copie seu Identificador de conta (veja onde)
Cole no campo abaixo e clique no botão Gerar
Identificador de conta



Atenção!
Após informar seu identificador de conta, serão gerados 2 (dois) códigos JavaScript distintos.
Copie e utilize o código referente ao ambiente desejado, atentando-se às diferenças do ambiente de "Homologação" e "Produção".


Este script permitirá executar duas funções:

getPaymentToken: Gera o payment_token de acordo com os dados do cartão;
getInstallments: Retorna as informações sobre parcelamento de acordo com as configurações de recebimento em sua conta.

Obtendo um "payment_token" (getPaymentToken) e informações sobre parcelamentos (getInstallments)
Código
$gn.ready(function (checkout) {

    checkout.getPaymentToken(
        {
            brand: 'visa', // bandeira do cartão
            number: '4012001038443335', // número do cartão
            cvv: '123', // código de segurança
            expiration_month: '05', // mês de vencimento
            expiration_year: '2021', // ano de vencimento
            reuse: false // tokenização/reutilização do payment_token
        },
        function (error, response) {
            if (error) {
                // Trata o erro ocorrido
                console.error(error);
            } else {
                // Trata a resposta
                console.log(response);
            }
        }
    );

checkout.getInstallments(
        50000, // valor total da cobrança
        'visa', // bandeira do cartão
        function (error, response) {
            if (error) {
                // Trata o erro ocorrido
                console.log(error);
            } else {
                // Trata a respostae
                console.log(response);
            }
        }
    );

});

Atributos relacionados ao envio de dados do cartão e retorno das funcões
$gn.ready (checkout)
Essa função de inicialização permite a chamada das funções getPaymentToken e getInstallments. Você passa um objeto (checkout) que recebe as instâncias dessas funções.


getPaymentToken
getInstallments

Back-end

Atenção
O procedimento de geração do payment_token no back-end foi descontinuado com base em medidas de segurança. A fim de garantir a proteção dos dados, informações sensíveis e evitar recusa nas transações de cartão de crédito, recomendamos que você descontinue o uso deste serviço . Com base em nossa avaliação, gostaríamos de sugerir a geração através do front-end da aplicação


Criação de cobrança por cartão de crédito em One Step (Um passo)
Após obter o payment_token através do código Javascript ou do back-end, seu servidor enviará as informações dos itens, da transação e do cliente, juntamente com o payment_token, para a API Efí.

Estrutura hierárquica dos atributos do Schema que podem ser utilizados:
POST /v1/charge/one-step

Criação de cobrança por cartão de crédito em Two Steps (Dois passos)
Nesta opção é necessário seguir dois passos, enviando o body da requisição com todos os atributos mínimos obrigatórios para a emissão da cobrança.

Crie a transação, informando o item/produto/serviço, valor, quantidade, etc;
Associe à forma de pagamento via boleto, informando o charge_id da transação e os dados do cliente.
A documentação continua com os procedimentos detalhados, mas lembre-se de instalar uma de nossas bibliotecas em seu servidor para executar os códigos de exemplo. Certifique-se de que a SDK da Efí foi instalada.

1. Criar transação
Primeiramente, precisamos gerar a transação (também chamada de "cobrança"). Nesse momento, você informará o nome do item/produto/serviço, o valor da transação, a quantidade e outras informações relevantes.

Após criá-la, será retornado o charge_id, que é o identificador único da transação e que será utilizado para associar à forma de pagamento.

No momento da criação, a transação recebe o status new, que significa que a cobrança foi gerada e está aguardando definição da forma de pagamento. Essa cobrança somente terá seu status alterado quando o integrador definir sua forma de pagamento.

Para gerar uma transação, você deve enviar uma requisição POST para a rota /v1/charge.

Estrutura hierárquica dos atributos do Schema que podem ser utilizados:
POST /v1/charge

2. Associar à forma de pagamento via cartão
Com a transação gerada com sucesso, agora vamos associar com a forma de pagamento desejada - neste caso, será credit_card (cartão de crédito). Para tal, deverá ser informado o charge_id obtido ao criar a transação.

Nesta etapa, seu backend enviará o restante das informações da transação, junto com o payment_token para a API Efí.

Para associar à forma de pagamento, você deve enviar uma requisição POST para a rota /v1/charge/:id/pay, onde :id é o charge_id da transação desejada.

Estrutura hierárquica dos atributos do Schema que podem ser utilizados:
POST /v1/charge/:id/pay


Pagamento realizado como Pessoa Jurídica (PJ)
O cliente associado à transação pode ser uma Pessoa Jurídica. Nesse caso é necessário informar a Razão Social e o CNPJ da empresa pagadora no atributo juridical_person.



Relação de todos os possíveis status de uma transação
Todas as transações possuem status, que representa a "situação" dessa transação. Portanto, é importante conhecer os possíveis status de uma transação na API para fornecer as devidas tratativas em seu sistema.

Confira neste link todos os detalhes dos possíveis status das transações.



Callbacks (notificações) das transações da API para seu sistema
As notificações permitem que você seja informado quando uma transação tiver seu status alterado. Dessa forma, você poderá identificar quando um boleto for pago, por exemplo.

Confira neste link todos os detalhes sobre como implementar a sua URL de notificação.



Arredondamento de tarifa do cartão e-commerce
A formatação da tarifa é feita com number_format, ou seja, um arredondamento simples. Valores menores que 5 é arredondado para baixo, valores maiores ou igual a 5 o arredondamento é para cima.

Exemplo:
Tarifa de 3,342 é arredondado para baixo 3,34 Tarifa de 3,335 vai ser arredondado para cima 3,34


Retentativa de pagamento via cartão de crédito
Os pagamentos realizados via cartão de crédito, que forem recusados por algum motivo operacional, como falta de limite, dados incorretos e problemas temporários com o cartão, poderão ter uma nova tentativa de pagamento via API.

Dessa forma, não será necessário realizar todo o processo de emissão da cobrança novamente, tornando o fluxo mais rápido e eficiente.

POST /v1/charge/:id/retry


Informação
Esta funcionalidade permite que o integrador tente reprocessar uma cobrança que falhou. Para isso, a cobrança deve atender aos seguintes critérios:

a cobrança deve ser de cartão de crédito
a cobrança deve ter o status unpaid


Estorno de pagamento via cartão de crédito
Este endpoint permite realizar o estorno de um pagamento efetuado por meio de cartão de crédito.

O estorno pode ser total ou parcial, dependendo do valor especificado. Se o valor não for informado, o estorno será total.

POST /v1/charge/card/:id/refund


Informação
Esta funcionalidade permite que o integrador faça o estorno de um pagamento. Para isso, a cobrança deve atender aos seguintes critérios:

a cobrança deve ter o status paid
não pode ter outro estorno para a mesma cobrança ainda em processamento
só pode ter um estorno por dia para a mesma cobrança (parcial)
a cobrança tem que ser do tipo cartão de crédito
o estorno parcial pode ser solicitado em até 90 dias após a confirmação do pagamento
o estorno total pode ser solicitado em até 360 dias após a confirmação do pagamento
o estorno não está disponível para vendas de marketplace


Retornar informações de cobrança existente
Para retornar informações de uma transação, você deve enviar uma requisição GET para a rota /v1/charge/:id, onde :id é o charge_id da transação desejada.

GET /v1/charge/:id
Retornar lista de cobranças
Para retornar as informações das cobranças emitidas em uma aplicação, você deve enviar uma requisição GET para a rota /v1/charges.

Este endpoint possui filtros para afunilar os resultados da busca, tais como CPF/CNPJ e status. Dentre todos os filtros disponíveis, os filtros charge_type, begin_date e end_date são obrigatórios e representam o tipo da transação e o intervalo de datas em que as cobranças consultadas devem estar compreendidas.


Importante!
Atualmente este recurso está em versão beta. Estamos entusiasmados em compartilhar essa ferramenta com você, porém, é essencial lembrar que ela está em desenvolvimento ativo e pode passar por alterações durante este período.
Valorizamos profundamente seu feedback durante esta fase e queremos ouvir suas experiências e sugestões para aprimorar continuamente nossos serviços. Sinta-se à vontade para entrar em contato conosco por meio de nossa comunidade do Discord ou outros canais de suporte.



GET /v1/charges


Limite de consumo
Assim como todos os endpoints de nossa API, a listagem de cobranças também possui um limite diário, que pode ser conferido na aba Limites de Consumo.

Caso as consultas excedam estes valores, recomendamos abrir um ticket em sua conta, solicitando a liberação.



Incluir "notification_url" e "custom_id" em uma transação existente
Você pode definir ou modificar as informações enviadas na propriedade metadata da transação a qualquer momento. Este endpoint é de extrema importância para atualizar a URL de notificação vinculada às transações ou modificar o custom_id associado anteriormente.

Para alterar a notification_url e/ou custom_id de uma transação, você deve enviar uma requisição PUT para a rota /v1/charge/:id/metadata, onde :id é o charge_id da transação desejada.

Casos de uso deste endpoint:
A pessoa integradora alterou o IP do servidor que estava associado à URL de notificação das transações;
A pessoa integradora atualizou a URL de notificação para as novas transações que forem criadas (createCharge), mas precisa atualizar também as transações anteriores (updateChargeMetadata) que foram geradas e que estão associadas com a URL incorreta/desatualizada;
Foi instalado SSL (https) no servidor do cliente e mesmo que o cliente defina uma regra de redirecionamento 301 ou 302, será necessário definir a nova URL nas transações que estão usando a URL "antiga";
A pessoa integradora gerou cobranças sem informar a URL de notificação ao enviar a requisição de criação da transação;
Modificar ou acrescentar uma informação junto ao atributo custom_id associado às transações geradas previamente;
Dentre outros possíveis cenários.
PUT /v1/charge/:id/metadata

Cancelar uma transação existente
Uma transação pode ser cancelada apenas se ela possuir o status new, waiting, unpaid ou link.

Quando uma transação é cancelada, existe apenas uma condição para que o status seja alterado novamente: se o cliente imprimir o boleto antes que a pessoa integradora cancele a transação, ele poderá realizar o pagamento normalmente em uma agência bancária. Nesse caso, tanto a pessoa integradora quanto a pagadora recebem a confirmação do pagamento, e o status da cobrança é alterado de canceled para paid.

Para cancelar uma transação, como um boleto, você deve enviar uma requisição PUT para a rota /v1/charge/:id/cancel, onde :id é o charge_id da transação que você deseja cancelar.

PUT /v1/charge:id/cancel

Acrescentar descrição ao histórico de uma transação
O histórico de uma transação mostra todas as ações que ocorreram até o momento, mas as mensagens personalizadas não afetam a transação em si, apenas aparecem no histórico.

Este pode ser visualizado tanto no detalhamento da transação pela interface quanto usando o endpoint de detalhes da transação.

Você pode visualizar o histórico tanto na interface de detalhes da transação quanto usando o endpoint de detalhes da transação.

Você pode visualizar o histórico da transação na interface ou usando o endpoint de detalhes da transação. Para isso, basta enviar o identificador charge_id e a mensagem que deseja adicionar ao histórico da transação. A descrição deve ter entre 1 e 255 caracteres.

POST /v1/charge/:id/history

Listar parcelas de acordo com a bandeira do cartão
O endpoint installments é utilizado para listar as parcelas de cada bandeira de cartão de crédito, já com os valores de juros e número de parcelas calculados de acordo com a conta integradora. Ou seja, se a sua conta possui uma configuração de juros para cartão de crédito (opção disponível para clientes que optaram por receber pagamentos de forma parcelada), você não precisa fazer nenhum cálculo adicional, pois esse endpoint já fornece os valores calculados automaticamente.

Bandeiras disponíveis: visa, mastercard, amex e elo.

GET /v1/installments

Transações de cartão de crédito não autorizadas
Em determinadas situações, uma transação de cartão de crédito pode ser recusada pela adquirente. Essas mensagens de retorno são enviadas diretamente pelas bandeiras ou pelas próprias adquirentes, conforme regras do mercado.

Ex: "51 - Transação não autorizada, entre em contato com o emissor do cartão.”

Esses códigos seguem o padrão definido pela ABECS (Associação Brasileira das Empresas de Cartões de Crédito e Serviços), conforme o normativo publicado em 15 de julho de 2020, que padroniza os códigos de retorno para transações de vendas recusadas.

Para acessar a tabela oficial, com todos os códigos possíveis de retorno, acesse o site da ABECS em Normativo ABECS.

Os códigos de retorno mais recorrentes são:

Código	Mensagem	Código ABECS
51	Transação não autorizada, entre em contato com o emissor do cartão	Saldo/Limite insuficiente
83	Transação não autorizada, entre em contato com o emissor do cartão	Senha vencida/inválida
Outros retornos recorrentes são:

A mensagem “Transação não autorizada. Cartão inválido” é retornada em caso de possível erro de digitação ou para cartão vencido.
A mensagem “Transações não autorizada, utilize outro método de pagamento” é retornada em caso de transação considerada com suspeita de alto risco, por análise do Efí ou da adquirente.