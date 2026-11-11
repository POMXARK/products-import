<?php

declare(strict_types=1);

namespace App\Application\Import\Port;

/** Порт чтения xlsx: потоковое чтение, чтобы большой файл не загружался в память целиком. */
interface SpreadsheetReader
{
    /**
     * Заголовки первого листа.
     *
     * @return list<string>
     *
     * @throws InvalidSpreadsheetException
     */
    public function headers(string $absolutePath): array;

    /**
     * Строки первого листа как "заголовок => значение". Ключ итератора: номер строки в файле.
     *
     * @return \Generator<int, array<string, string|int|float|null>>
     *
     * @throws InvalidSpreadsheetException файл не читается или нет строки заголовков
     */
    public function rows(string $absolutePath): \Generator;

    /** Количество строк с данными (без заголовка) для индикатора прогресса. */
    public function countDataRows(string $absolutePath): int;
}
