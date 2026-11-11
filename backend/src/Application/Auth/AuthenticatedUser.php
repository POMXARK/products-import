<?php

declare(strict_types=1);

namespace App\Application\Auth;

/** Пользователь, извлечённый из валидного JWT: кладётся в атрибуты запроса для контроллеров. */
final readonly class AuthenticatedUser
{
    /** id и логин из claims токена. */
    public function __construct(
        public int $id,
        public string $username,
    ) {
    }
}
