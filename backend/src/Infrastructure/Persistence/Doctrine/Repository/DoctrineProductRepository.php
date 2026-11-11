<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\Product\Product;
use App\Domain\Product\ProductFilter;
use App\Domain\Product\ProductRepository;
use App\Domain\Shared\Page;
use App\Domain\Shared\Pagination;
use App\Infrastructure\Persistence\Doctrine\EntityManagerProvider;
use Doctrine\ORM\QueryBuilder;

/** Хранилище товаров на Doctrine: поиск по фильтрам, upsert-блокировка, запись с атрибутами и изображениями. */
final readonly class DoctrineProductRepository implements ProductRepository
{
    /** Работает через провайдер EntityManager. */
    public function __construct(private EntityManagerProvider $provider)
    {
    }

    /** Карточка товара: атрибуты и изображения подгружаются одним запросом. */
    public function find(int $id): ?Product
    {
        return $this->provider->get()->createQueryBuilder()
            ->select('p', 'a', 'i')
            ->from(Product::class, 'p')
            ->leftJoin('p.attributes', 'a')
            ->leftJoin('p.images', 'i')
            ->where('p.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** Поиск по уникальному внешнему коду для upsert. */
    public function findByExternalCode(string $externalCode): ?Product
    {
        return $this->provider->get()->getRepository(Product::class)->findOneBy(['externalCode' => $externalCode]);
    }

    /** Сначала id страницы с LIMIT/OFFSET и COUNT, затем сами товары с изображениями для превью. */
    public function search(ProductFilter $filter, Pagination $pagination): Page
    {
        $em = $this->provider->get();

        $total = (int) $this->applyFilter($em->createQueryBuilder()->select('COUNT(p.id)')->from(Product::class, 'p'), $filter)
            ->getQuery()
            ->getSingleScalarResult();

        /** @var list<int> $ids */
        $ids = array_column(
            $this->applyFilter($em->createQueryBuilder()->select('p.id')->from(Product::class, 'p'), $filter)
                ->orderBy('p.id', 'ASC')
                ->setFirstResult($pagination->offset())
                ->setMaxResults($pagination->limit)
                ->getQuery()
                ->getArrayResult(),
            'id',
        );

        $items = [];
        if ([] !== $ids) {
            // Второй запрос с fetch join изображений: превью без N+1 и без LIMIT поверх JOIN.
            /** @var list<Product> $items */
            $items = $em->createQueryBuilder()
                ->select('p', 'i')
                ->from(Product::class, 'p')
                ->leftJoin('p.images', 'i')
                ->where('p.id IN (:ids)')
                ->setParameter('ids', $ids)
                ->orderBy('p.id', 'ASC')
                ->getQuery()
                ->getResult();
        }

        return new Page($items, $pagination->page, $pagination->limit, $total);
    }

    /**
     * pg_advisory_xact_lock по хешу external_code: два воркера не создадут дубль товара,
     * блокировка снимается вместе с транзакцией.
     */
    public function lockExternalCode(string $externalCode): void
    {
        $this->provider->connection()->executeStatement(
            'SELECT pg_advisory_xact_lock(hashtextextended(:code, 0))',
            ['code' => 'product:'.$externalCode],
        );
    }

    /** Сохраняет товар и каскадом его атрибуты и изображения. */
    public function save(Product $product): void
    {
        $em = $this->provider->get();
        $em->persist($product);
        $em->flush();
    }

    /** Удаляет товар; атрибуты и изображения удаляются каскадом. */
    public function remove(Product $product): void
    {
        $em = $this->provider->get();
        $em->remove($product);
        $em->flush();
    }

    /**
     * Фильтр по названию через LOWER(name) LIKE (индекс pg_trgm) и по диапазону цены включительно;
     * спецсимволы % и _ в поиске экранируются.
     */
    private function applyFilter(QueryBuilder $qb, ProductFilter $filter): QueryBuilder
    {
        if (null !== $filter->name) {
            $qb->andWhere('LOWER(p.name) LIKE :name')
                ->setParameter('name', '%'.addcslashes(mb_strtolower($filter->name), '%_\\').'%');
        }
        if (null !== $filter->priceMin) {
            $qb->andWhere('p.price >= :priceMin')->setParameter('priceMin', $filter->priceMin->toDecimal());
        }
        if (null !== $filter->priceMax) {
            $qb->andWhere('p.price <= :priceMax')->setParameter('priceMax', $filter->priceMax->toDecimal());
        }

        return $qb;
    }
}
