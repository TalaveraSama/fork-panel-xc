<?php

declare(strict_types=1);

namespace ForkPanelXc\Public\Controllers\Api;

use ForkPanelXc\Core\Http\ApiRequest;
use ForkPanelXc\Core\Http\ApiResponse;
use ForkPanelXc\Core\Support\Db;

class UsersController extends BaseApiController
{
    public function index(ApiRequest $request): ApiResponse
    {
        [$page, $perPage, $offset] = $this->pagination($request);
        [$field, $direction] = $this->sort($request, ['id', 'username', 'email', 'enabled', 'created_at']);

        $where = ['1 = 1'];
        $params = [];

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $where[] = '(username LIKE ? OR email LIKE ?)';
            $params[] = '%' . $search . '%';
            $params[] = '%' . $search . '%';
        }

        $status = $request->query('status');
        if ($status === 'active') {
            $where[] = 'enabled = 1';
        } elseif ($status === 'disabled') {
            $where[] = 'enabled = 0';
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) Db::column('SELECT COUNT(*) FROM users WHERE ' . $whereSql, $params);

        $rows = Db::select(
            sprintf(
                'SELECT id, username, email, enabled, is_admin, is_reseller, created_at, last_login,
                        (SELECT COUNT(*) FROM user_lines WHERE user_lines.user_id = users.id) AS lines_count
                 FROM users WHERE %s ORDER BY %s %s LIMIT %d OFFSET %d',
                $whereSql,
                $field,
                $direction,
                $perPage,
                $offset
            ),
            $params
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
            'INSERT INTO users (username, email, password_hash, enabled, is_admin, is_reseller, permissions, created_at, updated_at)
             VALUES (?, ?, ?, ?, 0, ?, ?, ?, ?)',
            [
                (string) $data['username'],
                (string) ($data['email'] ?? ''),
                password_hash((string) $data['password'], PASSWORD_ARGON2ID),
                !empty($data['enabled']) ? 1 : 0,
                !empty($data['is_reseller']) ? 1 : 0,
                json_encode($data['permissions'] ?? []),
                time(),
                time(),
            ]
        );

