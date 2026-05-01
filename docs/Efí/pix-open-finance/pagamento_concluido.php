<?php
/**
* Após conclusão do pagamento, e recebimento do webhook, o cliente é redirecionado para este arquivo
* Aqui irá apresentar as informações de pagamento concluído com sucesso ou falha
 */

/**
 * Exibe informações de pagamento concluído
 * O cliente é direcionado para este arquivo após a conclusão de um pagamento.
 */

/**
 * Extrair variáveis ​​POST em uma matriz
 */
function getPostData($key)
{
    return isset($_POST[$key]) ? $_POST[$key] : null;
}

/**
 * Atribuindo variáveis com nomes significativos e validação básica
 */
$paymentId = getPostData('identificadorPagamento');
$endToEndId = getPostData('endToEndId');
$status = getPostData('status');
$value = getPostData('valor');
$updatedAt = getPostData('dataCriacao');
$schedulingDate = getPostData('dataAgendamento');
$tipo = getPostData('tipo');
$reason = getPostData('motivo'); ?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exemplos Oficiais das APIs Efí</title>
    <link rel="shortcut icon" href="../assets/img/favicon.png" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-4bw+/aepP/YC94hEpVNVgiZdgIC5+VKNBQNGCHeKRQN+PtmoHDEXuppvnDJzQIu9" crossorigin="anonymous">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-HwwvtgBNo3bZJJLYd8oVXjrBZt8cqVSpeBNS5n7C8IVInixGAoxmnlMuBnhbgrkm"
        crossorigin="anonymous"></script>
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.4/jquery.min.js"></script>
    <link rel="stylesheet" href="../assets/bootstrap/css/style.css">
</head>

