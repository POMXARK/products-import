<?php

declare(strict_types=1);

use App\Entrypoint\Http\Controller\AuthController;
use App\Entrypoint\Http\Controller\DocsController;
use App\Entrypoint\Http\Controller\HealthController;
use App\Entrypoint\Http\Controller\ImportController;
use App\Entrypoint\Http\Controller\ProductController;
use App\Entrypoint\Http\Error\ProblemDetailsMiddleware;
use App\Entrypoint\Http\Middleware\JwtAuthMiddleware;
use App\Entrypoint\Http\Middleware\RateLimitMiddleware;
use Psr\Container\ContainerInterface;
use Slim\App;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;

/** Создаёт Slim-приложение: маршруты API и цепочку middleware. */
return static function (ContainerInterface $container): App {
    $app = AppFactory::createFromContainer($container);

    $app->group('/api', function (RouteCollectorProxy $api): void {
        // Публичные маршруты.
        $api->get('/health', HealthController::class);
        $api->post('/auth/login', [AuthController::class, 'login']);
        $api->get('/openapi.yaml', [DocsController::class, 'spec']);
        $api->get('/docs', [DocsController::class, 'ui']);

        // Маршруты под JWT.
        $api->group('', function (RouteCollectorProxy $secured): void {
            $secured->get('/products', [ProductController::class, 'list']);
            $secured->get('/products/{id:[0-9]+}', [ProductController::class, 'show']);
            $secured->get('/imports', [ImportController::class, 'list']);
            $secured->get('/imports/{id}', [ImportController::class, 'show']);
            $secured->post('/imports', [ImportController::class, 'upload'])->add(RateLimitMiddleware::class);
        })->add(JwtAuthMiddleware::class);
    });

    $app->addBodyParsingMiddleware();
    $app->addRoutingMiddleware();
    // Последний добавленный middleware выполняется первым: ошибки любого слоя превращаются в JSON.
    $app->add(ProblemDetailsMiddleware::class);

    return $app;
};
