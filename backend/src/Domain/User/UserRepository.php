<?php

declare(strict_types=1);

namespace App\Domain\User;

/** Хранилище пользователей (порт домена). */
interface UserRepository
{
    /** Пользователь по id из JWT. */
    public function find(int $id): ?User;

    /** Пользователь по логину при входе. */
    public function findByUsername(string $username): ?User;

    /** Сохраняет пользователя (команда app:user:create, фикстуры). */
    public function save(User $user): void;
}
