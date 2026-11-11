<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Import\Port\InvalidSpreadsheetException;
use App\Application\Import\Port\SpreadsheetReader;

/** Таблица из массива: строки задаются прямо в тесте. */
final class ArraySpreadsheetReader implements SpreadsheetReader
{
    /**
     * @param list<string>                                     $headers
     * @param array<int, array<string, string|int|float|null>> $rows    номер строки => данные
     */
    public function __construct(
        private readonly array $headers,
        private readonly array $rows,
        private readonly ?string $brokenWith = null,
    ) {
    }

    public function headers(string $absolutePath): array
    {
        if (null !== $this->brokenWith) {
            throw new InvalidSpreadsheetException($this->brokenWith);
        }

        return $this->headers;
    }

    public function rows(string $absolutePath): \Generator
    {
        yield from $this->rows;
    }

    public function countDataRows(string $absolutePath): int
    {
        return \count($this->rows);
    }
}
