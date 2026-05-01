Início
Introdução
​
Z-API - Asas para sua imaginação!
Z-API foi desenvolvido por programadores para programadores, por isso prezamos pela simplicidade e objetividade de tudo que nos propomos a fazer, sendo assim chega de conversa fiada e ** Let’s Bora!**
​
Mas o que é Z-API?
Você provavelmente já deve saber, mas vamos reafirmar!
Z-API é um serviço RestFul que provê uma API que permite que você interaja com seu WhatsApp através de uma API simples e intuitiva, além de webhooks para te avisar sobre interações com seu número.
Importante
O Z-API reafirma que não é destinada para prática de SPAM e envio de mensagens indesejadas ou qualquer ação que viole os termos de serviço do WhatsApp.
Utilize a API com sabedoria criando funcionalidades que gere valor aos seus clientes e aos usuários do WhatsApp.
​
Quem pode utilizar Z-API?
Não temos restrições quanto a utilização, mas geralmente são 2 públicos bem distintos que utilizam nossos serviços. São eles:
Programadores com conhecimentos em API’s RestFul. Se você não é, mas conhece alguém com estas competências, já serve :)
Utilizadores de soluções de terceiros que permitam integração com Z-API
​
Tá bom! Mas o que dá para fazer com ele?
De forma bem direta, tudo que você faz com WhatsApp Web você poderá fazer utilizando nosso serviço. Para isso basta ler o QRcode do Z-API e utilizar nosso serviço!
​
Tecnicamente, como funciona o fluxo de envio?
Para exemplificar, segue os passos de envio de uma mensagem de texto simples:
Você envia via API uma mensagem para o Z-API;
O Z-API adiciona em uma fila e te retorna o ID da mensagem;
Sua instância processa a fila enviando para o WhatsApp;
Seu Webhook de delivery é chamado quando a mensagem é processada, te avisando que foi enviada ou que houve falha.
Assim que o destinatário receber a mensagem, o Webhook de message-status é chamado informando RECEIVED.
Por fim quando o destinatário ler a mensagem o messages-status é chamado informando READ
​
Limites
Iniciei por este tópico porque é bem comum as pessoas perguntarem sobre quais os limites de envios com Z-API. Nós NÃO TEMOS LIMITE para número de mensagens enviadas! Mas é importante você entender que esta utilizando uma sessão do WhatsApp Web, então o padrão de utilização precisa ser compatível, além disso sempre recomendamos que você leia atentamente as políticas estabelecidas pelo proprio WhatsApp em sua pagina oficial https://www.whatsapp.com/legal.
NÃO ARMAZENAMOS MENSAGENS!
Todas as mensagens enviadas para nossa API serão encaminhadas para uma fila de mensageria e após o envio as mesmas são apagadas.
Lembre-se
O Facebook tem comportamentos diferentes para cada uma das versões do WhatsApp, nossa API disponibiliza métodos compativeis com a versão WEB.

ID e Token
Entenda como funciona a autenticação na Z-API

​
O que é e para que serve?
Para comunicação entre API’s vamos precisar estabelecer um protocolo de segurança entre as partes, sendo assim toda interação que você fizer com a Z-API vai precisar informar seus atributos de autenticação. Esses atributos vão compor a URL de integração com a Z-API, como no exemplo abaixo:
https://api.z-api.io/instances/SUA_INSTANCIA/token/SEU_TOKEN/send-text
​
Como consigo meu ID e Token?
Após criar sua conta na Z-API e criar uma instância, você terá duas informações que identificam e protegem a comunicação entre sua aplicação e a Z-API. Para acessar e visualizar os dados da sua instância, basta clicar em editar na instância desejada dentro do painel administrativo. Você pode ter múltiplas instâncias, cada uma com credenciais únicas.
Nunca compartilhe o seu ID e token com ninguém, pois qualquer pessoa que tiver essas informações poderá enviar mensagens em seu nome. Suas chamadas de API devem ser feitas a partir de um servidor, nunca do frontend, para evitar a exposição dos seus dados de autenticação.

Restrição de chamadas por IP
Restrinja as chamadas feitas à API com base nos endereços IP dos solicitantes

​
Restrição de IPs
O método de segurança de bloqueio por IP introduz uma camada adicional de proteção, permitindo aos usuários restringir as chamadas feitas à API com base nos endereços IP dos solicitantes.
Isso significa que você pode controlar quais IPs têm permissão para acessar sua API e quais são bloqueados.
​
Ativando o recurso
Para ativar esse recurso, siga os passos simples abaixo:
1
Faça o Login na Z-API

Acesse o painel de controle da Z-API com suas credenciais de administrador.
2
Navegue até a Página de Segurança

