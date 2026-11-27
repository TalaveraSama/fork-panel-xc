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
        \ForkPanelXc\Domain\Stream\StreamStats::reconcile();

        [$page, $perPage, $offset] = $this->pagination($request);
        [$field, $direction] = $this->sort($request, ['id', 'username', 'exp_date', 'max_connections', 'enabled', 'created_at']);

        $where = ['1 = 1'];
        $params = [];

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $where[] = 'l.username LIKE ?';
            $params[] = '%' . $search . '%';
        }

        $status = (string) $request->query('status', '');
        if ($status === 'active') {
            $where[] = 'l.enabled = 1 AND (l.exp_date IS NULL OR l.exp_date > ?)';
            $params[] = time();
        } elseif ($status === 'expired') {
            $where[] = 'l.exp_date IS NOT NULL AND l.exp_date <= ?';
            $params[] = time();
        } elseif ($status === 'disabled') {
            $where[] = 'l.enabled = 0';
        }

        $userId = (int) $request->query('user_id', 0);
        if ($userId > 0) {
            $where[] = 'l.user_id = ?';
            $params[] = $userId;
        }

        $whereSql = implode(' AND ', $where);
        $total = (int) Db::column('SELECT COUNT(*) FROM user_lines l WHERE ' . $whereSql, $params);

        $rows = Db::select(
            sprintf(
                'SELECT l.id, l.user_id, l.username, l.exp_date, l.max_connections, l.enabled,
                        l.connected, l.bitrate, l.created_at, u.username AS owner,
                        CASE
                            WHEN l.enabled = 0 THEN \'disabled\'
                            WHEN l.exp_date IS NOT NULL AND l.exp_date <= ? THEN \'expired\'
                            ELSE \'active\'
                        END AS status
                 FROM user_lines l
                 LEFT JOIN users u ON u.id = l.user_id
                 WHERE %s
                 ORDER BY l.%s %s
                 LIMIT %d OFFSET %d',
                $whereSql,
                $field,
                $direction,
                $perPage,
                $offset
            ),
            array_merge([time()], $params)
        );

        return ApiResponse::json($this->paginated($rows, $total, $page, $perPage));
    }

    public function lineShow(ApiRequest $request, string $id): ApiResponse
    {
        \ForkPanelXc\Domain\Stream\StreamStats::reconcile();

        $line = $this->findLine((int) $id);

        return $line === null
            ? ApiResponse::error('Line not found', 404)
            : ApiResponse::json(['data' => $line]);
    }

    public function lineStore(ApiRequest $request): ApiResponse
    {
        $data = $request->json();
        $errors = $this->require($data, ['username']);

        if ($errors !== null) {
            return ApiResponse::error('Validation failed', 422, $errors);
        }

        $username = trim((string) $data['username']);

        if (!preg_match('/^[A-Za-z0-9_.-]{3,64}$/', $username)) {
            return ApiResponse::error('Username must be 3-64 chars (letters, numbers, . _ -)', 422);
        }

        if (Db::first('SELECT id FROM user_lines WHERE username = ?', [$username]) !== null) {
            return ApiResponse::error('Line username already exists', 422);
        }

        $password = trim((string) ($data['password'] ?? ''));

        if ($password === '') {
            $password = $this->randomPassword();
        } elseif (strlen($password) < 6) {
            return ApiResponse::error('Password must be at least 6 characters', 422);
        }

        $expDate = $this->expDate($data['exp_date'] ?? null);

        if ($expDate === false) {
            return ApiResponse::error('Invalid expiration date', 422);
        }

        $userId = $this->ownerId($data['user_id'] ?? null);

        if ($userId === false) {
            return ApiResponse::error('Owner user not found', 422);
        }

        $id = Db::insert(
            'INSERT INTO user_lines (user_id, username, password_hash, exp_date, max_connections, enabled, connected, bitrate, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?)',
            [
                $userId,
                $username,
                password_hash($password, PASSWORD_ARGON2ID),
                $expDate,
                max(1, (int) ($data['max_connections'] ?? 1)),
                array_key_exists('enabled', $data) ? (!empty($data['enabled']) ? 1 : 0) : 1,
                time(),
            ]
        );

        return ApiResponse::json([
            'data' => $this->findLine($id),
            'credentials' => ['username' => $username, 'password' => $password],
        ], 201);
    }

    public function lineUpdate(ApiRequest $request, string $id): ApiResponse
    {
        $lineId = (int) $id;

        if ($this->findLine($lineId) === null) {
            return ApiResponse::error('Line not found', 404);
        }

        $data = $request->json();
        $allowed = [];

        if (array_key_exists('username', $data)) {
            $username = trim((string) $data['username']);

            if (!preg_match('/^[A-Za-z0-9_.-]{3,64}$/', $username)) {
                return ApiResponse::error('Username must be 3-64 chars (letters, numbers, . _ -)', 422);
            }

            $duplicate = Db::first('SELECT id FROM user_lines WHERE username = ? AND id <> ?', [$username, $lineId]);

            if ($duplicate !== null) {
                return ApiResponse::error('Line username already exists', 422);
            }

            $allowed['username'] = $username;
        }

        $password = trim((string) ($data['password'] ?? ''));

        if ($password !== '') {
            if (strlen($password) < 6) {
                return ApiResponse::error('Password must be at least 6 characters', 422);
            }

            $allowed['password_hash'] = password_hash($password, PASSWORD_ARGON2ID);
        }

        if (array_key_exists('exp_date', $data)) {
            $expDate = $this->expDate($data['exp_date']);

            if ($expDate === false) {
                return ApiResponse::error('Invalid expiration date', 422);
            }

            $allowed['exp_date'] = $expDate;
        }

        if (array_key_exists('max_connections', $data)) {
            $allowed['max_connections'] = max(1, (int) $data['max_connections']);
        }

        if (array_key_exists('enabled', $data)) {
            $allowed['enabled'] = !empty($data['enabled']) ? 1 : 0;
        }

        if (array_key_exists('user_id', $data)) {
            $userId = $this->ownerId($data['user_id']);

            if ($userId === false) {
                return ApiResponse::error('Owner user not found', 422);
            }

            $allowed['user_id'] = $userId;
        }

        if ($allowed === []) {
            return ApiResponse::json(['data' => $this->findLine($lineId)]);
        }

        $sets = implode(', ', array_map(static fn (string $key): string => $key . ' = ?', array_keys($allowed)));
        $params = array_values($allowed);
        $params[] = $lineId;

        Db::execute(sprintf('UPDATE user_lines SET %s WHERE id = ?', $sets), $params);

        return ApiResponse::json(['data' => $this->findLine($lineId)]);
    }

    public function lineDestroy(ApiRequest $request, string $id): ApiResponse
    {
        $deleted = Db::execute('DELETE FROM user_lines WHERE id = ?', [(int) $id]);

        return $deleted > 0
            ? ApiResponse::json(['success' => true])
            : ApiResponse::error('Line not found', 404);
    }

    public function lineResetPassword(ApiRequest $request, string $id): ApiResponse
    {
        $line = $this->findLine((int) $id);

        if ($line === null) {
            return ApiResponse::error('Line not found', 404);
        }

        $password = trim((string) ($request->json()['password'] ?? ''));

        if ($password === '') {
            $password = $this->randomPassword();
        } elseif (strlen($password) < 6) {
            return ApiResponse::error('Password must be at least 6 characters', 422);
        }

        Db::execute(
            'UPDATE user_lines SET password_hash = ? WHERE id = ?',
            [password_hash($password, PASSWORD_ARGON2ID), (int) $id]
        );

        return ApiResponse::json([
            'success' => true,
            'credentials' => ['username' => $line['username'], 'password' => $password],
        ]);
    }

    private function findLine(int $id): ?array
    {
        return Db::first(
            'SELECT l.id, l.user_id, l.username, l.exp_date, l.max_connections, l.enabled,
                    l.connected, l.bitrate, l.created_at, u.username AS owner,
                    CASE
                        WHEN l.enabled = 0 THEN \'disabled\'
                        WHEN l.exp_date IS NOT NULL AND l.exp_date <= ? THEN \'expired\'
                        ELSE \'active\'
                    END AS status
             FROM user_lines l
             LEFT JOIN users u ON u.id = l.user_id
             WHERE l.id = ?',
            [time(), $id]
        );
    }

    private function expDate(mixed $value): int|null|false
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (int) $value;
        }

        $value = trim((string) $value);

        // Date-only input (the UI date picker) expires at the end of that day UTC.
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value . ' 23:59:59', new \DateTimeZone('UTC'));

            return $date === false ? false : $date->getTimestamp();
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? false : $timestamp;
    }

    private function ownerId(mixed $value): int|null|false
    {
        if ($value === null || $value === '' || (int) $value === 0) {
            return null;
        }

        $userId = (int) $value;

        return Db::first('SELECT id FROM users WHERE id = ?', [$userId]) === null ? false : $userId;
    }

    private function randomPassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789';
        $password = '';

        for ($i = 0; $i < 12; $i++) {
            $password .= $alphabet[random_int(0, strlen($alphabet) - 1)];
        }

        return $password;
    }

    private function find(int $id): ?array
    {
        return Db::first(
            'SELECT id, username, email, enabled, is_admin, is_reseller, permissions, created_at, last_login FROM users WHERE id = ?',
            [$id]
        );
    }
}
