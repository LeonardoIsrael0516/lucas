ideia inicial:
Getty.

Open Source:
Infoprodutor > Alunos

Cloud:
Admin > Multi infoprodutores


Vamos criar uma aplicação.
Vai ser um checkout, com area de membros integrada, alunos, inforpodutos, tudo que uma plataforma de inforpodutos tem, como hotmart, cakto, kiwify por exemplo…

Mas cada infoprodutor vai poder integrar seu proprio gateway de preferencia que vamos integrar posteriormente…

A aplicação vai ter 2 modos:
open source, onde as pessoas vao pdoer usar gratuitamente no seu proprio vps ou hospedagem… instalando via docker (igual n8n) ou com wizard que vamos criar mais pra frente.

E uma versao cloud, que vai ser o saas, onde as pessoas vão poder adquirir atraves de plano e ja ter acesso a mesma plataforma como infoprodutor, porem nao vai rpecisar instalar e configurar na sua propria maquina, ja vai vim vai ser como Saas.

vou pasasr abaixo alguma referencias e rascunmho de como deve ser.
🚀 GUIA OFICIAL DO PROJETO
Plataforma Global de Checkout + Área de Membros
 Open Source • Multi-Tenant • Cloud + Self-Hosted

🎯 1. VISÃO DO PRODUTO
Construir uma plataforma:
Open source desde o início


Multi-tenant nativo


Instalável via Docker


Extensível por plugins


Cloud SaaS escalável


Exportável e portátil


Global


Inspirada em modelos modernos como o n8n, porém focada em vendas digitais.

Laravel + Vue.js + Inertia.js + shadcn-vue

Agora organizado de forma profissional 👇

🧱 STACK COMPLETA
🔹 Backend

PHP 8.3+

Laravel 11

MySQL ou MariaDB

Redis (fila + cache)

Laravel Horizon (monitorar filas)

Laravel Sanctum (auth API se precisar)

🔹 Frontend (dentro do Laravel)

Vue 3

Inertia.js

Vite

TailwindCSS

shadcn-vue (UI moderna)

Pinia (estado global se precisar)

Tudo dentro do mesmo projeto.
Um deploy só.
Sem SPA separada.

🗄 Banco de Dados

Recomendação estratégica:

MariaDB / MySQL para compatibilidade máxima


⚡ Infra recomendada
Desenvolvimento

Laravel local

Redis via Docker

Banco via Docker

Produção VPS

Nginx

PHP-FPM

MySQL/MariaDB

Redis

Supervisor para filas

Simples e robusto.

🧠 Arquitetura do Front

Inertia controla navegação

Vue renderiza páginas

shadcn-vue entrega componentes modernos

Tailwind estiliza

Vite builda

Resultado:
SPA moderna
Sem API separada
Sem complexidade desnecessária

🎯 Por que essa stack é forte

✔ Moderna
✔ Visual profissional
✔ Escalável
✔ Fácil de manter
✔ Boa para vender instalação
✔ Boa para SaaS próprio
✔ Performance excelente

🏗 Se quiser deixar nível profissional mesmo

Adicionar:

ESLint

Prettier

Testes com Pest (Laravel)

Vitest no front

CI/CD simples

🔥 Resultado final

Você terá:

Backend sólido
Frontend moderno estilo React
UI profissional
Deploy simples
Performance excelente
Sem overengineering

É uma stack equilibrada e madura.

basicament eo painel do infoprodutor vai ter:
- dasboard
- vendas
- produtos
- relatorios
- integrações
- configurações
etc..

Lembrando que alem de seguro, o sistema deve ser todo dividido modularizado o maximo possivel, para que quando formos mexer em algo, nao quebre outra coisa que ja esta funcionando e facilitar manutenção e upgrades depois..

Lembrando tambem que deve ta adptado para sistema de plugins, que vai ter futuramente, onde usuários poderam contribuir criando plugins para a plataforma, sem ter que mexer em nada do codigo fonte oficial.

lembrando tambem, para o modo cloud (saas), nao quero que seja baseado apenas em true ou false ligado, e sim que tenha mais arquivos para separar, para nao ter risco das pessoas baixarem e ligar o true podendo assim clonar o cloud.

lembrando que vamos rodar em docker, para facilitar quem for instalar sozinho tambem…
por padrao pra quem for instalar por conta propria pode ser sqlite mas tem opção de instalar com postgree tambem…

importante: use algum template pronto do frontend.. tem que ser bonito, moderno, profssionale  clean ao mesmo tempo…

Lembrando tambem que deve funcionar no sqlite tambem e no postgree. a rpncipio vamos testar primeiro no sqlite… ja pode dexar criado a conta de inforpdoutor e deve ser tudo funcional 

Lembrando que precisa ser muito seguro e otrmizado...
modularizado ao maximo, para facilitar manutenção de upgrades...
o tema precisa ser moderno... quero tanto white como dark... 

vai ter 2 vertentes:
1- modo opensource: esse modo vai ser a plataforma como painel do infoprodutor, onde ele vai poder gerenciar tudo, e tambem fazer configurações, tema, smtp, cores etc.. como se fosse o admin... ( inforpodutor vai istalar na sua propio servidor)

2- modo cloud: esse modo será usado por mim, vai ser a mesma plataforma, mesma coisas do opensource, a diferença é que cada inforpdoutor nao vai instalar no seu proprio servidor, e sim vai usar a minha estrutura, como se fosse um saas... vai tem um painel admin que será só meu, onde vou poder gerenciar os usuarios e planos e algumas coisas...
porem o infoprodutor vai ter que poder fazer tudo como no opensouce, personalizar... inclusive vai poder usar seu proprio dominio ou subdominio pra acessar o painel e tudo mais, como se fosse uma estrutura separada.. (veja se realmente isso é possivel e viavel)
importante: deve ser praticamente 1 plataforma, porem poder desmembrar...
Por exemplo ter estrutura de pastason eu eu posso remover essa pasta no modo opensource, e manter essas pastas onde o cloud funciona somente pra mim.. pra nao ter risco de outras pessoas baixarem e clonarem minha estrutura cloud... que seja simples de desativas o modo cloud, talvez somente removendo uma estrutura de pastas que sao necessrias pra funcionar + algo como true/false escondido...

outra coisa: a rota do admin para o modo cloud, quero algo como: /ananin (vai ser o meu painel admin)

lembrando que modo opensource vai ter: infoprodutor e aluno, essas duas roles
E no modo cloud vai ter tambem o admin que sera so meu



#importante:
- Tudo deve ser seguro.
- Tudo deve ser o mais modularizado e separado possivel, para que fique facil futuramente de mexer em coisas sem correr risco de quebrar o que ja funciona.
- todas as paginas tem que ta adaptado para plugins no futuro, inclsuive pra quem quiser criar plugins, que adicina função nova, modifica a plataforma etc...
- nao fique criando docuemntações md desnecessarios
 
Nota: Implementada aba "E-MAIL" nas configurações com campos completos (host, porta, usuário, senha criptografada, remetente, reply-to), teste de conexão e envio de e-mail de teste. (feita em 2026-02-25)