No painel da Z-API, encontre a opção “Segurança” no menu de navegação ou na área de configurações.
Painel de segurança Z-API
​
Funcionamento básico
Quando o módulo de Restrição de IPs não está ativado, a API funciona normalmente e permite o acesso de qualquer endereço IP que faça uma solicitação.
​
Comportamento de IP não cadastrado
Quando uma solicitação é feita a partir de um endereço IP que não está na lista de IPs permitidos, a API responde com uma mensagem de erro clara:
{
  "error": "[IP da chamada] not allowed"
}
​
Benefícios do bloqueio por IP
Controle
Com essa funcionalidade, você tem controle total sobre quem pode acessar a sua API.
Proteção contra Ameaças
A restrição de IPs ajuda a proteger sua API contra acessos não autorizados.
Conformidade de Segurança
Para empresas que precisam cumprir regulamentos rigorosos de segurança.

Autenticação de dois fatores
Adicione uma camada extra de segurança ao painel da Z-API com autenticação de dois fatores

​
Segurança de Dois Fatores
A implementação de uma etapa adicional de autenticação, conhecida como autenticação de dois fatores (2FA), é uma camada vital de segurança para proteger o painel da Z-API contra acessos não autorizados e ameaças cibernéticas.
​
Ativando o recurso
1
Acesse o Painel da Z-API

Faça login no painel da Z-API com suas credenciais.
2
Navegue até a Página de Segurança

No painel da Z-API, encontre a opção “Segurança” no menu de navegação.
3
Configure a Autenticação de Dois Fatores

Localize a opção “Autenticação de Dois Fatores” e clique em “Configurar Agora”. Um QR code será gerado para sincronização com aplicativos autenticadores como 1Password, Google Authenticator ou Microsoft Authenticator.
Configuração de dois fatores
​
Funcionamento
Após configurar, siga o fluxo abaixo para autenticar:
Abra o aplicativo autenticador no seu celular.
Adicione a conta manualmente ou escaneie o QR code gerado.
Vincule a conta da Z-API ao seu dispositivo.
Cada vez que você fizer login no painel Z-API, será solicitado que você forneça um código de uso único gerado pelo aplicativo autenticador.
O código se regenera a cada poucos segundos e é único por conta.
​
Benefícios do uso do 2FA
Proteção Adicional
A senha sozinha não é suficiente para acessar o painel. Mesmo que alguém descubra sua senha, ainda precisará do código gerado pelo autenticador.
Prevenção contra Acesso Não Autorizado
O acesso requer a posse do dispositivo móvel vinculado, dificultando significativamente tentativas de acesso não autorizado.
Segurança de Dados Sensíveis
Protege informações confidenciais armazenadas no painel da Z-API com uma camada extra de verificação.
Conformidade com Padrões de Segurança
Atende aos requisitos de segurança exigidos pela indústria e regulamentações vigentes.

Token de segurança da conta
Utilize a validação por token para adicionar uma camada de proteção às suas instâncias

​
Token de Segurança da Conta
Esse método de segurança da Z-API utiliza a validação por token, proporcionando uma camada adicional de proteção para suas instâncias, garantindo que apenas solicitações autorizadas tenham acesso aos seus recursos.
​
Ativando o recurso
Para habilitar o recurso de validação por token, siga estas etapas simples:
1
Faça login na sua conta Z-API

Acesse o painel da Z-API com suas credenciais.
2
Navegue até a aba Segurança

Localize o módulo “Token de Segurança da Conta”.
3
Clique em Configurar Agora

Isso gerará um token, que inicialmente estará desabilitado para evitar interrupções na operação da sua aplicação.
Token de segurança da conta
​
Funcionamento básico
O funcionamento do método de segurança por token é direto:
Após a geração do token, ele deve ser incluído na header de todas as suas requisições HTTP.
O token deve ser passado da seguinte forma:
Atributo: Client-Token
Valor: [token]
Após configurar seu ambiente para enviar o token nas requisições, você pode clicar em “Ativar Token”.
A partir deste momento, todas as instâncias da sua aplicação só aceitarão requisições que contenham o token na header.
​
Comportamento do token não cadastrado
Caso uma requisição seja feita sem o token configurado, a API responderá com um erro, conforme o exemplo abaixo:
{
  "error": "null not allowed"
}
​
Benefícios da validação por token
Proteção Reforçada
A validação por token garante que apenas requisições autorizadas acessem suas instâncias, adicionando uma barreira extra contra acessos indevidos.
Controle Total
Você decide quando ativar o token, podendo configurar todo o seu ambiente antes de habilitar a validação, evitando interrupções.

