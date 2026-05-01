https://ajuda.cademi.com.br/configuracoes/api
Saiba como utilizar documentações de API em sua Cademí

Endpoint
Use o seu domínio Cademí para acessar seu realm de consultas.

Seu endpoint terá o seguinte formato: https://(seu-subdominio).cademi.com.br/api/v1

Autenticação
Para se autenticar você precisa antes criar uma Chave de API da sua Cademí.

Ela será comunicada em todas as requisições pelo header: Authorization: (sua-api-key)


Como se autenticar e começar a usar sua API Cademí
Datas
Todas as datas informadas pela API estão em formato ISO-8601

Respostas
200 - Sucesso

403 - Sem header de segurança

405 - Token inválido

409 - Erro de regra de negócio

Exemplo - Respostas: 400x

Copiar
{
    "success":false,
    "code":int,
    "msg":string,
    "profiler":{
        "start":float,
        "finish":float,
        "process":float
    }
}
Existe uma limitação de 2 disparos via API por segundo, total de 120 disparos por minuto.
Atente-se para esse fator ao montar suas estruturas de automação.

Anterior
Webhooks
Próximo
Usuário
Atualizado há 3 anos

https://ajuda.cademi.com.br/configuracoes/api/usuario
Listar todos Usuários
Retorna todos os Usuários.

Parâmetros

usuario_email_id_doc - required - ID, email ou documento do usuário

Response - 200 - OK

Copiar
{
    "success":true,
    "code":200,
    "data":{
        "paginator":{
            "perPage": 150,
            "next_page_url": "http://membros.dvp/api/v1/usuario?cursor=eyJjcmVhdGVkX2F0IjoiMjVcLzA1XC8yMDIxIFx1MDBlMHMgMTY6MzUiLCJfcG9pbnRzVG9OZXh0SXRlbXMiOnRydWV9",
            "prev_page_url": null
        },
        "usuario":[{
            "id":1126594,
            "nome":"Renan C",
            "email":"teste@oculto.com.br",
            "doc": "123.123.123-12",
            "celular":null,
            "login_auto":"http://membros.dvp/auth/login?crstk=",
            "gratis": false,
            "criado_em":"2022-01-12T15:50:47-03:00",
            "ultimo_acesso_em":null
      },{
          ...
      }]
    },
    "profiler":{
        "start":1646263257.919774,
        "finish":1646263257.937168,
        "process":0.017393827438354492
    }
}
Usuário
Retorna um usuário pelo seu ID, Email ou documento.

Parâmetros

usuario_email_id_doc - required - ID ou email do usuário

Response - 200 - OK

Copiar
{
    "success":true,
    "code":200,
    "data":{
        "usuario":{
            "id":1126594,
            "nome":"Renan C",
            "email":"teste@oculto.com.br",
            "doc": "123.123.123-12",
            "celular":null,
            "login_auto":"http://membros.dvp/auth/login?crstk=",
            "gratis": false,
            "criado_em":"2022-01-12T15:50:47-03:00",
            "ultimo_acesso_em":null
        }
    },
    "profiler":{
        "start":1646263257.919774,
        "finish":1646263257.937168,
        "process":0.017393827438354492
    }
}
Atualizar Usuário
Atualiza informações do cadastro do usuário (nome, e-mail, documento e celular).

Parâmetros via post:

usuario_id

nome

email

doc

celular

Response - 200 - OK

Copiar
{
    "success":true,
    "code":200,
    "data":{
        "usuario":{
            "id":1126594,
            "nome":"Renan C",
            "email":"teste@oculto.com.br",
            "doc": "123.123.123-12",
            "celular":null,
            "login_auto":"http://membros.dvp/auth/login?crstk=",
            "gratis": false,
            "criado_em":"2022-01-12T15:50:47-03:00",
            "ultimo_acesso_em":null
        }
    },
    "profiler":{
        "start":1646263257.919774,
        "finish":1646263257.937168,
        "process":0.017393827438354492
    }
}
Remover Usuário
Retorna um usuário pelo seu ID, Email ou documento.

Parâmetros

usuario_email_id_doc - required - ID ou email do usuário

Response - 200 - OK

