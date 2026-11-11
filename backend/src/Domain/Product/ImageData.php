<?php

declare(strict_types=1);

namespace App\Domain\Product;

/** Изображение товара из колонки «Доп. поле: Ссылки на картинки»: исходный url и путь к скачанной копии. */
final readonly class ImageData
{
    /** path равен null, если файл скачать не удалось: ссылка сохраняется, ошибка уходит в отчёт. */
    public function __construct(
        public string $url,
        public ?string $path,
    ) {
    }
}
