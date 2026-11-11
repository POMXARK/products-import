<?php

declare(strict_types=1);

namespace App\Application\Import\Port;

/** Порт хранилища загруженных файлов импорта. */
interface FileStorage
{
    /** Переносит загруженный файл в хранилище и возвращает относительный путь. */
    public function storeUpload(string $sourcePath, string $extension): string;

    /** Полный путь к файлу для чтения воркером. */
    public function absolutePath(string $relativePath): string;

    /** Удаляет файл импорта после обработки. */
    public function delete(string $relativePath): void;
}