Copiar
{
    "success":true,
    "code":200,
    "data":{},
    "profiler":{
        "start":1646263257.919774,
        "finish":1646263257.937168,
        "process":0.017393827438354492
    }
}
Listar Acessos por Usuário
Retorna a lista de acessos em vigor e encerrados de um usuário.

Parâmetros

usuario_email_id_doc - required - ID ou email do usuário

Response - 200 - OK

Copiar
{
    "success":true,
    "code":200,
    "data":{
        "usuario":{
            "id":1126594,
            "nome":"Renan C",
            "email":"teste@oculto.com.br",
            "doc": "123.123.123-12",
            "celular":null,
            "login_auto":"http://membros.cademi.com.br/auth/login?crstk=",
            "gratis": false,
            "criado_em":"2022-01-12T15:50:47-03:00",
            "ultimo_acesso_em":null
        },
        "acesso":[
            {
                "produto":{
                    "id":232,
                    "ordem":1,
                    "nome":"Blog",
                    "oferta_url":null
                },
                "duracao":null,
                "duracao_tipo":"vitalicio",
                "comecou_em":"2022-01-12T16:50:00-03:00",
                "encerra_em":null,
                "encerrado":false
            },
            {
                "produto":{
                    "id":38,
                    "ordem":3,
                    "nome":"Wordpress para Iniciantes",
                    "oferta_url":"https://sun.eduzz.com/289038"
                },
                "duracao":"1",
                "duracao_tipo":"months",
                "comecou_em":"2022-01-14T10:59:04-03:00",
                "encerra_em":"2022-02-14T10:59:04-03:00",
                "encerrado":true
            }
        ]
    },
    "profiler":{
        "start":1646260665.579091,
        "finish":1646260665.61099,
        "process":0.03189897537231445
    }
}
Listar Progresso por Aluno e Produto
Retorna o progresso de um usuário em um determinado curso.

Parâmetros

usuario_email_id_doc - required - ID ou email do usuário

produto_id - required - ID do produto

Response - 200 - OK

Copiar
{
    "success":true,
    "code":200,
    "data":{
        "progresso":{
            "total":"41.7%",
            "assistidas":7,
            "completas":5,
            "aulas":[
                {
                    "item_id":2404,
                    "count":1,
                    "acesso_em":"2020-09-11 10:57:46",
                    "aula":{
                        "id":2404,
                        "ordem":1,
                        "nome":"Video no Youtube"
                    }
                },
                {
                    "item_id":2405,
                    "count":2,
                    "acesso_em":"2020-09-11 10:59:15",
                    "aula":{
                        "id":2405,
                        "ordem":3,
                        "nome":"Video no Vímeo"
                    }
                },
                {
                    "item_id":2407,
                    "count":1,
                    "acesso_em":"2021-03-08 10:26:23",
                    "aula":{
                        "id":2407,
                        "ordem":6,
                        "nome":"Podcast no Spotify"
                    }
                },
                {
                    "item_id":2409,
                    "count":2,
                    "acesso_em":"2021-03-08 10:25:49",
                    "aula":{
                        "id":2409,
                        "ordem":2,
                        "nome":"Live no Youtube"
                    }
                },
                {
                    "item_id":2790,
                    "count":1,
                    "acesso_em":"2021-03-08 10:30:14",
                    "aula":{
                        "id":2790,
                        "ordem":7,
                        "nome":"Comentários"
                    }
                }
            ]
        }
    },
    "profiler":{
        "start":1646238379.549108,
        "finish":1646238379.641742,
        "process":0.09263396263122559
    }
}
Listar Alunos por Tag
Retorna uma lista de usuários à partir da ID de uma determinada TAG.

Parâmetros

tag_id - required - ID da Tag

Response - 200 - OK

