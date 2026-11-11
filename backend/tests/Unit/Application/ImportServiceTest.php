<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Application\Import\ImportService;
use App\Application\Import\Port\ImportQueue;
use App\Domain\Import\ImportStatus;
use App\Tests\Support\InMemoryImportJobRepository;
use App\Tests\Support\NullFileStorage;
use PHPUnit\Framework\TestCase;

/** Загрузка файла создаёт задачу импорта и отправляет её в очередь. */
final class ImportServiceTest extends TestCase
{
    public function testCreatesQueuedJobAndPushesItToQueue(): void
    {
        $jobs = new InMemoryImportJobRepository();
        $queue = new class implements ImportQueue {
            /** @var list<string> */
            public array $pushed = [];

            public function push(string $importId): void
            {
                $this->pushed[] = $importId;
            }
        };

        $job = (new ImportService(new NullFileStorage(), $jobs, $queue))->enqueue('/tmp/php123', 'товары.xlsx', 7);

        self::assertSame(ImportStatus::Queued, $job->getStatus());
        self::assertSame([$job->getId()], $queue->pushed);
        self::assertSame(7, $job->getCreatedBy());
        self::assertSame('товары.xlsx', $job->getOriginalFilename());
    }

    public function testJobIsFailedWhenBrokerIsUnavailable(): void
    {
        $jobs = new InMemoryImportJobRepository();
        $queue = new class implements ImportQueue {
            public function push(string $importId): void
            {
                throw new \RuntimeException('connection refused');
            }
        };

        try {
            (new ImportService(new NullFileStorage(), $jobs, $queue))->enqueue('/tmp/php123', 'a.xlsx', null);
            self::fail('Ожидалось исключение брокера');
        } catch (\RuntimeException) {
        }

        $job = array_values($jobs->jobs)[0];
        self::assertSame(ImportStatus::Failed, $job->getStatus(), 'задача не должна навсегда остаться в queued');
    }
}
