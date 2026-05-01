<?php
//  Este arquivo é responsável por receber os dos dados do formulário e processar a requisição de iniciação de pagamento

// Carrega dependências e credenciais
$autoloadPath = realpath(__DIR__ . '/../vendor/autoload.php');
if (!file_exists($autoloadPath)) {
    respondWithError(500, "Autoload file not found: $autoloadPath");
}
require_once $autoloadPath;


// Configurações iniciais de autenticação da API Efí Bank
$file = file_get_contents(__DIR__ . '/../credentials.json');
$options = json_decode($file, true);

define('HISTORICAL_FILE', './storage/historicoPagamentos.json');

// Função principal para processar requisição
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST)) {
    processPayment($options);
} else {
    respondWithError(404, "Página não encontrada");
}

// ================== FUNÇÕES ==================

/**
 * Obtém um valor de $_POST com validação.
 */
function getPostData($key, $sanitizeCallback = null)
{
    $value = $_POST[$key] ?? null;
    return $sanitizeCallback ? $sanitizeCallback($value) : $value;
}

/**
 * Responde com um erro JSON e encerra a execução.
 */
function respondWithError($statusCode, $message)
{
    http_response_code($statusCode);
    echo json_encode(['message' => $message], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Processa a requisição de pagamento.
 */
function processPayment($options)
{
    // Captura os dados da requisição
    $paymentType = getPostData("selectedPaymentType");
    $institutionId = getPostData("institutionChosen");
    $cpf = getPostData("cpf", fn($cpf) => preg_replace('/[^0-9]/', '', $cpf));
    $value = getPostData("value");
    $payerInformation = getPostData("payerInformation");

    if (!$paymentType || !$institutionId || !$cpf || !$value || !$payerInformation) {
        respondWithError(400, "Dados insuficientes para processar o pagamento");
    }

    $idempotencyKey = generateIdempotencyKey();
    $ownId = "Pagamento-$paymentType-$idempotencyKey";

    $options["headers"] = [
        "x-idempotency-key" => $idempotencyKey
    ];

    $body = preparePaymentBody($paymentType, $institutionId, $cpf, $value, $payerInformation, $ownId);
    executePayment($paymentType, $options, $body);
}

/**
 * Gera uma chave única para controle de idempotência.
 */
function generateIdempotencyKey()
{
    return date('YmdHis') . bin2hex(random_bytes(12));
}

/**
 * Prepara o corpo da requisição de pagamento.
 */
function preparePaymentBody($paymentType, $institutionId, $cpf, $value, $payerInformation, $ownId)
{
    $body = [
        "pagador" => [
            "idParticipante" => (string) $institutionId,
            "cpf" => (string) $cpf
        ],
        "favorecido" => [
            "contaBanco" => [
                "codigoBanco" => "364",  // Código do Efí Bank
                "agencia" => "0001",
                "conta" => "470287", // Número da conta com dígito verificador, sem hífen
                "documento" => "09089356000118", // cpf ou cnpj  da conta
                "nome" => "Efí Bank", // Nome do titular da conta ou razão social/nome fantasia
                "tipoConta" => "CACC"
            ]
        ],
        "pagamento" => [
            "valor" => (string) $value,
            "infoPagador" => (string) $payerInformation,
            "idProprio" => $ownId
        ]
    ];

    if ($paymentType === 'scheduled-payment') { // Pagamentos agendados
        $body['pagamento']['dataAgendamento'] = getPostData("schedulingDate");
    } elseif ($paymentType === 'recurrent-payment') { // Pagamentos recorrentes
        $recurrenceData = prepareRecurrenceData();
        $body['pagamento']['recorrencia'] = $recurrenceData;
    }

    return $body;
}

/**
 * Prepara os dados de recorrência para pagamentos recorrentes.
 */
function prepareRecurrenceData()
{
    $recurrenceType = getPostData("recurrenceType");
    $paymentStartDate = getPostData("paymentStartDate");
    $amountRecurrence = getPostData("amountRecurrence");

    $recurrence = [
        'tipo' => (string) $recurrenceType,
        'dataInicio' => (string) $paymentStartDate,
        'quantidade' => (int) $amountRecurrence
    ];

    if ($recurrenceType === 'semanal') {
        $recurrence['diaDaSemana'] = getPostData("dayOfWeek");
    } elseif ($recurrenceType === 'mensal') {
        $recurrence['diaDoMes'] = (int) getPostData("dayOfMonth");
    }

    return $recurrence;
}

/**
 * Executa o pagamento usando a SDK.
 */
function executePayment($paymentType, $options, $body)
{
    try {
        $api = Efi\EfiPay::getInstance($options);

        $response = match ($paymentType) {
            'immediate-payment' => $api->ofStartPixPayment([], $body),
            'scheduled-payment' => $api->ofStartSchedulePixPayment([], $body),
            'recurrent-payment' => $api->ofStartRecurrencyPixPayment([], $body),
            default => throw new Exception("Tipo de pagamento inválido")
        };

        savePaymentHistory($response, $body, $paymentType);
        http_response_code(200);
        echo json_encode([
            "code" => 200,
            "identificadorPagamento" => $response['identificadorPagamento'],
            "redirectURI" => $response['redirectURI']
        ]);
    } catch (Efi\Exception\EfiException $e) {
        echo json_encode([
            'code' => $e->code,
            'error' => $e->error,
            'errorDescription' => $e->errorDescription,
        ]);
    } catch (Exception $e) {
        respondWithError(500, $e->getMessage());
    }
}

/**
 * Salva o histórico do pagamento no arquivo.
 */
function savePaymentHistory($response, $body, $paymentType)
{
    $data = [
        "identificadorPagamento" => $response['identificadorPagamento'],
        "identificadorProprio" => $body['pagamento']['idProprio'],
        "tipo" => $paymentType,
        "status" => "pendente",
        "valor" => $body['pagamento']['valor'],
        "dataCriacao" => date('Y-m-d H:i:s'),
        "url" => $response['redirectURI']
    ];

    if ($paymentType === 'scheduled-payment') {
        $data['dataAgendamento'] = $body['pagamento']['dataAgendamento'];
    }

    if ($paymentType === 'recurrent-payment') {
        $recurrenceType = $body['pagamento']['recorrencia']['tipo'];
        $data['tipoRecorrencia'] = $recurrenceType;
        $data['dataInicio'] = $body['pagamento']['recorrencia']['dataInicio'];
        $data['quantidade'] = $body['pagamento']['recorrencia']['quantidade'];

        if ($recurrenceType === 'semanal') {
            $data['diaDaSemana'] = $body['pagamento']['recorrencia']['diaDaSemana'];
        }

        if ($recurrenceType === 'mensal') {
            $data['diaDoMes'] = $body['pagamento']['recorrencia']['diaDoMes'];
        }
    }

    $history = file_exists(HISTORICAL_FILE) ? json_decode(file_get_contents(HISTORICAL_FILE), true) : [];
    $history[] = $data;

    $json = json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    $bytesWritten = file_put_contents(HISTORICAL_FILE, stripslashes($json));

    if ($bytesWritten === false) {
        respondWithError(500, "Erro de escrita no banco de dados.");
    }
}
