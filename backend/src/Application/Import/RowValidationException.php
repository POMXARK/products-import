<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Domain\Import\ImportIssue;

/** Строка файла не прошла валидацию: товар не записывается, ошибки уходят в отчёт. */
final class RowValidationException extends \RuntimeException
{
    /**
     * Все ошибки строки сразу, чтобы пользователь исправил их за один раз.
     *
     * @param non-empty-list<ImportIssue> $issues
     */
    public function __construct(public readonly array $issues)
    {
        parent::__construct($issues[0]->message);
    }
}
