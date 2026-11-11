<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\Product\Product;
use App\Domain\Product\ProductFilter;
use App\Domain\Product\ProductRepository;
use App\Domain\Shared\Page;
use App\Domain\Shared\Pagination;

/** Репозиторий в памяти: проверка прикладной логики импорта без БД. */
final class InMemoryProductRepository implements ProductRepository
{
    /** @var array<string, Product> */
    public array $byCode = [];

    /** @var list<string> */
    public array $locks = [];

    public function find(int $id): ?Product
    {
        foreach ($this->byCode as $product) {
            if ($product->getId() === $id) {
                return $product;
            }
        }

        return null;
    }

    public function findByExternalCode(string $externalCode): ?Product
    {
        return $this->byCode[$externalCode] ?? null;
    }

    public function search(ProductFilter $filter, Pagination $pagination): Page
    {
        $items = array_values($this->byCode);

        return new Page(\array_slice($items, $pagination->offset(), $pagination->limit), $pagination->page, $pagination->limit, \count($items));
    }

    public function lockExternalCode(string $externalCode): void
    {
        $this->locks[] = $externalCode;
    }

    public function save(Product $product): void
    {
        $this->byCode[$product->getExternalCode()] = $product;
    }

    public function remove(Product $product): void
    {
        unset($this->byCode[$product->getExternalCode()]);
    }
}
