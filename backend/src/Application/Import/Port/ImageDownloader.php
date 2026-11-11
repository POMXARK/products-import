<?php

declare(strict_types=1);

namespace App\Application\Import\Port;

/** Порт скачивания изображений товаров по ссылкам из файла импорта. */
interface ImageDownloader
{
    /**
     * Скачивает изображения параллельно. Ошибка по одной ссылке не прерывает остальные.
     *
     * @param list<string> $urls
     *
     * @return array<string, ImageDownloadResult> url => результат
     */
    public function downloadAll(array $urls): array;
}
