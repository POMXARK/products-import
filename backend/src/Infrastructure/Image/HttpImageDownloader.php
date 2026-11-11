<?php

declare(strict_types=1);

namespace App\Infrastructure\Image;

use App\Application\Import\Port\ImageDownloader;
use App\Application\Import\Port\ImageDownloadResult;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * ТЗ: "при импорте изображения скачиваются по ссылке из файла" и сохраняются в product_images.path.
 *
 * - запросы по одной строке идут параллельно (HttpClient мультиплексирует их);
 * - файл именуется по sha1 ссылки: одна картинка у десятка товаров скачивается один раз,
 *   повторный импорт не качает её заново;
 * - принимаются только изображения (Content-Type и сигнатура файла) не больше лимита;
 * - запись атомарная (tmp + rename), чтобы воркер не оставил обрезанный файл.
 *
 * Защита от SSRF (запросов во внутреннюю сеть) включается обёрткой NoPrivateNetworkHttpClient в контейнере.
 */
final readonly class HttpImageDownloader implements ImageDownloader
{
    private const array EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    /** HTTP-клиент (с защитой от SSRF), корень хранилища, лимит размера и таймаут из настроек. */
    public function __construct(
        private HttpClientInterface $http,
        private string $storageRoot,
        private int $maxBytes = 10 * 1024 * 1024,
        private float $timeoutSeconds = 15.0,
    ) {
    }

    /**
     * Скачивает все ссылки строки. Ошибка по одной ссылке не прерывает остальные:
     * товар всё равно записывается, а проблема попадает в отчёт импорта как предупреждение.
     */
    public function downloadAll(array $urls): array
    {
        $results = [];
        $pending = [];

        foreach (array_unique($urls) as $url) {
            $existing = $this->findExisting($url);
            if (null !== $existing) {
                $results[$url] = ImageDownloadResult::success($existing);
                continue;
            }

            try {
                $pending[$url] = $this->http->request('GET', $url, [
                    'timeout' => $this->timeoutSeconds,
                    'max_duration' => $this->timeoutSeconds * 2,
                    'max_redirects' => 3,
                    'headers' => ['Accept' => 'image/*'],
                ]);
            } catch (ExceptionInterface $e) {
                $results[$url] = ImageDownloadResult::failure($e->getMessage());
            }
        }

        foreach ($pending as $url => $response) {
            $results[$url] = $this->store($url, $response);
        }

        // Порядок как в файле: от него зависит позиция изображения в карточке.
        $ordered = [];
        foreach ($urls as $url) {
            if (isset($results[$url])) {
                $ordered[$url] = $results[$url];
            }
        }

        return $ordered;
    }

    /** Проверяет код ответа, размер и тип содержимого и атомарно сохраняет картинку в хранилище. */
    private function store(string $url, ResponseInterface $response): ImageDownloadResult
    {
        try {
            $status = $response->getStatusCode();
            if ($status >= 400) {
                return ImageDownloadResult::failure(\sprintf('HTTP %d', $status));
            }

            $headers = $response->getHeaders(false);
            $length = (int) ($headers['content-length'][0] ?? 0);
            if ($length > $this->maxBytes) {
                return ImageDownloadResult::failure('файл больше допустимого размера');
            }

            $body = '';
            foreach ($this->http->stream($response) as $chunk) {
                $body .= $chunk->getContent();
                if (\strlen($body) > $this->maxBytes) {
                    $response->cancel();

                    return ImageDownloadResult::failure('файл больше допустимого размера');
                }
            }
        } catch (ExceptionInterface $e) {
            return ImageDownloadResult::failure($e->getMessage());
        }

        // Тип определяется по содержимому, а не по заголовку сервера: заголовок легко подделать.
        $info = '' === $body ? false : @getimagesizefromstring($body);
        $mime = false === $info ? null : $info['mime'];
        if (null === $mime || !isset(self::EXTENSIONS[$mime])) {
            return ImageDownloadResult::failure('ответ не является изображением');
        }

        $relative = $this->relativePath($url, self::EXTENSIONS[$mime]);
        $absolute = $this->storageRoot.'/'.$relative;
        $dir = \dirname($absolute);
        if (!is_dir($dir) && !@mkdir($dir, 0o775, true) && !is_dir($dir)) {
            return ImageDownloadResult::failure('не удалось создать каталог для изображения');
        }

        $tmp = $absolute.'.'.bin2hex(random_bytes(4)).'.tmp';
        if (false === file_put_contents($tmp, $body) || !rename($tmp, $absolute)) {
            @unlink($tmp);

            return ImageDownloadResult::failure('не удалось сохранить файл');
        }

        return ImageDownloadResult::success($relative);
    }

    /** Ищет ранее скачанный файл той же ссылки с любым из допустимых расширений. */
    private function findExisting(string $url): ?string
    {
        foreach (self::EXTENSIONS as $extension) {
            $relative = $this->relativePath($url, $extension);
            if (is_file($this->storageRoot.'/'.$relative)) {
                return $relative;
            }
        }

        return null;
    }

    /** images/ab/abcdef….jpg: шардирование по первым символам хеша, чтобы каталог не разрастался. */
    private function relativePath(string $url, string $extension): string
    {
        $hash = sha1($url);

        return \sprintf('images/%s/%s.%s', substr($hash, 0, 2), $hash, $extension);
    }
}
