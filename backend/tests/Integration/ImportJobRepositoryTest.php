<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Domain\Import\ImportIssue;
use App\Domain\Import\ImportJob;
use App\Domain\Import\ImportJobRepository;
use App\Domain\Import\ImportStatus;
use App\Domain\Import\IssueSeverity;
use App\Tests\Support\DatabaseTestCase;

/** Статус и отчёт импорта сохраняются так, чтобы их видел клиент, опрашивающий прогресс. */
final class ImportJobRepositoryTest extends DatabaseTestCase
{
    private const string ID = '0190a0a0-0000-7000-8000-000000000001';

    public function testProgressAndReportArePersisted(): void
    {
        $jobs = $this->container->get(ImportJobRepository::class);
        $job = new ImportJob(self::ID, 'file.xlsx', 'uploads/file.xlsx');
        $jobs->save($job);

        $job->start();
        $job->setTotalRows(2);
        $job->recordCreated();
        $job->recordFailed();
        $job->addIssue(new ImportIssue(3, IssueSeverity::Error, 'Не заполнен внешний код', null, 'Внешний код'));
        $jobs->saveProgress($job);
        $this->em()->clear();

        $loaded = $jobs->find(self::ID);
        self::assertNotNull($loaded);
        self::assertSame(ImportStatus::Processing, $loaded->getStatus());
        self::assertSame(2, $loaded->getProcessedRows());
        self::assertEquals([new ImportIssue(3, IssueSeverity::Error, 'Не заполнен внешний код', null, 'Внешний код')], $loaded->getIssues());
    }

    public function testInvalidIdIsNotFoundInsteadOfDatabaseError(): void
    {
        self::assertNull($this->container->get(ImportJobRepository::class)->find('not-a-uuid'));
    }

    public function testLatestReturnsNewestFirst(): void
    {
        $jobs = $this->container->get(ImportJobRepository::class);
        $jobs->save(new ImportJob(self::ID, 'old.xlsx', 'a', null, new \DateTimeImmutable('-1 hour')));
        $jobs->save(new ImportJob('0190a0a0-0000-7000-8000-000000000002', 'new.xlsx', 'b'));

        self::assertSame(['new.xlsx', 'old.xlsx'], array_map(static fn (ImportJob $j): string => $j->getOriginalFilename(), $jobs->latest(10)));
    }
}
