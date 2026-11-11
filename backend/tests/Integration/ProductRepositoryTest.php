<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Domain\Product\ImageData;
use App\Domain\Product\Product;
use App\Domain\Product\ProductFilter;
use App\Domain\Product\ProductRepository;
use App\Domain\Shared\Money;
use App\Domain\Shared\Pagination;
use App\Infrastructure\Persistence\Fixtures\ProductFixtures;
use App\Tests\Support\DatabaseTestCase;
use Doctrine\Common\DataFixtures\Executor\ORMExecutor;
use Doctrine\Common\DataFixtures\Loader;

/** ТЗ: CRUD-тесты и фикстуры для products, product_attributes, product_images; пагинация и фильтры списка. */
final class ProductRepositoryTest extends DatabaseTestCase
{
    private ProductRepository $products;

    protected function setUp(): void
    {
        parent::setUp();
        $this->products = $this->container->get(ProductRepository::class);
    }

    private function loadFixtures(): void
    {
        $loader = new Loader();
        $loader->addFixture(new ProductFixtures());
        (new ORMExecutor($this->em()))->execute($loader->getFixtures(), append: true);
        $this->em()->clear();
    }

    public function testFixturesFillAllThreeTables(): void
    {
        $this->loadFixtures();

        self::assertSame(ProductFixtures::COUNT, $this->rowCount('products'));
        self::assertSame(ProductFixtures::COUNT * 3, $this->rowCount('product_attributes'));
        self::assertSame(ProductFixtures::COUNT * 2, $this->rowCount('product_images'));
    }

    public function testCreateReadUpdateDelete(): void
    {
        $product = new Product('CRUD-1', 'Футболка', Money::parse('1500'));
        $product->update('Футболка', 'Хлопок', Money::parse('1500'), '20.00');
        $product->syncAttributes(['Цвет' => 'Nero', 'Размер' => 'M']);
        $product->syncImages([new ImageData('https://img/1.jpg', 'images/1.jpg')]);
        $this->products->save($product);
        $id = (int) $product->getId();
        $this->em()->clear();

        $found = $this->products->find($id);
        self::assertNotNull($found);
        self::assertSame('1500.00', $found->getPrice());
        self::assertSame('20.00', $found->getDiscount());
        self::assertCount(2, $found->getAttributes());
        self::assertSame('images/1.jpg', $found->getImages()[0]->getPath());

        $found->update('Футболка 2', null, Money::parse('1600'), null);
        $found->syncAttributes(['Цвет' => 'Bordo']);
        $found->syncImages([]);
        $this->products->save($found);
        $this->em()->clear();

        $updated = $this->products->findByExternalCode('CRUD-1');
        self::assertNotNull($updated);
        self::assertSame('Футболка 2', $updated->getName());
        self::assertSame(1, $this->rowCount('product_attributes'), 'пропавший атрибут удалён (orphanRemoval)');
        self::assertSame(0, $this->rowCount('product_images'));

        $this->products->remove($updated);
        self::assertNull($this->products->find($id));
        self::assertSame(0, $this->rowCount('product_attributes'));
    }

    public function testExternalCodeIsUniqueInDatabase(): void
    {
        $this->products->save(new Product('DUP', 'A', Money::parse('1')));
        $this->em()->clear();

        $this->expectException(\Doctrine\DBAL\Exception\UniqueConstraintViolationException::class);
        $this->products->save(new Product('DUP', 'B', Money::parse('1')));
    }

    public function testPaginationReturnsTotalAndPage(): void
    {
        $this->loadFixtures();

        $page = $this->products->search(new ProductFilter(), new Pagination(2, 10));

        self::assertSame(ProductFixtures::COUNT, $page->total);
        self::assertSame(3, $page->totalPages());
        self::assertCount(10, $page->items);
        self::assertSame('FIXTURE-011', $page->items[0]->getExternalCode());
    }

    public function testFiltersByNameCaseInsensitiveAndPriceRange(): void
    {
        $this->products->save(new Product('N-1', 'Бермуды МУЖСКИЕ', Money::parse('1000')));
        $this->products->save(new Product('N-2', 'Бермуды мужские', Money::parse('3000')));
        $this->products->save(new Product('N-3', 'Легинсы', Money::parse('2000')));
        $this->products->save(new Product('N-4', 'Скидка 100%', Money::parse('500')));
        $this->em()->clear();

        $byName = $this->products->search(new ProductFilter('мужские'), new Pagination());
        self::assertSame(['N-1', 'N-2'], array_map(static fn (Product $p): string => $p->getExternalCode(), $byName->items));

        $byPrice = $this->products->search(new ProductFilter(null, Money::parse('1500'), Money::parse('3000')), new Pagination());
        self::assertSame(['N-2', 'N-3'], array_map(static fn (Product $p): string => $p->getExternalCode(), $byPrice->items));

        $both = $this->products->search(new ProductFilter('бермуды', null, Money::parse('1000')), new Pagination());
        self::assertSame(1, $both->total);

        $wildcard = $this->products->search(new ProductFilter('%'), new Pagination());
        self::assertSame(['N-4'], array_map(static fn (Product $p): string => $p->getExternalCode(), $wildcard->items), '% ищется буквально');
    }
}
