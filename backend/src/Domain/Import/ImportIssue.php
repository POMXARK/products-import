<?php

declare(strict_types=1);

namespace App\Domain\Import;

/** Одна запись отчёта об ошибках импорта: номер строки Excel, серьёзность, текст, код товара и колонка. */
final readonly class ImportIssue
{
    /** Номер строки совпадает с номером в Excel, чтобы пользователь сразу нашёл её в файле. */
    public function __construct(
        public int $row,
        public IssueSeverity $severity,
        public string $message,
        public ?string $externalCode = null,
        public ?string $field = null,
    ) {
    }

    /**
     * Сериализация для JSON-колонки отчёта и ответа API.
     *
     * @return array{row: int, severity: string, message: string, externalCode: ?string, field: ?string}
     */
    public function toArray(): array
    {
        return [
            'row' => $this->row,
            'severity' => $this->severity->value,
            'message' => $this->message,
            'externalCode' => $this->externalCode,
            'field' => $this->field,
        ];
    }

    /**
     * Восстановление записи отчёта из JSON-колонки.
     *
     * @param array{row: int, severity: string, message: string, externalCode?: ?string, field?: ?string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['row'],
            IssueSeverity::from($data['severity']),
            $data['message'],
            $data['externalCode'] ?? null,
            $data['field'] ?? null,
        );
    }
}
