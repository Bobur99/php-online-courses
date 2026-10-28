<?php

declare(strict_types=1);

define('STORAGE_FILE', getenv('PAYMENT_STORAGE_FILE') ?: __DIR__.'/../storage/payments.json');

final class PaymentHttpException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status)
    {
        parent::__construct($message);
    }
}

function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    exit;
}

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (! (error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
set_exception_handler(static function (Throwable $exception): never {
    if ($exception instanceof PaymentHttpException) {
        jsonResponse(['message' => $exception->getMessage()], $exception->status);
    }
    error_log('Payment provider error: '.get_class($exception));
    jsonResponse(['message' => 'Payment service is temporarily unavailable.'], 500);
});

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin === (getenv('PAYMENT_UI_ORIGIN') ?: 'http://localhost:5173')) {
    header('Access-Control-Allow-Origin: '.$origin);
    header('Vary: Origin');
}
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-store');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Keep the entire read/modify/write operation under one lock, not only the write.
function paymentStorage(?callable $update = null): array
{
    $file = fopen(STORAGE_FILE, 'c+');
    if ($file === false) {
        throw new RuntimeException('Cannot open payment storage.');
    }
    try {
        if (! flock($file, $update ? LOCK_EX : LOCK_SH)) {
            throw new RuntimeException('Cannot lock payment storage.');
        }
        $content = stream_get_contents($file);
        $payments = json_decode($content === '' ? '{}' : $content, true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($payments)) {
            throw new RuntimeException('Invalid payment storage.');
        }
        if ($update) {
            $payments = $update($payments);
            $encoded = json_encode($payments, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
            rewind($file);
            if (fwrite($file, $encoded) !== strlen($encoded) || ! ftruncate($file, strlen($encoded)) || ! fflush($file)) {
                throw new RuntimeException('Cannot save payment storage.');
            }
        }

        return $payments;
    } finally {
        flock($file, LOCK_UN);
        fclose($file);
    }
}

function requestData(): array
{
    try {
        $data = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new PaymentHttpException('Invalid JSON.', 422);
    }
    if (! is_array($data)) {
        throw new PaymentHttpException('Invalid payment data.', 422);
    }

    return $data;
}

function finalizePayment(string $operationId, string $status): never
{
    $secret = getenv('PAYMENT_PROVIDER_WEBHOOK_SECRET');
    if (! $secret) {
        throw new RuntimeException('Webhook secret is not configured.');
    }
    $payments = paymentStorage(static function (array $payments) use ($operationId, $status): array {
        if (! isset($payments[$operationId])) {
            throw new PaymentHttpException('Payment not found.', 404);
        }
        $payment = &$payments[$operationId];
        if ($payment['status'] !== 'pending' && $payment['status'] !== $status) {
            throw new PaymentHttpException('Payment result cannot be changed.', 409);
        }
        $payment['status'] = $status;
        if (($payment['webhook_delivered'] ?? false) !== true) {
            $payment['webhook_delivered'] = false;
            $payment['webhook_attempts'] = ($payment['webhook_attempts'] ?? 0) + 1;
        }
        $payment['updated_at'] = gmdate('c');

        return $payments;
    });
    $payment = $payments[$operationId];
    if (($payment['webhook_delivered'] ?? false) === true) {
        jsonResponse(['operation_id' => $operationId, 'status' => $status, 'webhook_delivered' => true]);
    }

    $payload = json_encode(['operation_id' => $operationId, 'status' => $status], JSON_THROW_ON_ERROR);
    $signature = hash_hmac('sha256', $payload, $secret);
    $context = stream_context_create(['http' => [
        'method' => 'POST', 'header' => "Content-Type: application/json\r\nX-Payment-Signature: {$signature}",
        'content' => $payload, 'ignore_errors' => true, 'timeout' => 5,
    ]]);
    $responseStatus = 0;
    try {
        $result = file_get_contents($payment['webhook_url'], false, $context);
        $headers = http_get_last_response_headers() ?: [];
        // Redirects are not expected; use the final HTTP status if any are returned.
        foreach ($headers as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $match)) {
                $responseStatus = (int) $match[1];
            }
        }
        $delivered = $result !== false && $responseStatus >= 200 && $responseStatus < 300;
    } catch (ErrorException) {
        $delivered = false;
    }
    $updated = paymentStorage(static function (array $payments) use ($operationId, $delivered, $responseStatus): array {
        // A concurrent failed attempt must not overwrite a confirmed delivery.
        if (($payments[$operationId]['webhook_delivered'] ?? false) !== true || $delivered) {
            $payments[$operationId]['webhook_delivered'] = $delivered;
            $payments[$operationId]['webhook_http_status'] = $responseStatus ?: null;
        }
        $payments[$operationId]['updated_at'] = gmdate('c');

        return $payments;
    });
    $delivered = $updated[$operationId]['webhook_delivered'];
    error_log(json_encode(['event' => 'payment_webhook', 'operation_id' => $operationId,
        'payment_status' => $status, 'delivered' => $delivered, 'http_status' => $responseStatus]));
    jsonResponse(['message' => $delivered ? 'Payment completed.' : 'Webhook delivery failed.',
        'operation_id' => $operationId, 'status' => $status, 'webhook_delivered' => $delivered], $delivered ? 200 : 502);
}

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

