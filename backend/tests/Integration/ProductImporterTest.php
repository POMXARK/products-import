<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Application\Import\Port\ImageDownloader;
use App\Application\Import\ProductImporter;
use App\Application\Import\ProductRowParser;
use App\Domain\Product\ProductRepository;
use App\Infrastructure\Persistence\Doctrine\EntityManagerProvider;
use App\Tests\Support\DatabaseTestCase;
use App\Tests\Support\FakeImageDownloader;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Tools\DsnParser;

/** ТЗ: upsert по external_code; товар, атрибуты и изображения в одной транзакции. */
final class ProductImporterTest extends DatabaseTestCase
{
    protected function overrides(): array
    {
        return [ImageDownloader::class => new FakeImageDownloader()];
    }

    /** @param array<string, string|null> $override */
    private function import(array $override = [], string $importId = '0190a0a0-0000-7000-8000-000000000001'): bool
    {
        $row = (new ProductRowParser())->parse(2, $override + [
            'Внешний код' => 'SNL-1',
            'Наименование' => 'Бермуды',
            'Цена: Цена продажи' => '1320,00',
            'Закупочная цена' => '960,00',
            'Доп. поле: Цвет' => 'Nero',
            'Доп. поле: Размер' => '46',
            'Доп. поле: Ссылки на фото' => 'https://img/1.jpg https://img/broken.jpg',
        ]);
        $created = $this->container->get(ProductImporter::class)->import($row, $importId)->created;
        $this->em()->clear();

        return $created;
    }

    public function testReimportUpdatesSameProductInsteadOfDuplicating(): void
    {
        self::assertTrue($this->import());
        self::assertFalse($this->import(['Наименование' => 'Бермуды v2', 'Доп. поле: Размер' => null, 'Доп. поле: Ссылки на фото' => 'https://img/1.jpg']));

        self::assertSame(1, $this->rowCount('products'));
        self::assertSame(1, $this->rowCount('product_attributes'), 'пустой атрибут удалён');
        self::assertSame(1, $this->rowCount('product_images'));
        $product = $this->container->get(ProductRepository::class)->findByExternalCode('SNL-1');
        self::assertNotNull($product);
        self::assertSame('Бермуды v2', $product->getName());
        self::assertSame('27.27', $product->getDiscount());
    }

    public function testFailedProductWriteRollsBackAttributesAndImages(): void
    {
        $this->connection()->executeStatement('ALTER TABLE product_images ADD CONSTRAINT test_reject CHECK (url NOT LIKE \'%reject%\')');
        try {
            $this->import(['Доп. поле: Ссылки на фото' => 'https://img/reject.jpg']);
            self::fail('Ожидалась ошибка записи');
        } catch (\Doctrine\DBAL\Exception) {
        } finally {
            $this->connection()->executeStatement('ALTER TABLE product_images DROP CONSTRAINT test_reject');
        }

        self::assertSame(0, $this->rowCount('products'), 'без товара не остаётся "висящих" атрибутов');
        self::assertSame(0, $this->rowCount('product_attributes'));
        self::assertTrue($this->container->get(EntityManagerProvider::class)->get()->isOpen(), 'провайдер выдаёт новый EntityManager после сбоя');
    }

    public function testAdvisoryLockSerializesConcurrentUpsertOfSameCode(): void
    {
        $repository = $this->container->get(ProductRepository::class);
        $other = DriverManager::getConnection((new DsnParser(['postgresql' => 'pdo_pgsql']))->parse((string) getenv('DATABASE_URL')));

        $this->connection()->beginTransaction();
        try {
            $repository->lockExternalCode('SNL-1');

            $lockedForOther = !$other->fetchOne("SELECT pg_try_advisory_xact_lock(hashtextextended('product:SNL-1', 0))");
            $otherCodeFree = (bool) $other->fetchOne("SELECT pg_try_advisory_xact_lock(hashtextextended('product:SNL-2', 0))");
        } finally {
            $this->connection()->rollBack();
            $other->close();
        }

        self::assertTrue($lockedForOther, 'второй воркер ждёт, пока первый не закончит upsert того же товара');
        self::assertTrue($otherCodeFree, 'другие товары не блокируются');
    }
}
