<?php

declare(strict_types=1);

namespace App\Entrypoint\Http;

use Psr\Http\Message\ResponseInterface;

/** Единый формат JSON-ответов API. */
final class JsonResponder
{
    /** Пишет данные в тело ответа как JSON с кодом статуса. */
    public static function json(ResponseInterface $response, mixed $data, int $status = 200): ResponseInterface
    {
        $response->getBody()->write(json_encode($data, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_PRESERVE_ZERO_FRACTION));

        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')->withStatus($status);
    }

    /**
     * Ошибка в формате RFC 7807 (application/problem+json): клиент получает код, текст и поля с ошибками.
     *
     * @param array<string, mixed> $extra
     */
    public static function problem(ResponseInterface $response, int $status, string $title, ?string $detail = null, array $extra = []): ResponseInterface
    {
        $body = ['type' => 'about:blank', 'title' => $title, 'status' => $status] + (null === $detail ? [] : ['detail' => $detail]) + $extra;
        $response->getBody()->write(json_encode($body, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES));

        return $response->withHeader('Content-Type', 'application/problem+json; charset=utf-8')->withStatus($status);
    }
}
