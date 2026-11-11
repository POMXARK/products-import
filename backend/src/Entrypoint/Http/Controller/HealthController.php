<?php

declare(strict_types=1);

namespace App\Entrypoint\Http\Controller;

use App\Entrypoint\Http\JsonResponder;
use Doctrine\DBAL\Connection;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * ТЗ: "health check endpoint (GET /api/health)".
 * Проверяет доступность БД: без неё API бесполезен, поэтому 503 сигналит оркестратору снять под из балансировки.
 */
final readonly class HealthController
{
    /** Соединение DBAL для SELECT 1. */
    public function __construct(private Connection $connection)
    {
    }

    /** 200 и status=ok, если БД отвечает; 503 и status=fail, если нет. */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $checks = ['database' => 'ok'];
        try {
            $this->connection->executeQuery('SELECT 1');
        } catch (\Throwable) {
            $checks['database'] = 'fail';
        }

        $healthy = !\in_array('fail', $checks, true);

        return JsonResponder::json($response, ['status' => $healthy ? 'ok' : 'fail', 'checks' => $checks], $healthy ? 200 : 503);
    }
}
