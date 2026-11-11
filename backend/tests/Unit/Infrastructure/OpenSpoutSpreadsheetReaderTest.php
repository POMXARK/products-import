<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure;

use App\Application\Import\Port\InvalidSpreadsheetException;
use App\Application\Import\ProductRowParser;
use App\Infrastructure\Xlsx\OpenSpoutSpreadsheetReader;
use PHPUnit\Framework\TestCase;

/** Чтение файла-примера из ТЗ: 40 товаров, цены строками с запятой, атрибуты "Доп. поле". */
final class OpenSpoutSpreadsheetReaderTest extends TestCase
{
    private const string SAMPLE = __DIR__.'/../../Fixtures/import_example.xlsx';

    public function testReadsSampleFileFromTask(): void
    {
        $reader = new OpenSpoutSpreadsheetReader();

        $headers = $reader->headers(self::SAMPLE);
        (new ProductRowParser())->assertHeaders($headers);
        self::assertSame(40, $reader->countDataRows(self::SAMPLE));

        $rows = iterator_to_array($reader->rows(self::SAMPLE));
        self::assertSame(2, array_key_first($rows), 'ключ равен номеру строки в Excel');
        $first = $rows[2];
        self::assertNotEmpty($first['Внешний код']);
        self::assertNotEmpty($first['Наименование']);

        $parser = new ProductRowParser();
        $parsed = $parser->parse(2, $first);
        self::assertGreaterThan(0, $parsed->price->minorUnits);
        self::assertNotEmpty($parsed->attributes);
        self::assertNotEmpty($parsed->imageUrls);
    }

    public function testNonXlsxFileIsRejectedAsInvalidSpreadsheet(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'bad');
        file_put_contents($path, 'not a zip');

        try {
            $this->expectException(InvalidSpreadsheetException::class);
            (new OpenSpoutSpreadsheetReader())->headers($path);
        } finally {
            unlink($path);
        }
    }
}