Copiar
{
    "success":true,
    "code":200,
    "data":{
        "paginator":{
            "perPage":150,
            "next_page_url":"http://membros.dvp/api/v1/usuario/lista_por_tag/1?cursor=",
            "prev_page_url":null
        },
        "usuario":[
            {
                "id":1051,
                "nome":"Joana C",
                "email":"teste@oculto.com.br",
                "doc": "123.123.123-12",
                "celular":null,
                "login_auto":"http://membros.dvp/auth/login?crstk=",
                "gratis": false,
                "criado_em":"2020-01-12 19:59:20",
                "ultimo_acesso_em":null
            },
            {
                "id":1060,
                "nome":"Gilson D",
                "email":"teste@oculto.com.br",
                "doc": "123.123.123-12",
                "celular":null,
                "login_auto":"http://membros.dvp/auth/login?crstk=",
                "gratis": false,
                "criado_em":"2020-01-20 15:22:56",
                "ultimo_acesso_em":"2020-01-20 15:34:57"
            },
            {
                "id":14904,
                "nome":"Márcio B",
                "email":"teste@oculto.com.br",
                "doc": "123.123.123-12",
                "celular":null,
                "login_auto":"http://membros.dvp/auth/login?crstk=",
                "gratis": false,
                "criado_em":"2020-04-12 15:02:02",
                "ultimo_acesso_em":"2020-06-22 15:13:39"
            }
        ]
    },
    "profiler":{
        "start":1646237242.936336,
        "finish":1646237243.014636,
        "process":0.07829999923706055
    }
}
Adicionar Tag à um Aluno
Adiciona uma TAG a um determinado Usuário

Parâmetros via post:

usuario_id

tag_id

Response - 200 - OK

Copiar
{
    "success":true,
    "code":200,
    "data":{
        "usuario_id":1290011,
        "tag_id":472,
        "id":192906
    },
    "profiler":{
        "start":1659370561.955213,
        "finish":1659370562.031905,
        "process":0.07669186592102051
    }
}
Remover Tag de um Aluno
Remove uma TAG de um determinado Usuário

Parâmetros via post:

usuario_id

tag_id

Response - 200 - OK

Copiar
{
    "success":true,
    "code":200,
    "profiler":{
        "start":1659370691.751104,
        "finish":1659370691.889728,
        "process":0.13862395286560059
    }
}

https://ajuda.cademi.com.br/configuracoes/api/tag
Tag
Listar todas as Tags
Retorna todas as tags

Response - 200 - OK

Copiar
{
    "success":true,
    "code":200,
    "data":{
        "itens":[
            {
                "id":69,
                "nome":"Turma Padr\u00e3o"
            },
            {
                "id":272,
                "nome":"Turma 2"
            },
            {
                "id":350,
                "nome":"Turma do Anual"
            },
            {
                "id":351,
                "nome":"Turma do Mensal"
            },
            {
                "id":471,
                "nome":"Turma 3"
            },
            {
                "id":472,
                "nome":"Banco A"
            }
        ]
    },
    "profiler":{
        "start":1659370360.186277,
        "finish":1659370360.208106,
        "process":0.02182912826538086
    }
}
#1

https://ajuda.cademi.com.br/configuracoes/api/produto
Produto
Listar todos os Produtos
Retorna todos os produtos.

Response - 200 - OK

Copiar
{
    "success":true,
    "code":200,
    "data":{
        "paginator":{
            "perPage":15,
            "next_page_url":null,
            "prev_page_url":null
        },
        "produto":[
            {
                "id":5937,
                "ordem":3,
                "nome":"/instahack"
            },
            {
                "id":1126,
                "ordem":1,
                "nome":"Atendimentos"
            },
            {
                "id":232,
                "ordem":1,
                "nome":"Blog"
            },
            {
                "id":231,
                "ordem":1,
                "nome":"Conhecendo a Cademí"
            },
            {
                "id":230,
                "ordem":1,
                "nome":"Documentários"
            },
            {
                "id":142,
                "ordem":2,
                "nome":"Elementor para Iniciantes"
            },
            {
                "id":290,
                "ordem":2,
                "nome":"Novidades"
            },
            {
                "id":38,
                "ordem":3,
                "nome":"Wordpress para Iniciantes"
            }
        ]
    },
    "profiler":{
        "start":1646241573.10665,
        "finish":1646241573.124576,
        "process":0.01792597770690918
    }
}
Produto
Retorna um produto.

Parâmetros

produto_id - required - ID do Produto

Response - 200 - OK

