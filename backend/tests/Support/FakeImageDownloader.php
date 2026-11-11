<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Application\Import\Port\ImageDownloader;
use App\Application\Import\Port\ImageDownloadResult;

/**
 * Скачивание без сети: ссылки со словом "broken" возвращают ошибку, остальные успешны.
 * Если передан ImmediateTransactionManager, фиксирует попытку скачать внутри транзакции.
 */
final class FakeImageDownloader implements ImageDownloader
{
    public bool $calledInsideTransaction = false;

    /** @var list<string> */
    public array $requested = [];

    public function __construct(private readonly ?ImmediateTransactionManager $transactions = null)
    {
    }

    public function downloadAll(array $urls): array
    {
        if ($this->transactions?->inTransaction) {
            $this->calledInsideTransaction = true;
        }

        $results = [];
        foreach ($urls as $url) {
            $this->requested[] = $url;
            $results[$url] = str_contains($url, 'broken')
                ? ImageDownloadResult::failure('HTTP 403')
                : ImageDownloadResult::success('images/'.substr(sha1($url), 0, 2).'/'.sha1($url).'.jpg');
        }

        return $results;
    }
}
