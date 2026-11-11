<?php

declare(strict_types=1);

namespace App\Domain\Product;

use App\Domain\Shared\Money;

/**
 * ТЗ: discount = (Цена продажи − Закупочная цена) / Цена продажи × 100.
 * Считается в целых числах и округляется до сотых (half up), чтобы не зависеть от float.
 * Закупка дороже продажи даёт отрицательную скидку: это честный результат формулы из ТЗ.
 */
final class DiscountCalculator
{
    /** Нижняя граница, которую вмещает колонка discount DECIMAL(7,2), в сотых долях процента. */
    public const int MIN_HUNDREDTHS = -9_999_999;

    /**
     * Скидка в процентах строкой "33.33" или null, если закупочная цена не указана.
     *
     * @throws DiscountOutOfRange закупочная цена настолько выше продажной, что скидку нельзя сохранить
     */
    public function calculate(Money $salePrice, ?Money $purchasePrice): ?string
    {
        if (null === $purchasePrice || $salePrice->isZero()) {
            return null;
        }

        // Скидка в сотых долях процента: (sale - purchase) * 10000 / sale.
        $numerator = ($salePrice->minorUnits - $purchasePrice->minorUnits) * 10000;
        $sign = $numerator < 0 ? -1 : 1;
        $hundredths = intdiv(abs($numerator) * 2 + $salePrice->minorUnits, $salePrice->minorUnits * 2) * $sign;
        if ($hundredths < self::MIN_HUNDREDTHS) {
            throw new DiscountOutOfRange('Закупочная цена превышает цену продажи более чем в 1000 раз');
        }

        return \sprintf('%s%d.%02d', $hundredths < 0 ? '-' : '', intdiv(abs($hundredths), 100), abs($hundredths) % 100);
    }
}
