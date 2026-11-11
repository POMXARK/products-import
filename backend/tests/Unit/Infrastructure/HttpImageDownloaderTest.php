<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure;

use App\Infrastructure\Image\HttpImageDownloader;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/** ТЗ: изображения скачиваются и сохраняются локально; битая ссылка не ломает импорт товара. */
final class HttpImageDownloaderTest extends TestCase
{
    private const string PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    private string $root;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir().'/images-'.bin2hex(random_bytes(4));
        mkdir($this->root);
    }

    protected function tearDown(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->root, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->root);
    }

    public function testStoresImageByContentTypeAndReportsFailuresPerUrl(): void
    {
        $png = (string) base64_decode(self::PNG_1X1, true);
        $client = new MockHttpClient(static fn (string $method, string $url): MockResponse => match (true) {
            str_contains($url, 'ok') => new MockResponse($png, ['response_headers' => ['content-type' => 'text/plain']]),
            str_contains($url, 'forbidden') => new MockResponse('', ['http_code' => 403]),
            default => new MockResponse('<html>не картинка</html>'),
        });
        $downloader = new HttpImageDownloader($client, $this->root);

        $results = $downloader->downloadAll(['https://cdn/ok.jpg', 'https://cdn/forbidden.jpg', 'https://cdn/html.jpg']);

        self::assertSame(['https://cdn/ok.jpg', 'https://cdn/forbidden.jpg', 'https://cdn/html.jpg'], array_keys($results), 'порядок как в файле');
        $ok = $results['https://cdn/ok.jpg'];
        self::assertTrue($ok->isSuccess());
        self::assertStringEndsWith('.png', (string) $ok->path, 'расширение по содержимому, а не по заголовку сервера');
        self::assertFileExists($this->root.'/'.$ok->path);
        self::assertSame('HTTP 403', $results['https://cdn/forbidden.jpg']->error);
        self::assertSame('ответ не является изображением', $results['https://cdn/html.jpg']->error);
    }

    public function testAlreadyDownloadedImageIsNotRequestedAgain(): void
    {
        $png = (string) base64_decode(self::PNG_1X1, true);
        $calls = 0;
        $client = new MockHttpClient(static function () use ($png, &$calls): MockResponse {
            ++$calls;

            return new MockResponse($png);
        });
        $downloader = new HttpImageDownloader($client, $this->root);

        $first = $downloader->downloadAll(['https://cdn/a.png']);
        $second = $downloader->downloadAll(['https://cdn/a.png']);

        self::assertSame(1, $calls);
        self::assertSame($first['https://cdn/a.png']->path, $second['https://cdn/a.png']->path);
    }

    public function testRejectsImageLargerThanLimit(): void
    {
        $client = new MockHttpClient(new MockResponse(str_repeat('x', 2048)));
        $downloader = new HttpImageDownloader($client, $this->root, maxBytes: 1024);

        $result = $downloader->downloadAll(['https://cdn/big.jpg'])['https://cdn/big.jpg'];

        self::assertFalse($result->isSuccess());
        self::assertSame('файл больше допустимого размера', $result->error);
    }

    public function testNetworkErrorBecomesFailure(): void
    {
        $client = new MockHttpClient(new MockResponse('', ['error' => 'Could not resolve host']));

        $result = (new HttpImageDownloader($client, $this->root))->downloadAll(['https://nowhere/a.jpg'])['https://nowhere/a.jpg'];

        self::assertFalse($result->isSuccess());
        self::assertStringContainsString('Could not resolve host', (string) $result->error);
    }
}