        return ApiResponse::json(['data' => $this->find($id)], 201);
    }

    public function show(ApiRequest $request, string $id): ApiResponse
    {
        $user = $this->find((int) $id);

        return $user === null
            ? ApiResponse::error('User not found', 404)
            : ApiResponse::json(['data' => $user]);
    }

    public function update(ApiRequest $request, string $id): ApiResponse
    {
        $userId = (int) $id;

        if ($this->find($userId) === null) {
            return ApiResponse::error('User not found', 404);
        }

        $data = $request->json();
        $allowed = array_intersect_key($data, array_flip(['username', 'email', 'enabled', 'is_reseller', 'permissions']));

        if ($allowed === []) {
            return ApiResponse::json(['data' => $this->find($userId)]);
        }

        if (isset($allowed['permissions'])) {
            $allowed['permissions'] = json_encode($allowed['permissions']);
        }

        $sets = implode(', ', array_map(static fn (string $key): string => $key . ' = ?', array_keys($allowed)));
        $params = array_values($allowed);
        $params[] = time();
        $params[] = $userId;

        Db::execute(sprintf('UPDATE users SET %s, updated_at = ? WHERE id = ?', $sets), $params);

        return ApiResponse::json(['data' => $this->find($userId)]);
    }

    public function destroy(ApiRequest $request, string $id): ApiResponse
    {
        $userId = (int) $id;
        $requester = $request->user();

        if ($requester !== null && $requester->id() === $userId) {
            return ApiResponse::error('You cannot delete your own account', 422);
        }

        $deleted = Db::execute('DELETE FROM users WHERE id = ?', [$userId]);

        return $deleted > 0
            ? ApiResponse::json(['success' => true])
            : ApiResponse::error('User not found', 404);
    }

    public function bulkDelete(ApiRequest $request): ApiResponse
    {
        $ids = array_values(array_filter(array_map('intval', (array) ($request->json()['ids'] ?? [])), static fn (int $id): bool => $id > 0));

        if ($ids === []) {
            return ApiResponse::error('IDs array required', 422);
        }

        $deleted = Db::execute(
            'DELETE FROM users WHERE id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')',
            $ids
        );

        return ApiResponse::json(['deleted' => $deleted]);
    }

    public function bulkUpdate(ApiRequest $request): ApiResponse
    {
        $payload = $request->json();
        $ids = array_values(array_filter(array_map('intval', (array) ($payload['ids'] ?? [])), static fn (int $id): bool => $id > 0));
        $updates = $payload['updates'] ?? null;

        if ($ids === [] || !is_array($updates) || $updates === []) {
            return ApiResponse::error('IDs array and updates object required', 422);
        }

        $allowed = array_intersect_key($updates, array_flip(['enabled', 'is_reseller']));

        if ($allowed === []) {
            return ApiResponse::error('No supported update fields', 422);
        }

        $sets = implode(', ', array_map(static fn (string $key): string => $key . ' = ?', array_keys($allowed)));
        $params = array_values($allowed);
        $params[] = time();
        $params = array_merge($params, $ids);

        $updated = Db::execute(
            sprintf('UPDATE users SET %s, updated_at = ? WHERE id IN (%s)', $sets, implode(',', array_fill(0, count($ids), '?'))),
            $params
        );

        return ApiResponse::json(['updated' => $updated]);
    }

    public function stats(ApiRequest $request, string $id): ApiResponse
    {
        return ApiResponse::json(['data' => [
            'lines' => (int) Db::column('SELECT COUNT(*) FROM user_lines WHERE user_id = ?', [(int) $id]),
            'connections' => (int) Db::column('SELECT COUNT(*) FROM user_lines WHERE user_id = ? AND connected = 1', [(int) $id]),
            'last_login' => Db::column('SELECT last_login FROM users WHERE id = ?', [(int) $id]),
        ]]);
    }

    public function activity(ApiRequest $request, string $id): ApiResponse
    {
        $rows = Db::select(
            'SELECT id, event, status, created_at FROM activity_log WHERE user_id = ? ORDER BY id DESC LIMIT 50',
            [(int) $id]
        );

        return ApiResponse::json(['data' => $rows]);
    }

    public function resetPassword(ApiRequest $request, string $id): ApiResponse
    {
        $password = (string) ($request->json()['password'] ?? '');

        if (strlen($password) < 8) {
            return ApiResponse::error('Password must be at least 8 characters', 422);
        }

        $updated = Db::execute(
            'UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?',
            [password_hash($password, PASSWORD_ARGON2ID), time(), (int) $id]
        );

        return $updated > 0
            ? ApiResponse::json(['success' => true])
            : ApiResponse::error('User not found', 404);
    }

    public function toggleStatus(ApiRequest $request, string $id): ApiResponse
    {
        $enabled = !empty($request->json()['enabled']) ? 1 : 0;
        $updated = Db::execute('UPDATE users SET enabled = ?, updated_at = ? WHERE id = ?', [$enabled, time(), (int) $id]);

        return $updated > 0
            ? ApiResponse::json(['success' => true, 'enabled' => (bool) $enabled])
            : ApiResponse::error('User not found', 404);
    }

    public function assignBouquet(ApiRequest $request, string $id): ApiResponse
    {
        $bouquetId = (int) ($request->json()['bouquet_id'] ?? 0);

        if ($bouquetId <= 0) {
            return ApiResponse::error('bouquet_id is required', 422);
        }

        Db::execute(
            'INSERT IGNORE INTO user_bouquets (user_id, bouquet_id) VALUES (?, ?)',
            [(int) $id, $bouquetId]
        );

        return ApiResponse::json(['success' => true]);
    }

    public function removeBouquet(ApiRequest $request, string $id, string $bouquetId): ApiResponse
    {
        Db::execute('DELETE FROM user_bouquets WHERE user_id = ? AND bouquet_id = ?', [(int) $id, (int) $bouquetId]);

        return ApiResponse::json(['success' => true]);
    }

    public function linesIndex(ApiRequest $request): ApiResponse
    {
        [$page, $perPage, $offset] = $this->pagination($request);
        $total = (int) Db::column('SELECT COUNT(*) FROM user_lines');
        $rows = Db::select(
            sprintf('SELECT * FROM user_lines ORDER BY id DESC LIMIT %d OFFSET %d', $perPage, $offset)
        );

        return ApiResponse::json($this->paginated($rows, $total, $page, $perPage));
    }

    public function lineShow(ApiRequest $request, string $id): ApiResponse
    {
        $row = Db::first('SELECT * FROM user_lines WHERE id = ?', [(int) $id]);

        return $row === null
            ? ApiResponse::error('Line not found', 404)
            : ApiResponse::json(['data' => $row]);
    }

    private function find(int $id): ?array
    {
        return Db::first(
            'SELECT id, username, email, enabled, is_admin, is_reseller, permissions, created_at, last_login FROM users WHERE id = ?',
            [$id]
        );
    }
}
