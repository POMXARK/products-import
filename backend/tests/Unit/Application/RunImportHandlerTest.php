<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Application\Import\ProductImporter;
use App\Application\Import\ProductRowParser;
use App\Application\Import\RunImport;
use App\Application\Import\RunImportHandler;
use App\Domain\Import\ImportJob;
use App\Domain\Import\ImportStatus;
use App\Domain\Import\IssueSeverity;
use App\Tests\Support\ArraySpreadsheetReader;
use App\Tests\Support\FakeImageDownloader;
use App\Tests\Support\ImmediateTransactionManager;
use App\Tests\Support\InMemoryImportJobRepository;
use App\Tests\Support\InMemoryProductRepository;
use App\Tests\Support\NullFileStorage;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/** ТЗ: невалидные строки не прерывают импорт, а попадают в отчёт; прогресс виден во время обработки. */
final class RunImportHandlerTest extends TestCase
{
    private const string ID = '0190a0a0-0000-7000-8000-000000000001';
    private const array HEADERS = ['Внешний код', 'Наименование', 'Цена: Цена продажи', 'Закупочная цена'];

    private InMemoryImportJobRepository $jobs;
    private InMemoryProductRepository $products;

    protected function setUp(): void
    {
        $this->jobs = new InMemoryImportJobRepository();
        $this->products = new InMemoryProductRepository();
        $this->jobs->save(new ImportJob(self::ID, 'file.xlsx', 'uploads/file.xlsx'));
    }

    /** @param array<int, array<string, string|null>> $rows */
    private function handler(array $rows, ?string $brokenWith = null, int $progressEvery = 2): RunImportHandler
    {
        $transactions = new ImmediateTransactionManager();

        return new RunImportHandler(
            $this->jobs,
            new ArraySpreadsheetReader(self::HEADERS, $rows, $brokenWith),
            new ProductRowParser(),
            new ProductImporter($this->products, $transactions, new FakeImageDownloader()),
            new NullFileStorage(),
            $transactions,
            new NullLogger(),
            $progressEvery,
        );
    }

    private function job(): ImportJob
    {
        $job = $this->jobs->find(self::ID);
        self::assertNotNull($job);

        return $job;
    }

    public function testInvalidRowsGoToReportAndDoNotStopImport(): void
    {
        $handler = $this->handler([
            2 => ['Внешний код' => 'A', 'Наименование' => 'Товар A', 'Цена: Цена продажи' => '100', 'Закупочная цена' => '50'],
            3 => ['Внешний код' => null, 'Наименование' => 'Без кода', 'Цена: Цена продажи' => '100', 'Закупочная цена' => null],
            4 => ['Внешний код' => null, 'Наименование' => null, 'Цена: Цена продажи' => null, 'Закупочная цена' => null],
            5 => ['Внешний код' => 'B', 'Наименование' => 'Товар B', 'Цена: Цена продажи' => 'дорого', 'Закупочная цена' => null],
            6 => ['Внешний код' => 'A', 'Наименование' => 'Товар A, дубль ниже по файлу', 'Цена: Цена продажи' => '110', 'Закупочная цена' => null],
        ]);

        $handler(new RunImport(self::ID));

        $job = $this->job();
        self::assertSame(ImportStatus::Completed, $job->getStatus());
        self::assertSame([1, 1, 2], [$job->getCreatedCount(), $job->getUpdatedCount(), $job->getFailedCount()]);
        self::assertSame([3, 5], array_map(static fn ($i): int => $i->row, $job->getIssues()), 'пустая строка 4 пропускается молча');
        self::assertSame(IssueSeverity::Error, $job->getIssues()[0]->severity);
        self::assertSame('Товар A, дубль ниже по файлу', $this->products->byCode['A']->getName());
    }

    public function testProgressIsSavedWhileRowsAreProcessed(): void
    {
        $rows = [];
        for ($i = 2; $i <= 7; ++$i) {
            $rows[$i] = ['Внешний код' => "C{$i}", 'Наименование' => 'Товар', 'Цена: Цена продажи' => '10', 'Закупочная цена' => null];
        }

        $this->handler($rows, progressEvery: 2)(new RunImport(self::ID));

        // start + totalRows + 3 промежуточных (каждые 2 строки) + complete.
        self::assertSame(6, $this->jobs->progressSaves);
        self::assertSame(6, $this->job()->getTotalRows());
    }

    public function testBrokenFileFailsJobWithoutRetry(): void
    {
        $this->handler([], brokenWith: 'Не удалось открыть xlsx-файл')(new RunImport(self::ID));

        self::assertSame(ImportStatus::Failed, $this->job()->getStatus());
        self::assertSame('Не удалось открыть xlsx-файл', $this->job()->getFailureReason());
    }

    public function testDuplicateMessageForFinishedJobIsIgnored(): void
    {
        $this->handler([])(new RunImport(self::ID));
        $saves = $this->jobs->progressSaves;

        $this->handler([2 => ['Внешний код' => 'A', 'Наименование' => 'A', 'Цена: Цена продажи' => '1', 'Закупочная цена' => null]])(new RunImport(self::ID));

        self::assertSame($saves, $this->jobs->progressSaves);
        self::assertSame([], $this->products->byCode);
    }

    public function testUnknownJobIsDropped(): void
    {
        $this->handler([])(new RunImport('0190a0a0-0000-7000-8000-00000000ffff'));

        self::assertSame(0, $this->jobs->progressSaves);
    }
}
