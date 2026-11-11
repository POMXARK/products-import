<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Application\Import\Port\InvalidSpreadsheetException;
use App\Application\Import\ProductRowParser;
use App\Application\Import\RowValidationException;
use App\Domain\Import\IssueSeverity;
use PHPUnit\Framework\TestCase;

/** Разбор строки xlsx: обязательные поля, цены, атрибуты "Доп. поле", ссылки на изображения. */
final class ProductRowParserTest extends TestCase
{
    private ProductRowParser $parser;

    protected function setUp(): void
    {
        $this->parser = new ProductRowParser();
    }

    /**
     * @param array<string, string|int|float|null> $override
     *
     * @return array<string, string|int|float|null>
     */
    private function row(array $override = []): array
    {
        return $override + [
            'Внешний код' => 'SNL-1',
            'Наименование' => 'Бермуды мужские',
            'Описание' => 'Хлопок',
            'Цена: Цена продажи' => '1320,00',
            'Закупочная цена' => '960,00',
            'Доп. поле: Размер' => '46(L)',
            'Доп. поле: Цвет' => 'Nero',
            'Доп. поле: Пустое' => '  ',
            'Доп. поле: Ссылки на фото' => 'https://img/1.jpg, https://img/2.jpg',
            'Доп. поле: Ссылка на упаковку' => 'https://img/1.jpg',
            'Не доп. поле' => 'игнорируется',
        ];
    }

    public function testParsesValidRow(): void
    {
        $row = $this->parser->parse(2, $this->row());

        self::assertSame('SNL-1', $row->externalCode);
        self::assertSame(132000, $row->price->minorUnits);
        self::assertSame('27.27', $row->discount);
        self::assertSame(['Размер' => '46(L)', 'Цвет' => 'Nero'], $row->attributes);
        self::assertSame(['https://img/1.jpg', 'https://img/2.jpg'], $row->imageUrls, 'дубли ссылок схлопываются');
        self::assertSame([], $row->warnings);
    }

    public function testExternalCodeAsNumberFromCellBecomesString(): void
    {
        $row = $this->parser->parse(2, $this->row(['Внешний код' => 12345.0]));

        self::assertSame('12345', $row->externalCode);
    }

    public function testCollectsAllErrorsOfRowAtOnce(): void
    {
        try {
            $this->parser->parse(7, $this->row(['Внешний код' => null, 'Наименование' => '', 'Цена: Цена продажи' => 'abc']));
            self::fail('Ожидалась ошибка валидации');
        } catch (RowValidationException $e) {
            $fields = array_map(static fn ($i): ?string => $i->field, $e->issues);
            self::assertSame(['Внешний код', 'Наименование', 'Цена: Цена продажи'], $fields);
            self::assertSame(7, $e->issues[0]->row);
            self::assertSame(IssueSeverity::Error, $e->issues[0]->severity);
        }
    }

    public function testZeroPriceIsRejected(): void
    {
        $this->expectException(RowValidationException::class);
        $this->parser->parse(2, $this->row(['Цена: Цена продажи' => '0']));
    }

    public function testDiscountOutOfStorageRangeIsRowError(): void
    {
        $this->expectException(RowValidationException::class);
        $this->parser->parse(2, $this->row(['Цена: Цена продажи' => '0,01', 'Закупочная цена' => '100000']));
    }

    public function testMissingPurchasePriceMeansNoDiscount(): void
    {
        self::assertNull($this->parser->parse(2, $this->row(['Закупочная цена' => null]))->discount);
    }

    public function testInvalidImageLinkIsWarningNotError(): void
    {
        $row = $this->parser->parse(2, $this->row(['Доп. поле: Ссылки на фото' => 'javascript:alert(1) https://img/ok.jpg', 'Доп. поле: Ссылка на упаковку' => null]));

        self::assertSame(['https://img/ok.jpg'], $row->imageUrls);
        self::assertCount(1, $row->warnings);
        self::assertSame(IssueSeverity::Warning, $row->warnings[0]->severity);
    }

    public function testEmptyRowIsDetected(): void
    {
        self::assertTrue($this->parser->isEmpty(['a' => null, 'b' => '  ']));
        self::assertFalse($this->parser->isEmpty(['a' => null, 'b' => 'x']));
    }

    public function testFileWithoutRequiredColumnsIsRejectedAsWhole(): void
    {
        $this->expectException(InvalidSpreadsheetException::class);
        $this->expectExceptionMessage('Внешний код');
        $this->parser->assertHeaders(['Наименование', 'Цена: Цена продажи']);
    }
}
