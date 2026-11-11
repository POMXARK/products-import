<?php

declare(strict_types=1);

namespace App\Domain\Shared;

/** Параметры пагинации: номер страницы с 1 и размер страницы не больше MAX_LIMIT. */
final readonly class Pagination
{
    public const int DEFAULT_LIMIT = 20;
    public const int MAX_LIMIT = 100;

    /** Неверные page и limit отклоняются сразу, чтобы в SQL не попал отрицательный OFFSET. */
    public function __construct(
        public int $page = 1,
        public int $limit = self::DEFAULT_LIMIT,
    ) {
        if ($page < 1) {
            throw new \InvalidArgumentException('page must be >= 1');
        }
        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw new \InvalidArgumentException(\sprintf('limit must be between 1 and %d', self::MAX_LIMIT));
        }
    }

    /** Смещение для SQL OFFSET. */
    public function offset(): int
    {
        return ($this->page - 1) * $this->limit;
    }
}
