<?php

declare(strict_types=1);

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;

require dirname(__DIR__).'/vendor/autoload.php';

/** Собирает DI-контейнер; общая точка входа для HTTP, консоли и тестов. */
return static function (array $overrides = []): ContainerInterface {
    $builder = new ContainerBuilder();
    $builder->useAutowiring(true);
    $builder->addDefinitions(__DIR__.'/container.php');
    if ([] !== $overrides) {
        $builder->addDefinitions($overrides);
    }

    return $builder->build();
};
