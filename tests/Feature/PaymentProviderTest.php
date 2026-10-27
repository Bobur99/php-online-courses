<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;

class PaymentProviderTest extends TestCase
{
    private string $projectRoot;
    private string $storageFile;
    private string $storageBackup;
    private $providerProcess;
    private $webhookProcess;
    private string $webhookRouterFile;
    private int $providerPort;
    private int $webhookPort;

    protected function setUp(): void
    {
        parent::setUp();

        $this->projectRoot = dirname(__DIR__, 2);

        $this->storageFile = $this->projectRoot . '/services/payment-provider/storage/payments.json';
        $this->storageBackup = file_exists($this->storageFile)
            ? (string) file_get_contents($this->storageFile)
            : '[]';
        file_put_contents($this->storageFile, '[]');

        $this->providerPort = random_int(19000, 19900);
        $this->webhookPort = random_int(20000, 20900);

        $this->webhookRouterFile = $this->webhookRouter();
        $this->webhookProcess = $this->startServer(
            PHP_BINARY . ' -S 127.0.0.1:' . $this->webhookPort . ' ' . escapeshellarg($this->webhookRouterFile)
        );
        $this->providerProcess = $this->startServer(
            PHP_BINARY . ' -S 127.0.0.1:' . $this->providerPort . ' -t ' . escapeshellarg($this->projectRoot . '/services/payment-provider/public')
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

        file_put_contents($this->storageFile, $this->storageBackup);
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
        file_put_contents($router, "<?php\nhttp_response_code(200);\nheader('Content-Type: application/json');\necho json_encode(['ok' => true]);\n");
        return $router;
    }
}