if ($method === 'POST' && $uri === '/payments') {
    $data = requestData();
    if (! isset($data['amount'], $data['currency'], $data['description'], $data['webhook_url'])
        || ! is_numeric($data['amount']) || (float) $data['amount'] < 0 || (float) $data['amount'] != (int) $data['amount']
        || $data['currency'] !== 'UZS' || ! is_string($data['description']) || trim($data['description']) === ''
        || ! is_string($data['webhook_url']) || ! filter_var($data['webhook_url'], FILTER_VALIDATE_URL)
        || ! in_array(parse_url($data['webhook_url'], PHP_URL_SCHEME), ['http', 'https'], true)) {
        throw new PaymentHttpException('Invalid payment data.', 422);
    }
    $operationId = 'operation-'.bin2hex(random_bytes(12));
    paymentStorage(static function (array $payments) use ($operationId, $data): array {
        $payments[$operationId] = ['operation_id' => $operationId, 'amount' => (int) $data['amount'],
            'currency' => 'UZS', 'description' => trim($data['description']), 'webhook_url' => $data['webhook_url'],
            'status' => 'pending', 'webhook_delivered' => false, 'webhook_attempts' => 0,
            'webhook_http_status' => null, 'created_at' => gmdate('c'), 'updated_at' => gmdate('c')];

        return $payments;
    });
    $checkoutUrl = rtrim(getenv('PAYMENT_CHECKOUT_URL') ?: 'http://localhost:5173/pay', '/');
    jsonResponse(['operation_id' => $operationId, 'payment_url' => "{$checkoutUrl}/{$operationId}"], 201);
}

if ($method === 'GET' && $uri === '/payments') {
    $payments = array_values(paymentStorage());
    usort($payments, static fn (array $a, array $b): int => strcmp($b['created_at'], $a['created_at']));
    jsonResponse(['payments' => $payments]);
}

if ($method === 'GET' && preg_match('#^/payments/([^/]+)$#', $uri, $matches)) {
    $payments = paymentStorage();
    if (! isset($payments[$matches[1]])) {
        throw new PaymentHttpException('Payment not found.', 404);
    }
    jsonResponse(['payment' => $payments[$matches[1]]]);
}

if ($method === 'POST' && preg_match('#^/payments/([^/]+)/(complete|fail)$#', $uri, $matches)) {
    finalizePayment($matches[1], $matches[2] === 'complete' ? 'success' : 'failed');
}

jsonResponse(['message' => 'Route not found.'], 404);
