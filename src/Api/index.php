<?php

declare(strict_types=1);

/**
 * Fork Panel XC API front controller.
 */

use ForkPanelXc\Core\Http\ApiRequest;
use ForkPanelXc\Core\Http\ApiResponse;

require __DIR__ . '/bootstrap.php';

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');

try {
    /** @var \ForkPanelXc\Core\Routing\Router $router */
    $router = require dirname(__DIR__) . '/routes/api.php';

    $response = $router->dispatch(new ApiRequest());
} catch (\Throwable $e) {
    error_log('[fork-panel-xc] ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());

    $debug = (bool) \ForkPanelXc\Core\Support\Env::get('APP_DEBUG', false);

    $response = ApiResponse::json([
        'error' => 'Internal server error',
        'detail' => $debug ? $e->getMessage() : null,
    ], 500);
}

$response->send();
