<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger;

use App\Application\Import\Port\ImportQueue;
use App\Application\Import\RunImport;
use Symfony\Component\Messenger\MessageBusInterface;

/** Адаптер порта ImportQueue: команда RunImport уходит через Symfony Messenger в RabbitMQ. */
final readonly class MessengerImportQueue implements ImportQueue
{
    /** Шина Messenger с маршрутизацией RunImport в транспорт async. */
    public function __construct(private MessageBusInterface $bus)
    {
    }

    /** Публикует RunImport; транспорт и ретраи настраиваются в config/container.php. */
    public function push(string $importId): void
    {
        $this->bus->dispatch(new RunImport($importId));
    }
}
