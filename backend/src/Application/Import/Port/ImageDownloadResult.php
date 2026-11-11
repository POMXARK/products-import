<?php

declare(strict_types=1);

namespace App\Application\Import\Port;

/** Результат скачивания одной картинки: путь к файлу или текст ошибки для отчёта. */
final readonly class ImageDownloadResult
{
    /** Ровно одно из полей заполнено. */
    private function __construct(
        public ?string $path,
        public ?string $error,
    ) {
    }

    /** Картинка сохранена в хранилище по указанному пути. */
    public static function success(string $path): self
    {
        return new self($path, null);
    }

    /** Скачать не удалось; ссылка остаётся у товара, ошибка попадает в отчёт как предупреждение. */
    public static function failure(string $error): self
    {
        return new self(null, $error);
    }

    /** Удалось ли получить файл. */
    public function isSuccess(): bool
    {
        return null !== $this->path;
    }
}
