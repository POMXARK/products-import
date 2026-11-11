<?php

declare(strict_types=1);

namespace App\Application\Product\View;

use App\Domain\Product\Product;

/** Представление товара для API: сущность наружу не отдаётся. */
final readonly class ProductView implements \JsonSerializable
{
    /**
     * Только данные для ответа API, без ссылок на сущности ORM.
     *
     * @param list<array{id: int|null, key: string, value: string}>                     $attributes
     * @param list<array{id: int|null, url: string, path: ?string, publicUrl: ?string}> $images
     */
    private function __construct(
        public int $id,
        public string $externalCode,
        public string $name,
        public ?string $description,
        public string $price,
        public ?string $discount,
        public ?string $lastImportId,
        public string $createdAt,
        public string $updatedAt,
        public ?array $attributes,
        public ?array $images,
        public ?string $previewUrl,
    ) {
    }

    /** Элемент списка: основные поля и превью первой картинки. */
    public static function summary(Product $product, string $publicStoragePrefix): self
    {
        $preview = null;
        foreach ($product->getImages() as $image) {
            if (null !== $image->getPath()) {
                $preview = $publicStoragePrefix.$image->getPath();
                break;
            }
        }

        return self::build($product, null, null, $preview);
    }

    /** Карточка товара: атрибуты, все изображения и lastImportId для показа прогресса импорта. */
    public static function full(Product $product, string $publicStoragePrefix): self
    {
        $attributes = array_map(static fn ($a): array => [
            'id' => $a->getId(),
            'key' => $a->getKey(),
            'value' => $a->getValue(),
        ], $product->getAttributes());

        $images = array_map(static fn ($i): array => [
            'id' => $i->getId(),
            'url' => $i->getUrl(),
            'path' => $i->getPath(),
            'publicUrl' => null === $i->getPath() ? null : $publicStoragePrefix.$i->getPath(),
        ], $product->getImages());

        $preview = null;
        foreach ($images as $image) {
            if (null !== $image['publicUrl']) {
                $preview = $image['publicUrl'];
                break;
            }
        }

        return self::build($product, $attributes, $images, $preview);
    }

    /**
     * Общая сборка полей товара для обоих видов представления.
     *
     * @param list<array{id: int|null, key: string, value: string}>|null                     $attributes
     * @param list<array{id: int|null, url: string, path: ?string, publicUrl: ?string}>|null $images
     */
    private static function build(Product $product, ?array $attributes, ?array $images, ?string $preview): self
    {
        return new self(
            (int) $product->getId(),
            $product->getExternalCode(),
            $product->getName(),
            $product->getDescription(),
            $product->getPrice(),
            $product->getDiscount(),
            $product->getLastImportId(),
            $product->getCreatedAt()->format(\DATE_ATOM),
            $product->getUpdatedAt()->format(\DATE_ATOM),
            $attributes,
            $images,
            $preview,
        );
    }

    /**
     * JSON-ответ; атрибуты и изображения есть только в карточке.
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        $data = [
            'id' => $this->id,
            'externalCode' => $this->externalCode,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'discount' => $this->discount,
            'previewUrl' => $this->previewUrl,
            'lastImportId' => $this->lastImportId,
            'createdAt' => $this->createdAt,
            'updatedAt' => $this->updatedAt,
        ];
        if (null !== $this->attributes) {
            $data['attributes'] = $this->attributes;
        }
        if (null !== $this->images) {
            $data['images'] = $this->images;
        }

        return $data;
    }
}
