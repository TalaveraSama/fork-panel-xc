<?php

declare(strict_types=1);

namespace ForkPanelXc\Core\Middleware;

use ForkPanelXc\Core\Http\ApiRequest;
use ForkPanelXc\Core\Http\ApiResponse;
use ForkPanelXc\Domain\Auth\AuthService;

final class AuthMiddleware
{
    public function __invoke(ApiRequest $request): ?ApiResponse
    {
        $token = $request->bearerToken();

        if ($token === null) {
            return ApiResponse::error('Authentication required', 401);
        }

        $user = (new AuthService())->validateToken($token);

        if ($user === null) {
            return ApiResponse::error('Invalid or expired token', 401);
        }

        $request->setUser($user);

        return null;
    }
}
