<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger;

use App\Application\Import\RunImport;
use App\Domain\Import\ImportJobRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

/**
 * Когда Messenger исчерпал повторы, задача импорта помечается failed.
 * Без этого клиент видел бы вечный "processing" и индикатор прогресса никогда бы не завершился.
 * Само сообщение при этом уходит в очередь failed для разбора.
 */
final readonly class MarkImportFailedListener
{
    /** Работает через доменный репозиторий задач импорта. */
    public function __construct(
        private ImportJobRepository $jobs,
        private LoggerInterface $logger,
    ) {
    }

    /** Срабатывает на каждую ошибку обработки, но действует только после последней попытки. */
    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();
        if ($event->willRetry() || !$message instanceof RunImport) {
            return;
        }

        try {
            $job = $this->jobs->find($message->importId);
            if (null === $job || $job->getStatus()->isFinal()) {
                return;
            }
            $job->fail('Импорт прерван из-за внутренней ошибки, подробности в логах');
            $this->jobs->saveProgress($job);
        } catch (\Throwable $e) {
            $this->logger->error('Cannot mark import as failed', ['import_id' => $message->importId, 'exception' => $e]);
        }
    }
}
