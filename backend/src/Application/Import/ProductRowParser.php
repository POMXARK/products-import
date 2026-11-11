<?php

declare(strict_types=1);

namespace App\Application\Import;

use App\Application\Import\Port\InvalidSpreadsheetException;
use App\Domain\Import\ImportIssue;
use App\Domain\Import\IssueSeverity;
use App\Domain\Product\DiscountCalculator;
use App\Domain\Product\DiscountOutOfRange;
use App\Domain\Shared\Money;

/**
 * Превращает строку xlsx в ProductRow и собирает все ошибки строки сразу,
 * чтобы в отчёте было видно всё, что нужно исправить, а не только первую проблему.
 */
final class ProductRowParser
{
    public const string COL_EXTERNAL_CODE = 'Внешний код';
    public const string COL_NAME = 'Наименование';
    public const string COL_DESCRIPTION = 'Описание';
    public const string COL_PRICE = 'Цена: Цена продажи';
    public const string COL_PURCHASE_PRICE = 'Закупочная цена';
    public const string ATTRIBUTE_PREFIX = 'Доп. поле:';

    /** Колонки с префиксом "Доп. поле", из которых берутся изображения, а не атрибуты. */
    public const array IMAGE_COLUMNS = [
        'Доп. поле: Ссылка на упаковку',
        'Доп. поле: Ссылки на фото',
    ];

    private const array REQUIRED_COLUMNS = [self::COL_EXTERNAL_CODE, self::COL_NAME, self::COL_PRICE];

    private const int MAX_EXTERNAL_CODE = 255;
    private const int MAX_NAME = 500;
    private const int MAX_ATTRIBUTE_KEY = 255;
    private const int MAX_URL = 2048;

    /** Калькулятор скидки подставляется для тестов; по умолчанию стандартный. */
    public function __construct(private readonly DiscountCalculator $discounts = new DiscountCalculator())
    {
    }

    /**
     * Без колонок «Внешний код», «Наименование» и «Цена: Цена продажи» файл непригоден:
     * импорт сразу завершается failed, а не пишет по ошибке на каждую строку.
     *
     * @param list<string> $headers
     *
     * @throws InvalidSpreadsheetException
     */
    public function assertHeaders(array $headers): void
    {
        $missing = array_values(array_diff(self::REQUIRED_COLUMNS, $headers));
        if ([] !== $missing) {
            throw new InvalidSpreadsheetException('В файле нет обязательных колонок: '.implode(', ', $missing));
        }
    }

