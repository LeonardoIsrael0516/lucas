<?php

/**
 * Exemplo de configuração de webhook
 * Nessa página você pode configurar o webhook e URL de redirecionamento da sua aplicação
 * Essa configuração é obrigatória para a iniciação de pagamentos via Open Finance e pode ser feita apenas uma vez
 * Documentação técnica:  https://dev.efipay.com.br/docs/api-open-finance/configuracoes-de-aplicacao#configurar-urls-da-aplicação
 */

define('CREDENTIALS_FILE', __DIR__ . '/../credentials.php');
define('CERTS_PATH', '../certs/');

// Carrega dependências e credenciais
$autoloadPath = realpath(__DIR__ . '/../vendor/autoload.php');
if (!file_exists($autoloadPath)) {
    die("Autoload file not found: $autoloadPath");
}
require_once $autoloadPath;

use Efi\Exception\EfiException;
use Efi\EfiPay;

// Carrega arquivo de credenciais
if (!file_exists(CREDENTIALS_FILE)) {
    die("Credentials file not found: " . CREDENTIALS_FILE);
}
$credentials = include CREDENTIALS_FILE;

// Configurações iniciais de autenticação da API Efí Bank
$options = [
    "clientId" => $credentials['clientId'],
    "clientSecret" => $credentials['clientSecret'],
    "certificate" => CERTS_PATH . $credentials['certificate'],
    "sandbox" => $credentials['sandbox']
];

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
