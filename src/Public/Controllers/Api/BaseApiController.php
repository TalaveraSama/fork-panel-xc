<?php

declare(strict_types=1);

namespace ForkPanelXc\Public\Controllers\Api;

use ForkPanelXc\Core\Http\ApiRequest;

abstract class BaseApiController
{
    protected function pagination(ApiRequest $request): array
    {
        $page = max(1, (int) $request->query('page', 1));
        $perPage = (int) $request->query('per_page', 25);
        $perPage = $perPage < 1 ? 25 : min($perPage, 200);

        return [$page, $perPage, ($page - 1) * $perPage];
    }

    protected function sort(ApiRequest $request, array $allowed, string $default = 'id'): array
    {
        $field = (string) $request->query('sort_field', $default);
        $field = in_array($field, $allowed, true) ? $field : $default;
        $direction = strtolower((string) $request->query('sort_direction', 'desc')) === 'asc' ? 'ASC' : 'DESC';

        return [$field, $direction];
    }

    protected function paginated(array $rows, int $total, int $page, int $perPage): array
    {
        return [
            'data' => $rows,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => $perPage > 0 ? (int) ceil($total / $perPage) : 1,
        ];
    }

    protected function require(array $data, array $fields): ?array
    {
        $errors = [];

        foreach ($fields as $field) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $errors[$field] = [sprintf('%s is required', $field)];
            }
        }

        return $errors === [] ? null : $errors;
    }
}
