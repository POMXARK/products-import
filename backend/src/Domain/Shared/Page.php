<?php

declare(strict_types=1);

namespace App\Domain\Shared;

/**
 * Страница результатов с метаданными пагинации для списка товаров.
 *
 * @template T
 */
final readonly class Page
{
    /**
     * Элементы текущей страницы, номер, размер и общее число найденных записей.
     *
     * @param list<T> $items
     */
    public function __construct(
        public array $items,
        public int $page,
        public int $limit,
        public int $total,
    ) {
    }

    /** Число страниц для пагинатора на клиенте; при пустом результате 0. */
    public function totalPages(): int
    {
        return 0 === $this->total ? 0 : (int) ceil($this->total / $this->limit);
    }
}