Copiar
{
    "success":true,
    "code":200,
    "data":{
        "produto":{
            "id":231,
            "ordem":1,
            "nome":"Conhecendo a Cademí",
            "vitrine":{
                "id":10,
                "ordem":1,
                "nome":"✨ Conheça"
            }
        }
    },
    "profiler":{
        "start":1646241401.540869,
        "finish":1646241401.572547,
        "process":0.031677961349487305
    }
}

https://ajuda.cademi.com.br/configuracoes/api/aula
Aula
Listar Aulas por Produto
Lista todas as aulas de um produto seguindo a ordenação configurada.

Parâmetros

produto_id - required - ID do Produto

Response - 200 - OK

Copiar
{
    "success":true,
    "code":200,
    "data":{
        "paginator":{
            "perPage":150,
            "next_page_url":"http://membros.dvp/api/v1/usuario/lista_por_tag/1?cursor=",
            "prev_page_url":null
        },
        "usuario":[
            {
                "id":1051,
                "nome":"Joana C",
                "email":"teste@oculto.com.br",
                "celular":null,
                "login_auto":"http://membros.dvp/auth/login?crstk=",
                "criado_em":"2020-01-12 19:59:20",
                "ultimo_acesso_em":null
            },
            {
                "id":1060,
                "nome":"Gilson D",
                "email":"teste@oculto.com.br",
                "celular":null,
                "login_auto":"http://membros.dvp/auth/login?crstk=",
                "criado_em":"2020-01-20 15:22:56",
                "ultimo_acesso_em":"2020-01-20 15:34:57"
            },
            {
                "id":14904,
                "nome":"Márcio B",
                "email":"teste@oculto.com.br",
                "celular":null,
                "login_auto":"http://membros.dvp/auth/login?crstk=",
                "criado_em":"2020-04-12 15:02:02",
                "ultimo_acesso_em":"2020-06-22 15:13:39"
            }
        ]
    },
    "profiler":{
        "start":1646237242.936336,
        "finish":1646237243.014636,
        "process":0.07829999923706055
    }
}

https://ajuda.cademi.com.br/configuracoes/api/entrega
Entrega
Adicionar uma Entrega
Envie uma comunicação de entrega com processamento em tempo de requisição, sem aguardar filas. - Apenas para entregas Customizadas.

Parâmetros

(Post - Payload - Entrega Customizada)

Response - 200 - OK

Copiar
{
    "success": true,
    "code": 200,
    "data":[{
        "id": 2722598,
        "instancia_id": 3,
        "integracao_id": 4916,
        "importacao_id": null,
        "importacao_comecou_em": null,
        "processado": 1,
        "engine": "custom",
        "engine_id": "1",
        "recorrencia_id": null,
        "recorrencia_status": null,
        "recorrencia_encerra_em": null,
        "status": "aprovado",
        "pagamento": null,
        "produto_id": "TESTE",
        "produto_nome": null,
        "itens": null,
        "cliente_nome": "Aluno Teste",
        "cliente_doc": "",
        "cliente_email": "teste@teste.com.br",
        "cliente_celular": null,
        "cliente_endereco": null,
        "cliente_endereco_cep": null,
        "cliente_endereco_comp": null,
        "cliente_endereco_n": null,
        "cliente_endereco_bairro": null,
        "cliente_endereco_cidade": null,
        "cliente_endereco_estado": null,
        "valor": null,
        "erro": null,
        "payload":{…},
        "payload_headers":{…},
        "updated_at": "07/04/2022 às 11:39",
        "created_at": "07/04/2022 às 11:38",
    }],
    "profiler":{
        "start": 1649342374.452024,
        "finish": 1649342374.559871,
        "process": 0.10784697532653809
    }
}

https://ajuda.cademi.com.br/configuracoes/webhooks
🪝
Webhooks
Saiba como conectar sua Cademí com outros sistemas por meio de Webhooks

Os Webhooks são uma forma de envio de informações, que são passadas como um gatilho, sempre que um evento acontece.

Pensando em soluções de negócio mais robustas e complexas, com maiores estruturas de dados, a Cademí desenvolveu documentações de Webhooks que possibilitam a de dados comunicação entre plataformas.

Além disso, é possível também visualizar um histórico de comunicações realizadas nos últimos 60 dias, com os códigos de resposta informados pela URL que receberá as cargas.


