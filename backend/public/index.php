<?php

declare(strict_types=1);

$container = (require __DIR__.'/../config/bootstrap.php')();
(require __DIR__.'/../config/app.php')($container)->run();
