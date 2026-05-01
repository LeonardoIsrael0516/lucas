<?php

/**
 * Este arquivo verifica o status de um pagamento pelo identificador fornecido
 * Quando o cliente concluiu o pagamento/agendamento, ele é direcionado para para o arquivo `aguardando_pagamento.php`.
 * No arquivo `aguardando_pagamento.php` tem um script que executa este arquivo "verificar_pagamento.php" a cada 3 segundos 
 * para ler o banco de dados (/storage/historicoPagamentos.json) e verificar se o pagamento foi atualizado com base no webhook.
 */

$identificadorPagamento = $_POST['identificadorPagamento'] ?? null;

if (!$identificadorPagamento) {
    // Se o identificador de pagamento não for fornecido, retorna status 'inexistente'
    echo json_encode(['status' => 'inexistente']);
    return;
}

$historicoPagamento = './storage/historicoPagamentos.json';

// Lê o conteúdo do arquivo e decodifica o JSON
$conteudo = file_get_contents($historicoPagamento);
$pagamentos = json_decode($conteudo, true);

// Busca o pagamento correspondente ao identificador fornecido
foreach ($pagamentos as $pagamento) {
    if ($pagamento['identificadorPagamento'] === $identificadorPagamento) {
        // Pagamento encontrado, retorna os dados
        echo json_encode($pagamento);
        return;
    }
}

// Se nenhum pagamento correspondente for encontrado, retorna status 'inexistente'
echo json_encode(['status' => 'inexistente']);
