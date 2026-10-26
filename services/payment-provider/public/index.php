<?php

declare(strict_types=1);

const STORAGE_FILE = __DIR__ . '/../storage/payments.json';

header('Content-Type: application/json');

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
        'status' => 'pending',
    ];

    savePayments($payments);

    jsonResponse([
        'operation_id' => $operationId,
        'payment_url' => "https://payment.test/pay/{$operationId}",
    ], 201);
}

if ($method === 'POST' && preg_match('#^/payments/([^/]+)/complete$#', $uri, $matches)) {
    $operationId = $matches[1];
    $payments = readPayments();

    if (! isset($payments[$operationId])) {
        jsonResponse([
            'message' => 'Payment not found.',
        ], 404);
    }

    $payment = $payments[$operationId];
    $payment['status'] = 'success';
    $payments[$operationId] = $payment;

    savePayments($payments);

    $payload = json_encode([
        'operation_id' => $operationId,
        'status' => 'success',
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

    if ($result === false) {
        jsonResponse([
            'message' => 'Webhook delivery failed.',
        ], 502);
    }

    jsonResponse([
        'message' => 'Payment completed.',
        'operation_id' => $operationId,
        'status' => 'success',
    ]);
}

jsonResponse([
    'message' => 'Route not found.',
], 404);