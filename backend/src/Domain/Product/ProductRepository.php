<?php

declare(strict_types=1);

namespace App\Domain\Product;

use App\Domain\Shared\Page;
use App\Domain\Shared\Pagination;

/** Хранилище товаров (порт домена, реализация через Doctrine в Infrastructure). */
interface ProductRepository
{
    /** Товар по id для карточки; null, если его нет. */
    public function find(int $id): ?Product;

    /** Товар по внешнему коду: импорт по нему решает, создать или обновить. */
    public function findByExternalCode(string $externalCode): ?Product;

    /**
     * Страница списка товаров с фильтрами по названию и цене, порядок по id.
     *
     * @return Page<Product>
     */
    public function search(ProductFilter $filter, Pagination $pagination): Page;

    /** Блокировка на время текущей транзакции, сериализует upsert одного external_code. */
    public function lockExternalCode(string $externalCode): void;

    /** Сохраняет новый или изменённый товар вместе с атрибутами и изображениями. */
    public function save(Product $product): void;

    /** Удаляет товар вместе с атрибутами и изображениями. */
    public function remove(Product $product): void;
}
