<?php

declare(strict_types=1);

namespace App\Infrastructure\Auth;

use App\Application\Auth\AuthenticatedUser;
use App\Application\Auth\InvalidTokenException;
use App\Application\Auth\IssuedToken;
use App\Application\Auth\TokenIssuer;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

/**
 * ТЗ (расширенный функционал): "аутентификация: JWT или сессия".
 * Выбран JWT (HS256): API без состояния, фронтенд кладёт токен в заголовок Authorization через interceptor.
 */
final readonly class JwtTokenIssuer implements TokenIssuer
{
    private const string ALGORITHM = 'HS256';

    /** Секрет (не короче 32 байт), срок жизни и издатель из настроек. */
    public function __construct(
        private string $secret,
        private int $ttlSeconds,
        private string $issuer = 'products-import',
    ) {
        if (\strlen($secret) < 32) {
            throw new \InvalidArgumentException('JWT secret must be at least 32 bytes');
        }
    }

    /** Выдаёт токен после успешного входа; срок жизни задаётся JWT_TTL. */
    public function issue(int $userId, string $username): IssuedToken
    {
        $now = time();
        $expiresAt = $now + $this->ttlSeconds;

        $token = JWT::encode([
            'iss' => $this->issuer,
            'sub' => (string) $userId,
            'name' => $username,
            'iat' => $now,
            'exp' => $expiresAt,
        ], $this->secret, self::ALGORITHM);

        return new IssuedToken($token, (new \DateTimeImmutable())->setTimestamp($expiresAt));
    }

    /** Проверяет подпись, срок и издателя; любое несоответствие означает 401. */
    public function verify(string $token): AuthenticatedUser
    {
        try {
            $payload = JWT::decode($token, new Key($this->secret, self::ALGORITHM));
        } catch (\Throwable $e) {
            throw new InvalidTokenException('Invalid token', 0, $e);
        }

        if (($payload->iss ?? null) !== $this->issuer || !isset($payload->sub, $payload->name)) {
            throw new InvalidTokenException('Invalid token claims');
        }

        return new AuthenticatedUser((int) $payload->sub, (string) $payload->name);
    }
}
