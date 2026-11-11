<?php

declare(strict_types=1);

namespace App\Application\Import;

/** Сообщение в очередь: только идентификатор, состояние читается из БД. */
final readonly class RunImport
{
    /** UUID задачи импорта. */
    public function __construct(public string $importId)
    {
    }
}
