<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class PaymentProviderTest extends TestCase
{
    private string $projectRoot;

    private string $storageFile;

    private $providerProcess;

    private $webhookProcess;

    private string $webhookRouterFile;

    private int $providerPort;

    private int $webhookPort;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectRoot = dirname(__DIR__, 2);

        $this->storageFile = tempnam(sys_get_temp_dir(), 'provider-test-');
        file_put_contents($this->storageFile, '[]');

        $this->providerPort = random_int(19000, 19900);
        $this->webhookPort = random_int(20000, 20900);

        $this->webhookRouterFile = $this->webhookRouter();
        $this->webhookProcess = $this->startServer(
            PHP_BINARY.' -S 127.0.0.1:'.$this->webhookPort.' '.escapeshellarg($this->webhookRouterFile)
        );
        $this->providerProcess = $this->startServer(
            'PAYMENT_PROVIDER_WEBHOOK_SECRET=test-secret PAYMENT_STORAGE_FILE='.escapeshellarg($this->storageFile).' '.PHP_BINARY.' -S 127.0.0.1:'.$this->providerPort.' -t '.escapeshellarg($this->projectRoot.'/services/payment-provider/public')
        );
    }

    protected function tearDown(): void
    {
        if (is_resource($this->providerProcess)) {
            proc_terminate($this->providerProcess);
            proc_close($this->providerProcess);
        }
        if (is_resource($this->webhookProcess)) {
            proc_terminate($this->webhookProcess);
            proc_close($this->webhookProcess);
        }

        if (isset($this->storageFile)) {
            unlink($this->storageFile);
        }
        if (isset($this->webhookRouterFile)) {
            @unlink($this->webhookRouterFile);
        }
        parent::tearDown();
    }

    public function test_provider_creates_and_lists_pending_payment(): void
    {
        [$status, $body] = $this->request('POST', '/payments', [
            'amount' => 450000,
            'currency' => 'UZS',
            'description' => 'Основы Laravel',
            'webhook_url' => "http://127.0.0.1:{$this->webhookPort}/webhook",
        ]);

        $this->assertSame(201, $status);
        $this->assertStringStartsWith('operation-', $body['operation_id']);

        [$listStatus, $list] = $this->request('GET', '/payments');

        $this->assertSame(200, $listStatus);
        $this->assertCount(1, $list['payments']);
        $this->assertSame('pending', $list['payments'][0]['status']);
    }

    public function test_provider_completes_payment_and_delivers_webhook(): void
    {
        [, $created] = $this->request('POST', '/payments', [
            'amount' => 450000,
            'currency' => 'UZS',
            'description' => 'Основы Laravel',
            'webhook_url' => "http://127.0.0.1:{$this->webhookPort}/webhook",
        ]);

        [$status, $result] = $this->request('POST', "/payments/{$created['operation_id']}/complete");

        $this->assertSame(200, $status);
        $this->assertSame('success', $result['status']);

        [, $payment] = $this->request('GET', "/payments/{$created['operation_id']}");
        $this->assertSame('success', $payment['payment']['status']);
    }

    public function test_provider_returns_not_found_for_unknown_operation(): void
    {
        [$status, $body] = $this->request('GET', '/payments/operation-missing');

        $this->assertSame(404, $status);
        $this->assertSame('Payment not found.', $body['message']);
    }

    public function test_failed_webhook_delivery_is_visible_and_can_be_retried(): void
    {
        [, $created] = $this->request('POST', '/payments', $this->paymentData('/webhook?status=503'));
        [$status] = $this->request('POST', "/payments/{$created['operation_id']}/complete");
        $this->assertSame(502, $status);
        [, $result] = $this->request('GET', "/payments/{$created['operation_id']}");
        $this->assertSame('success', $result['payment']['status']);
        $this->assertFalse($result['payment']['webhook_delivered']);
        $this->assertSame(503, $result['payment']['webhook_http_status']);
        $payments = json_decode(file_get_contents($this->storageFile), true);
        $payments[$created['operation_id']]['webhook_url'] = "http://127.0.0.1:{$this->webhookPort}/webhook";
        file_put_contents($this->storageFile, json_encode($payments));
        [$retryStatus] = $this->request('POST', "/payments/{$created['operation_id']}/complete");
        $this->assertSame(200, $retryStatus);
        [, $result] = $this->request('GET', "/payments/{$created['operation_id']}");
        $this->assertTrue($result['payment']['webhook_delivered']);
        $this->assertSame(2, $result['payment']['webhook_attempts']);
    }

    public function test_terminal_result_cannot_be_reversed(): void
    {
        [, $created] = $this->request('POST', '/payments', $this->paymentData());
        $path = "/payments/{$created['operation_id']}";
        $this->assertSame(200, $this->request('POST', "$path/fail")[0]);
        $this->assertSame(409, $this->request('POST', "$path/complete")[0]);
        $this->assertSame('failed', $this->request('GET', $path)[1]['payment']['status']);
    }

    public function test_invalid_payment_data_is_rejected_and_no_return_link_is_created(): void
    {
        $data = $this->paymentData();
        $data['amount'] = -10;
        $this->assertSame(422, $this->request('POST', '/payments', $data)[0]);
        [, $created] = $this->request('POST', '/payments', $this->paymentData());
        [, $payment] = $this->request('GET', "/payments/{$created['operation_id']}");
        $this->assertArrayNotHasKey('return_url', $payment['payment']);
        $this->assertCount(1, json_decode(file_get_contents($this->storageFile), true));
    }

    private function paymentData(string $path = '/webhook'): array
    {
        return ['amount' => 100, 'currency' => 'UZS', 'description' => 'Test',
            'webhook_url' => "http://127.0.0.1:{$this->webhookPort}{$path}"];
    }

    private function request(string $method, string $path, ?array $payload = null): array
    {
        $headers = ['Content-Type: application/json'];
        $options = [
            'http' => [
                'method' => $method,
                'ignore_errors' => true,
                'header' => implode("\r\n", $headers),
                'timeout' => 5,
            ],
        ];

        if ($payload !== null) {
            $options['http']['content'] = json_encode($payload, JSON_UNESCAPED_UNICODE);
        }

        $body = file_get_contents("http://127.0.0.1:{$this->providerPort}{$path}", false, stream_context_create($options));
        $status = 0;
        if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $match)) {
            $status = (int) $match[1];
        }

        return [$status, json_decode($body ?: '{}', true)];
    }

    private function startServer(string $command)
    {
        $process = proc_open($command, [
            0 => ['file', '/dev/null', 'r'],
            1 => ['file', '/dev/null', 'w'],
            2 => ['file', '/dev/null', 'w'],
        ], $pipes, $this->projectRoot);

        $this->waitForPort(str_contains($command, (string) $this->providerPort)
            ? $this->providerPort
            : $this->webhookPort);

        return $process;
    }

    private function waitForPort(int $port): void
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            set_error_handler(static fn (): bool => true);
            $socket = fsockopen('127.0.0.1', $port, $errorCode, $errorMessage, 0.1);
            restore_error_handler();
            if (is_resource($socket)) {
                fclose($socket);

                return;
            }
            usleep(50000);
        }

        $this->fail("Test server did not start on port {$port}.");
    }

    private function webhookRouter(): string
    {
        $router = tempnam(sys_get_temp_dir(), 'payment-webhook-');
        file_put_contents($router, '<?php $signature = hash_hmac("sha256", file_get_contents("php://input"), getenv("PAYMENT_PROVIDER_WEBHOOK_SECRET") ?: "test-secret"); http_response_code(hash_equals($signature, $_SERVER["HTTP_X_PAYMENT_SIGNATURE"] ?? "") ? (int)($_GET["status"] ?? 200) : 401); header("Content-Type: application/json"); echo json_encode(["ok" => true]);');

        return $router;
    }
}
