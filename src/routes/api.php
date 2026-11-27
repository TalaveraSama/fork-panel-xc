<?php

declare(strict_types=1);

use ForkPanelXc\Core\Middleware\AuthMiddleware;
use ForkPanelXc\Core\Middleware\PanelAuthMiddleware;
use ForkPanelXc\Core\Middleware\PlayerAuthMiddleware;
use ForkPanelXc\Core\Routing\Router;
use ForkPanelXc\Public\Controllers\Api\AuthController;
use ForkPanelXc\Public\Controllers\Api\BouquetsController;
use ForkPanelXc\Public\Controllers\Api\CategoriesController;
use ForkPanelXc\Public\Controllers\Api\DashboardController;
use ForkPanelXc\Public\Controllers\Api\EpgController;
use ForkPanelXc\Public\Controllers\Api\ModulesController;
use ForkPanelXc\Public\Controllers\Api\MoviesController;
use ForkPanelXc\Public\Controllers\Api\PanelApiController;
use ForkPanelXc\Public\Controllers\Api\PlayerApiController;
use ForkPanelXc\Public\Controllers\Api\ResellersController;
use ForkPanelXc\Public\Controllers\Api\SeriesController;
use ForkPanelXc\Public\Controllers\Api\SettingsController;
use ForkPanelXc\Public\Controllers\Api\StreamingController;
use ForkPanelXc\Public\Controllers\Api\StreamsController;
use ForkPanelXc\Public\Controllers\Api\ToolsController;
use ForkPanelXc\Public\Controllers\Api\UsersController;

$router = new Router();

// ---- Public auth endpoints -------------------------------------------------
$router->group('/api', function (Router $router): void {
    $router->group('/auth', function (Router $router): void {
        $router->post('/login', [AuthController::class, 'login'], ['rate_limit:10,60']);
        $router->post('/2fa/verify', [AuthController::class, 'verifyTwoFactor'], ['rate_limit:10,60']);
        $router->post('/refresh', [AuthController::class, 'refresh'], ['rate_limit:30,60']);
        $router->post('/forgot-password', [AuthController::class, 'forgotPassword'], ['rate_limit:5,60']);
    });
});