A Cademí possui alguns eventos que podem ser comunicados à outras plataformas, para criar e configurar acesse ⚙️ ➡ Webhooks.

Os eventos atualmente disponíveis são:

Esse evento é disparado toda vez que um aluno emite um certificado de um determinado Produto.


Copiar
{
    "event_id": "47190b41-f388-4975-ba48-44ad749748b7",
    "event_type": "certificado.emitido",
    "event": {
        "emissao": {
            "uid": "ABC123",
            "pdf": "http://membros.cademi.com.br/system/file/certificado/ABC123"
        },
        "usuario": {
            "id": 123,
            "nome": "Aluno Fantasia",
            "email": "teste@teste.com.br",
            "doc": "123.123.123-12",
            "celular": "12312312330",
            "login_auto": "http://membros.cademi.com.br/auth/login?crstk=",
            "criado_em": "2022-02-10 11:38:50",
            "ultimo_acesso_em": "2022-02-10 11:43:13"
        },
        "produto": {
            "id": 231,
            "nome": "Conhecendo a Cademí",
            "vitrine": {
                "id": 10,
                "nome": "✨ Conheça"
            }
        }
    }
}
Esse evento é disparado toda vez que uma Entrega é adicionada a um determinado aluno.


Copiar
{
    "event_id": "6b4be100-064c-4473-bb64-4df335a858af",
    "event_type": "entrega.adicionada",
    "event": {
        "usuario": {
            "id": 123,
            "nome": "Aluno Fantasia",
            "email": "teste@teste.com.br",
            "doc": "123.123.123-12",
            "celular": "12312312330",
            "login_auto": "http://membros.cademi.com.br/auth/login?crstk=",
            "criado_em": "2022-02-10 11:38:50",
            "ultimo_acesso_em": "2022-02-10 11:43:13"
        },
        "entrega": {
            "id": 685,
            "nome": "Assinatura Anual",
            "engine": "hotmart",
            "engine_id": "321"
        }
    }
}
Esse evento é disparado toda vez que um aluno é aprovado em uma determinada Prova.


Copiar
{
    "event_id": "830433f5-3173-4490-ab0f-f0ceee8cd1f7",
    "event_type": "prova.aprovado",
    "event": {
        "usuario": {
            "id": 123,
            "nome": "Aluno Fantasia",
            "email": "teste@teste.com.br",
            "doc": "123.123.123-12",
            "celular": "13997206774",
            "login_auto": "http://membros.cademi.com.br/auth/login?crstk=",
            "criado_em": "2022-02-10 11:38:50",
            "ultimo_acesso_em": "2022-02-10 11:43:13"
        },
        "produto": {
            "id": 231,
            "nome": "Conhecendo a Cademí",
            "vitrine": {
                "id": 10,
                "nome": "✨ Conheça"
            }
        },
        "prova": {
            "id": 1,
            "produto_id": 231,
            "titulo": "Prova Exemplo",
            "nota_minima": "70"
        },
        "resultado": {
            "id": 123,
            "nota_final": 100,
            "acertos": 3,
            "erros": 0
        }
    }
}
Esse evento é disparado toda vez que um aluno é reprovado em uma determinada Prova.


Copiar
{
    "event_id": "5cead490-dd74-4ebb-834c-7b4fd858a455",
    "event_type": "prova.reprovado",
    "event": {
        "usuario": {
            "id": 123,
            "nome": "Aluno Fantasia",
            "email": "teste@teste.com.br",
            "doc": "123.123.123-12",
            "celular": "12312312330",
            "login_auto": "http://membros.cademi.com.br/auth/login?crstk=",
            "criado_em": "2022-02-10 11:38:50",
            "ultimo_acesso_em": "2022-02-10 11:43:13"
        },
        "produto": {
            "id": 231,
            "nome": "Conhecendo a Cademí",
            "vitrine": {
                "id": 10,
                "nome": "✨ Conheça"
            }
        },
        "prova": {
            "id": 1,
            "produto_id": 231,
            "titulo": "Prova Exemplo",
            "nota_minima": "70"
        },
        "resultado": {
            "id": 123,
            "nota_final": 33,
            "acertos": 1,
            "erros": 2
        }
    }
}
Esse evento é disparado toda vez que um usuário assina um termo de aceite.


