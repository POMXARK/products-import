<?php

declare(strict_types=1);

/*
 * Загрузчик EntityManager для phpstan-doctrine: по XML-маппингу он проверяет DQL и понимает,
 * что id сущностей назначает ORM. Подключение к БД при анализе не открывается.
 */
$_ENV['JWT_SECRET'] ??= str_repeat('x', 32);

$container = (require __DIR__.'/../config/bootstrap.php')();

return $container->get(App\Infrastructure\Persistence\Doctrine\EntityManagerProvider::class)->get();
