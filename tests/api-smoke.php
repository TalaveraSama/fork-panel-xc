<?php

declare(strict_types=1);

/**
 * Database-free smoke test for the Fork Panel XC API runtime.
 *
 * Run:  php tests/api-smoke.php
 */

require dirname(__DIR__) . '/src/Api/bootstrap.php';

use ForkPanelXc\Core\Http\ApiRequest;
use ForkPanelXc\Core\Http\ApiResponse;
use ForkPanelXc\Core\Routing\Router;
use ForkPanelXc\Core\Support\Jwt;
use ForkPanelXc\Core\Support\StreamToken;

$failures = 0;

function check(string $label, bool $condition, string $detail = ''): void
{
    global $failures;

    if ($condition) {
        echo "  ok   {$label}\n";

        return;
    }

    $failures++;
    echo "  FAIL {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
}

function request(string $method, string $uri): ApiRequest
{
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $uri;
    $_GET = [];
    $_POST = [];

    return new ApiRequest();
}

echo "Router\n";

class SmokeBlockMw
{
    public function __invoke(ApiRequest $request): ?ApiResponse
    {
        return ApiResponse::error('blocked', 403);
    }
}

$router = new Router();
$router->get('/t/{id}', static fn (ApiRequest $r, string $id): ApiResponse => ApiResponse::json(['id' => (int) $id]));
$router->group('/api', function (Router $router): void {
    $router->post('/thing', static fn (): ApiResponse => ApiResponse::json(['created' => true], 201));
});
$router->get('/blocked', static fn (): ApiResponse => ApiResponse::json(['never' => true]), [SmokeBlockMw::class]);

$response = $router->dispatch(request('GET', '/t/42'));
check('route parameter', $response->status() === 200 && str_contains($response->body(), '"id":42'), $response->body());

$response = $router->dispatch(request('GET', '/t/42/'));
check('trailing slash tolerated', $response->status() === 200);

$response = $router->dispatch(request('POST', '/t/42'));
check('405 on wrong method', $response->status() === 405 && ($response->headers()['Allow'] ?? '') === 'GET');

$response = $router->dispatch(request('GET', '/missing'));
check('404 on unknown route', $response->status() === 404);

$response = $router->dispatch(request('POST', '/api/thing'));
check('group prefix + 201', $response->status() === 201 && str_contains($response->body(), 'created'), $response->body());

$response = $router->dispatch(request('GET', '/blocked'));
check('middleware short-circuit', $response->status() === 403 && str_contains($response->body(), 'blocked'), $response->body());

$router->get('/script/{id}.xml', static fn (ApiRequest $r, string $id): ApiResponse => ApiResponse::raw('<id>' . $id . '</id>', 'application/xml'));
$response = $router->dispatch(request('GET', '/script/9.xml'));
check('suffix route + raw body', $response->status() === 200 && $response->body() === '<id>9</id>', $response->body());

echo "JWT\n";

putenv('JWT_SECRET=smoke-secret');
putenv('JWT_TTL=60');
putenv('JWT_REFRESH_TTL=10080');

[$access, $refresh] = Jwt::issue(7);
$claims = Jwt::decode($access);
check('access round-trip', is_array($claims) && (int) $claims['sub'] === 7 && $claims['typ'] === 'access');
$refreshClaims = Jwt::decode($refresh);
check('refresh round-trip', is_array($refreshClaims) && $refreshClaims['typ'] === 'refresh');
check('tampered token rejected', Jwt::decode($access . 'x') === null);
check('wrong secret rejected', Jwt::decode($access, 'other-secret') === null);

$expired = Jwt::encode(['sub' => 1, 'typ' => 'access', 'exp' => time() - 10]);
check('expired token rejected', Jwt::decode($expired) === null);

echo "Stream tokens\n";

putenv('STREAM_TOKEN_SECRET=stream-secret');
putenv('STREAM_TOKEN_TTL=3600');

$token = StreamToken::issue(3, 99, 'live');
$payload = StreamToken::parse($token);
check('stream token round-trip', is_array($payload) && (int) $payload['line'] === 3 && (int) $payload['stream'] === 99, var_export($payload, true));
check('tampered stream token rejected', StreamToken::parse($token . 'x') === null);

echo "ApiRequest\n";

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/api/streams?page=2&search=news';
$_GET = ['page' => '2', 'search' => 'news'];
$_POST = [];
$_SERVER['HTTP_AUTHORIZATION'] = 'Bearer abc.def.ghi';

$request = new ApiRequest();
check('query params', $request->query('page') === '2' && $request->query('search') === 'news');
check('path without query', $request->path() === '/api/streams', $request->path());
check('bearer token extracted', $request->bearerToken() === 'abc.def.ghi');
check('query default', $request->query('missing', 'fallback') === 'fallback');

echo "\n" . ($failures === 0 ? 'All checks passed.' : "{$failures} check(s) failed.") . "\n";
exit($failures === 0 ? 0 : 1);
