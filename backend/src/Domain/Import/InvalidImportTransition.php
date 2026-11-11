<?php

declare(strict_types=1);

namespace App\Domain\Import;

/** Недопустимая смена статуса импорта, например попытка завершить уже упавший импорт. */
final class InvalidImportTransition extends \DomainException
{
    /** Ошибка с указанием задачи, текущего и запрошенного статуса для логов. */
    public static function from(string $id, ImportStatus $current, ImportStatus $target): self
    {
        return new self(\sprintf('Import %s cannot move from %s to %s', $id, $current->value, $target->value));
    }
}
