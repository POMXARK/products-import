<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Application\Import\Port\ImageDownloader;
use App\Domain\User\User;
use App\Domain\User\UserRepository;
use App\Tests\Support\DatabaseTestCase;
use App\Tests\Support\FakeImageDownloader;
use Psr\Http\Message\ResponseInterface;
use Slim\App;
use Slim\Psr7\Factory\ServerRequestFactory;
use Slim\Psr7\Factory\StreamFactory;
use Slim\Psr7\UploadedFile;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\TransportInterface;

/**
 * Сквозной сценарий через HTTP-слой: вход, загрузка файла-примера из ТЗ, обработка очереди,
 * список с фильтрами и карточка товара. Брокер заменён in-memory транспортом, сеть картинок заглушкой.
 */
final class ApiTest extends DatabaseTestCase
{
    private const string SAMPLE = __DIR__.'/../Fixtures/import_example.xlsx';

    /** @var App<\Psr\Container\ContainerInterface|null> */
    private App $app;

    protected function overrides(): array
    {
        return [ImageDownloader::class => new FakeImageDownloader()];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->container->get(UserRepository::class)->save(User::register('admin', 'secret-password'));
        $this->app = (require \dirname(__DIR__, 2).'/config/app.php')($this->container);
    }

    /** @param array<string, mixed>|null $json */
    private function request(string $method, string $uri, ?array $json = null, ?string $token = null, ?UploadedFile $file = null): ResponseInterface
    {
        $request = (new ServerRequestFactory())->createServerRequest($method, $uri, ['REMOTE_ADDR' => '127.0.0.1']);
        parse_str((string) parse_url($uri, \PHP_URL_QUERY), $query);
        $request = $request->withQueryParams($query);
        if (null !== $json) {
            $request = $request->withHeader('Content-Type', 'application/json')
                ->withBody((new StreamFactory())->createStream(json_encode($json, \JSON_THROW_ON_ERROR)));
        }
        if (null !== $token) {
            $request = $request->withHeader('Authorization', 'Bearer '.$token);
        }
        if (null !== $file) {
            $request = $request->withUploadedFiles(['file' => $file]);
        }

        return $this->app->handle($request);
    }

    /** @return array<string, mixed> */
    private function json(ResponseInterface $response): array
    {
        $data = json_decode((string) $response->getBody(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    private function login(): string
    {
        $response = $this->request('POST', '/api/auth/login', ['username' => 'admin', 'password' => 'secret-password']);
        self::assertSame(200, $response->getStatusCode());

        return (string) $this->json($response)['token'];
    }

    private function sampleUpload(string $name = 'import example.xlsx', ?string $content = null): UploadedFile
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'upl');
        file_put_contents($path, $content ?? file_get_contents(self::SAMPLE));

        return new UploadedFile((new StreamFactory())->createStreamFromFile($path), $name, null, (int) filesize($path), \UPLOAD_ERR_OK);
    }

    /** То, что делает воркер messenger:consume: забирает сообщения из очереди и обрабатывает их. */
    private function runWorker(): int
    {
        /** @var TransportInterface $transport */
        $transport = $this->container->get('messenger.transports')->get('async');
        $bus = $this->container->get(MessageBusInterface::class);
        $handled = 0;
        foreach ($transport->get() as $envelope) {
            $bus->dispatch($envelope->with(new ReceivedStamp('async')));
            $transport->ack($envelope);
            ++$handled;
        }

        return $handled;
    }

    public function testHealthCheckIsPublic(): void
    {
        $response = $this->request('GET', '/api/health');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['status' => 'ok', 'checks' => ['database' => 'ok']], $this->json($response));
    }

