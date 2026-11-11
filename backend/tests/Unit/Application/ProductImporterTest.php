<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application;

use App\Application\Import\ProductImporter;
use App\Application\Import\ProductRowParser;
use App\Tests\Support\FakeImageDownloader;
use App\Tests\Support\ImmediateTransactionManager;
use App\Tests\Support\InMemoryProductRepository;
use PHPUnit\Framework\TestCase;

/** Upsert товара по external_code: одна транзакция на товар, скачивание изображений вне её. */
final class ProductImporterTest extends TestCase
{
    private InMemoryProductRepository $products;
    private ImmediateTransactionManager $transactions;
    private FakeImageDownloader $images;
    private ProductImporter $importer;

    protected function setUp(): void
    {
        $this->products = new InMemoryProductRepository();
        $this->transactions = new ImmediateTransactionManager();
        $this->images = new FakeImageDownloader($this->transactions);
        $this->importer = new ProductImporter($this->products, $this->transactions, $this->images);
    }

    /** @param array<string, string|null> $override */
    private function row(array $override = []): \App\Application\Import\ProductRow
    {
        return (new ProductRowParser())->parse(2, $override + [
            'Внешний код' => 'SNL-1',
            'Наименование' => 'Бермуды',
            'Цена: Цена продажи' => '100',
            'Закупочная цена' => '50',
            'Доп. поле: Цвет' => 'Nero',
            'Доп. поле: Ссылки на фото' => 'https://img/1.jpg https://img/broken.jpg',
        ]);
    }

    public function testCreatesProductThenUpdatesItOnReimport(): void
    {
        $first = $this->importer->import($this->row(), 'import-1');
        $second = $this->importer->import($this->row(['Наименование' => 'Бермуды v2', 'Доп. поле: Цвет' => 'Bordo']), 'import-2');

        self::assertTrue($first->created);
        self::assertFalse($second->created);
        self::assertCount(1, $this->products->byCode);
        $product = $this->products->byCode['SNL-1'];
        self::assertSame('Бермуды v2', $product->getName());
        self::assertSame('Bordo', $product->getAttributes()[0]->getValue());
        self::assertSame('import-2', $product->getLastImportId());
        self::assertSame('50.00', $product->getDiscount());
    }

    public function testDownloadsImagesOutsideTransactionAndLocksCode(): void
    {
        $this->importer->import($this->row(), 'import-1');

        self::assertFalse($this->images->calledInsideTransaction, 'сеть внутри транзакции держит соединение и блокировки');
        self::assertSame(1, $this->transactions->transactions, 'товар, атрибуты и изображения пишутся одной транзакцией');
        self::assertSame(['SNL-1'], $this->products->locks);
    }

    public function testFailedImageBecomesWarningAndIsKeptWithoutFile(): void
    {
        $outcome = $this->importer->import($this->row(), 'import-1');

        self::assertCount(1, $outcome->warnings);
        self::assertStringContainsString('broken.jpg', $outcome->warnings[0]->message);
        $images = $this->products->byCode['SNL-1']->getImages();
        self::assertCount(2, $images);
        self::assertNotNull($images[0]->getPath());
        self::assertNull($images[1]->getPath());
    }
}
