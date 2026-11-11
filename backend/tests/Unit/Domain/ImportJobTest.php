<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Domain\Import\ImportIssue;
use App\Domain\Import\ImportJob;
use App\Domain\Import\ImportStatus;
use App\Domain\Import\InvalidImportTransition;
use App\Domain\Import\IssueSeverity;
use PHPUnit\Framework\TestCase;

/** Жизненный цикл импорта: queued, processing, затем completed или failed без возврата из финального статуса. */
final class ImportJobTest extends TestCase
{
    private function job(): ImportJob
    {
        return new ImportJob('0190a0a0-0000-7000-8000-000000000001', 'file.xlsx', 'uploads/file.xlsx');
    }

    public function testHappyPathCountsRowsAndCompletes(): void
    {
        $job = $this->job();
        $job->start();
        $job->setTotalRows(3);
        $job->recordCreated();
        $job->recordUpdated();
        $job->recordFailed();
        $job->complete();

        self::assertSame(ImportStatus::Completed, $job->getStatus());
        self::assertSame(3, $job->getProcessedRows());
        self::assertSame([1, 1, 1], [$job->getCreatedCount(), $job->getUpdatedCount(), $job->getFailedCount()]);
        self::assertNotNull($job->getFinishedAt());
    }

    public function testRedeliveredMessageRestartsCountersFromScratch(): void
    {
        $job = $this->job();
        $job->start();
        $job->recordCreated();
        $job->addIssue(new ImportIssue(2, IssueSeverity::Error, 'ошибка'));

        $job->start();

        self::assertSame(0, $job->getProcessedRows());
        self::assertSame([], $job->getIssues());
    }

    public function testCompletedImportCannotBeRestarted(): void
    {
        $job = $this->job();
        $job->start();
        $job->complete();

        $this->expectException(InvalidImportTransition::class);
        $job->start();
    }

    public function testCompletedImportCannotBeFailed(): void
    {
        $job = $this->job();
        $job->start();
        $job->complete();

        $this->expectException(InvalidImportTransition::class);
        $job->fail('поздняя ошибка');
    }

    public function testCannotCompleteImportThatWasNotStarted(): void
    {
        $this->expectException(InvalidImportTransition::class);
        $this->job()->complete();
    }

    public function testCannotCountRowsOutsideProcessing(): void
    {
        $this->expectException(InvalidImportTransition::class);
        $this->job()->recordCreated();
    }

    public function testQueuedImportCanFailWhenBrokerIsDown(): void
    {
        $job = $this->job();
        $job->fail('Не удалось поставить импорт в очередь');

        self::assertSame(ImportStatus::Failed, $job->getStatus());
        self::assertSame('Не удалось поставить импорт в очередь', $job->getFailureReason());
    }

    public function testReportIsCappedToKeepJobRowSmall(): void
    {
        $job = $this->job();
        $job->start();
        for ($i = 0; $i < ImportJob::MAX_ISSUES + 5; ++$i) {
            $job->addIssue(new ImportIssue($i, IssueSeverity::Warning, 'w'));
        }

        self::assertCount(ImportJob::MAX_ISSUES, $job->getIssues());
        self::assertTrue($job->isIssuesTruncated());
    }
}
