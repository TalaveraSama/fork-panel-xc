# Fork Panel XC — architecture notes

Fork Panel XC keeps the original XC_VM panel code intact under the `XcVm\`
namespace and adds a new, API-first layer under `ForkPanelXc\`. Both namespaces
are mapped to `src/` in `src/composer.json`.

```
src/
├─ Api/                     # New API runtime
│  ├─ bootstrap.php         # Standalone PSR-4 loader (ForkPanelXc\ + XcVm\)
│  ├─ index.php             # Front controller for /api, /player_api, /panel_api, streaming
│  └─ migrations/           # SQL migrations (applied by console.php db:migrate)
├─ Core/
│  ├─ Http/ApiRequest.php   # JSON request wrapper
│  ├─ Http/ApiResponse.php  # JSON/raw/redirect responses
│  ├─ Routing/Router.php    # Regex router with groups + middleware aliases
│  ├─ Middleware/           # auth, permission, rate_limit, player_auth, panel_auth
│  └─ Support/              # Env, Db (PDO), Jwt (HS256), StreamToken (HMAC)
├─ Domain/
│  ├─ Auth/AuthService.php  # Login, JWT validation, password change
│  ├─ User/PanelUser.php    # Panel user entity
│  └─ Module/               # Module contract + manifest discovery
├─ Public/Controllers/Api/  # One controller per resource
├─ Modules/ExampleModule/   # Reference module (module.json + controllers/jobs/commands)
└─ routes/api.php           # Route table
```

## Request flow

1. nginx routes `/api/*`, `/player_api/*`, `/panel_api/*` and `/live|movie|series|epg/*`
   to `src/Api/index.php`.
2. `bootstrap.php` registers the autoloader and loads `config/.env`.
3. `routes/api.php` builds the router; `ApiRequest` wraps the globals.
4. Middleware runs in order: `rate_limit` (public auth) → `auth` → `permission:<name>`.
5. The controller returns an `ApiResponse`, which writes status, headers and body.

## Authentication

- `POST /api/auth/login` → `access_token` + `refresh_token` (HS256, `JWT_TTL` minutes).
- `GET /api/auth/me` returns the user, roles and permissions.
- Admins have every permission; other users get the JSON array stored in
  `users.permissions` (e.g. `["streams.view","users.view"]`).
- `POST /api/auth/refresh` rotates the access token.

Two-factor verification is intentionally closed until the TOTP bridge to the
original panel is wired: `verifyTwoFactor()` rejects codes instead of accepting
any value.

## Streaming URLs

`StreamToken::issue($lineId, $streamId, $kind)` produces
`base64(payload).hmac`. The token expires after `STREAM_TOKEN_TTL` seconds.

- `GET /live/{token}`, `/movie/{token}`, `/series/{token}` → 302 to the source
  (only `http`/`https` sources are accepted).
- `GET /epg/{token}.xml` → XMLTV document built from `epg_programmes`.

## Module system

A module is a directory under `src/Modules/<Name>/` with a `module.json`
manifest (`id`, `name`, `version`, `description`, `author`, `permissions`,
optional `cron_jobs`, `cli_commands`, `dashboard_widgets`, `api_endpoints`).

HTTP routes declared in `module.json` under `api_routes` are registered at
runtime by `ForkPanelXc\Domain\Module\ModuleRouteLoader`, but only while the
module is installed and enabled in the `modules` table:

```json
"api_routes": [
  {
    "method": "GET",
    "path": "/api/tmdb/search",
    "handler": "ForkPanelXc\\Modules\\Tmdb\\Controllers\\TmdbController@search",
    "middleware": ["auth", "permission:tmdb.use"]
  }
]
```

Disabling a module removes its routes on the next request (404), so a broken
module cannot take down the API.

```
php console.php module:list
php console.php module:install example_module
php console.php module:enable example_module
php console.php module:disable example_module
php console.php module:uninstall example_module
```

The install state lives in the `modules` table; the manifest stays the source of
truth for metadata. Modules are only loaded when the `ModulesController` or a
module-aware endpoint touches them, so a broken module cannot take down the API.

## Database

- All migrations live in `src/Api/migrations/*.sql` and are applied by
  `php console.php db:migrate` (tracked in the `migrations` table).
- Timestamps are Unix seconds (`BIGINT`).
- The first `db:migrate` seeds the `admin` user with
  `APP_ADMIN_PASSWORD` (printed by the installer).

## Frontend

Vue 3 + Vite + Pinia + Tailwind. `frontend/vite.config.js` proxies `/api`,
`/player_api` and `/panel_api` to `http://localhost:8080` in development and
builds into `frontend/dist`.

Routes live in `frontend/src/router/index.js`; permissions are checked in the
navigation guard via `useAuthStore().hasPermissions()`.

## What is intentionally still thin

- Billing/credits flows, MAG/Enigma device provisioning UI, EPG merge editor and
  the transcoding profile editor are not implemented in this first fork drop.
- WebSocket push is not part of this drop; the dashboard polls
  `/api/dashboard/stats` every 30 s and reconnects the optional socket if one is
  deployed later.
