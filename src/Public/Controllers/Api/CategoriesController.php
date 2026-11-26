<?php

declare(strict_types=1);

namespace ForkPanelXc\Public\Controllers\Api;

use ForkPanelXc\Core\Http\ApiRequest;
use ForkPanelXc\Core\Http\ApiResponse;
use ForkPanelXc\Core\Support\Db;

class CategoriesController extends BaseApiController
{
    public function index(ApiRequest $request): ApiResponse
    {
        return ApiResponse::json([
            'data' => Db::select('SELECT id, category_name AS name, category_type AS type, sort_order FROM categories ORDER BY sort_order, category_name'),
        ]);
    }

    public function store(ApiRequest $request): ApiResponse
    {
        $data = $request->json();
        $errors = $this->require($data, ['name']);

        if ($errors !== null) {
            return ApiResponse::error('Validation failed', 422, $errors);
        }

        $id = Db::insert(
            'INSERT INTO categories (category_name, category_type, sort_order, created_at) VALUES (?, ?, ?, ?)',
            [(string) $data['name'], (string) ($data['type'] ?? 'live'), (int) ($data['sort_order'] ?? 0), time()]
        );

        return ApiResponse::json(['data' => $this->find($id)], 201);
    }

    public function update(ApiRequest $request, string $id): ApiResponse
    {
        $data = $request->json();
        $allowed = [];

        if (isset($data['name'])) {
            $allowed['category_name'] = (string) $data['name'];
        }
        if (isset($data['type'])) {
            $allowed['category_type'] = (string) $data['type'];
        }
        if (isset($data['sort_order'])) {
            $allowed['sort_order'] = (int) $data['sort_order'];
        }

        if ($allowed === []) {
            return ApiResponse::error('No supported fields', 422);
        }

        $sets = implode(', ', array_map(static fn (string $key): string => $key . ' = ?', array_keys($allowed)));
        $params = array_values($allowed);
        $params[] = (int) $id;

        Db::execute(sprintf('UPDATE categories SET %s WHERE id = ?', $sets), $params);

        return ApiResponse::json(['data' => $this->find((int) $id)]);
    }

    public function destroy(ApiRequest $request, string $id): ApiResponse
    {
        Db::execute('UPDATE streams SET category_id = NULL WHERE category_id = ?', [(int) $id]);
        $deleted = Db::execute('DELETE FROM categories WHERE id = ?', [(int) $id]);

        return $deleted > 0 ? ApiResponse::json(['success' => true]) : ApiResponse::error('Category not found', 404);
    }

    public function reorder(ApiRequest $request): ApiResponse
    {
        $ids = $request->json()['ids'] ?? [];

        if (!is_array($ids)) {
            return ApiResponse::error('IDs array required', 422);
        }

        foreach (array_values($ids) as $index => $id) {
            Db::execute('UPDATE categories SET sort_order = ? WHERE id = ?', [$index, (int) $id]);
        }

        return ApiResponse::json(['success' => true]);
    }

    private function find(int $id): ?array
    {
        return Db::first('SELECT id, category_name AS name, category_type AS type, sort_order FROM categories WHERE id = ?', [$id]);
    }
}
