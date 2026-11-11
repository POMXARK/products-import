<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Domain\Import\ImportIssue;
use App\Domain\Import\ImportJob;

/** Представление задачи импорта для API: статус, процент прогресса, счётчики и отчёт. */
final readonly class ImportJobView
{
    /**
     * Прогресс считается от обработанных строк и не достигает 100, пока импорт не завершён,
     * чтобы клиент не прекратил опрос раньше времени. Отчёт отдаётся только в карточке задачи, в истории его нет.
     *
     * @return array<string, mixed>
     */
    public static function fromJob(ImportJob $job, bool $withIssues = true): array
    {
        $total = $job->getTotalRows();
        $progress = match (true) {
            $job->getStatus()->isFinal() => 100,
            null === $total || 0 === $total => 0,
            default => (int) min(99, floor($job->getProcessedRows() * 100 / $total)),
        };

        $data = [
            'id' => $job->getId(),
            'status' => $job->getStatus()->value,
            'filename' => $job->getOriginalFilename(),
            'progress' => $progress,
            'totalRows' => $total,
            'processedRows' => $job->getProcessedRows(),
            'createdCount' => $job->getCreatedCount(),
            'updatedCount' => $job->getUpdatedCount(),
            'failedCount' => $job->getFailedCount(),
            'failureReason' => $job->getFailureReason(),
            'createdAt' => $job->getCreatedAt()->format(\DATE_ATOM),
            'startedAt' => $job->getStartedAt()?->format(\DATE_ATOM),
            'finishedAt' => $job->getFinishedAt()?->format(\DATE_ATOM),
        ];

        if ($withIssues) {
            $data['issues'] = array_map(static fn (ImportIssue $i): array => $i->toArray(), $job->getIssues());
            $data['issuesTruncated'] = $job->isIssuesTruncated();
        }

        return $data;
    }
}
