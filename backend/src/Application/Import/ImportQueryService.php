<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Domain\Import\ImportJob;
use App\Domain\Import\ImportJobRepository;
use App\Domain\Shared\NotFoundException;

/** Чтение задач импорта для API: опрос статуса и история загрузок. */
final readonly class ImportQueryService
{
    /** Работает через порт хранилища задач. */
    public function __construct(private ImportJobRepository $jobs)
    {
    }

    /**
     * Карточка задачи с отчётом: её опрашивает клиент, пока импорт не завершится.
     *
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        $job = $this->jobs->find($id) ?? throw new NotFoundException(\sprintf('Import %s not found', $id));

        return ImportJobView::fromJob($job);
    }

    /**
     * История импортов без отчётов, чтобы список оставался лёгким.
     *
     * @return list<array<string, mixed>>
     */
    public function latest(int $limit): array
    {
        return array_map(static fn (ImportJob $j): array => ImportJobView::fromJob($j, false), $this->jobs->latest($limit));
    }
}
