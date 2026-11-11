<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Domain\Import\ImportJob;
use App\Domain\Import\ImportJobRepository;

/** Хранилище задач импорта в памяти; считает вызовы saveProgress, чтобы проверить частоту обновления прогресса. */
final class InMemoryImportJobRepository implements ImportJobRepository
{
    /** @var array<string, ImportJob> */
    public array $jobs = [];

    public int $progressSaves = 0;

    public function find(string $id): ?ImportJob
    {
        return $this->jobs[$id] ?? null;
    }

    public function latest(int $limit): array
    {
        return \array_slice(array_values($this->jobs), 0, $limit);
    }

    public function save(ImportJob $job): void
    {
        $this->jobs[$job->getId()] = $job;
    }

    public function saveProgress(ImportJob $job): void
    {
        ++$this->progressSaves;
        $this->jobs[$job->getId()] = $job;
    }
}
