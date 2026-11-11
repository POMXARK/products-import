<?php

declare(strict_types=1);

namespace App\Entrypoint\Http\Controller;

use App\Application\Product\ProductQueryService;
use App\Application\Shared\ValidationException;
use App\Domain\Product\ProductFilter;
use App\Domain\Shared\Money;
use App\Domain\Shared\Pagination;
use App\Entrypoint\Http\JsonResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Страницы "Список товаров" и "Карточка товара".
 * Контроллер разбирает и валидирует параметры запроса, всю работу делает ProductQueryService.
 */
final readonly class ProductController
{
    /** Работа с каталогом идёт через ProductQueryService. */
    public function __construct(private ProductQueryService $products)
    {
    }

    /**
     * GET /api/products?page=&limit=&name=&price_min=&price_max=
     * ТЗ: серверная пагинация и фильтр по name и диапазону price.
     */
    public function list(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $query = $request->getQueryParams();
        $errors = [];

        $page = $this->positiveInt($query, 'page', 1, $errors);
        $limit = $this->positiveInt($query, 'limit', Pagination::DEFAULT_LIMIT, $errors);
        if ($limit > Pagination::MAX_LIMIT) {
            $errors['limit'] = \sprintf('Не больше %d', Pagination::MAX_LIMIT);
        }

        $name = \is_string($query['name'] ?? null) ? trim($query['name']) : '';
        if (mb_strlen($name) > 200) {
            $errors['name'] = 'Не длиннее 200 символов';
        }
        $priceMin = $this->money($query, 'price_min', $errors);
        $priceMax = $this->money($query, 'price_max', $errors);
        if (null !== $priceMin && null !== $priceMax && $priceMin->minorUnits > $priceMax->minorUnits) {
            $errors['price_min'] = 'Минимальная цена больше максимальной';
        }

        if ([] !== $errors) {
            throw new ValidationException($errors);
        }

        $result = $this->products->list(
            new ProductFilter('' === $name ? null : $name, $priceMin, $priceMax),
            new Pagination($page, $limit),
        );

        return JsonResponder::json($response, [
            'items' => $result->items,
            'meta' => [
                'page' => $result->page,
                'limit' => $result->limit,
                'total' => $result->total,
                'totalPages' => $result->totalPages(),
            ],
        ]);
    }

    /**
     * GET /api/products/{id}
     * ТЗ: карточка с основными полями, доп. атрибутами и изображениями.
     *
     * @param array{id: string} $args
     */
    public function show(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        return JsonResponder::json($response, $this->products->get((int) $args['id']));
    }

    /**
     * page и limit: целые числа от 1; неверное значение даёт ошибку поля, а не молчаливую подмену.
     *
     * @param array<string, mixed>  $query
     * @param array<string, string> $errors
     */
    private function positiveInt(array $query, string $key, int $default, array &$errors): int
    {
        $raw = $query[$key] ?? null;
        if (null === $raw || '' === $raw) {
            return $default;
        }
        if (!\is_string($raw) || !ctype_digit($raw) || (int) $raw < 1) {
            $errors[$key] = 'Ожидается целое число больше нуля';

            return $default;
        }

        return (int) $raw;
    }

    /**
     * Граница фильтра по цене: число с точностью до копеек; неверный формат даёт ошибку поля.
     *
     * @param array<string, mixed>  $query
     * @param array<string, string> $errors
     */
    private function money(array $query, string $key, array &$errors): ?Money
    {
        $raw = $query[$key] ?? null;
        if (null === $raw || '' === $raw) {
            return null;
        }
        try {
            return Money::parse(\is_string($raw) ? $raw : '');
        } catch (\InvalidArgumentException) {
            $errors[$key] = 'Ожидается неотрицательное число';

            return null;
        }
    }
}
