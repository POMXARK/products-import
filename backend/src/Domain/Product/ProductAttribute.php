<?php

declare(strict_types=1);

namespace App\Domain\Product;

/**
 * Характеристика товара из колонки «Доп. поле: <название>» (размер, цвет, бренд и т.д.).
 * Ключ уникален в пределах товара.
 */
class ProductAttribute
{
    private ?int $id = null;

    /** Ключ берётся из заголовка колонки без префикса «Доп. поле:», значение из ячейки. */
    public function __construct(
        private Product $product,
        private string $key,
        private string $value,
    ) {
    }

    /** Внутренний id характеристики. */
    public function getId(): ?int
    {
        return $this->id;
    }

    /** Товар-владелец. */
    public function getProduct(): Product
    {
        return $this->product;
    }

    /** Название характеристики, например «Размер». */
    public function getKey(): string
    {
        return $this->key;
    }

    /** Значение характеристики, например «XL». */
    public function getValue(): string
    {
        return $this->value;
    }

    /** При повторном импорте значение обновляется на месте, без удаления и вставки строки. */
    public function changeValue(string $value): void
    {
        $this->value = $value;
    }
}
