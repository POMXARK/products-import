<?php

declare(strict_types=1);

namespace App\Application\Shared;

/** Порт транзакций: прикладной слой управляет границами транзакции, не зная о Doctrine. */
interface TransactionManager
{
    /**
     * Выполняет операцию в одной транзакции: товар, атрибуты и изображения пишутся вместе или откатываются вместе.
     *
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function transactional(callable $operation): mixed;

    /** Отпускает загруженные сущности: длинный импорт не должен копить их в памяти. */
    public function clear(): void;
}