// ---- Authenticated panel API ----------------------------------------------
$router->group('/api', function (Router $router): void {
    $router->group('/auth', function (Router $router): void {
        $router->post('/logout', [AuthController::class, 'logout']);
        $router->get('/me', [AuthController::class, 'me']);
        $router->put('/profile', [AuthController::class, 'updateProfile']);
        $router->put('/password', [AuthController::class, 'changePassword']);
    });

    $router->get('/dashboard/stats', [DashboardController::class, 'stats']);
    $router->get('/dashboard/activity', [DashboardController::class, 'activity']);

    $router->group('/streams', function (Router $router): void {
        $router->get('', [StreamsController::class, 'index']);
        $router->post('', [StreamsController::class, 'store'], ['permission:streams.create']);
        $router->get('/categories', [StreamsController::class, 'categories']);
        $router->get('/export', [StreamsController::class, 'export']);
        $router->post('/import', [StreamsController::class, 'import'], ['permission:streams.create']);
        $router->delete('', [StreamsController::class, 'bulkDelete'], ['permission:streams.delete']);
        $router->patch('/bulk', [StreamsController::class, 'bulkUpdate'], ['permission:streams.edit']);
        $router->get('/{id}', [StreamsController::class, 'show']);
        $router->put('/{id}', [StreamsController::class, 'update'], ['permission:streams.edit']);
        $router->delete('/{id}', [StreamsController::class, 'destroy'], ['permission:streams.delete']);
        $router->get('/{id}/stats', [StreamsController::class, 'stats']);
        $router->post('/{id}/test', [StreamsController::class, 'test']);
    });

    $router->group('/movies', function (Router $router): void {
        $router->get('', [MoviesController::class, 'index']);
        $router->post('', [MoviesController::class, 'store'], ['permission:vod.create']);
        $router->get('/{id}', [MoviesController::class, 'show']);
        $router->put('/{id}', [MoviesController::class, 'update'], ['permission:vod.edit']);
        $router->delete('/{id}', [MoviesController::class, 'destroy'], ['permission:vod.delete']);
    });

    $router->group('/series', function (Router $router): void {
        $router->get('', [SeriesController::class, 'index']);
        $router->post('', [SeriesController::class, 'store'], ['permission:vod.create']);
        $router->get('/{id}', [SeriesController::class, 'show']);
        $router->put('/{id}', [SeriesController::class, 'update'], ['permission:vod.edit']);
        $router->delete('/{id}', [SeriesController::class, 'destroy'], ['permission:vod.delete']);
        $router->get('/{id}/episodes', [SeriesController::class, 'episodes']);
        $router->post('/{id}/episodes', [SeriesController::class, 'storeEpisode'], ['permission:vod.edit']);
    });

    $router->group('/users', function (Router $router): void {
        $router->get('', [UsersController::class, 'index']);
        $router->post('', [UsersController::class, 'store'], ['permission:users.create']);
        $router->delete('', [UsersController::class, 'bulkDelete'], ['permission:users.delete']);
        $router->patch('/bulk', [UsersController::class, 'bulkUpdate'], ['permission:users.edit']);
        $router->get('/{id}', [UsersController::class, 'show']);
        $router->put('/{id}', [UsersController::class, 'update'], ['permission:users.edit']);
        $router->delete('/{id}', [UsersController::class, 'destroy'], ['permission:users.delete']);
        $router->get('/{id}/stats', [UsersController::class, 'stats']);
        $router->get('/{id}/activity', [UsersController::class, 'activity']);
        $router->post('/{id}/reset-password', [UsersController::class, 'resetPassword'], ['permission:users.edit']);
        $router->patch('/{id}/status', [UsersController::class, 'toggleStatus'], ['permission:users.edit']);
        $router->post('/{id}/bouquets', [UsersController::class, 'assignBouquet'], ['permission:users.edit']);
        $router->delete('/{id}/bouquets/{bouquetId}', [UsersController::class, 'removeBouquet'], ['permission:users.edit']);
    });

    $router->group('/lines', function (Router $router): void {
        $router->get('', [UsersController::class, 'linesIndex']);
        $router->post('', [UsersController::class, 'lineStore'], ['permission:lines.create']);
        $router->get('/{id}', [UsersController::class, 'lineShow']);
        $router->put('/{id}', [UsersController::class, 'lineUpdate'], ['permission:lines.edit']);
        $router->delete('/{id}', [UsersController::class, 'lineDestroy'], ['permission:lines.delete']);
        $router->post('/{id}/reset-password', [UsersController::class, 'lineResetPassword'], ['permission:lines.edit']);
    });

    $router->group('/resellers', function (Router $router): void {
        $router->get('', [ResellersController::class, 'index']);
        $router->post('', [ResellersController::class, 'store'], ['permission:resellers.create']);
        $router->get('/{id}', [ResellersController::class, 'show']);
        $router->put('/{id}', [ResellersController::class, 'update'], ['permission:resellers.edit']);
        $router->delete('/{id}', [ResellersController::class, 'destroy'], ['permission:resellers.delete']);
    });

    $router->group('/epg', function (Router $router): void {
        $router->get('', [EpgController::class, 'index']);
        $router->get('/sources', [EpgController::class, 'sources']);
        $router->post('/sources', [EpgController::class, 'storeSource'], ['permission:epg.sources']);
        $router->put('/sources/{id}', [EpgController::class, 'updateSource'], ['permission:epg.sources']);
        $router->delete('/sources/{id}', [EpgController::class, 'deleteSource'], ['permission:epg.sources']);
        $router->post('/sources/{id}/sync', [EpgController::class, 'syncSource'], ['permission:epg.sources']);
        $router->get('/channels', [EpgController::class, 'channels']);
    });

    $router->group('/bouquets', function (Router $router): void {
        $router->get('', [BouquetsController::class, 'index']);
        $router->post('', [BouquetsController::class, 'store'], ['permission:bouquets.create']);
        $router->get('/{id}', [BouquetsController::class, 'show']);
        $router->put('/{id}', [BouquetsController::class, 'update'], ['permission:bouquets.edit']);
        $router->delete('/{id}', [BouquetsController::class, 'destroy'], ['permission:bouquets.delete']);
        $router->post('/{id}/streams', [BouquetsController::class, 'addStream'], ['permission:bouquets.edit']);
        $router->delete('/{id}/streams/{streamId}', [BouquetsController::class, 'removeStream'], ['permission:bouquets.edit']);
    });

    $router->group('/categories', function (Router $router): void {
        $router->get('', [CategoriesController::class, 'index']);
        $router->post('', [CategoriesController::class, 'store'], ['permission:categories.create']);
        $router->patch('/order', [CategoriesController::class, 'reorder'], ['permission:categories.edit']);
        $router->put('/{id}', [CategoriesController::class, 'update'], ['permission:categories.edit']);
        $router->delete('/{id}', [CategoriesController::class, 'destroy'], ['permission:categories.delete']);
    });

    $router->group('/settings', function (Router $router): void {
        $router->get('', [SettingsController::class, 'index']);
        $router->get('/general', [SettingsController::class, 'general']);
        $router->put('/general', [SettingsController::class, 'updateGeneral'], ['permission:settings.general']);
        $router->get('/streaming', [SettingsController::class, 'streaming']);
        $router->put('/streaming', [SettingsController::class, 'updateStreaming'], ['permission:settings.streaming']);
        $router->get('/appearance', [SettingsController::class, 'appearance']);
        $router->put('/appearance', [SettingsController::class, 'updateAppearance'], ['permission:settings.appearance']);
        $router->get('/security', [SettingsController::class, 'security']);
        $router->put('/security', [SettingsController::class, 'updateSecurity'], ['permission:settings.security']);
        $router->get('/cluster', [SettingsController::class, 'cluster']);
        $router->put('/cluster', [SettingsController::class, 'updateCluster'], ['permission:settings.cluster']);
        $router->get('/modules', [SettingsController::class, 'modules']);
        $router->get('/backup', [SettingsController::class, 'backup']);
        $router->post('/backup', [SettingsController::class, 'createBackup'], ['permission:settings.backup']);
        $router->get('/logs', [SettingsController::class, 'logs']);
    });

    $router->group('/tools', function (Router $router): void {
        $router->get('/diagnostics', [ToolsController::class, 'diagnostics']);
        $router->post('/diagnostics/stream', [ToolsController::class, 'testStream']);
        $router->get('/database', [ToolsController::class, 'database']);
        $router->post('/database/migrate', [ToolsController::class, 'runMigrations'], ['permission:tools.database']);
        $router->get('/cache', [ToolsController::class, 'cache']);
        $router->post('/cache/clear', [ToolsController::class, 'clearCache'], ['permission:tools.cache']);
    });

    $router->group('/modules', function (Router $router): void {
        $router->get('', [ModulesController::class, 'index']);
        $router->get('/{id}', [ModulesController::class, 'show']);
        $router->post('/{id}/install', [ModulesController::class, 'install'], ['permission:modules.install']);
        $router->post('/{id}/update', [ModulesController::class, 'update'], ['permission:modules.update']);
        $router->post('/{id}/uninstall', [ModulesController::class, 'uninstall'], ['permission:modules.uninstall']);
        $router->post('/{id}/enable', [ModulesController::class, 'enable'], ['permission:modules.manage']);
        $router->post('/{id}/disable', [ModulesController::class, 'disable'], ['permission:modules.manage']);
    });
}, ['auth']);

