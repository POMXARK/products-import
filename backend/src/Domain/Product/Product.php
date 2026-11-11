<?php

declare(strict_types=1);

namespace App\Domain\Product;

use App\Domain\Shared\Money;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

/**
 * Товар каталога. Уникален по external_code («Внешний код» в файле): по нему импорт решает, создать или обновить.
 * Доменная сущность без зависимостей от ORM, маппинг описан в XML в Infrastructure.
 */
class Product
{
    private ?int $id = null;

    private ?string $description = null;

    /** DECIMAL хранится строкой: Doctrine не переводит его во float. */
    private string $price;

    /** Скидка в процентах; null, если закупочная цена не указана. */
    private ?string $discount = null;

    /** Импорт, который последним записал товар: по нему карточка показывает статус и отчёт. */
    private ?string $lastImportId = null;

    private \DateTimeImmutable $createdAt;

    private \DateTimeImmutable $updatedAt;

    /** @var Collection<int, ProductAttribute> */
    private Collection $attributes;

    /** @var Collection<int, ProductImage> */
    private Collection $images;

    /** Новый товар из строки импорта: внешний код, название и цена продажи обязательны по ТЗ. */
    public function __construct(
        private string $externalCode,
        private string $name,
        Money $price,
        ?\DateTimeImmutable $now = null,
    ) {
        $this->price = $price->toDecimal();
        $this->createdAt = $now ?? new \DateTimeImmutable();
        $this->updatedAt = $this->createdAt;
        $this->attributes = new ArrayCollection();
        $this->images = new ArrayCollection();
    }

    /** Повторный импорт того же external_code перезаписывает поля товара значениями из файла. */
    public function update(
        string $name,
        ?string $description,
        Money $price,
        ?string $discount,
        ?\DateTimeImmutable $now = null,
    ): void {
        $this->name = $name;
        $this->description = $description;
        $this->price = $price->toDecimal();
        $this->discount = $discount;
        $this->updatedAt = $now ?? new \DateTimeImmutable();
    }

    /** Запоминает импорт, последним записавший товар: карточка товара показывает его прогресс и отчёт. */
    public function markImportedBy(string $importId): void
    {
        $this->lastImportId = $importId;
    }

    /**
     * Приводит атрибуты к переданному набору: обновляет совпадающие ключи,
     * добавляет новые и удаляет пропавшие (orphanRemoval), не пересоздавая строки без нужды.
     *
     * @param array<string, string> $attributes
     */
    public function syncAttributes(array $attributes): void
    {
        foreach ($this->attributes as $attribute) {
            $key = $attribute->getKey();
            if (!\array_key_exists($key, $attributes)) {
                $this->attributes->removeElement($attribute);
                continue;
            }
            $attribute->changeValue($attributes[$key]);
            unset($attributes[$key]);
        }

        foreach ($attributes as $key => $value) {
            $this->attributes->add(new ProductAttribute($this, (string) $key, $value));
        }
    }

    /**
     * Синхронизирует изображения по url, сохраняя порядок из файла.
     *
     * @param list<ImageData> $images
     */
    public function syncImages(array $images): void
    {
        $byUrl = [];
        foreach ($images as $position => $image) {
            $byUrl[$image->url] ??= [$image, $position];
        }

        foreach ($this->images as $existing) {
            if (!isset($byUrl[$existing->getUrl()])) {
                $this->images->removeElement($existing);
                continue;
            }
            [$image, $position] = $byUrl[$existing->getUrl()];
            $existing->update($image->path, $position);
            unset($byUrl[$existing->getUrl()]);
        }

        foreach ($byUrl as [$image, $position]) {
            $this->images->add(new ProductImage($this, $image->url, $image->path, $position));
        }
    }

    /** Внутренний id: используется в URL карточки товара. */
    public function getId(): ?int
    {
        return $this->id;
    }

    /** Внешний код товара из файла: ключ upsert. */
    public function getExternalCode(): string
    {
        return $this->externalCode;
    }

    /** Наименование товара: по нему работает фильтр списка. */
    public function getName(): string
    {
        return $this->name;
    }

    /** Описание товара; может отсутствовать в файле. */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /** Цена продажи в виде DECIMAL-строки "1320.00": по ней работает фильтр по диапазону цены. */
    public function getPrice(): string
    {
        return $this->price;
    }

    /** Скидка в процентах от цены продажи до закупочной; null без закупочной цены. */
    public function getDiscount(): ?string
    {
        return $this->discount;
    }

    /** UUID импорта, последним записавшего товар. */
    public function getLastImportId(): ?string
    {
        return $this->lastImportId;
    }

    /** Когда товар впервые появился в каталоге. */
    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** Когда товар последний раз обновлялся импортом. */
    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Дополнительные характеристики из колонок «Доп. поле: ...», кроме ссылок на картинки.
     *
     * @return list<ProductAttribute>
     */
    public function getAttributes(): array
    {
        return array_values($this->attributes->toArray());
    }

    /**
     * Изображения товара в порядке ссылок из файла.
     *
     * @return list<ProductImage>
     */
    public function getImages(): array
    {
        $images = array_values($this->images->toArray());
        usort($images, static fn (ProductImage $a, ProductImage $b): int => $a->getPosition() <=> $b->getPosition());

        return $images;
    }
}
