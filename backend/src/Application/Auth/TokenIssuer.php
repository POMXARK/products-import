<?php

declare(strict_types=1);

namespace App\Application\Auth;

/** Порт выпуска и проверки токенов: прикладной слой не зависит от конкретной JWT-библиотеки. */
interface TokenIssuer
{
    /** Выпускает токен для пользователя после успешного входа. */
    public function issue(int $userId, string $username): IssuedToken;

    /**
     * Проверяет подпись и срок токена из заголовка Authorization.
     *
     * @throws InvalidTokenException
     */
    public function verify(string $token): AuthenticatedUser;
}
