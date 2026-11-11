<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use App\Application\Shared\TransactionManager;

/** Адаптер порта транзакций на Doctrine: границы транзакции задаёт прикладной слой. */
final readonly class DoctrineTransactionManager implements TransactionManager
{
    /** Берёт актуальный EntityManager у провайдера. */
    public function __construct(private EntityManagerProvider $provider)
    {
    }

    /** Операция и flush выполняются в одной транзакции, при исключении всё откатывается. */
    public function transactional(callable $operation): mixed
    {
        return $this->provider->get()->wrapInTransaction(static fn (): mixed => $operation());
    }

    /** Очищает UnitOfWork между строками импорта, чтобы память воркера не росла. */
    public function clear(): void
    {
        $this->provider->get()->clear();
    }
}
