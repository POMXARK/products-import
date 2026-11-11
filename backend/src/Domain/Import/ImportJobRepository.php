<?php

declare(strict_types=1);

namespace App\Domain\Import;

/** Хранилище задач импорта (порт домена, реализация в Infrastructure). */
interface ImportJobRepository
{
    /** Задача по UUID; null, если её нет. Нужна для опроса статуса и для воркера. */
    public function find(string $id): ?ImportJob;

    /**
     * Последние импорты, новые первыми: история загрузок на странице импорта.
     *
     * @return list<ImportJob>
     */
    public function latest(int $limit): array;

    /** Сохраняет задачу целиком: создание при загрузке и смена статуса. */
    public function save(ImportJob $job): void;

    /**
     * Фиксирует прогресс и отчёт отдельно от транзакций товаров,
     * чтобы клиент видел движение во время длинного импорта.
     */
    public function saveProgress(ImportJob $job): void;
}
