<?php

namespace ForkPanelXc\Modules\ExampleModule\Controllers;

use ForkPanelXc\Core\Http\Request;
use ForkPanelXc\Core\Http\Response;
use ForkPanelXc\Core\Validation\InputValidator;

class ExampleModuleController
{
    public function index(Request $request): Response
    {
        // Get query parameters
        $params = $request->getQueryParams();
        $page = (int)($params['page'] ?? 1);
        $perPage = (int)($params['per_page'] ?? 25);

        // In a real module, you would fetch from database
        $items = [
            ['id' => 1, 'name' => 'Item 1', 'status' => 'active', 'created_at' => time()],
            ['id' => 2, 'name' => 'Item 2', 'status' => 'inactive', 'created_at' => time() - 86400],
        ];

        return Response::json([
            'data' => $items,
            'total' => count($items),
            'page' => $page,
            'per_page' => $perPage,
            'last_page' => 1,
        ]);
    }

    public function store(Request $request): Response
    {
        $data = $request->getJsonBody();

        // Validate
        $required = ['name'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return Response::json(['error' => "Field '$field' is required"], 422);
            }
        }

        // Create item (in real module, save to database)
        $item = [
            'id' => rand(1000, 9999),
            'name' => $data['name'],
            'status' => $data['status'] ?? 'active',
            'created_at' => time(),
        ];

        return Response::json(['data' => $item], 201);
    }

    public function show(Request $request, int $id): Response
    {
        // In real module, fetch from database
        if ($id === 1) {
            return Response::json(['data' => [
                'id' => 1,
                'name' => 'Item 1',
                'status' => 'active',
                'description' => 'This is item 1',
                'created_at' => time(),
            ]]);
        }

        return Response::json(['error' => 'Item not found'], 404);
    }

    public function update(Request $request, int $id): Response
    {
        $data = $request->getJsonBody();

        // In real module, update in database
        if ($id === 1) {
            return Response::json(['data' => [
                'id' => 1,
                'name' => $data['name'] ?? 'Item 1',
                'status' => $data['status'] ?? 'active',
                'updated_at' => time(),
            ]]);
        }

        return Response::json(['error' => 'Item not found'], 404);
    }

    public function destroy(Request $request, int $id): Response
    {
        // In real module, delete from database
        if ($id === 1 || $id === 2) {
            return Response::json(['success' => true]);
        }

        return Response::json(['error' => 'Item not found'], 404);
    }
}