    /**
     * Пустые строки в файле пропускаются без ошибки, но сохраняют нумерацию строк Excel.
     *
     * @param array<string, string|int|float|null> $data
     */
    public function isEmpty(array $data): bool
    {
        foreach ($data as $value) {
            if (null !== $value && '' !== trim((string) $value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Валидирует обязательные поля (внешний код, название, цена больше нуля), формат цен и ссылок,
     * считает скидку, разбирает «Доп. поле: ...» на атрибуты и изображения.
     * Некритичное (битая ссылка) идёт в предупреждения, критичное приводит к пропуску строки.
     *
     * @param array<string, string|int|float|null> $data
     *
     * @throws RowValidationException
     */
    public function parse(int $rowNumber, array $data): ProductRow
    {
        $errors = [];
        $warnings = [];

        $externalCode = $this->string($data, self::COL_EXTERNAL_CODE);
        $error = static fn (string $field, string $message): ImportIssue => new ImportIssue($rowNumber, IssueSeverity::Error, $message, $externalCode, $field);

        if (null === $externalCode) {
            $errors[] = $error(self::COL_EXTERNAL_CODE, 'Не заполнен внешний код');
        } elseif (mb_strlen($externalCode) > self::MAX_EXTERNAL_CODE) {
            $errors[] = $error(self::COL_EXTERNAL_CODE, \sprintf('Внешний код длиннее %d символов', self::MAX_EXTERNAL_CODE));
        }

        $name = $this->string($data, self::COL_NAME);
        if (null === $name) {
            $errors[] = $error(self::COL_NAME, 'Не заполнено наименование');
        } elseif (mb_strlen($name) > self::MAX_NAME) {
            $errors[] = $error(self::COL_NAME, \sprintf('Наименование длиннее %d символов', self::MAX_NAME));
        }

        $price = $this->money($data, self::COL_PRICE);
        if (null === $price) {
            $errors[] = $error(self::COL_PRICE, 'Не заполнена цена продажи');
        } elseif (false === $price) {
            $errors[] = $error(self::COL_PRICE, 'Цена продажи не является числом с точностью до копеек');
        } elseif ($price->isZero()) {
            $errors[] = $error(self::COL_PRICE, 'Цена продажи должна быть больше нуля');
        }

        $purchasePrice = $this->money($data, self::COL_PURCHASE_PRICE);
        if (false === $purchasePrice) {
            $errors[] = $error(self::COL_PURCHASE_PRICE, 'Закупочная цена не является числом с точностью до копеек');
        }

        $discount = null;
        if ($price instanceof Money && !$price->isZero() && false !== $purchasePrice) {
            try {
                $discount = $this->discounts->calculate($price, $purchasePrice);
            } catch (DiscountOutOfRange $e) {
                $errors[] = $error(self::COL_PURCHASE_PRICE, $e->getMessage());
            }
        }

        $attributes = [];
        $imageUrls = [];
        foreach ($data as $header => $value) {
            if (!str_starts_with($header, self::ATTRIBUTE_PREFIX)) {
                continue;
            }
            $stringValue = $this->normalize($value);
            if (null === $stringValue) {
                continue;
            }

            if (\in_array($header, self::IMAGE_COLUMNS, true)) {
                foreach ($this->splitUrls($stringValue) as $url) {
                    if ($this->isValidUrl($url)) {
                        $imageUrls[] = $url;
                    } else {
                        $warnings[] = new ImportIssue($rowNumber, IssueSeverity::Warning, \sprintf('Некорректная ссылка на изображение: %s', mb_substr($url, 0, 200)), $externalCode, $header);
                    }
                }
                continue;
            }

            $key = trim(mb_substr($header, mb_strlen(self::ATTRIBUTE_PREFIX)));
            if ('' === $key || mb_strlen($key) > self::MAX_ATTRIBUTE_KEY) {
                $warnings[] = new ImportIssue($rowNumber, IssueSeverity::Warning, 'Пропущен атрибут с некорректным названием колонки', $externalCode, $header);
                continue;
            }
            $attributes[$key] = $stringValue;
        }

        // Если ошибок нет, обязательные поля гарантированно заполнены (проверено выше).
        if ([] !== $errors) {
            throw new RowValidationException($errors);
        }
        if (null === $externalCode || null === $name || !$price instanceof Money) {
            throw new \LogicException('Обязательные поля проверены выше и не могут быть пустыми без ошибки.');
        }

        return new ProductRow(
            $rowNumber,
            $externalCode,
            $name,
            $this->string($data, self::COL_DESCRIPTION),
            $price,
            $purchasePrice,
            $discount,
            $attributes,
            array_values(array_unique($imageUrls)),
            $warnings,
        );
    }

    /**
     * Значение текстовой колонки после нормализации.
     *
     * @param array<string, string|int|float|null> $data
     */
    private function string(array $data, string $column): ?string
    {
        return $this->normalize($data[$column] ?? null);
    }

    /** Обрезает пробелы и приводит числа из ячеек к строке; пустое значение даёт null. */
    private function normalize(string|int|float|null $value): ?string
    {
        if (null === $value) {
            return null;
        }
        if (\is_float($value) && floor($value) === $value && abs($value) < \PHP_INT_MAX) {
            $value = (int) $value;
        }
        $string = trim((string) $value);

        return '' === $string ? null : $string;
    }

    /**
     * Разбирает цену из ячейки: принимает запятую и пробелы-разделители тысяч, как в выгрузках.
     *
     * @param array<string, string|int|float|null> $data
     *
     * @return Money|false|null null: пусто, false: не число
     */
    private function money(array $data, string $column): Money|false|null
    {
        $value = $data[$column] ?? null;
        if (null === $value || (\is_string($value) && '' === trim($value))) {
            return null;
        }

        try {
            return Money::parse($value);
        } catch (\InvalidArgumentException) {
            return false;
        }
    }

    /**
     * В ячейке может быть несколько ссылок через запятую, точку с запятой или перевод строки.
     *
     * @return list<string>
     */
    private function splitUrls(string $value): array
    {
        $parts = preg_split('/[\s,;]+/u', $value, -1, \PREG_SPLIT_NO_EMPTY);

        return false === $parts ? [] : $parts;
    }

    /** Принимаются только http(s)-ссылки разумной длины: остальное в предупреждения отчёта. */
    private function isValidUrl(string $url): bool
    {
        if (mb_strlen($url) > self::MAX_URL || false === filter_var($url, \FILTER_VALIDATE_URL)) {
            return false;
        }
        $scheme = strtolower((string) parse_url($url, \PHP_URL_SCHEME));

        return 'http' === $scheme || 'https' === $scheme;
    }
}
