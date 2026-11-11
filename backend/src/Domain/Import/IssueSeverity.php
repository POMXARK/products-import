<?php

declare(strict_types=1);

namespace App\Domain\Import;

/** Серьёзность замечания в отчёте импорта. */
enum IssueSeverity: string
{
    /** Строка пропущена: товар не записан. */
    case Error = 'error';
    /** Строка записана, но не полностью: например, не скачалось изображение. */
    case Warning = 'warning';
}
