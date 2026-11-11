<?php

declare(strict_types=1);

namespace App\Application\Auth;

/** Неверный логин или пароль; HTTP-слой отвечает 401. */
final class InvalidCredentialsException extends \RuntimeException
{
}
