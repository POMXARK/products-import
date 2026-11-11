<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Проверка боевой сборки HTTP-клиента для скачивания изображений.
 *
 * В остальных тестах загрузчик подменён фейком, поэтому реальная цепочка клиентов из контейнера
 * (с защитой от SSRF) здесь собирается целиком: ошибка в зависимостях иначе всплыла бы только в воркере.
 */
final class HttpClientWiringTest extends TestCase
{
    /** Ссылки из xlsx присылает пользователь: запрос во внутреннюю сеть должен блокироваться до соединения. */
    public function testProductionClientBlocksPrivateNetwork(): void
    {
        $container = (require \dirname(__DIR__, 2).'/config/bootstrap.php')();
        $client = $container->get(HttpClientInterface::class);

        try {
            $client->request('GET', 'http://127.0.0.1:9/image.jpg')->getStatusCode();
            self::fail('Запрос в приватную сеть не был заблокирован');
        } catch (TransportExceptionInterface $e) {
            self::assertStringContainsString('blocked', $e->getMessage());
        }
    }
}
