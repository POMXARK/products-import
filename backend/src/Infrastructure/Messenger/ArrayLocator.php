<?php

declare(strict_types=1);

namespace App\Infrastructure\Messenger;

use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

/**
 * Минимальный PSR-11 локатор: Messenger ищет транспорты и стратегии повторов по имени.
 */
final readonly class ArrayLocator implements ContainerInterface
{
    /**
     * Карта "имя => сервис", собранная в контейнере.
     *
     * @param array<string, object> $services
     */
    public function __construct(private array $services)
    {
    }

    /** Возвращает сервис по имени транспорта. */
    public function get(string $id): object
    {
        return $this->services[$id] ?? throw new class(\sprintf('Service "%s" not found', $id)) extends \RuntimeException implements NotFoundExceptionInterface {};
    }

    /** Проверяет, зарегистрирован ли транспорт с таким именем. */
    public function has(string $id): bool
    {
        return isset($this->services[$id]);
    }
}
