<?php

declare(strict_types=1);

namespace App\Entrypoint\Http\Middleware;

use App\Application\Auth\InvalidTokenException;
use App\Application\Auth\TokenIssuer;
use App\Entrypoint\Http\JsonResponder;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * ТЗ: защита маршрутов JWT. Пропускает запрос только с действующим Bearer-токеном
 * и кладёт пользователя в атрибут запроса (он нужен rate limiter и автору импорта).
 */
final readonly class JwtAuthMiddleware implements MiddlewareInterface
{
    public const string USER_ATTRIBUTE = 'auth.user';

    /** Проверку токена выполняет порт TokenIssuer. */
    public function __construct(
        private TokenIssuer $tokens,
        private ResponseFactoryInterface $responses,
    ) {
    }

    /** Без токена или с просроченным токеном возвращает 401, на который фронтенд отвечает переходом на login. */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $header = $request->getHeaderLine('Authorization');
        if (!preg_match('/^Bearer\s+(\S+)$/i', $header, $m)) {
            return $this->unauthorized('Требуется авторизация');
        }

        try {
            $user = $this->tokens->verify($m[1]);
        } catch (InvalidTokenException) {
            return $this->unauthorized('Токен недействителен или истёк');
        }

        return $handler->handle($request->withAttribute(self::USER_ATTRIBUTE, $user));
    }

    /** Ответ 401 в формате problem+json с заголовком WWW-Authenticate: Bearer. */
    private function unauthorized(string $detail): ResponseInterface
    {
        return JsonResponder::problem($this->responses->createResponse(), 401, 'Unauthorized', $detail)
            ->withHeader('WWW-Authenticate', 'Bearer');
    }
}
