<?php

declare(strict_types=1);

namespace App\Domain\User;

/** Пользователь, который входит в админку по JWT и загружает файлы импорта. */
class User
{
    private ?int $id = null;

    /** Восстановление из хранилища: пароль хранится только в виде хэша. */
    public function __construct(
        private string $username,
        private string $passwordHash,
    ) {
    }

    /** Регистрация: пароль сразу хэшируется (password_hash с алгоритмом по умолчанию). */
    public static function register(string $username, string $plainPassword): self
    {
        return new self($username, password_hash($plainPassword, \PASSWORD_DEFAULT));
    }

    /** Проверка пароля при входе. */
    public function verifyPassword(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->passwordHash);
    }

    /** Внутренний id: попадает в sub JWT-токена и в createdBy импорта. */
    public function getId(): ?int
    {
        return $this->id;
    }

    /** Логин пользователя. */
    public function getUsername(): string
    {
        return $this->username;
    }
}
