<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Domain\Import\ImportIssue;

/** Результат записи одной строки: создан или обновлён товар и какие были предупреждения. */
final readonly class ImportOutcome
{
    /**
     * created=false означает, что товар с таким external_code уже существовал.
     *
     * @param list<ImportIssue> $warnings
     */
    public function __construct(
        public bool $created,
        public array $warnings,
    ) {
    }
}
