<?php

declare(strict_types=1);

namespace App\Application\Auth;

use App\Domain\User\UserRepository;

/** Сценарий входа по ТЗ «JWT-логин»: проверяет логин и пароль, выдаёт токен для Angular-клиента. */
final readonly class AuthService
{
    /** Хеш-заглушка, чтобы проверка пароля занимала одинаковое время. */
    private const string DUMMY_HASH = '$2y$10$jeub2mn9y/LwPmsNFlP08ezdTR0.75hrRRNHbqGLCpj5D8ywQCCky';

    /** Зависит только от портов: хранилища пользователей и выпуска токенов. */
    public function __construct(
        private UserRepository $users,
        private TokenIssuer $tokens,
    ) {
    }

    /**
     * Неверный логин и неверный пароль дают одну и ту же ошибку, чтобы не раскрывать существующие учётки.
     *
     * @throws InvalidCredentialsException
     */
    public function login(string $username, string $password): IssuedToken
    {
        $user = $this->users->findByUsername($username);
        if (null === $user) {
            // Одинаковое время ответа для существующего и несуществующего логина.
            password_verify($password, self::DUMMY_HASH);

            throw new InvalidCredentialsException();
        }
        if (!$user->verifyPassword($password)) {
            throw new InvalidCredentialsException();
        }

        return $this->tokens->issue((int) $user->getId(), $user->getUsername());
    }
}
