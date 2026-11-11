<?php

declare(strict_types=1);

namespace App\Domain\Import;

/**
 * Жизненный цикл импорта: queued, processing, completed или failed.
 * Переходы проверяет ImportJob, финальные статусы не меняются.
 */
enum ImportStatus: string
{
    /** Файл принят и ждёт воркера в очереди RabbitMQ. */
    case Queued = 'queued';
    /** Воркер читает файл и записывает товары. */
    case Processing = 'processing';
    /** Файл обработан, результат и отчёт доступны. */
    case Completed = 'completed';
    /** Импорт прерван целиком, причина в failureReason. */
    case Failed = 'failed';

    /** Завершённый импорт больше не меняет статус: опрос прогресса на клиенте можно остановить. */
    public function isFinal(): bool
    {
        return self::Completed === $this || self::Failed === $this;
    }
}
