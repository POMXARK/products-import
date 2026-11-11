<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Repository;

use App\Domain\User\User;
use App\Domain\User\UserRepository;
use App\Infrastructure\Persistence\Doctrine\EntityManagerProvider;

/** Хранилище пользователей в таблице users. */
final readonly class DoctrineUserRepository implements UserRepository
{
    /** Работает через провайдер EntityManager. */
    public function __construct(private EntityManagerProvider $provider)
    {
    }

    /** Пользователь по id. */
    public function find(int $id): ?User
    {
        return $this->provider->get()->find(User::class, $id);
    }

    /** Пользователь по уникальному логину для входа. */
    public function findByUsername(string $username): ?User
    {
        return $this->provider->get()->getRepository(User::class)->findOneBy(['username' => $username]);
    }

    /** Сохраняет пользователя. */
    public function save(User $user): void
    {
        $em = $this->provider->get();
        $em->persist($user);
        $em->flush();
    }
}
