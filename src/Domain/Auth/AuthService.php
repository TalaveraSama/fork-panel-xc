<?php

declare(strict_types=1);

namespace ForkPanelXc\Domain\Auth;

use ForkPanelXc\Core\Support\Db;
use ForkPanelXc\Core\Support\Env;
use ForkPanelXc\Core\Support\Jwt;
use ForkPanelXc\Domain\User\PanelUser;

/**
 * Authentication service for the Fork Panel XC API.
 *
 * Passwords are expected to be stored with password_hash(); legacy plaintext
 * or MD5 rows are rejected until they are migrated.
 */
final class AuthService
{
    public function login(string $username, string $password): AuthResult
    {
        if ($username === '' || $password === '') {
            return AuthResult::failure('Username and password are required');
        }

        $row = Db::first(
            'SELECT id, username, email, password_hash, enabled, is_admin, is_reseller, permissions, two_factor_secret
             FROM users WHERE username = ? LIMIT 1',
            [$username]
        );

        if ($row === null || !password_verify($password, (string) $row['password_hash'])) {
            return AuthResult::failure('Invalid username or password');
        }

        if (!(bool) $row['enabled']) {
            return AuthResult::failure('Account is disabled');
        }

        if (!empty($row['two_factor_secret'])) {
            return AuthResult::twoFactor((int) $row['id']);
        }

        return $this->completeLogin((int) $row['id']);
    }

    public function verifyTwoFactor(int $userId, string $code): AuthResult
    {
        $row = Db::first('SELECT * FROM users WHERE id = ? LIMIT 1', [$userId]);

        if ($row === null || !(bool) $row['enabled']) {
            return AuthResult::failure('Invalid account');
        }

        // TOTP verification is delegated to the panel's existing 2FA handler.
        // Until that bridge is wired, reject codes instead of accepting any.
        return AuthResult::failure('Two-factor verification is not configured');
    }

    public function refresh(string $refreshToken): AuthResult
    {
        $claims = Jwt::decode($refreshToken);

        if ($claims === null || ($claims['typ'] ?? '') !== 'refresh') {
            return AuthResult::failure('Invalid refresh token');
        }

        return $this->completeLogin((int) $claims['sub']);
    }

    public function validateToken(string $token): ?PanelUser
    {
        $claims = Jwt::decode($token);

        if ($claims === null || ($claims['typ'] ?? '') !== 'access') {
            return null;
        }

        $row = Db::first(
            'SELECT id, username, email, enabled, is_admin, is_reseller, permissions FROM users WHERE id = ? LIMIT 1',
            [(int) $claims['sub']]
        );

        if ($row === null || !(bool) $row['enabled']) {
            return null;
        }

        return PanelUser::fromRow($row);
    }

    public function changePassword(int $userId, string $current, string $new): AuthResult
    {
        $row = Db::first('SELECT password_hash FROM users WHERE id = ? LIMIT 1', [$userId]);

        if ($row === null) {
            return AuthResult::failure('User not found');
        }

        if (!password_verify($current, (string) $row['password_hash'])) {
            return AuthResult::failure('Current password is incorrect');
        }

        if (strlen($new) < 8) {
            return AuthResult::failure('New password must be at least 8 characters');
        }

        Db::execute(
            'UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?',
            [password_hash($new, PASSWORD_ARGON2ID), time(), $userId]
        );

        return new AuthResult(true);
    }

    private function completeLogin(int $userId): AuthResult
    {
        $row = Db::first(
            'SELECT id, username, email, enabled, is_admin, is_reseller, permissions FROM users WHERE id = ? LIMIT 1',
            [$userId]
        );

        if ($row === null || !(bool) $row['enabled']) {
            return AuthResult::failure('Account is disabled');
        }

        $user = PanelUser::fromRow($row);
        [$access, $refresh] = Jwt::issue($user->id());

        Db::execute('UPDATE users SET last_login = ? WHERE id = ?', [time(), $user->id()]);

        return AuthResult::success($access, $refresh, $user->toArray());
    }
}
