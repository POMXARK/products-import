<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Domain\Shared\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Цены из xlsx приходят строками с запятой и пробелами; копейки не должны теряться. */
final class MoneyTest extends TestCase
{
    /** @return iterable<string, array{string|int|float, int}> */
    public static function validAmounts(): iterable
    {
        yield 'запятая из файла' => ['1320,00', 132000];
        yield 'точка' => ['1320.5', 132050];
        yield 'пробел-разделитель тысяч' => ['1 320,99', 132099];
        yield 'неразрывный пробел' => ["1\u{00A0}320", 132000];
        yield 'целое число из ячейки' => [960, 96000];
        yield 'float из ячейки' => [1320.1, 132010];
        yield 'ноль' => ['0', 0];
    }

    #[DataProvider('validAmounts')]
    public function testParsesAmountsFromSpreadsheet(string|int|float $raw, int $expectedMinorUnits): void
    {
        self::assertSame($expectedMinorUnits, Money::parse($raw)->minorUnits);
    }

    /** @return iterable<string, array{string|int|float}> */
    public static function invalidAmounts(): iterable
    {
        yield 'текст' => ['abc'];
        yield 'отрицательная' => ['-10'];
        yield 'три знака после запятой' => ['10,005'];
        yield 'пусто' => [''];
        yield 'слишком большое целое' => [10_000_000_000];
        yield 'слишком длинная строка' => ['12345678901'];
    }

    #[DataProvider('invalidAmounts')]
    public function testRejectsInvalidAmounts(string|int|float $raw): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Money::parse($raw);
    }

    public function testFormatsAsDecimalForDatabase(): void
    {
        self::assertSame('1320.05', Money::fromMinorUnits(132005)->toDecimal());
        self::assertSame('0.00', Money::fromMinorUnits(0)->toDecimal());
    }
}
