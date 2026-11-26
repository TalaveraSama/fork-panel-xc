<?php

declare(strict_types=1);

namespace ForkPanelXc\Public\Controllers\Api;

use ForkPanelXc\Core\Http\ApiRequest;
use ForkPanelXc\Core\Http\ApiResponse;
use ForkPanelXc\Core\Support\Db;
use ForkPanelXc\Domain\Auth\AuthService;

class AuthController extends BaseApiController
{
    public function __construct(private ?AuthService $auth = null)
    {
        $this->auth ??= new AuthService();
    }

    public function login(ApiRequest $request): ApiResponse
    {
        $data = $request->json();
        $errors = $this->require($data, ['username', 'password']);

        if ($errors !== null) {
            return ApiResponse::error('Validation failed', 422, $errors);
        }

        $result = $this->auth->login((string) $data['username'], (string) $data['password']);

        if ($result->requiresTwoFactor) {
            return ApiResponse::json([
                'two_factor_required' => true,
                'user_id' => $result->userId,
            ]);
        }

        if (!$result->success) {
            return ApiResponse::error($result->error, 401);
        }

        $user = Db::first('SELECT id, username, email, is_admin, is_reseller, permissions FROM users WHERE id = ?', [(int) ($result->user['id'] ?? 0)]) ?? [];

        return ApiResponse::json([
            'access_token' => $result->accessToken,
            'refresh_token' => $result->refreshToken,
            'user' => $result->user,
            'permissions' => json_decode((string) ($user['permissions'] ?? '[]'), true) ?: [],
            'roles' => array_values(array_filter([
                ((int) ($user['is_admin'] ?? 0)) === 1 ? 'admin' : null,
                ((int) ($user['is_reseller'] ?? 0)) === 1 ? 'reseller' : null,
            ])) ?: ['user'],
        ]);
    }

    public function verifyTwoFactor(ApiRequest $request): ApiResponse
    {
        $data = $request->json();
        $errors = $this->require($data, ['user_id', 'code']);

        if ($errors !== null) {
            return ApiResponse::error('Validation failed', 422, $errors);
        }

        $result = $this->auth->verifyTwoFactor((int) $data['user_id'], (string) $data['code']);

        if (!$result->success) {
            return ApiResponse::error($result->error, 401);
        }

        return ApiResponse::json([
            'access_token' => $result->accessToken,
            'refresh_token' => $result->refreshToken,
            'user' => $result->user,
        ]);
    }

    public function refresh(ApiRequest $request): ApiResponse
    {
        $token = (string) ($request->json()['refresh_token'] ?? '');

        if ($token === '') {
            return ApiResponse::error('Refresh token required', 422);
        }

        $result = $this->auth->refresh($token);

        if (!$result->success) {
            return ApiResponse::error($result->error, 401);
        }

        return ApiResponse::json(['access_token' => $result->accessToken]);
    }

    public function logout(ApiRequest $request): ApiResponse
    {
        // Tokens are stateless; the client discards them.
        return ApiResponse::json(['success' => true]);
    }

    public function me(ApiRequest $request): ApiResponse
    {
        $user = $request->user();

        if ($user === null) {
            return ApiResponse::error('Authentication required', 401);
        }

        return ApiResponse::json([
            'user' => $user->toArray(),
            'permissions' => $user->permissions(),
            'roles' => $user->roles(),
        ]);
    }

    public function updateProfile(ApiRequest $request): ApiResponse
    {
        $user = $request->user();

        if ($user === null) {
            return ApiResponse::error('Authentication required', 401);
        }

        $data = $request->json();
        $allowed = array_intersect_key($data, array_flip(['email', 'full_name', 'timezone', 'language']));

        if ($allowed !== []) {
            $sets = implode(', ', array_map(static fn (string $key): string => $key . ' = ?', array_keys($allowed)));
            $params = array_values($allowed);
            $params[] = time();
            $params[] = $user->id();

            Db::execute(sprintf('UPDATE users SET %s, updated_at = ? WHERE id = ?', $sets), $params);
        }

        $row = Db::first('SELECT id, username, email, is_admin, is_reseller, permissions FROM users WHERE id = ?', [$user->id()]);

        return ApiResponse::json([
            'user' => $row ?? $user->toArray(),
        ]);
    }

    public function changePassword(ApiRequest $request): ApiResponse
    {
        $user = $request->user();

        if ($user === null) {
            return ApiResponse::error('Authentication required', 401);
        }

        $data = $request->json();
        $errors = $this->require($data, ['current_password', 'new_password']);

        if ($errors !== null) {
            return ApiResponse::error('Validation failed', 422, $errors);
        }

        $result = $this->auth->changePassword($user->id(), (string) $data['current_password'], (string) $data['new_password']);

        if (!$result->success) {
            return ApiResponse::error($result->error, 400);
        }

        return ApiResponse::json(['success' => true]);
    }

    public function forgotPassword(ApiRequest $request): ApiResponse
    {
        $email = (string) ($request->json()['email'] ?? '');

        if ($email !== '') {
            $row = Db::first('SELECT id FROM users WHERE email = ? LIMIT 1', [$email]);

            if ($row !== null) {
                $token = bin2hex(random_bytes(32));

                Db::execute(
                    'INSERT INTO password_resets (user_id, token, created_at, expires_at) VALUES (?, ?, ?, ?)',
                    [(int) $row['id'], hash('sha256', $token), time(), time() + 3600]
                );

                // Delivery is handled by the panel mailer once configured.
            }
        }

        return ApiResponse::json(['message' => 'If the account exists, a reset link has been sent']);
    }
}
