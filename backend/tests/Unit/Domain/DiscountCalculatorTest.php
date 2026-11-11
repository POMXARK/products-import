<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Domain\Product\DiscountCalculator;
use App\Domain\Product\DiscountOutOfRange;
use App\Domain\Shared\Money;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Формула из ТЗ: discount = (Цена продажи − Закупочная цена) / Цена продажи × 100. */
final class DiscountCalculatorTest extends TestCase
{
    /** @return iterable<string, array{string, ?string, ?string}> */
    public static function cases(): iterable
    {
        yield 'строка из примера файла' => ['1320,00', '960,00', '27.27'];
        yield 'округление вверх' => ['3', '1', '66.67'];
        yield 'без скидки' => ['100', '100', '0.00'];
        yield 'закупка дороже продажи' => ['100', '150', '-50.00'];
        yield 'закупка бесплатна' => ['100', '0', '100.00'];
        yield 'нет закупочной цены' => ['100', null, null];
    }

    #[DataProvider('cases')]
    public function testCalculatesDiscountBySpecFormula(string $sale, ?string $purchase, ?string $expected): void
    {
        $discount = (new DiscountCalculator())->calculate(Money::parse($sale), null === $purchase ? null : Money::parse($purchase));

        self::assertSame($expected, $discount);
    }

    public function testRejectsDiscountThatDoesNotFitStorage(): void
    {
        $this->expectException(DiscountOutOfRange::class);
        (new DiscountCalculator())->calculate(Money::parse('0,01'), Money::parse('100000'));
    }
}
