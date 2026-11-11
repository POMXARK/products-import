<?php

declare(strict_types=1);

namespace App\Infrastructure\Xlsx;

use App\Application\Import\Port\InvalidSpreadsheetException;
use App\Application\Import\Port\SpreadsheetReader;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Options;
use OpenSpout\Reader\XLSX\Reader;

/**
 * ТЗ: "парсинг файла, запись в БД". Чтение xlsx потоково через OpenSpout:
 * память не растёт с размером файла, в отличие от загрузки всей книги в PhpSpreadsheet.
 */
final class OpenSpoutSpreadsheetReader implements SpreadsheetReader
{
    /** Первая строка первого листа: названия колонок, по которым строки сопоставляются с полями товара. */
    public function headers(string $absolutePath): array
    {
        foreach ($this->rawRows($absolutePath) as $cells) {
            return $this->normalizeHeaders($cells);
        }

        throw new InvalidSpreadsheetException('Файл пустой: нет строки заголовков');
    }

    /** Строки данных как "заголовок => значение"; ключ итератора равен номеру строки в Excel (для отчёта). */
    public function rows(string $absolutePath): \Generator
    {
        $headers = null;
        foreach ($this->rawRows($absolutePath) as $rowNumber => $cells) {
            if (null === $headers) {
                $headers = $this->normalizeHeaders($cells);
                continue;
            }

            $assoc = [];
            foreach ($headers as $index => $header) {
                if ('' === $header) {
                    continue;
                }
                $value = $cells[$index] ?? null;
                $assoc[$header] = match (true) {
                    $value instanceof \DateTimeInterface => $value->format('Y-m-d H:i:s'),
                    \is_bool($value) => $value ? '1' : '0',
                    \is_string($value), \is_int($value), \is_float($value) => $value,
                    default => null,
                };
            }

            yield $rowNumber => $assoc;
        }

        if (null === $headers) {
            throw new InvalidSpreadsheetException('Файл пустой: нет строки заголовков');
        }
    }

    /** Число строк с данными: знаменатель для индикатора прогресса импорта. */
    public function countDataRows(string $absolutePath): int
    {
        $count = 0;
        foreach ($this->rawRows($absolutePath) as $_) {
            ++$count;
        }

        return max(0, $count - 1);
    }

    /**
     * Ячейки первого листа построчно с номерами строк Excel.
     *
     * @return \Generator<int, list<mixed>>
     */
    private function rawRows(string $absolutePath): \Generator
    {
        $options = new Options();
        $options->SHOULD_FORMAT_DATES = false;
        // Пустые строки не пропускаются, иначе номера строк в отчёте разойдутся с Excel.
        $options->SHOULD_PRESERVE_EMPTY_ROWS = true;
        $reader = new Reader($options);

        try {
            $reader->open($absolutePath);
        } catch (\Throwable $e) {
            throw new InvalidSpreadsheetException('Не удалось открыть xlsx-файл: '.$e->getMessage(), 0, $e);
        }

        try {
            foreach ($reader->getSheetIterator() as $sheet) {
                $rowNumber = 0;
                /** @var Row $row */
                foreach ($sheet->getRowIterator() as $row) {
                    ++$rowNumber;
                    yield $rowNumber => $row->toArray();
                }
                break; // Импортируется только первый лист.
            }
        } catch (InvalidSpreadsheetException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new InvalidSpreadsheetException('Не удалось прочитать xlsx-файл: '.$e->getMessage(), 0, $e);
        } finally {
            $reader->close();
        }
    }

    /**
     * Заголовки колонок без лишних пробелов, чтобы «Цена: Цена продажи » совпала с ожидаемой колонкой.
     *
     * @param list<mixed> $cells
     *
     * @return list<string>
     */
    private function normalizeHeaders(array $cells): array
    {
        return array_map(static fn (mixed $c): string => \is_scalar($c) ? trim((string) $c) : '', $cells);
    }
}
