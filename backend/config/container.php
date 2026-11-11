<?php

declare(strict_types=1);

use App\Application\Auth\TokenIssuer;
use App\Application\Import\Port\FileStorage;
use App\Application\Import\Port\ImageDownloader;
use App\Application\Import\Port\ImportQueue;
use App\Application\Import\Port\SpreadsheetReader;
use App\Application\Import\RunImport;
use App\Application\Import\RunImportHandler;
use App\Application\Product\ProductQueryService;
use App\Application\Shared\TransactionManager;
use App\Domain\Import\ImportJobRepository;
use App\Domain\Product\ProductRepository;
use App\Domain\User\UserRepository;
use App\Entrypoint\Console\LoadFixturesCommand;
use App\Entrypoint\Http\Controller\DocsController;
use App\Entrypoint\Http\Error\ProblemDetailsMiddleware;
use App\Entrypoint\Http\Middleware\RateLimitMiddleware;
use App\Entrypoint\Http\Upload\XlsxUploadValidator;
use App\Infrastructure\Auth\JwtTokenIssuer;
use App\Infrastructure\Image\HttpImageDownloader;
use App\Infrastructure\Messenger\ArrayLocator;
use App\Infrastructure\Messenger\DoctrineConnectionMiddleware;
use App\Infrastructure\Messenger\MarkImportFailedListener;
use App\Infrastructure\Messenger\MessengerImportQueue;
use App\Infrastructure\Persistence\Doctrine\DoctrineTransactionManager;
use App\Infrastructure\Persistence\Doctrine\EntityManagerProvider;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineImportJobRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineProductRepository;
use App\Infrastructure\Persistence\Doctrine\Repository\DoctrineUserRepository;
use App\Infrastructure\Storage\LocalFileStorage;
use App\Infrastructure\Xlsx\OpenSpoutSpreadsheetReader;

use function DI\autowire;
use function DI\get;

use Doctrine\DBAL\Configuration as DbalConfiguration;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\AbstractAsset;
use Doctrine\DBAL\Tools\DsnParser;
use Doctrine\ORM\Configuration as OrmConfiguration;
use Doctrine\ORM\Mapping\Driver\SimplifiedXmlDriver;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Factory\ResponseFactory;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\DoctrineDbalAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\NoPrivateNetworkHttpClient;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransportFactory;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Middleware\SendMessageMiddleware;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransportFactory;
use Symfony\Component\Messenger\Transport\Sender\SendersLocator;
use Symfony\Component\Messenger\Transport\Serialization\PhpSerializer;
use Symfony\Component\Messenger\Transport\TransportFactory;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\CacheStorage;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Сборка зависимостей. Связывает порты application-слоя с реализациями из infrastructure:
 * бизнес-логика не знает про Doctrine, OpenSpout, HttpClient и RabbitMQ.
 */
