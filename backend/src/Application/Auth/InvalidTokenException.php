<?php

declare(strict_types=1);

namespace App\Application\Auth;

/** Токен отсутствует, подделан или истёк; HTTP-слой отвечает 401, клиент уводит на логин. */
final class InvalidTokenException extends \RuntimeException
{
}
