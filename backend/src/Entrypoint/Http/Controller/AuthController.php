<?php

declare(strict_types=1);

namespace App\Entrypoint\Http\Controller;

use App\Application\Auth\AuthService;
use App\Application\Shared\ValidationException;
use App\Entrypoint\Http\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** ТЗ: login-страница и JWT. Контроллер только разбирает тело запроса и отдаёт токен. */
final readonly class AuthController
{
    /** Вся логика входа в AuthService. */
    public function __construct(private AuthService $auth)
    {
    }

    /** POST /api/auth/login: логин и пароль в JSON, в ответе токен и срок его действия. */
    public function login(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $request->getParsedBody();
        $username = \is_array($body) && \is_string($body['username'] ?? null) ? trim($body['username']) : '';
        $password = \is_array($body) && \is_string($body['password'] ?? null) ? $body['password'] : '';

        $errors = [];
        if ('' === $username) {
            $errors['username'] = 'Укажите логин';
        }
        if ('' === $password) {
            $errors['password'] = 'Укажите пароль';
        }
        if ([] !== $errors) {
            throw new ValidationException($errors);
        }

        $token = $this->auth->login($username, $password);

        return JsonResponder::json($response, [
            'token' => $token->token,
            'tokenType' => 'Bearer',
            'expiresAt' => $token->expiresAt->format(\DATE_ATOM),
        ]);
    }
}
