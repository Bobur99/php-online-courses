<?php

declare(strict_types=1);

const STORAGE_FILE = __DIR__ . '/../storage/payments.json';

header('Content-Type: application/json');
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, ['http://localhost:5173', 'http://localhost:5174'], true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function readPayments(): array
{
    if (! file_exists(STORAGE_FILE)) {
        return [];
    }

    $content = file_get_contents(STORAGE_FILE);
    $payments = json_decode($content ?: '[]', true);

    return is_array($payments) ? $payments : [];
}

function savePayments(array $payments): void
{
    file_put_contents(
        STORAGE_FILE,
        json_encode($payments, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );
}

function requestData(): array
{
    $data = json_decode(file_get_contents('php://input'), true);

    return is_array($data) ? $data : [];
}

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($method === 'POST' && $uri === '/payments') {
    $data = requestData();

    foreach (['amount', 'currency', 'description', 'webhook_url'] as $field) {
        if (! array_key_exists($field, $data)) {
            jsonResponse([
                'message' => "Field {$field} is required.",
            ], 422);
        }
    }

    $operationId = 'operation-' . bin2hex(random_bytes(6));

    $payments = readPayments();

    $payments[$operationId] = [
        'operation_id' => $operationId,
        'amount' => $data['amount'],
        'currency' => $data['currency'],
        'description' => $data['description'],
        'webhook_url' => $data['webhook_url'],
        'return_url' => $data['return_url'] ?? null,
        'status' => 'pending',
        'created_at' => gmdate('c'),
        'updated_at' => gmdate('c'),
    ];

    savePayments($payments);

    $checkoutBaseUrl = rtrim(getenv('PAYMENT_CHECKOUT_URL') ?: 'http://localhost:5174/pay', '/');

    jsonResponse([
        'operation_id' => $operationId,
        'payment_url' => "{$checkoutBaseUrl}/{$operationId}",
    ], 201);
}

function finalizePayment(string $operationId, string $status): never
{
    $payments = readPayments();

    if (! isset($payments[$operationId])) {
        jsonResponse([
            'message' => 'Payment not found.',
        ], 404);
    }

    $payment = $payments[$operationId];
    $payment['status'] = $status;
    $payment['updated_at'] = gmdate('c');
    $payments[$operationId] = $payment;

    savePayments($payments);

    $payload = json_encode([
        'operation_id' => $operationId,
        'status' => $status,
    ], JSON_UNESCAPED_UNICODE);

    $secret = getenv('PAYMENT_PROVIDER_WEBHOOK_SECRET') ?: 'test-secret';
    $signature = hash_hmac('sha256', $payload, $secret);

    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'header' => implode("\r\n", [
                'Content-Type: application/json',
                'X-Payment-Signature: ' . $signature,
            ]),
            'content' => $payload,
            'ignore_errors' => true,
            'timeout' => 5,
        ],
    ]);

    $result = file_get_contents($payment['webhook_url'], false, $context);

    $responseStatus = 0;
    $responseHeaders = function_exists('http_get_last_response_headers')
        ? http_get_last_response_headers()
        : [];
    if (isset($responseHeaders[0]) && preg_match('/\s(\d{3})\s/', $responseHeaders[0], $statusMatch)) {
        $responseStatus = (int) $statusMatch[1];
    }

    if ($result === false || $responseStatus < 200 || $responseStatus >= 300) {
        jsonResponse([
            'message' => 'Webhook delivery failed.',
            'status' => $responseStatus ?: null,
        ], 502);
    }

    jsonResponse([
        'message' => 'Payment completed.',
        'operation_id' => $operationId,
        'status' => $status,
        'return_url' => $payment['return_url']
            ? $payment['return_url'] . (str_contains($payment['return_url'], '?') ? '&' : '?') . 'payment=' . $status
            : null,
    ]);
}

if ($method === 'GET' && $uri === '/payments') {
    $payments = array_values(readPayments());
    usort($payments, static fn (array $left, array $right): int => strcmp(
        (string) ($right['created_at'] ?? ''),
        (string) ($left['created_at'] ?? '')
    ));

    jsonResponse([
        'payments' => $payments,
    ]);
}

if ($method === 'GET' && preg_match('#^/payments/([^/]+)$#', $uri, $matches)) {
    $payments = readPayments();

    if (! isset($payments[$matches[1]])) {
        jsonResponse(['message' => 'Payment not found.'], 404);
    }

    jsonResponse(['payment' => $payments[$matches[1]]]);
}

if ($method === 'POST' && preg_match('#^/payments/([^/]+)/(complete|fail)$#', $uri, $matches)) {
    finalizePayment($matches[1], $matches[2] === 'complete' ? 'success' : 'failed');
}

jsonResponse([
    'message' => 'Route not found.',
], 404);
