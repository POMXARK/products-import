<?php

declare(strict_types=1);

namespace App\Domain\Shared;

/** Запрошенный объект (товар, импорт) не найден; HTTP-слой превращает это в 404. */
final class NotFoundException extends \RuntimeException
{
}
