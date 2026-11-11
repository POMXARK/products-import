<?php

declare(strict_types=1);

namespace App\Application\Import\Port;

/**
 * ТЗ: "импорт асинхронный (Symfony Messenger + RabbitMQ)".
 * Порт очереди: прикладной слой знает только "поставить импорт в очередь", а не про Messenger или брокер.
 */
interface ImportQueue
{
    /** Отправляет задачу импорта воркеру; бросает исключение, если брокер недоступен. */
    public function push(string $importId): void;
}
