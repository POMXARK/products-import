<?php

declare(strict_types=1);

namespace App\Domain\Product;

/**
 * Изображение товара: ссылка из файла и локальная копия, которую отдаёт nginx.
 * Ссылка уникальна в пределах товара.
 */
class ProductImage
{
    private ?int $id = null;

    /** Позиция задаёт порядок картинок как в ячейке файла. */
    public function __construct(
        private Product $product,
        private string $url,
        /** Путь относительно хранилища; null, если скачать не удалось (ошибка попадает в отчёт импорта). */
        private ?string $path,
        private int $position,
    ) {
    }

    /** Внутренний id изображения. */
    public function getId(): ?int
    {
        return $this->id;
    }

    /** Товар-владелец. */
    public function getProduct(): Product
    {
        return $this->product;
    }

    /** Исходная ссылка из файла. */
    public function getUrl(): string
    {
        return $this->url;
    }

    /** Путь к скачанному файлу в хранилище; null, если скачать не удалось. */
    public function getPath(): ?string
    {
        return $this->path;
    }

    /** Порядковый номер картинки в карточке. */
    public function getPosition(): int
    {
        return $this->position;
    }

    /** Повторный импорт той же ссылки обновляет позицию и, если скачивание удалось, путь к файлу. */
    public function update(?string $path, int $position): void
    {
        // Не затираем ранее скачанный файл, если текущая попытка не удалась.
        $this->path = $path ?? $this->path;
        $this->position = $position;
    }
}
