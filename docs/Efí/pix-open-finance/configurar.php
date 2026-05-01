<?php

/**
 * Exemplo de configuração de webhook
 * Nessa página você pode configurar o webhook e URL de redirecionamento da sua aplicação
 * Essa configuração é obrigatória para a iniciação de pagamentos via Open Finance e pode ser feita apenas uma vez
 * Documentação técnica:  https://dev.efipay.com.br/docs/api-open-finance/configuracoes-de-aplicacao#configurar-urls-da-aplicação
 */

// Carrega dependências e credenciais
$autoload = realpath(__DIR__ . '/../vendor/autoload.php');
if (!file_exists($autoload)) {
    die("Autoload file not found or on path <code>$autoload</code>.");
}

require_once $autoload;

use Efi\Exception\EfiException;
use Efi\EfiPay;

// Lê o arquivo json com suas credenciais
$file = file_get_contents(__DIR__ . '/../credentials.json');
$options = json_decode($file, true);

/**
 * Para configurar o webhook, mantenha os arquivos `aguardando_pagamento.php` e `webhook/index.php` no seu servidor web. Os nomes e diretórios não precisam ser exatamente esses.
 */
$body = [
    "redirectURL" => "https://seu-dominio.com.br/pix-open-finance/aguardando_pagamento.php", // Página que o cliente é redirecionado após realizar o pagamento
    "webhookURL" => "https://seu-dominio.com.br/pix-open-finance/webhook/index.php", // Página que receberá as notificações
    "webhookSecurity" => [
        "type" => "hmac", // Outra opção é "mtls", sendo necessário configuração adicional conforme documentação: https://dev.efipay.com.br/docs/api-open-finance/webhooks
        "hash" => "HASH_QUE_SÓ_SEU_SISTEMA_TEM_CONHECIMENTO" // Necessário para "type: hmac"
    ],
    "processPayment" => "async"
];

try {
    $api = new EfiPay($options);
    $response = $api->ofConfigUpdate($params = [], $body);

    print_r("<pre>" . json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "</pre>");
} catch (EfiException $e) {
    print_r($e->code . "<br>");
    print_r($e->error . "<br>");
    print_r($e->errorDescription) . "<br>";
} catch (Exception $e) {
    print_r($e->getMessage());
}
