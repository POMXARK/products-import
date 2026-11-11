<?php

declare(strict_types=1);

namespace App\Entrypoint\Http\Controller;

use App\Application\Auth\AuthenticatedUser;
use App\Application\Import\ImportJobView;
use App\Application\Import\ImportQueryService;
use App\Application\Import\ImportService;
use App\Entrypoint\Http\JsonResponder;
use App\Entrypoint\Http\Middleware\JwtAuthMiddleware;
use App\Entrypoint\Http\Upload\XlsxUploadValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Страница "Импорт товаров".
 * ТЗ: импорт асинхронный, поэтому загрузка возвращает 202 и id задачи, а статус и отчёт
 * клиент опрашивает отдельно (polling индикатора прогресса).
 */
final readonly class ImportController
{
    /** Валидатор файла, сервис постановки в очередь и чтение задач. */
    public function __construct(
        private XlsxUploadValidator $validator,
        private ImportService $imports,
        private ImportQueryService $queries,
    ) {
    }

    /** POST /api/imports (multipart, поле file): проверяет файл и ставит импорт в очередь. */
    public function upload(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $file = $request->getUploadedFiles()['file'] ?? null;
        $path = $this->validator->validate($file instanceof \Psr\Http\Message\UploadedFileInterface ? $file : null);

        $user = $request->getAttribute(JwtAuthMiddleware::USER_ATTRIBUTE);
        $job = $this->imports->enqueue(
            $path,
            (string) $file?->getClientFilename(),
            $user instanceof AuthenticatedUser ? $user->id : null,
        );

        return JsonResponder::json($response, ImportJobView::fromJob($job), 202)
            ->withHeader('Location', '/api/imports/'.$job->getId());
    }

    /**
     * GET /api/imports/{id}: статус, прогресс в процентах и отчёт по строкам с ошибками.
     *
     * @param array{id: string} $args
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return JsonResponder::json($response, $this->queries->get($args['id']));
    }

    /** GET /api/imports: последние импорты для истории на странице импорта. */
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return JsonResponder::json($response, ['items' => $this->queries->latest(20)]);
    }
}