// ---- Player API (Xtream-compatible) ---------------------------------------
$router->group('/player_api', function (Router $router): void {
    $router->get('/login', [PlayerApiController::class, 'login']);
    $router->get('/live_categories', [PlayerApiController::class, 'liveCategories']);
    $router->get('/vod_categories', [PlayerApiController::class, 'vodCategories']);
    $router->get('/series_categories', [PlayerApiController::class, 'seriesCategories']);
    $router->get('/live_streams', [PlayerApiController::class, 'liveStreams']);
    $router->get('/vod_streams', [PlayerApiController::class, 'vodStreams']);
    $router->get('/series', [PlayerApiController::class, 'series']);
    $router->get('/series_info', [PlayerApiController::class, 'seriesInfo']);
    $router->get('/episode_sources', [PlayerApiController::class, 'episodeSources']);
}, ['player_auth']);

// ---- Panel API (Ministra/MAG) ---------------------------------------------
$router->group('/panel_api', function (Router $router): void {
    $router->post('/login', [PanelApiController::class, 'login']);
    $router->get('/get_channels', [PanelApiController::class, 'getChannels']);
    $router->get('/get_epg', [PanelApiController::class, 'getEpg']);
}, ['panel_auth']);

// ---- Streaming endpoints (signed tokens) ----------------------------------
$router->get('/live/{token}', [StreamingController::class, 'live']);
$router->get('/movie/{token}', [StreamingController::class, 'movie']);
$router->get('/series/{token}', [StreamingController::class, 'series']);
$router->get('/epg/{token}.xml', [StreamingController::class, 'epgXml']);

// ---- Module-provided routes (installed + enabled modules) -----------------
\ForkPanelXc\Domain\Module\ModuleRouteLoader::register($router);

return $router;
