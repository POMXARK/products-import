<?php

declare(strict_types=1);

namespace App\Domain\Product;

use App\Domain\Shared\Money;

/** Фильтры списка товаров из ТЗ: поиск по названию и диапазон цены. */
final readonly class ProductFilter
{
    /** name ищется без учёта регистра по подстроке; границы цены включительные, любую можно не задавать. */
    public function __construct(
        public ?string $name = null,
        public ?Money $priceMin = null,
        public ?Money $priceMax = null,
    ) {
    }
}
