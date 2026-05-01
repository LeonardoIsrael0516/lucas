Introdução as API
Transformando sua Interação com o WhatsApp

Bem-vindo à API da Menuia, sua porta de entrada para uma experiência revolucionária de integração com o WhatsApp. Se você busca automatizar processos, ampliar a eficiência de seus projetos e aprimorar a comunicação com seus clientes, você está no lugar certo.
Nossa API foi cuidadosamente desenvolvida para capacitar seu projeto, permitindo que você aproveite ao máximo o potencial do WhatsApp como uma ferramenta de comunicação e interação em tempo real. Seja qual for o seu objetivo - desde fornecer suporte ao cliente 24/7 até criar experiências interativas e envolventes - a API da Menuia está aqui para ajudar.
Imagine automatizar o envio de mensagens, receber notificações em tempo real, coletar dados valiosos e personalizar a experiência do usuário de acordo com suas necessidades. Com a API da Menuia, tudo isso se torna possível. Ela oferece flexibilidade, escalabilidade e simplicidade, tornando a integração com o WhatsApp uma tarefa acessível para qualquer desenvolvedor.
Nesta documentação, você encontrará todas as informações necessárias para começar sua jornada de integração com a API da Menuia. Desde os conceitos básicos até as funcionalidades avançadas, estamos aqui para guiá-lo em cada passo do processo. Abrace a oportunidade de simplificar seus processos, conectar-se de maneira mais eficiente com seu público e elevar a qualidade de suas interações no WhatsApp.
Estamos empolgados para ver como você utilizará a API da Menuia para transformar seus projetos e alcançar novos patamares de sucesso. Vamos começar a explorar o potencial infinito de comunicação e automação que ela oferece. Seja bem-vindo à revolução da integração com o WhatsApp.
Endpoints e tokens
youtube
facebook
instagram
Endpoints e tokens
O que seria um endpoint?

Em uma URL de API, você normalmente tem o seguinte formato:
https://chatbot.menuia.com/api/create-message?appkey=xxx000&authkey=yyyy00
No modelo abaixo temos um url padrão, utilizado para consumir API.
https://chatbot.menuia.com/api/
Em seguida temos o Endpoint que identifica qual recurso ou serviço você deseja acessar, “ação” pode especificar a ação a ser executada nesse recurso e
create-message
appkey é o token que é obtido quando se habilita um dispositivo para que o mesmo possa consumir api.
authkey é o token que é gerado quando o usuário é cadastrado em nosso site.
Introdução as API
AuthKey
youtube
facebook
instagram
AuthKey
Token de autenticação usuários.

Por meio deste token exclusivo, o servidor recebe as informações fornecidas pelo usuário e realiza um processo abrangente de autenticação e validação. Isso envolve vários passos, como verificar a autenticidade do token, confirmar a identidade do usuário, verificar as permissões associadas a esse usuário e garantir que ele tenha o acesso apropriado aos recursos ou funcionalidades solicitados.
O token age como uma credencial digital, permitindo que o servidor verifique se o usuário é quem ele afirma ser. O servidor também avalia se o token não está expirado e se ainda é válido para a ação em questão. Além disso, o servidor pode verificar se o usuário tem as permissões necessárias para realizar a ação desejada, garantindo que apenas usuários autorizados possam executar determinadas operações. O token é gerado automaticamente, mas pode ser regenerado pelo painel acessando:
https://chatbot.menuia.com/user/auth-key
Endpoints e tokens
AppKey
youtube
facebook
instagram
AppKey
Token de autenticação do dispositivo.

Uma AppKey, ou chave de aplicativo (Instancia), é uma sequência única de caracteres alfanuméricos atribuída a um aplicativo específico. Ela é usada para autenticar e autorizar a interação entre o aplicativo e uma API ou servidor. A principal função da AppKey é identificar o aplicativo que está fazendo uma solicitação e permitir que o servidor ou a API verifique sua legitimidade.
Em termos simples, a AppKey atua como um “código de acesso” que garante que apenas aplicativos autorizados tenham permissão para acessar os recursos ou funcionalidades oferecidos pela API. A mesma é criada através da API do desenvolvedor ou através do site acessando:
https://chatbot.menuia.com/user/apps
AuthKey
Introdução
youtube
facebook
instagram
Introdução
Formas de consumir API de configurações

Um parâmetro de API é uma parte fundamental das chamadas de API. Ele é usado para passar informações específicas e configurar o comportamento de uma função ou recurso da API. Os parâmetros permitem que você personalize solicitações de API, ajustando os resultados e as ações realizadas pelo servidor.
AppKey
Destinatários
youtube
facebook
instagramConsumindo API de configurações
Formas de consumir API de configurações

​
Alterar Recado
POST https://chatbot.menuia.com/api/settings
Path Parameters
Name	Type	Description
appkey*	Texto	Chave de autenticação do aplicativo
authkey*	Texto	Chave de autenticação do usuário
message*	Texto	Ex: Disponível
editeStatus*	Booleano	true
200
404
500
{“status”:200,“message”:“Recado atualizado com sucesso!”}
​
Alterar Nome
POST https://chatbot.menuia.com/api/settings
Path Parameters
Name	Type	Description
appkey*	Texto	Chave de autenticação do aplicativo
authkey*	Texto	Chave de autenticação do usuário
message*	Booleano	false
altereNome*	Booleano	true
200
404
500
{“status”:200,“message”:“Nome Atualizado com sucesso!”}
​
Obter Imagem - (Usuarios ou Grupo)
POST https://chatbot.menuia.com/api/settings
Path Parameters
Name	Type	Description
appkey*	Texto	Chave de autenticação do aplicativo
authkey*	Texto	Chave de autenticação do usuário
message*	Booleano	false
obterImagem*	Booleano	true
to*	Texto	Ex: +5581989769960 Ou ID Grupo
200
404
500
{“status”:200,“message”:“Nome Atualizado com sucesso!”}
Introdução
Criando um Fluxo
youtube
facebook
instagram
Powered by

https://docs.menuia.com/api-reference/create-message/enviarMensagemAgendada
https://docs.menuia.com/webhook-reference/events/audio