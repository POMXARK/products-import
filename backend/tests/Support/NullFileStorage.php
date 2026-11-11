<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Import\Port\FileStorage;

/** Хранилище-заглушка: путь возвращается как есть, файлы не трогаются. */
final class NullFileStorage implements FileStorage
{
    public function storeUpload(string $sourcePath, string $extension): string
    {
        return 'uploads/test.'.$extension;
    }

    public function absolutePath(string $relativePath): string
    {
        return '/tmp/'.$relativePath;
    }

    public function delete(string $relativePath): void
    {
    }
}
