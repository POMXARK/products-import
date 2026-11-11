<?php

declare(strict_types=1);

namespace App\Entrypoint\Http\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/** ТЗ: "API описано через Swagger / OpenAPI". Отдаёт спецификацию и страницу Swagger UI. */
final readonly class DocsController
{
    /** Путь к openapi.yaml из настроек. */
    public function __construct(private string $specPath)
    {
    }

    /** GET /api/openapi.yaml: спецификация OpenAPI 3.1. */
    public function spec(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write((string) file_get_contents($this->specPath));

        return $response->withHeader('Content-Type', 'application/yaml; charset=utf-8');
    }

    /** GET /api/docs: Swagger UI, читающий /api/openapi.yaml. */
    public function ui(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $response->getBody()->write(<<<'HTML'
            <!doctype html>
            <html lang="ru">
            <head>
              <meta charset="utf-8">
              <title>Products Import API</title>
              <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui.css">
            </head>
            <body>
              <div id="swagger-ui"></div>
              <script src="https://cdn.jsdelivr.net/npm/swagger-ui-dist@5.17.14/swagger-ui-bundle.js"></script>
              <script>window.ui = SwaggerUIBundle({ url: '/api/openapi.yaml', dom_id: '#swagger-ui', persistAuthorization: true });</script>
            </body>
            </html>
            HTML);

        return $response->withHeader('Content-Type', 'text/html; charset=utf-8');
    }
}
