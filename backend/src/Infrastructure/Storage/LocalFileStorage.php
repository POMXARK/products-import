<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use App\Application\Import\Port\FileStorage;
use Symfony\Component\Uid\Uuid;

/**
 * Локальное хранилище загруженных xlsx-файлов.
 *
 * ТЗ: импорт асинхронный, поэтому файл из запроса нужно сохранить до того, как воркер его прочитает.
 * Файл кладётся под сгенерированным именем: исходное имя от клиента в путь не попадает.
 */
final readonly class LocalFileStorage implements FileStorage
{
    /** Корень хранилища из STORAGE_PATH (общий том для app, worker и nginx). */
    public function __construct(private string $root)
    {
    }

    /** Переносит загруженный файл в uploads/YYYY/MM/<uuid>.<ext> и возвращает относительный путь. */
    public function storeUpload(string $sourcePath, string $extension): string
    {
        $relative = \sprintf('uploads/%s/%s.%s', date('Y/m'), Uuid::v7()->toRfc4122(), $extension);
        $target = $this->absolutePath($relative);
        $this->ensureDirectory(\dirname($target));

        if (!@rename($sourcePath, $target) && !@copy($sourcePath, $target)) {
            throw new \RuntimeException('Cannot store uploaded file');
        }

        return $relative;
    }

    /** Абсолютный путь внутри корня хранилища; выход за корень через ".." запрещён. */
    public function absolutePath(string $relativePath): string
    {
        if (str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            throw new \InvalidArgumentException('Invalid storage path');
        }

        return rtrim($this->root, '/').'/'.$relativePath;
    }

    /** Удаляет файл, если он есть. */
    public function delete(string $relativePath): void
    {
        $path = $this->absolutePath($relativePath);
        if (is_file($path)) {
            unlink($path);
        }
    }

    /** Создаёт каталог, если его ещё нет; гонка двух процессов обрабатывается. */
    private function ensureDirectory(string $dir): void
    {
        if (!is_dir($dir) && !@mkdir($dir, 0o775, true) && !is_dir($dir)) {
            throw new \RuntimeException(\sprintf('Cannot create directory %s', $dir));
        }
    }
}
