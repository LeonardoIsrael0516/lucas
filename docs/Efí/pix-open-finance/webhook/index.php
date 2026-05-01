<?php
define('HISTORICAL_FILE', '../storage/historicoPagamentos.json');

$minhaHash = "HASH-UNICA-GERADA-POR-VOCÊ-QUE-SO-SEU-SISTEMA-TEM_CONHECIMENTO";

// Valida a hash retornada no parâmetro da URL da notificação do webhook para comparar com a hash fornecida na configuração
$hash = $_GET['hmac'] ?? null;
if ($hash !== $minhaHash) {
    respondWithError(401, "Solicitação de webhook não autorizada");
}

// Verificação de IP - Deixe comentado caso queira testar localmente usando Postman, por exemplo. E habilite em ambiente de produção
// $requestIp = $_SERVER['REMOTE_ADDR'];
// if ($requestIp !== "34.193.116.226") { // IP utilizado pelo API do Efí Bank nas notificações. Se for executar testes locais, comente este trecho de código
//     respondWithError(401, "Solicitação de webhook não autorizada");
//    exit();
// }

// Lê dados JSON recebidos do webhook
$receivedData = json_decode(file_get_contents('php://input'), true);

if (!$receivedData) {
    respondWithError(400, "Dados do webhook inválidos ou ausentes");
}

// Verifica existência do histórico
// Este é apenas um exemplo. Recomendamos que você implemente sua própria lógica de armazenamento de dados de maneira segura
if (!file_exists(HISTORICAL_FILE)) {
    respondWithError(404, "Histórico de pagamento não encontrado");
}

// ================== PROCESSAMENTO ==================

switch ($receivedData['tipo']) {
    case 'pagamento':
        processarNotificacao($receivedData, 'pagamento');
        break;
    case 'recorrencia':
        processarNotificacao($receivedData, 'recorrencia');
        break;
    case 'devolucao':
        processarNotificacao($receivedData, 'devolucao');
        break;
    default:
        respondWithError(404, "Tipo de notificação não reconhecido");
}

// ================== FUNÇÕES ==================

/**
 * Processa notificações de pagamento, recorrência ou devolução.
 */
function processarNotificacao($receivedData, $tipo)
{
    $pagamentos = json_decode(file_get_contents(HISTORICAL_FILE), true);
    $idPagamento = $receivedData['identificadorPagamento']; // Identificador do pagamento  recebido da notificação 
    $pagamentoEncontrado = false;

    /**
     * Verifica se a notificação é corresponde uma iniciação de pagamento no banco de dados.
     */
    foreach ($pagamentos as &$pagamento) {
        if ($pagamento['identificadorPagamento']  ===$idPagamento) {
            atualizarPagamento($pagamento, $receivedData, $tipo);
            $pagamentoEncontrado = true;
            break;
        }
    }

    if ($pagamentoEncontrado) {
        salvarHistoricoPagamento($pagamentos);
        respondWithSuccess($idPagamento, "Pagamento atualizado com sucesso");
    } else {
        respondWithError(404, "Pagamento não encontrado");
    }
}

/**
 * Atualiza os dados do pagamento com base na notificação.
 */
function atualizarPagamento(&$pagamento, $receivedData, $tipo)
{
    $pagamento['status'] = $receivedData['status'] ?? $pagamento['status'];
    $pagamento['valor'] = $receivedData['valor'] ?? $pagamento['valor'];
    $pagamento['dataCriacao'] = $receivedData['dataCriacao'] ?? $pagamento['dataCriacao'];

    if (isset($receivedData['endToEndId'])) {
        $pagamento['endToEndId'] = $receivedData['endToEndId'];
    }

    if ($tipo === 'devolucao') {
        $pagamento['identificadorDevolucao'] = $receivedData['identificadorDevolucao'] ?? null;
    }

    if (isset($receivedData['motivo'])) {
        $pagamento['motivo'] = $receivedData['motivo'];
    }

    $pagamento['historico'][] = $receivedData; // Adiciona a notificação ao histórico
}

/**
 * Salva o histórico de pagamentos no arquivo.
 */
function salvarHistoricoPagamento($pagamentos)
{
    $json = json_encode($pagamentos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    if (!file_put_contents(HISTORICAL_FILE, stripslashes($json))) {
        respondWithError(500, "Erro ao salvar dados no histórico");
    }
}

/**
 * Envia uma resposta HTTP de erro.
 */
function respondWithError($statusCode, $message)
{
    http_response_code($statusCode);
    echo json_encode(["error" => $message], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

/**
 * Envia uma resposta HTTP de sucesso.
 */
function respondWithSuccess($idPagamento, $message)
{
    http_response_code(200);
    echo json_encode([
        "IdPagamento" => $idPagamento,
        "message" => $message
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}
