<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Application\Import\Port\FileStorage;
use App\Application\Import\Port\ImportQueue;
use App\Domain\Import\ImportJob;
use App\Domain\Import\ImportJobRepository;
use Symfony\Component\Uid\Uuid;

/** Ставит импорт в очередь: сохраняет файл, создаёт задачу и отправляет сообщение воркеру. */
final readonly class ImportService
{
    /** Хранилище файлов, задачи и очередь передаются как порты, конкретные реализации в Infrastructure. */
    public function __construct(
        private FileStorage $storage,
        private ImportJobRepository $jobs,
        private ImportQueue $queue,
    ) {
    }

    /**
     * Принимает уже провалидированный xlsx: сохраняет его, создаёт задачу queued и отправляет в очередь.
     * Ответ 202 уходит сразу, обработка идёт в воркере. Если брокер недоступен, задача помечается failed.
     */
    public function enqueue(string $uploadedFilePath, string $originalFilename, ?int $userId): ImportJob
    {
        $storedPath = $this->storage->storeUpload($uploadedFilePath, 'xlsx');
        $job = new ImportJob(Uuid::v7()->toRfc4122(), mb_substr($originalFilename, 0, 255), $storedPath, $userId);
        $this->jobs->save($job);

        try {
            $this->queue->push($job->getId());
        } catch (\Throwable $e) {
            // Брокер недоступен: задача не должна навсегда зависнуть в статусе queued.
            $job->fail('Не удалось поставить импорт в очередь');
            $this->jobs->saveProgress($job);

            throw $e;
        }

        return $job;
    }
}
