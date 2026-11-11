<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Application\Import\Port\FileStorage;
use App\Application\Import\Port\InvalidSpreadsheetException;
use App\Application\Import\Port\SpreadsheetReader;
use App\Application\Shared\TransactionManager;
use App\Domain\Import\ImportJob;
use App\Domain\Import\ImportJobRepository;
use Psr\Log\LoggerInterface;

/**
 * Обработчик сообщения RunImport (вызывается воркером Symfony Messenger).
 *
 * Невалидные строки не прерывают импорт и попадают в отчёт. Непригодный файл завершает
 * задачу статусом failed без повторов. Инфраструктурные ошибки (БД недоступна)
 * пробрасываются наружу, и Messenger повторяет сообщение: upsert делает повтор безопасным.
 */
final readonly class RunImportHandler
{
    /** Все зависимости приходят через порты Application и домена. */
    public function __construct(
        private ImportJobRepository $jobs,
        private SpreadsheetReader $reader,
        private ProductRowParser $parser,
        private ProductImporter $importer,
        private FileStorage $storage,
        private TransactionManager $transactions,
        private LoggerInterface $logger,
        private int $progressEveryRows = 10,
    ) {
    }

    /**
     * Переводит задачу в processing, считает строки, обрабатывает файл и завершает задачу completed.
     * Непригодный файл завершает её failed сразу, без повторов.
     */
    public function __invoke(RunImport $message): void
    {
        $job = $this->jobs->find($message->importId);
        if (null === $job) {
            $this->logger->warning('Import job not found, message dropped', ['import_id' => $message->importId]);

            return;
        }
        if ($job->getStatus()->isFinal()) {
            $this->logger->info('Import job already finished, duplicate message skipped', ['import_id' => $job->getId()]);

            return;
        }

        $job->start();
        $this->jobs->saveProgress($job);

        $path = $this->storage->absolutePath($job->getStoredPath());

        try {
            $this->parser->assertHeaders($this->reader->headers($path));
            $job->setTotalRows($this->reader->countDataRows($path));
            $this->jobs->saveProgress($job);
            $this->processRows($job, $path);
        } catch (InvalidSpreadsheetException $e) {
            $job->fail($e->getMessage());
            $this->jobs->saveProgress($job);
            $this->logger->notice('Import failed: invalid spreadsheet', ['import_id' => $job->getId(), 'reason' => $e->getMessage()]);

            return;
        }

        $job->complete();
        $this->jobs->saveProgress($job);
        $this->logger->info('Import completed', [
            'import_id' => $job->getId(),
            'created' => $job->getCreatedCount(),
            'updated' => $job->getUpdatedCount(),
            'failed' => $job->getFailedCount(),
        ]);
    }

    /**
     * Строка за строкой: парсинг, upsert товара, учёт created/updated/failed и запись ошибок в отчёт.
     * Каждая строка в своей транзакции, поэтому ошибка одной строки не откатывает остальные.
     */
    private function processRows(ImportJob $job, string $path): void
    {
        $sinceProgress = 0;

        foreach ($this->reader->rows($path) as $rowNumber => $data) {
            if ($this->parser->isEmpty($data)) {
                continue;
            }

            try {
                $row = $this->parser->parse($rowNumber, $data);
            } catch (RowValidationException $e) {
                $job->recordFailed();
                array_map($job->addIssue(...), $e->issues);
                $this->tick($job, $sinceProgress);
                continue;
            }

            $outcome = $this->importer->import($row, $job->getId());
            $outcome->created ? $job->recordCreated() : $job->recordUpdated();
            array_map($job->addIssue(...), $outcome->warnings);

            // Товар записан, сущности больше не нужны: держим память плоской на больших файлах.
            $this->transactions->clear();
            $this->tick($job, $sinceProgress);
        }
    }

    /** Каждые N строк сохраняет прогресс отдельным UPDATE: клиент видит, как растёт процент. */
    private function tick(ImportJob $job, int &$sinceProgress): void
    {
        if (++$sinceProgress >= $this->progressEveryRows) {
            $this->jobs->saveProgress($job);
            $sinceProgress = 0;
        }
    }
}
