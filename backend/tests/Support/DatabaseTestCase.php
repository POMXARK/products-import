<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Infrastructure\Persistence\Doctrine\EntityManagerProvider;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

/**
 * База для тестов на реальном PostgreSQL (отдельная БД products_test).
 * Схема накатывается миграциями один раз за прогон, перед каждым тестом таблицы очищаются.
 */
abstract class DatabaseTestCase extends TestCase
{
    private static bool $migrated = false;

    protected ContainerInterface $container;

    protected function setUp(): void
    {
        if (!self::$migrated) {
            exec('php '.\dirname(__DIR__, 2).'/bin/console migrations:migrate --no-interaction --quiet 2>&1', $output, $code);
            self::assertSame(0, $code, implode("\n", $output));
            self::$migrated = true;
        }

        $this->container = (require \dirname(__DIR__, 2).'/config/bootstrap.php')($this->overrides());
        $this->connection()->executeStatement(
            'TRUNCATE products, product_attributes, product_images, import_jobs, users, rate_limiter RESTART IDENTITY CASCADE',
        );
    }

    /** @return array<string, mixed> */
    protected function overrides(): array
    {
        return [];
    }

    protected function connection(): Connection
    {
        return $this->container->get(Connection::class);
    }

    protected function em(): EntityManagerInterface
    {
        return $this->container->get(EntityManagerProvider::class)->get();
    }

    protected function rowCount(string $table): int
    {
        return (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM '.$table);
    }
}
