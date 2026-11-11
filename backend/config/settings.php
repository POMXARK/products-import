<?php

declare(strict_types=1);

/**
 * Настройки из переменных окружения (12-factor): один образ для dev, CI и prod.
 * Описание каждой переменной в .env.example.
 */
$env = static function (string $key, ?string $default = null): string {
    $value = $_ENV[$key] ?? getenv($key);
    if (false === $value || '' === $value) {
        if (null === $default) {
            throw new RuntimeException(sprintf('Environment variable %s is required', $key));
        }

        return $default;
    }

    return (string) $value;
};

$root = dirname(__DIR__);

return [
    'env' => $env('APP_ENV', 'prod'),
    'debug' => filter_var($env('APP_DEBUG', '0'), \FILTER_VALIDATE_BOOL),
    'root' => $root,
    'log_level' => $env('LOG_LEVEL', 'info'),
    'database_url' => $env('DATABASE_URL', 'postgresql://app:app@db:5432/products'),
    'messenger' => [
        'transport_dsn' => $env('MESSENGER_TRANSPORT_DSN', 'amqp://guest:guest@rabbitmq:5672/%2f/imports'),
        'failed_dsn' => $env('MESSENGER_FAILED_TRANSPORT_DSN', 'amqp://guest:guest@rabbitmq:5672/%2f/imports_failed'),
        'max_retries' => (int) $env('MESSENGER_MAX_RETRIES', '3'),
        'retry_delay_ms' => (int) $env('MESSENGER_RETRY_DELAY_MS', '2000'),
    ],
    'jwt' => [
        'secret' => $env('JWT_SECRET'),
        'ttl' => (int) $env('JWT_TTL', '3600'),
    ],
    'storage' => [
        'path' => $env('STORAGE_PATH', $root.'/storage'),
        'public_prefix' => $env('PUBLIC_STORAGE_PREFIX', '/storage/'),
    ],
    'import' => [
        'max_file_bytes' => (int) $env('IMPORT_MAX_FILE_BYTES', (string) (10 * 1024 * 1024)),
        'rate_limit' => (int) $env('IMPORT_RATE_LIMIT', '5'),
        'rate_interval' => $env('IMPORT_RATE_INTERVAL', '1 minute'),
        'progress_every_rows' => (int) $env('IMPORT_PROGRESS_EVERY_ROWS', '5'),
    ],
    'images' => [
        'max_bytes' => (int) $env('IMAGE_MAX_BYTES', (string) (10 * 1024 * 1024)),
        'timeout' => (float) $env('IMAGE_TIMEOUT_SECONDS', '15'),
        'allow_private_network' => filter_var($env('IMAGE_ALLOW_PRIVATE_NETWORK', '0'), \FILTER_VALIDATE_BOOL),
    ],
    'admin' => [
        'username' => $env('ADMIN_USERNAME', 'admin'),
        'password' => $env('ADMIN_PASSWORD', 'admin12345'),
    ],
];
