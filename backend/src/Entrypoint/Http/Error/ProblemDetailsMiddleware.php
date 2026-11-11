<?php

declare(strict_types=1);

namespace App\Entrypoint\Http\Error;

use App\Application\Auth\InvalidCredentialsException;
use App\Application\Shared\ValidationException;
use App\Domain\Shared\NotFoundException;
use App\Entrypoint\Http\JsonResponder;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpException;

/**
 * Переводит исключения в HTTP-ответы, чтобы контроллеры не содержали try/catch.
 * Ошибки 5xx логируются с трассировкой, а клиенту уходит текст без внутренних деталей.
 */
final readonly class ProblemDetailsMiddleware implements MiddlewareInterface
{
    /** В debug-режиме клиенту отдаётся текст исключения, в prod только общая фраза. */
    public function __construct(
        private ResponseFactoryInterface $responses,
        private LoggerInterface $logger,
        private bool $debug = false,
    ) {
    }

    /** Ловит исключение из любого слоя и отдаёт соответствующий код: 401, 404, 422, 4xx от Slim или 500. */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (ValidationException $e) {
            return JsonResponder::problem($this->responses->createResponse(), 422, 'Validation failed', $e->getMessage(), ['errors' => $e->getErrors()]);
        } catch (NotFoundException $e) {
            return JsonResponder::problem($this->responses->createResponse(), 404, 'Not Found', $e->getMessage());
        } catch (InvalidCredentialsException) {
            return JsonResponder::problem($this->responses->createResponse(), 401, 'Unauthorized', 'Неверный логин или пароль');
        } catch (HttpException $e) {
            return JsonResponder::problem($this->responses->createResponse(), $e->getCode(), $e->getTitle(), $e->getMessage());
        } catch (\Throwable $e) {
            $this->logger->error('Unhandled exception', ['exception' => $e, 'path' => $request->getUri()->getPath()]);

            return JsonResponder::problem(
                $this->responses->createResponse(),
                500,
                'Internal Server Error',
                $this->debug ? $e::class.': '.$e->getMessage() : 'Внутренняя ошибка сервера',
            );
        }
    }
}
