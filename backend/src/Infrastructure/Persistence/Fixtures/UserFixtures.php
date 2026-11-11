<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Fixtures;

use App\Domain\User\User;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Persistence\ObjectManager;

/** Пользователь для входа на login-странице (логин и пароль из ADMIN_USERNAME / ADMIN_PASSWORD). */
final class UserFixtures extends AbstractFixture
{
    /** Логин и пароль из настроек. */
    public function __construct(
        private readonly string $username,
        private readonly string $password,
    ) {
    }

    /** Создаёт администратора с захешированным паролем. */
    public function load(ObjectManager $manager): void
    {
        $manager->persist(User::register($this->username, $this->password));
        $manager->flush();
    }
}
