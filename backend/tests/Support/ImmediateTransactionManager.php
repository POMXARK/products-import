<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Shared\TransactionManager;

/** Выполняет операцию сразу и считает транзакции: так тест видит, что сеть не попадает внутрь транзакции. */
final class ImmediateTransactionManager implements TransactionManager
{
    public int $transactions = 0;

    public bool $inTransaction = false;

    public function transactional(callable $operation): mixed
    {
        ++$this->transactions;
        $this->inTransaction = true;
        try {
            return $operation();
        } finally {
            $this->inTransaction = false;
        }
    }

    public function clear(): void
    {
    }
}
