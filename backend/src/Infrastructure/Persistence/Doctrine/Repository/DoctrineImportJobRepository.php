<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Import\ImportIssue;
use App\Domain\Import\ImportJob;
use App\Domain\Import\ImportJobRepository;
use App\Infrastructure\Persistence\Doctrine\EntityManagerProvider;
use Doctrine\DBAL\Types\Types;

/** Хранилище задач импорта в таблице import_jobs. */
final readonly class DoctrineImportJobRepository implements ImportJobRepository
{
    /** Работает через провайдер EntityManager. */
    public function __construct(private EntityManagerProvider $provider)
    {
    }

    /** Задача по UUID с принудительным обновлением из БД: прогресс мог записать воркер. */
    public function find(string $id): ?ImportJob
    {
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id)) {
            return null;
        }

        $em = $this->provider->get();
        // Прогресс пишется мимо UnitOfWork, поэтому всегда читаем свежую строку.
        $job = $em->find(ImportJob::class, $id);
        if (null !== $job) {
            $em->refresh($job);
        }

        return $job;
    }

    /** История импортов, новые первыми. */
    public function latest(int $limit): array
    {
        /** @var list<ImportJob> */
        return $this->provider->get()->createQueryBuilder()
            ->select('j')
            ->from(ImportJob::class, 'j')
            ->orderBy('j.createdAt', 'DESC')
            // created_at с точностью до секунды; UUIDv7 монотонен по времени и делает порядок детерминированным.
            ->addOrderBy('j.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /** Сохраняет задачу через UnitOfWork. */
    public function save(ImportJob $job): void
    {
        $em = $this->provider->get();
        $em->persist($job);
        $em->flush();
    }

    /**
     * Прогресс и отчёт пишутся прямым UPDATE в автокоммите: клиент видит их сразу,
     * не дожидаясь транзакций товаров.
     */
    public function saveProgress(ImportJob $job): void
    {
        $this->provider->connection()->update('import_jobs', [
            'status' => $job->getStatus()->value,
            'total_rows' => $job->getTotalRows(),
            'processed_rows' => $job->getProcessedRows(),
            'created_count' => $job->getCreatedCount(),
            'updated_count' => $job->getUpdatedCount(),
            'failed_count' => $job->getFailedCount(),
            'issues' => array_map(static fn (ImportIssue $i): array => $i->toArray(), $job->getIssues()),
            'issues_truncated' => $job->isIssuesTruncated(),
            'failure_reason' => $job->getFailureReason(),
            'started_at' => $job->getStartedAt(),
            'finished_at' => $job->getFinishedAt(),
        ], ['id' => $job->getId()], [
            'total_rows' => Types::INTEGER,
            'issues' => Types::JSON,
            'issues_truncated' => Types::BOOLEAN,
            'started_at' => Types::DATETIME_IMMUTABLE,
            'finished_at' => Types::DATETIME_IMMUTABLE,
        ]);
    }
}
