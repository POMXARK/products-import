<?php

declare(strict_types=1);

namespace App\Domain\Product;

/** Скидка по формуле из ТЗ вышла за пределы, которые можно хранить: строка импорта отклоняется с понятной причиной. */
final class DiscountOutOfRange extends \DomainException
{
}
