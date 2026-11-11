<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\Configuration;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Даёт текущий EntityManager и умеет пересоздать его.
 *
 * Doctrine закрывает EntityManager после исключения во flush. В долгоживущем воркере
 * без пересоздания все следующие сообщения падали бы с "EntityManager is closed".
 */
final class EntityManagerProvider
{
    private ?EntityManagerInterface $entityManager = null;

    /** Соединение и конфигурация ORM: из них создаётся новый EntityManager, если прежний закрыт. */
    public function __construct(
        private readonly Connection $connection,
        private readonly Configuration $configuration,
    ) {
    }

    /** Текущий EntityManager; закрытый после ошибки заменяется новым. */
    public function get(): EntityManagerInterface
    {
        if (null === $this->entityManager || !$this->entityManager->isOpen()) {
            $this->entityManager = new EntityManager($this->connection, $this->configuration);
        }

        return $this->entityManager;
    }

    /** Соединение DBAL для advisory-блокировок и прямых UPDATE прогресса. */
    public function connection(): Connection
    {
        return $this->connection;
    }
}
