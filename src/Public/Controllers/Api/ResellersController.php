<?php

declare(strict_types=1);

namespace ForkPanelXc\Public\Controllers\Api;

use ForkPanelXc\Core\Http\ApiRequest;
use ForkPanelXc\Core\Http\ApiResponse;
use ForkPanelXc\Core\Support\Db;

class ResellersController extends BaseApiController
{
    public function index(ApiRequest $request): ApiResponse
    {
        [$page, $perPage, $offset] = $this->pagination($request);

        $total = (int) Db::column('SELECT COUNT(*) FROM users WHERE is_reseller = 1');
        $rows = Db::select(
            sprintf(
                'SELECT id, username, email, enabled, credits, created_at,
                        (SELECT COUNT(*) FROM users u WHERE u.reseller_id = users.id) AS users_count
                 FROM users WHERE is_reseller = 1 ORDER BY id DESC LIMIT %d OFFSET %d',
                $perPage,
                $offset
            )
        );

        return ApiResponse::json($this->paginated($rows, $total, $page, $perPage));
    }

    public function store(ApiRequest $request): ApiResponse
    {
        $data = $request->json();
        $errors = $this->require($data, ['username', 'password']);

        if ($errors !== null) {
            return ApiResponse::error('Validation failed', 422, $errors);
        }

        if (strlen((string) $data['password']) < 8) {
            return ApiResponse::error('Password must be at least 8 characters', 422);
        }

        if (Db::first('SELECT id FROM users WHERE username = ?', [(string) $data['username']]) !== null) {
            return ApiResponse::error('Username already exists', 422);
        }

        $id = Db::insert(
            'INSERT INTO users (username, email, password_hash, enabled, is_admin, is_reseller, credits, created_at, updated_at)
             VALUES (?, ?, ?, ?, 0, 1, ?, ?, ?)',
            [
                (string) $data['username'],
                (string) ($data['email'] ?? ''),
                password_hash((string) $data['password'], PASSWORD_ARGON2ID),
                !empty($data['enabled']) ? 1 : 0,
                (int) ($data['credits'] ?? 0),
                time(),
                time(),
            ]
        );

        return ApiResponse::json(['data' => $this->find($id)], 201);
    }

    public function show(ApiRequest $request, string $id): ApiResponse
    {
        $reseller = $this->find((int) $id);

        return $reseller === null
            ? ApiResponse::error('Reseller not found', 404)
            : ApiResponse::json(['data' => $reseller]);
    }

    public function update(ApiRequest $request, string $id): ApiResponse
    {
        $data = $request->json();
        $allowed = array_intersect_key($data, array_flip(['email', 'enabled', 'credits']));

        if ($allowed === []) {
            return ApiResponse::error('No supported fields', 422);
        }

        $sets = implode(', ', array_map(static fn (string $key): string => $key . ' = ?', array_keys($allowed)));
        $params = array_values($allowed);
        $params[] = time();
        $params[] = (int) $id;

        Db::execute(sprintf('UPDATE users SET %s, updated_at = ? WHERE id = ? AND is_reseller = 1', $sets), $params);

        return ApiResponse::json(['data' => $this->find((int) $id)]);
    }

    public function destroy(ApiRequest $request, string $id): ApiResponse
    {
        $deleted = Db::execute('DELETE FROM users WHERE id = ? AND is_reseller = 1', [(int) $id]);

        return $deleted > 0 ? ApiResponse::json(['success' => true]) : ApiResponse::error('Reseller not found', 404);
    }

    private function find(int $id): ?array
    {
        return Db::first(
            'SELECT id, username, email, enabled, credits, created_at FROM users WHERE id = ? AND is_reseller = 1',
            [$id]
        );
    }
}
