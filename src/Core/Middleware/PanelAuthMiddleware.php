<?php

declare(strict_types=1);

namespace ForkPanelXc\Core\Middleware;

use ForkPanelXc\Core\Http\ApiRequest;
use ForkPanelXc\Core\Http\ApiResponse;

final class PanelAuthMiddleware
{
    public function __invoke(ApiRequest $request): ?ApiResponse
    {
        $token = (string) ($request->post('token') ?? $request->query('token', ''));

        if ($token === '') {
            return ApiResponse::error('Token is required', 401);
        }

        return null;
    }
}
