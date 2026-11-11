<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Application\Import\Port\ImageDownloader;
use App\Application\Shared\TransactionManager;
use App\Domain\Import\ImportIssue;
use App\Domain\Import\IssueSeverity;
use App\Domain\Product\ImageData;
use App\Domain\Product\Product;
use App\Domain\Product\ProductRepository;

/**
 * Upsert одного товара по external_code.
 *
 * Изображения скачиваются до открытия транзакции: сетевые запросы внутри транзакции
 * держали бы соединение и блокировки на время загрузки. Товар, атрибуты и изображения
 * записываются одной транзакцией, поэтому товар не остаётся без атрибутов при сбое.
 */
final readonly class ProductImporter
{
    /** Работает через порты хранилища товаров, транзакций и загрузчика изображений. */
    public function __construct(
        private ProductRepository $products,
        private TransactionManager $transactions,
        private ImageDownloader $images,
    ) {
    }

    /**
     * Записывает строку: создаёт товар с новым external_code или обновляет существующий,
     * синхронизируя атрибуты и изображения с файлом. Ошибки скачивания картинок не отменяют запись товара.
     */
    public function import(ProductRow $row, string $importId): ImportOutcome
    {
        $warnings = $row->warnings;
        $imageData = [];
        foreach ($this->images->downloadAll($row->imageUrls) as $url => $result) {
            $imageData[] = new ImageData($url, $result->path);
            if (!$result->isSuccess()) {
                $warnings[] = new ImportIssue(
                    $row->rowNumber,
                    IssueSeverity::Warning,
                    \sprintf('Не удалось скачать изображение %s: %s', $url, $result->error),
                    $row->externalCode,
                    'image',
                );
            }
        }

        $created = $this->transactions->transactional(function () use ($row, $importId, $imageData): bool {
            // Блокировка по external_code закрывает гонку двух параллельных импортов одного товара.
            $this->products->lockExternalCode($row->externalCode);

            $product = $this->products->findByExternalCode($row->externalCode);
            $created = null === $product;
            $product ??= new Product($row->externalCode, $row->name, $row->price);

            $product->update($row->name, $row->description, $row->price, $row->discount);
            $product->syncAttributes($row->attributes);
            $product->syncImages($imageData);
            $product->markImportedBy($importId);

            $this->products->save($product);

            return $created;
        });

        return new ImportOutcome($created, $warnings);
    }
}