return [
    'settings' => static fn (): array => require __DIR__.'/settings.php',

    LoggerInterface::class => static function (ContainerInterface $c): LoggerInterface {
        $logger = new Logger('app');
        $handler = new StreamHandler('php://stderr', Logger::toMonologLevel($c->get('settings')['log_level']));
        $handler->setFormatter(new JsonFormatter());

        return $logger->pushHandler($handler);
    },

    ResponseFactoryInterface::class => autowire(ResponseFactory::class),

    // --- Doctrine ---
    Connection::class => static function (ContainerInterface $c): Connection {
        $params = (new DsnParser(['postgresql' => 'pdo_pgsql', 'postgres' => 'pdo_pgsql', 'pgsql' => 'pdo_pgsql']))
            ->parse($c->get('settings')['database_url']);

        $config = new DbalConfiguration();
        // Таблица rate limiter не описана в маппинге и не должна попадать в diff схемы.
        $config->setSchemaAssetsFilter(static fn (string|AbstractAsset $asset): bool => !in_array(
            $asset instanceof AbstractAsset ? $asset->getName() : $asset,
            ['rate_limiter'],
            true,
        ));

        return DriverManager::getConnection($params, $config);
    },
    OrmConfiguration::class => static function (ContainerInterface $c): OrmConfiguration {
        $settings = $c->get('settings');
        $config = new OrmConfiguration();
        $config->setMetadataDriverImpl(new SimplifiedXmlDriver([
            $settings['root'].'/src/Infrastructure/Persistence/Doctrine/Mapping' => 'App\Domain',
        ], '.orm.xml'));
        $config->setProxyDir($settings['root'].'/var/cache/doctrine/proxies');
        $config->setProxyNamespace('DoctrineProxies');
        $config->setAutoGenerateProxyClasses(true);
        $cache = 'prod' === $settings['env']
            ? new PhpFilesAdapter('doctrine', 0, $settings['root'].'/var/cache')
            : new ArrayAdapter();
        $config->setMetadataCache($cache);
        $config->setQueryCache($cache);

        return $config;
    },
    EntityManagerProvider::class => autowire(),
    TransactionManager::class => autowire(DoctrineTransactionManager::class),
    ProductRepository::class => autowire(DoctrineProductRepository::class),
    ImportJobRepository::class => autowire(DoctrineImportJobRepository::class),
    UserRepository::class => autowire(DoctrineUserRepository::class),

    // --- Import ---
    FileStorage::class => static fn (ContainerInterface $c): FileStorage => new LocalFileStorage($c->get('settings')['storage']['path']),
    SpreadsheetReader::class => autowire(OpenSpoutSpreadsheetReader::class),
    HttpClientInterface::class => static function (ContainerInterface $c): HttpClientInterface {
        $client = HttpClient::create(['headers' => ['User-Agent' => 'products-import/1.0']]);

        // Ссылки в файле присылает пользователь: без защиты импорт мог бы ходить во внутреннюю сеть (SSRF).
        return $c->get('settings')['images']['allow_private_network'] ? $client : new NoPrivateNetworkHttpClient($client);
    },
    ImageDownloader::class => static function (ContainerInterface $c): ImageDownloader {
        $s = $c->get('settings');

        return new HttpImageDownloader($c->get(HttpClientInterface::class), $s['storage']['path'], $s['images']['max_bytes'], $s['images']['timeout']);
    },
    RunImportHandler::class => static fn (ContainerInterface $c): RunImportHandler => new RunImportHandler(
        $c->get(ImportJobRepository::class),
        $c->get(SpreadsheetReader::class),
        $c->get(App\Application\Import\ProductRowParser::class),
        $c->get(App\Application\Import\ProductImporter::class),
        $c->get(FileStorage::class),
        $c->get(TransactionManager::class),
        $c->get(LoggerInterface::class),
        $c->get('settings')['import']['progress_every_rows'],
    ),
    ProductQueryService::class => static fn (ContainerInterface $c): ProductQueryService => new ProductQueryService(
        $c->get(ProductRepository::class),
        $c->get('settings')['storage']['public_prefix'],
    ),

    // --- Auth ---
    TokenIssuer::class => static fn (ContainerInterface $c): TokenIssuer => new JwtTokenIssuer($c->get('settings')['jwt']['secret'], $c->get('settings')['jwt']['ttl']),

    // --- HTTP ---
    XlsxUploadValidator::class => static fn (ContainerInterface $c): XlsxUploadValidator => new XlsxUploadValidator($c->get('settings')['import']['max_file_bytes']),
    ProblemDetailsMiddleware::class => static fn (ContainerInterface $c): ProblemDetailsMiddleware => new ProblemDetailsMiddleware($c->get(ResponseFactoryInterface::class), $c->get(LoggerInterface::class), $c->get('settings')['debug']),
    'import.rate_limiter' => static function (ContainerInterface $c): RateLimiterFactory {
        $s = $c->get('settings')['import'];

        return new RateLimiterFactory(
            ['id' => 'import', 'policy' => 'sliding_window', 'limit' => $s['rate_limit'], 'interval' => $s['rate_interval']],
            new CacheStorage(new DoctrineDbalAdapter($c->get(Connection::class), '', 0, ['db_table' => 'rate_limiter'])),
        );
    },
    RateLimitMiddleware::class => static fn (ContainerInterface $c): RateLimitMiddleware => new RateLimitMiddleware($c->get('import.rate_limiter'), $c->get(ResponseFactoryInterface::class)),
    DocsController::class => static fn (ContainerInterface $c): DocsController => new DocsController($c->get('settings')['root'].'/openapi.yaml'),

    LoadFixturesCommand::class => static fn (ContainerInterface $c): LoadFixturesCommand => new LoadFixturesCommand(
        $c->get(EntityManagerProvider::class),
        $c->get('settings'),
    ),

    // --- Messenger ---
    ImportQueue::class => autowire(MessengerImportQueue::class),
    'messenger.transports' => static function (ContainerInterface $c): ArrayLocator {
        $s = $c->get('settings')['messenger'];
        $factory = new TransportFactory([new AmqpTransportFactory(), new InMemoryTransportFactory()]);
        $serializer = new PhpSerializer();

        return new ArrayLocator([
            'async' => $factory->createTransport($s['transport_dsn'], [], $serializer),
            'failed' => $factory->createTransport($s['failed_dsn'], [], $serializer),
        ]);
    },
    MessageBusInterface::class => static function (ContainerInterface $c): MessageBusInterface {
        return new MessageBus([
            new SendMessageMiddleware(new SendersLocator([RunImport::class => ['async']], $c->get('messenger.transports')), $c->get(EventDispatcherInterface::class)),
            new DoctrineConnectionMiddleware($c->get(EntityManagerProvider::class)),
            new HandleMessageMiddleware(new HandlersLocator([
                RunImport::class => [static fn (RunImport $m) => $c->get(RunImportHandler::class)($m)],
            ])),
        ]);
    },
    EventDispatcherInterface::class => static function (ContainerInterface $c): EventDispatcher {
        $s = $c->get('settings')['messenger'];
        $dispatcher = new EventDispatcher();
        $transports = $c->get('messenger.transports');
        $logger = $c->get(LoggerInterface::class);

        // Повторы с экспоненциальной паузой, затем сообщение уходит в очередь failed.
        $dispatcher->addSubscriber(new SendFailedMessageForRetryListener(
            $transports,
            new ArrayLocator(['async' => new MultiplierRetryStrategy($s['max_retries'], $s['retry_delay_ms'], 2)]),
            $logger,
        ));
        $dispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener(
            new ArrayLocator(['async' => $transports->get('failed')]),
            $logger,
        ));
        $dispatcher->addListener(WorkerMessageFailedEvent::class, $c->get(MarkImportFailedListener::class), -200);

        return $dispatcher;
    },
    Psr\EventDispatcher\EventDispatcherInterface::class => get(EventDispatcherInterface::class),
];
