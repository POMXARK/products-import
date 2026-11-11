<?php

declare(strict_types=1);

namespace App\Application\Product;

use App\Application\Product\View\ProductView;
use App\Domain\Product\ProductFilter;
use App\Domain\Product\ProductRepository;
use App\Domain\Shared\NotFoundException;
use App\Domain\Shared\Page;
use App\Domain\Shared\Pagination;

/** Чтение каталога для API: список с фильтрами и пагинацией, карточка товара. */
final readonly class ProductQueryService
{
    /** Префикс публичного URL хранилища нужен, чтобы отдать ссылки на скачанные картинки. */
    public function __construct(
        private ProductRepository $products,
        private string $publicStoragePrefix,
    ) {
    }

    /**
     * Список товаров по ТЗ: фильтры по названию и цене, пагинация, лёгкие элементы без атрибутов.
     *
     * @return Page<ProductView>
     */
    public function list(ProductFilter $filter, Pagination $pagination): Page
    {
        $page = $this->products->search($filter, $pagination);

        return new Page(
            array_map(fn ($p): ProductView => ProductView::summary($p, $this->publicStoragePrefix), $page->items),
            $page->page,
            $page->limit,
            $page->total,
        );
    }

    /** Карточка товара со всеми атрибутами, изображениями и id последнего импорта. */
    public function get(int $id): ProductView
    {
        $product = $this->products->find($id) ?? throw new NotFoundException(\sprintf('Product %d not found', $id));

        return ProductView::full($product, $this->publicStoragePrefix);
    }
}
