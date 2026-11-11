<?php

declare(strict_types=1);

namespace App\Entrypoint\Http\Middleware;

use App\Application\Auth\AuthenticatedUser;
use App\Entrypoint\Http\JsonResponder;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * ТЗ: "rate limiting на endpoint импорта".
 *
 * Лимит считается на пользователя (или IP без авторизации). Состояние лимитера лежит в БД,
 * поэтому работает одинаково при нескольких процессах php-fpm и нескольких контейнерах app.
 */
final readonly class RateLimitMiddleware implements MiddlewareInterface
{
    /** Фабрика лимитера sliding window с параметрами из IMPORT_RATE_LIMIT и IMPORT_RATE_INTERVAL. */
    public function __construct(
        private RateLimiterFactory $limiter,
        private ResponseFactoryInterface $responses,
    ) {
    }

    /** При превышении возвращает 429 с Retry-After; иначе пропускает и сообщает остаток в X-RateLimit-*. */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $user = $request->getAttribute(JwtAuthMiddleware::USER_ATTRIBUTE);
        $key = $user instanceof AuthenticatedUser
            ? 'user-'.$user->id
            : 'ip-'.($request->getServerParams()['REMOTE_ADDR'] ?? 'unknown');

        $limit = $this->limiter->create($key)->consume();
        if (!$limit->isAccepted()) {
            $retryAfter = max(1, $limit->getRetryAfter()->getTimestamp() - time());

            return JsonResponder::problem($this->responses->createResponse(), 429, 'Too Many Requests', 'Слишком много импортов, попробуйте позже')
                ->withHeader('Retry-After', (string) $retryAfter)
                ->withHeader('X-RateLimit-Limit', (string) $limit->getLimit())
                ->withHeader('X-RateLimit-Remaining', '0');
        }

        return $handler->handle($request)
            ->withHeader('X-RateLimit-Limit', (string) $limit->getLimit())
            ->withHeader('X-RateLimit-Remaining', (string) $limit->getRemainingTokens());
    }
}
