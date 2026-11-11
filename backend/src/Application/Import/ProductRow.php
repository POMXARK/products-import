<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Domain\Import\ImportIssue;
use App\Domain\Shared\Money;

/** Провалидированная строка файла импорта, готовая к записи: типизированные поля, атрибуты, ссылки и предупреждения. */
final readonly class ProductRow
{
    /**
     * Скидка уже посчитана парсером из цены продажи и закупочной цены.
     *
     * @param array<string, string> $attributes
     * @param list<string>          $imageUrls
     * @param list<ImportIssue>     $warnings   некритичные замечания по строке
     */
    public function __construct(
        public int $rowNumber,
        public string $externalCode,
        public string $name,
        public ?string $description,
        public Money $price,
        public ?Money $purchasePrice,
        public ?string $discount,
        public array $attributes,
        public array $imageUrls,
        public array $warnings = [],
    ) {
    }
}
