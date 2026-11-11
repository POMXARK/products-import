<?php

declare(strict_types=1);

namespace App\Domain\Shared;

/**
 * Денежная сумма в минимальных единицах (копейках).
 * Целые числа вместо float: цена не теряет копейки при парсинге и пересчёте.
 */
final readonly class Money
{
    /** Максимум целой части: колонка цены DECIMAL(12,2) вмещает 10 знаков до запятой. */
    public const int MAX_UNITS = 9_999_999_999;

    /** Сумма не бывает отрицательной: цены в файле неотрицательны. */
    private function __construct(public int $minorUnits)
    {
        if ($minorUnits < 0) {
            throw new \InvalidArgumentException('Amount must not be negative');
        }
    }

    /** Сумма из копеек, например из уже сохранённого значения. */
    public static function fromMinorUnits(int $minorUnits): self
    {
        return new self($minorUnits);
    }

    /**
     * Принимает "1320,00", "1 320.5", "960" и числа из ячейки xlsx.
     *
     * @throws \InvalidArgumentException
     */
    public static function parse(string|int|float $raw): self
    {
        if (\is_int($raw)) {
            if ($raw > self::MAX_UNITS) {
                throw new \InvalidArgumentException(\sprintf('Amount %d is too large', $raw));
            }

            return new self($raw * 100);
        }
        if (\is_float($raw)) {
            $raw = number_format($raw, 2, '.', '');
        }

        $normalized = str_replace([' ', "\u{00A0}", "\u{202F}"], '', trim($raw));
        $normalized = str_replace(',', '.', $normalized);

        if (!preg_match('/^(\d{1,10})(?:\.(\d{1,2}))?$/', $normalized, $m)) {
            throw new \InvalidArgumentException(\sprintf('Invalid amount "%s"', $raw));
        }

        $fraction = str_pad($m[2] ?? '', 2, '0');

        return new self((int) $m[1] * 100 + (int) $fraction);
    }

    /** Нулевая цена продажи недопустима по ТЗ: проверка для валидатора строки. */
    public function isZero(): bool
    {
        return 0 === $this->minorUnits;
    }

    /** Строка для DECIMAL(12,2): "1320.00". */
    public function toDecimal(): string
    {
        return \sprintf('%d.%02d', intdiv($this->minorUnits, 100), $this->minorUnits % 100);
    }
}
