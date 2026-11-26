<?php

declare(strict_types=1);

namespace ForkPanelXc\Domain\Auth;

/**
 * Result object for authentication operations.
 */
final class AuthResult
{
    public function __construct(
        public bool $success = false,
        public string $error = '',
        public bool $requiresTwoFactor = false,
        public ?int $userId = null,
        public ?string $accessToken = null,
        public ?string $refreshToken = null,
        public ?array $user = null
    ) {
    }

    public static function failure(string $error): self
    {
        return new self(false, $error);
    }

    public static function twoFactor(int $userId): self
    {
        return new self(true, '', true, $userId);
    }

    public static function success(string $access, string $refresh, array $user): self
    {
        return new self(true, '', false, null, $access, $refresh, $user);
    }
}
