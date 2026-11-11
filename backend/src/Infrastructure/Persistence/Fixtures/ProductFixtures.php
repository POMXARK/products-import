<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Fixtures;

use App\Domain\Product\DiscountCalculator;
use App\Domain\Product\ImageData;
use App\Domain\Product\Product;
use App\Domain\Shared\Money;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Persistence\ObjectManager;

/**
 * ТЗ: "сидеры (fixtures) для всех трёх таблиц". Заполняет products, product_attributes и product_images
 * детерминированными данными: тесты и демо получают одинаковый набор при каждом запуске.
 */
final class ProductFixtures extends AbstractFixture
{
    public const int COUNT = 30;

    private const array NAMES = ['Бермуды мужские', 'Легинсы женские', 'Боди женское', 'Бандалетки', 'Футболка'];
    private const array COLORS = ['Nero', 'Bordo', 'Fumo', 'Militare', 'Avorio'];
    private const array SIZES = ['42(S)', '44(M)', '46(L)', '48(XL)'];

    /** Создаёт товары с атрибутами "Размер/Цвет/Бренд" и двумя изображениями у каждого. */
    public function load(ObjectManager $manager): void
    {
        $discounts = new DiscountCalculator();

        for ($i = 1; $i <= self::COUNT; ++$i) {
            $color = self::COLORS[$i % \count(self::COLORS)];
            $size = self::SIZES[$i % \count(self::SIZES)];
            $name = \sprintf('%s, %s, %s', self::NAMES[$i % \count(self::NAMES)], $color, $size);
            $price = Money::fromMinorUnits(50000 + $i * 5000);
            $purchase = Money::fromMinorUnits(intdiv($price->minorUnits * 2, 3));

            $product = new Product(\sprintf('FIXTURE-%03d', $i), $name, $price);
            $product->update($name, 'Описание тестового товара №'.$i, $price, $discounts->calculate($price, $purchase));
            $product->syncAttributes(['Размер' => $size, 'Цвет' => $color, 'Бренд' => 'OMSA']);
            $product->syncImages([
                new ImageData(\sprintf('https://example.com/images/%03d-1.jpg', $i), null),
                new ImageData(\sprintf('https://example.com/images/%03d-2.jpg', $i), null),
            ]);

            $manager->persist($product);
        }

        $manager->flush();
    }
}
