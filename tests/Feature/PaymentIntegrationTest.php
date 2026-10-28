<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Course;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Real HTTP integration against separate PHP processes and the test database. */
class PaymentIntegrationTest extends TestCase
{
    use DatabaseMigrations;

    private array $servers = [];

    private array $appUrls = [];

    private string $providerUrl;

    private string $storageFile;

    private string $token;

    private Course $course;

    protected function setUp(): void
    {
        parent::setUp();
        $student = User::factory()->create(['role' => 'student']);
        $teacher = User::factory()->create(['role' => 'teacher']);
        $category = Category::create(['name' => 'Integration']);
        $this->course = Course::create(['title' => 'Integration', 'description' => 'Test', 'price' => 100,
            'category_id' => $category->id, 'author_id' => $teacher->id, 'status' => 'published']);
        $this->token = $student->createToken('integration')->plainTextToken;
        $this->storageFile = tempnam(sys_get_temp_dir(), 'provider-integration-');
        $providerPort = $this->freePort();
        $this->providerUrl = "http://127.0.0.1:{$providerPort}";
        $ports = [$this->freePort(), $this->freePort()];
        $this->appUrls = array_map(fn (int $port): string => "http://127.0.0.1:{$port}", $ports);
        $env = array_merge(getenv(), array_filter($_ENV, 'is_scalar'), [
            'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'DB_CONNECTION' => 'pgsql',
            'DB_HOST' => config('database.connections.pgsql.host'),
            'DB_DATABASE' => config('database.connections.pgsql.database'),
            'DB_USERNAME' => config('database.connections.pgsql.username'),
            'DB_PASSWORD' => config('database.connections.pgsql.password'),
            'DB_URL' => '', 'QUEUE_CONNECTION' => 'database', 'CACHE_STORE' => 'array',
            'PAYMENT_PROVIDER_URL' => $this->providerUrl,
            'PAYMENT_PROVIDER_WEBHOOK_SECRET' => 'integration-secret',
            'PAYMENT_PROVIDER_WEBHOOK_URL' => $this->appUrls[0].'/api/webhooks/payment',
            'PAYMENT_STORAGE_FILE' => $this->storageFile,
        ]);
        $this->startServer($providerPort, base_path('services/payment-provider/public/index.php'), $env);
        foreach ($ports as $port) {
            $this->startServer($port, public_path('index.php'), $env);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->servers as $server) {
            proc_terminate($server);
            proc_close($server);
        }
        if (isset($this->storageFile)) {
            unlink($this->storageFile);
        }
        parent::tearDown();
    }

    public function test_real_provider_webhook_grants_access_and_notifies_only_once(): void
    {
        $created = $this->buy();
        $this->assertSame(201, $created->status());
        $operation = $created->json('purchase.provider_operation_id');
        $this->assertSame('pending', Purchase::first()->status);
        $result = Http::post("{$this->providerUrl}/payments/{$operation}/complete");
        $this->assertSame(200, $result->status());
        $this->assertSame('paid', Purchase::first()->status);
        $this->assertTrue(Http::withToken($this->token)->get($this->appUrls[0]."/api/courses/{$this->course->id}/purchase")->json('is_purchased'));
        $this->assertSame(1, DB::table('jobs')->count());
        Http::post("{$this->providerUrl}/payments/{$operation}/complete");
        $this->assertSame(1, DB::table('jobs')->count());
        $this->assertArrayNotHasKey('return_url', $result->json());
    }

    public function test_parallel_purchase_requests_create_one_operation(): void
    {
        $responses = Http::pool(fn (Pool $pool) => [
            $pool->withToken($this->token)->acceptJson()->post($this->appUrls[0]."/api/courses/{$this->course->id}/purchase"),
            $pool->withToken($this->token)->acceptJson()->post($this->appUrls[1]."/api/courses/{$this->course->id}/purchase"),
        ]);
        $statuses = array_map(fn ($response) => $response->status(), $responses);
        sort($statuses);
        $this->assertSame([201, 422], $statuses);
        $this->assertSame(1, Purchase::count());
        $this->assertCount(1, Http::get($this->providerUrl.'/payments')->json('payments'));
    }

    public function test_parallel_webhooks_dispatch_one_notification(): void
    {
        $operation = $this->buy()->json('purchase.provider_operation_id');
        $payload = json_encode(['operation_id' => $operation, 'status' => 'success']);
        $signature = hash_hmac('sha256', $payload, 'integration-secret');
        $responses = Http::pool(fn (Pool $pool) => array_map(fn (string $url) => $pool->withHeader('X-Payment-Signature', $signature)->acceptJson()->withBody($payload, 'application/json')->post($url.'/api/webhooks/payment'), $this->appUrls));
        foreach ($responses as $response) {
            $this->assertSame(200, $response->status());
        }
        $this->assertSame('paid', Purchase::first()->status);
        $this->assertSame(1, DB::table('jobs')->count());
    }

    private function buy(): Response
    {
        return Http::withToken($this->token)->acceptJson()->post($this->appUrls[0]."/api/courses/{$this->course->id}/purchase");
    }

    private function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        return $port;
    }

    private function startServer(int $port, string $router, array $env): void
    {
        $this->servers[] = proc_open([PHP_BINARY, '-S', "127.0.0.1:{$port}", $router], [
            0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w'],
        ], $pipes, base_path(), $env);
        for ($attempt = 0; $attempt < 50; $attempt++) {
            $socket = @fsockopen('127.0.0.1', $port);
            if ($socket) {
                fclose($socket);

                return;
            }
            usleep(20000);
        }
        $this->fail('Integration server did not start.');
    }
}
