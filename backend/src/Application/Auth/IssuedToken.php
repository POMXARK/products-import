<?php

declare(strict_types=1);

namespace App\Application\Auth;

/** Выданный JWT и момент его истечения: клиент по нему знает, когда нужен повторный вход. */
final readonly class IssuedToken
{
    /** Строка токена и срок действия. */
    public function __construct(
        public string $token,
        public \DateTimeImmutable $expiresAt,
    ) {
    }
}
