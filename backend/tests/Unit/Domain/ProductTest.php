<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain;

use App\Domain\Product\ImageData;
use App\Domain\Product\Product;
use App\Domain\Shared\Money;
use PHPUnit\Framework\TestCase;

/** Повторный импорт приводит атрибуты и изображения товара к состоянию из нового файла. */
final class ProductTest extends TestCase
{
    public function testSyncAttributesUpdatesAddsAndRemovesByKey(): void
    {
        $product = new Product('SNL-1', 'Шорты', Money::parse('100'));
        $product->syncAttributes(['Размер' => '42', 'Цвет' => 'Nero']);
        $size = $product->getAttributes()[0];

        $product->syncAttributes(['Размер' => '44', 'Бренд' => 'OMSA']);

        $attributes = [];
        foreach ($product->getAttributes() as $attribute) {
            $attributes[$attribute->getKey()] = $attribute->getValue();
        }
        self::assertSame(['Размер' => '44', 'Бренд' => 'OMSA'], $attributes);
        self::assertSame($size, $product->getAttributes()[0], 'существующая строка обновляется, а не пересоздаётся');
    }

    public function testSyncImagesKeepsFileOrderAndDropsMissingUrls(): void
    {
        $product = new Product('SNL-1', 'Шорты', Money::parse('100'));
        $product->syncImages([new ImageData('https://a/1.jpg', 'images/1.jpg'), new ImageData('https://a/2.jpg', 'images/2.jpg')]);

        $product->syncImages([new ImageData('https://a/3.jpg', 'images/3.jpg'), new ImageData('https://a/1.jpg', 'images/1.jpg')]);

        $urls = array_map(static fn ($i): string => $i->getUrl(), $product->getImages());
        self::assertSame(['https://a/3.jpg', 'https://a/1.jpg'], $urls);
    }

    public function testFailedDownloadKeepsPreviouslyStoredFile(): void
    {
        $product = new Product('SNL-1', 'Шорты', Money::parse('100'));
        $product->syncImages([new ImageData('https://a/1.jpg', 'images/1.jpg')]);

        $product->syncImages([new ImageData('https://a/1.jpg', null)]);

        self::assertSame('images/1.jpg', $product->getImages()[0]->getPath());
    }

    public function testDuplicateUrlsInFileAreStoredOnce(): void
    {
        $product = new Product('SNL-1', 'Шорты', Money::parse('100'));
        $product->syncImages([new ImageData('https://a/1.jpg', null), new ImageData('https://a/1.jpg', null)]);

        self::assertCount(1, $product->getImages());
    }

    public function testUpdateChangesFieldsAndRemembersImport(): void
    {
        $created = new \DateTimeImmutable('2026-01-01');
        $product = new Product('SNL-1', 'Шорты', Money::parse('100'), $created);

        $product->update('Шорты новые', 'Описание', Money::parse('120,50'), '10.00', new \DateTimeImmutable('2026-02-01'));
        $product->markImportedBy('import-1');

        self::assertSame('Шорты новые', $product->getName());
        self::assertSame('120.50', $product->getPrice());
        self::assertSame('10.00', $product->getDiscount());
        self::assertSame('import-1', $product->getLastImportId());
        self::assertSame($created, $product->getCreatedAt());
        self::assertEquals(new \DateTimeImmutable('2026-02-01'), $product->getUpdatedAt());
    }
}
