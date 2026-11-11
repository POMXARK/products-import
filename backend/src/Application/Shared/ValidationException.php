<?php

declare(strict_types=1);

namespace App\Application\Shared;

/** Ошибки входных данных запроса (фильтры, логин); HTTP-слой отвечает 422 со списком полей. */
final class ValidationException extends \RuntimeException
{
    /**
     * Сообщения по каждому полю для подсветки на форме.
     *
     * @param array<string, string> $errors поле => сообщение
     */
    public function __construct(private readonly array $errors, string $message = 'Validation failed')
    {
        parent::__construct($message);
    }

    /**
     * Ошибки по полям для ответа application/problem+json.
     *
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
