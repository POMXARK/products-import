<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger;

use App\Infrastructure\Persistence\Doctrine\EntityManagerProvider;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Middleware\MiddlewareInterface;
use Symfony\Component\Messenger\Middleware\StackInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

/**
 * Готовит Doctrine к обработке сообщения в долгоживущем воркере.
 *
 * - проверяет соединение: PostgreSQL мог закрыть его за время простоя, DBAL переподключится сам;
 * - пересоздаёт EntityManager, если прошлый импорт его закрыл исключением;
 * - очищает UnitOfWork после сообщения, чтобы импорты не делили загруженные сущности.
 */
final readonly class DoctrineConnectionMiddleware implements MiddlewareInterface
{
    /** Провайдер, умеющий пересоздать закрытый EntityManager. */
    public function __construct(private EntityManagerProvider $provider)
    {
    }

    /** Применяется только к сообщениям, полученным воркером из очереди. */
    public function handle(Envelope $envelope, StackInterface $stack): Envelope
    {
        if (null === $envelope->last(ReceivedStamp::class)) {
            return $stack->next()->handle($envelope, $stack);
        }

        $connection = $this->provider->connection();
        try {
            $connection->executeQuery($connection->getDatabasePlatform()->getDummySelectSQL());
        } catch (\Throwable) {
            $connection->close();
        }

        $this->provider->get();

        try {
            return $stack->next()->handle($envelope, $stack);
        } finally {
            $this->provider->get()->clear();
        }
    }
}
