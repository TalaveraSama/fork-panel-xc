<?php

declare(strict_types=1);

namespace ForkPanelXc\Core\Middleware;

use ForkPanelXc\Core\Http\ApiRequest;
use ForkPanelXc\Core\Http\ApiResponse;
use ForkPanelXc\Domain\User\User;

final class PermissionMiddleware
{
    public function __invoke(ApiRequest $request, string $permission = ''): ?ApiResponse
    {
        $user = $request->user();

        if (!$user instanceof User) {
            return ApiResponse::error('Authentication required', 401);
        }

        if ($user->hasRole('admin')) {
            return null;
        }

        if ($permission !== '' && !$user->hasPermission($permission)) {
            return ApiResponse::error('Insufficient permissions', 403);
        }

        return null;
    }
}