Copiar
{
    "event_id": "7547ecd4-867d-4933-859b-95dd5334c66c",
    "event_type": "termo.assinado",
    "event": {
        "usuario": {
            "id": 123,
            "nome": "Aluno Fantasia",
            "email": "teste@oculto.com.br",
            "doc": "123.123.123-12",
            "celular": "13997206774",
            "login_auto": "http://membros.dvp/auth/login?crstk=",
            "criado_em": "2022-02-10T07:56:40-03:00",
            "ultimo_acesso_em": null
        },
        "log": {
            "id": 123,
            "referencia": "plataforma_aceite",
            "referencia_id": null,
            "ip": "::1",
            "useragent": "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.109 Safari/537.36",
            "criado_em": "2022-03-05T12:28:26-03:00"
        }
    }
}
Esse evento é disparado toda vez que um usuário gratuito é criado, ou um usuário é definido como gratuito.


Copiar
{
    "event_id": "41244473-629b-48a5-9bd5-04d1cc7b6412",
    "event_type": "usuario.gratis.criado",
    "event": {
        "usuario": {
            "id": 123,
            "nome": "Aluno Fantasia",
            "email": "teste@teste.com.br",
            "doc": "123.123.123-12",
            "celular": "13997206774",
            "login_auto": "http://membros.cademi.com.br/auth/login?crstk=",
            "criado_em": null,
            "ultimo_acesso_em": null
        }
    }
}
Esse evento é disparado toda vez que um usuário é criado.


Copiar
{
    "event_id": "01664950-b5fc-4d03-b8d0-b6d0576c3ae8",
    "event_type": "usuario.criado",
    "event": {
        "usuario": {
            "id": 123,
            "nome": "Aluno Fantasia",
            "email": "teste@teste.com.br",
            "doc": "123.123.123-12",
            "celular": "13997206774",
            "login_auto": "http://membros.cademi.com.br/auth/login?crstk=",
            "criado_em": null,
            "ultimo_acesso_em": null
        }
    }
}
Esse evento é disparado toda vez que é registrado o progresso de um aluno.

O valor de "progresso" representa a porcentagem total de progresso do aluno neste produto.


Copiar
{
    "event_id": "23d0d57c-062c-4f82-9202-56af5cbb61f4",
    "event_type": "usuario.progresso",
    "event": {
        "usuario": {
            "id": 123,
            "nome": "Aluno Fantasia",
            "email": "teste@teste.com.br",
            "doc": "123.123.123-12",
            "celular": "13997206774",
            "login_auto": "http://membros.cademi.com.br/auth/login?crstk=",
            "criado_em": null,
            "ultimo_acesso_em": null
        },
        "produto": {
            "id": 12345,
            "ordem": 5,
            "nome": "Nome do produto",
            "oferta_url": null,
            "vitrine": {
                "id": 1,
                "ordem": 5,
                "nome": "Nome da vitrine"
            }
        },
        "progresso": 30
    }
}
Esse evento é disparado toda vez que o aluno concluí uma aula.


Copiar
{
    "event_id": "71a4d6b6-a10f-4c03-a566-eb7db7a33e1b",
    "event_type": "aula.concluida",
    "event": {
        "usuario": {
            "id": 123,
            "nome": "Aluno Fantasia",
            "email": "teste@teste.com.br",
            "doc": "123.123.123-12",
            "celular": "13997206774",
            "login_auto": "http://membros.cademi.com.br/auth/login?crstk=",
            "gratis": false,
            "pontos": 1,
            "criado_em": "null",
            "ultimo_acesso_em": "null",
        },
        "produto": {
            "id": 12345,
            "ordem": 1,
            "nome": "Nome do produto",
            "oferta_url": "null",
            "vitrine": {
                "id": 1,
                "ordem": 5,
                "nome": "Nome da Vitrine"
            }
        },
        "aula": {
            "id": 12345,
            "tipo": "default",
            "ordem": 1,
            "nome": "Nome da Aula",
            "secao": {
                "id": 1,
                "tipo": "modulo",
                "ordem": 3,
                "nome": "Nome do Módulo"
            }
        }
    }
}