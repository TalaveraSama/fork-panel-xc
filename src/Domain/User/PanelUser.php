<?php

declare(strict_types=1);

namespace ForkPanelXc\Domain\User;

/**
 * Panel user entity used by the Fork Panel XC API.
 */
final class User
{
    public function __construct(
        private int $id,
        private string $username,
        private string $email,
        private bool $enabled,
        private bool $isAdmin,
        private bool $isReseller,
        private array $permissions = [],
        private array $roles = []
    ) {
    }

    public static function fromRow(array $row): self
    {
        $isAdmin = (bool) ($row['is_admin'] ?? false);
        $isReseller = (bool) ($row['is_reseller'] ?? false);

        $roles = $isAdmin ? ['admin'] : ($isReseller ? ['reseller'] : ['user']);

        $permissions = [];
        if (isset($row['permissions']) && $row['permissions'] !== null) {
            $decoded = json_decode((string) $row['permissions'], true);
            $permissions = is_array($decoded) ? $decoded : [];
        }

        return new self(
            (int) $row['id'],
            (string) $row['username'],
            (string) ($row['email'] ?? ''),
            (bool) ($row['enabled'] ?? true),
            $isAdmin,
            $isReseller,
            $permissions,
            $roles
        );
    }

    public function id(): int
    {
        return $this->id;
    }

    public function username(): string
    {
        return $this->username;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    public function isAdmin(): bool
    {
        return $this->isAdmin;
    }

    public function isReseller(): bool
    {
        return $this->isReseller;
    }

    public function permissions(): array
    {
        return $this->permissions;
    }

    public function roles(): array
    {
        return $this->roles;
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles, true);
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isAdmin) {
            return true;
        }

        return in_array($permission, $this->permissions, true);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'username' => $this->username,
            'email' => $this->email,
            'enabled' => $this->enabled,
            'is_admin' => $this->isAdmin,
            'is_reseller' => $this->isReseller,
        ];
    }
}
