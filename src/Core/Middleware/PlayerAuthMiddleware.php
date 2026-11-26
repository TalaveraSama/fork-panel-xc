<?php

declare(strict_types=1);

namespace ForkPanelXc\Core\Middleware;

use ForkPanelXc\Core\Http\ApiRequest;
use ForkPanelXc\Core\Http\ApiResponse;

final class PlayerAuthMiddleware
{
    public function __invoke(ApiRequest $request): ?ApiResponse
    {
        $username = (string) $request->query('username', '');
        $password = (string) $request->query('password', '');

        if ($username === '' || $password === '') {
            return ApiResponse::error('Username and password are required', 401);
        }

        return null;
    }
}