<body>
    <nav class="navbar navbar-expand-md navbar-light bg-light">
        <div class="container-fluid navbar-efi pt-2 pb-3">
            <a class="navbar-brand" href="/">
                <img src="../assets/img/logo-efi-pay.svg" alt="Efí" width="90px">
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse"
                aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarCollapse">
                <ul class="navbar-nav col-lg-12 justify-content-md-end justify-content-lg-end ">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle text-end" href="#" role="button" data-bs-toggle="dropdown"
                            aria-expanded="false">
                            Exemplos
                        </a>
                        <ul class="dropdown-menu">
                            <li><a href="./boleto/">Boletos</a></li>
                            <li><a href="./cartao/">Cartão</a></li>
                            <li><a href="./pix/">Pix</a></li>
                            <li><a href="./pix-open-finance/">Pix via Open Finance</a></li>
                            <li><a href="./assinatura/">Assinaturas</a></li>
                            <li><a href="./carne/">Carnê</a></li>
                            <li><a href="./link-de-pagamento/">Link de Pagamento</a></li>
                            <li><a href="./split-de-pagamento/">Split de Pagamento</a></li>
                        </ul>
                    </li>
                    <div class="d-flex justify-content-md-between justify-content-sm-between">
                        <a target="blank" class="btn btn-efi-blue"
                            href="https://sejaefi.com.br/central-de-ajuda/efi-bank/como-abrir-conta-na-efi-bank#conteudo">Abra
                            sua conta grátis</a>
                        <a target="blank" class="btn btn-efi " href="https://app.sejaefi.com.br/">Acessar minha
                            conta</a>
                    </div>
            </div>
        </div>
    </nav>

    <div class="page-container">
        <div class="container">
            <div style="margin-top: 5rem;" class="row justify-content-md-center">
                <div class="col text-start">

                    <?php if (in_array($status, ['aceito', 'agendado', 'devolvido', 'concluida', 'ativa'])): ?>
                        <div class="d-flex justify-content-start mb-3 align-items-center">
                            <?php
                            $mensagensStatus = [
                                'aceito' => "Pagamento realizado com sucesso! 🙌",
                                'devolvido' => "Pagamento devolvido com sucesso! 🙌",
                                'agendado' => "Pagamento agendado com sucesso! 🙌",
                                'concluida' => "Pagamento agendado com sucesso! 🙌",
                                'ativa' => "Recorrência criada com sucesso! 🙌"
                            ];
                            echo "<h3>{$mensagensStatus[$status]}</h3>";
                            ?>
                        </div>

                        <?php
                        $mensagensTipo = [
                            'immediate-payment' => "Seu pagamento foi finalizado com sucesso.",
                            'scheduled-payment' => "O agendamento do pagamento foi realizado com sucesso.",
                            'recurrent-payment' => "Os agendamentos para pagamento recorrente foram realizados com sucesso."
                        ];
                        echo "<p>{$mensagensTipo[$tipo]}</p>";
                        ?>

                        <h4>Dados da transação:</h4>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Identificador pagamento:</label>
                                <input class="form-control" type="text" value="<?= $paymentId ?>" disabled>
                            </div>
                            <?php
                            if ($tipo !== 'recurrent-payment') {
                                ?>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Identificador da transação:</label>
                                    <input class="form-control" type="text" value="<?= $endToEndId ?>" disabled>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Status:</label>
                                    <input class="form-control" type="text" value="<?= $status ?>" disabled>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Valor:</label>
                                    <input class="form-control" type="text" value="<?= $value ?>" disabled>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label
                                        class="form-label"><?php echo ($tipo === 'immediate-payment') ? "Data do pagamento (UTC-0)" : "Data do agendamento:"; ?></label>
                                    <input class="form-control" type="text"
                                        value="<?php echo ($tipo === 'immediate-payment') ? $updatedAt : $schedulingDate; ?>"
                                        disabled>
                                </div>
                            <?php } else {
                                $recurrenceType = getPostData('tipoRecorrencia');
                                $ramountRecurrence = getPostData('quantidade');
                                ?>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Valor:</label>
                                    <input class="form-control" type="text" value="<?= $value ?>" disabled>
                                </div>
                                <h4>Recorrência
                                    <?php echo $recurrenceType === 'diaria' ? 'diária' : $recurrenceType;
                                    echo " - " . $ramountRecurrence; ?>
                                    agendamentos:
                                </h4>
                                <div class="col scroll-box mx-3">
                                    <?php

                                    $historical = getPostData('historico');
                                    $historical = $historical[0];

                                    foreach ($historical['recorrencia'] as $key => $value) {
                                        ?>
                                        <div class="row mw-100">
                                            <hr>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Identificador da transação:</label>
                                                <input class="form-control" type="text" value="<?= $value['endToEndId'] ?>"
                                                    disabled>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Status:</label>
                                                <input class="form-control" type="text" value="<?= $value['status'] ?>" disabled>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label class="form-label">Data:</label>
                                                <input class="form-control" type="text" value="<?= $value['dataAgendamento'] ?>"
                                                    disabled>
                                            </div>
                                        </div>
                                    <?php }
                            } ?>
                            </div>

                        <?php elseif ($status === 'expirado'): ?>
                            <div class="d-flex justify-content-start align-items-center">
                                <h3>Pagamento expirado. 🙁</h3>
                            </div>
                            <p>O pagamento foi iniciado, mas não foi finalizado.</p>
                            <hr>
                            <div class="row">
                                <div class="col">
                                    <label class="form-label">Identificador pagamento:</label>
                                    <input class="form-control" type="text" value="<?= $paymentId ?>" disabled>
                                </div>
                            </div>

                        <?php else: ?>
                            <div class="d-flex justify-content-start mb-3 align-items-center">
                                <h3>Pagamento processado com falha. 🙁</h3>
                            </div>
                            <p>O pagamento não foi processado conforme esperado. Veja os detalhes a seguir:</p>
                            <hr>
                            <div class="row">
                                <div class="mb-8 col-md-8">
                                    <label class="form-label">Identificador pagamento:</label>
                                    <input class="form-control" type="text" value="<?= $paymentId ?>" disabled>
                                </div>
                                <div class="mb-4 col-md-4">
                                    <label class="form-label">Status:</label>
                                    <input class="form-control" type="text" value="<?= $status ?>" disabled>
                                </div>
                                <div class="mb-12 col-md-12">
                                    <label class="form-label">Motivo:</label>
                                    <input class="form-control" type="text" value="<?= $reason ?>" disabled>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="col-lg-12 mt-3 d-flex justify-content-end">
                            <a href="./" type="button" id="new-simulation"
                                class="btn btn-efi-blue icon-success">Realizar nova simulação</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <footer>
            <div class="container mt-4 mb-4">
                <div class="row ">
                    <div class="col-lg-6 col-md-12 text-lg-start text-center mb-4 mb-lg-0">
                        <span class="contact-title-efi">Efí Bank</span>
                        <br>
                        <span class="info_endereco">Av Paulista, 1337, Edifício Paulista 1 - Bela Vista, São Paulo, SP -
                            01311-200, Brasil</span>
                    </div>
                    <div class="col-lg-6 col-md-12">
                        <div class="row">
                            <div class="col-6 col-md-3 col-sm-3" id="contact-box">
                                <span class="contact-title">(11) 2394 2208</span>
                                <div id="contact-box">
                                    <p>São Paulo e região</p>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 col-sm-3" id="contact-box">
                                <span class="contact-title">0800 941 2343</span>
                                <div id="contact-box">
                                    <p>Ligações de telefone fixo</p>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 col-sm-3" id="contact-box">
                                <span class="contact-title">4000 1234</span>
                                <div id="contact-box">
                                    <p>Regiões metropolitanas</p>
                                </div>
                            </div>
                            <div class="col-6 col-md-3 col-sm-3" id="contact-box">
                                <span class="contact-title">0800 940 0361</span>
                                <div id="contact-box">
                                    <p>Ouvidoria</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="row footer-redes">
                    <div class="row">
                        <div class="col-lg-6 col-md-12 text-center">
                            <div class="row">
                                <div class="col-md-2">
                                    <a href="https://sejaefi.com.br/efi-bank" target="_blank"><svg
                                            xmlns="http://www.w3.org/2000/svg" width="65.161" height="51.056"
                                            viewBox="0 0 65.161 51.056">
                                            <g id="Group_27158" data-name="Group 27158"
                                                transform="translate(-96 -5165.238)">
                                                <path id="Path_21451" data-name="Path 21451"
                                                    d="M56.808,11.283l8.353-3.077V0L55.05,7.913a2.1,2.1,0,0,0-1.026,1.709,1.906,1.906,0,0,0,2.784,1.661M54.024,39.761H59.4V13.872H54.024Zm-24.374,0h5.373V29.8H48.455V24.911H35.023c-.1-4.054,2.638-6.5,6.4-6.5a8.916,8.916,0,0,1,5.129,1.661l3.419-3.859a13.52,13.52,0,0,0-8.4-2.833c-7.083,0-11.918,4.689-11.918,11.528ZM5.471,24.081c.782-3.517,3.566-5.666,7.424-5.666,3.908,0,6.5,2.149,6.985,5.666Zm19.929,2.2c0-5.959-4.3-12.9-12.5-12.9C5.666,13.384,0,18.806,0,26.621S5.715,40.249,13.433,40.249a13.774,13.774,0,0,0,11.186-5.666l-3.761-3.077a9.066,9.066,0,0,1-7.571,3.712,7.448,7.448,0,0,1-7.815-6.252H22.748A2.356,2.356,0,0,0,25.4,26.279"
                                                    transform="translate(96 5165.238)" fill="#586475" />
                                                <path id="Path_21452" data-name="Path 21452"
                                                    d="M105.38,141.781h-.3l2.538,6.168h1.244l-2.716-6.345a.915.915,0,0,0-.888-.635.952.952,0,0,0-.914.635l-2.715,6.345h1.218Zm-10.533,6.168h1.142v-6.98H94.847Zm3.426-3.274a2.023,2.023,0,0,0,2.157-1.954c0-1.066-.838-1.752-2.107-1.752h-2.97v1.016h2.817a.944.944,0,0,1,1.066.939.961.961,0,0,1-.965,1.015Zm11.879,3.274h1.142l-.051-6.295-.609.229,4.036,5.584a1.01,1.01,0,0,0,.812.482.7.7,0,0,0,.66-.761v-6.219H115l.051,6.3.609-.229-4.036-5.584a.932.932,0,0,0-.787-.482.718.718,0,0,0-.685.762Zm-7.234-1.4h4.67v-1.015h-4.67Zm20.229,1.4h1.447l-3.223-3.5,3.2-3.477H123.1l-2.64,2.919a.763.763,0,0,0,0,1.142Zm-4.671,0h1.142v-6.98h-1.142Zm-23.122,0H98.3c1.548,0,2.411-.761,2.411-1.929a2.227,2.227,0,0,0-2.437-2.081H95.354v1.015h3.071c.634,0,1.066.33,1.066.914,0,.659-.533,1.066-1.294,1.066H95.354Z"
                                                    transform="translate(30.809 5068.345)" fill="#586475" />
                                            </g>
                                        </svg></a>
                                </div>
                                <div class="col mt-2 info text-lg-start text-center">
                                    © 2007-<span id="ano-atual"></span> • Efí - Instituição de Pagamento. Todos os
                                    direitos
                                    reservados.
                                    <br>Efí S.A. CNPJ: 09.089.356/0001-18
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6 col-md-12 justify-content-lg-end justify-content-center d-flex">
                            <ul class="list-unstyled d-flex mt-3 mt-lg-0">
                                <li class="ms-6"><a href="https://www.youtube.com/@sejaefi" target="_blank"><img
                                            src="../assets/img/rede1.svg" /></a></li>
                                <li class="ms-6"><a href="https://www.instagram.com/sejaefi/" target="_blank"><img
                                            src="../assets/img/rede2.svg" /></a></li>
                                <li class="ms-6"><a href="https://www.linkedin.com/company/sejaefi/"
                                        target="_blank"><img src="../assets/img/rede-3.svg" /></a></li>
                                <li class="ms-6"><a href="https://www.facebook.com/sejaefi" target="_blank"><img
                                            src="../assets/img/rede-4.svg" /></a></li>
                                <li class="ms-6"><a href="https://twitter.com/sejaefi" target="_blank"><img
                                            src="../assets/img/rede-5.svg" /></a></li>
                                <li class="ms-6"><a href="https://www.tiktok.com/@sejaefi" target="_blank"><img
                                            src="../assets/img/rede-6.svg" /></a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
</body>

</html>