    public function testLoginErrors(): void
    {
        self::assertSame(401, $this->request('POST', '/api/auth/login', ['username' => 'admin', 'password' => 'wrong'])->getStatusCode());

        $response = $this->request('POST', '/api/auth/login', []);
        self::assertSame(422, $response->getStatusCode());
        self::assertSame('application/problem+json; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertArrayHasKey('username', $this->json($response)['errors']);
    }

    public function testApiRequiresValidToken(): void
    {
        self::assertSame(401, $this->request('GET', '/api/products')->getStatusCode());
        self::assertSame(401, $this->request('GET', '/api/products', token: 'garbage')->getStatusCode());
    }

    public function testFullImportFlowFromSampleFile(): void
    {
        $token = $this->login();

        $upload = $this->request('POST', '/api/imports', token: $token, file: $this->sampleUpload());
        self::assertSame(202, $upload->getStatusCode());
        $job = $this->json($upload);
        self::assertSame('queued', $job['status']);
        self::assertSame('/api/imports/'.$job['id'], $upload->getHeaderLine('Location'));

        self::assertSame(1, $this->runWorker());

        $status = $this->json($this->request('GET', '/api/imports/'.$job['id'], token: $token));
        self::assertSame('completed', $status['status']);
        self::assertSame(100, $status['progress']);
        self::assertSame(40, $status['totalRows']);
        self::assertSame(40, $status['createdCount'] + $status['failedCount']);

        $list = $this->json($this->request('GET', '/api/products?page=2&limit=15', token: $token));
        self::assertSame(['page' => 2, 'limit' => 15, 'total' => $status['createdCount'], 'totalPages' => (int) ceil($status['createdCount'] / 15)], $list['meta']);
        self::assertCount(15, $list['items']);
        self::assertArrayNotHasKey('attributes', $list['items'][0], 'список лёгкий, атрибуты только в карточке');

        $card = $this->json($this->request('GET', '/api/products/'.$list['items'][0]['id'], token: $token));
        self::assertNotEmpty($card['attributes']);
        self::assertNotEmpty($card['images']);
        self::assertStringStartsWith('/storage/images/', (string) $card['images'][0]['publicUrl']);
        self::assertSame($job['id'], $card['lastImportId']);

        // Повторная загрузка того же файла обновляет товары, а не дублирует их.
        $second = $this->json($this->request('POST', '/api/imports', token: $token, file: $this->sampleUpload()));
        $this->runWorker();
        $secondStatus = $this->json($this->request('GET', '/api/imports/'.$second['id'], token: $token));
        self::assertSame(0, $secondStatus['createdCount']);
        self::assertSame($status['createdCount'], $secondStatus['updatedCount']);

        $history = $this->json($this->request('GET', '/api/imports', token: $token));
        self::assertSame([$second['id'], $job['id']], array_column($history['items'], 'id'));
    }

    public function testListFiltersAndValidation(): void
    {
        $token = $this->login();
        $this->request('POST', '/api/imports', token: $token, file: $this->sampleUpload());
        $this->runWorker();

        $all = $this->json($this->request('GET', '/api/products?limit=100', token: $token));
        $name = mb_substr((string) $all['items'][0]['name'], 0, 6);
        $byName = $this->json($this->request('GET', '/api/products?name='.rawurlencode(mb_strtoupper($name)), token: $token));
        self::assertGreaterThan(0, $byName['meta']['total']);
        foreach ($byName['items'] as $item) {
            self::assertStringContainsStringIgnoringCase($name, $item['name']);
        }

        $byPrice = $this->json($this->request('GET', '/api/products?price_min=1000&price_max=2000&limit=100', token: $token));
        foreach ($byPrice['items'] as $item) {
            self::assertGreaterThanOrEqual(1000, (float) $item['price']);
            self::assertLessThanOrEqual(2000, (float) $item['price']);
        }

        $invalid = $this->request('GET', '/api/products?page=0&limit=500&price_min=5&price_max=1', token: $token);
        self::assertSame(422, $invalid->getStatusCode());
        self::assertSame(['page', 'limit', 'price_min'], array_keys($this->json($invalid)['errors']));

        self::assertSame(404, $this->request('GET', '/api/products/999999', token: $token)->getStatusCode());
        self::assertSame(404, $this->request('GET', '/api/imports/not-a-uuid', token: $token)->getStatusCode());
    }

    public function testInvalidFileIsRejectedBeforeQueue(): void
    {
        $response = $this->request('POST', '/api/imports', token: $this->login(), file: $this->sampleUpload('prices.xlsx', 'name;price'));

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(0, $this->runWorker());
    }

    public function testImportEndpointIsRateLimited(): void
    {
        $token = $this->login();
        $limit = (int) getenv('IMPORT_RATE_LIMIT');

        for ($i = 0; $i < $limit; ++$i) {
            self::assertSame(202, $this->request('POST', '/api/imports', token: $token, file: $this->sampleUpload())->getStatusCode());
        }
        $blocked = $this->request('POST', '/api/imports', token: $token, file: $this->sampleUpload());

        self::assertSame(429, $blocked->getStatusCode());
        self::assertGreaterThan(0, (int) $blocked->getHeaderLine('Retry-After'));
    }

    public function testEveryRouteIsDescribedInOpenApi(): void
    {
        $spec = (string) file_get_contents(\dirname(__DIR__, 2).'/openapi.yaml');
        preg_match_all('#^  (/api/[^:]+):$#m', $spec, $m);
        $documented = $m[1];

        foreach ($this->app->getRouteCollector()->getRoutes() as $route) {
            $path = preg_replace('#\{(\w+)(:[^}]+)?\}#', '{$1}', $route->getPattern());
            self::assertContains($path, $documented, $path.' нет в openapi.yaml');
        }

        self::assertSame(200, $this->request('GET', '/api/openapi.yaml')->getStatusCode());
    }
}
