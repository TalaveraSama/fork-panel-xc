# Fork Panel XC - Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0-beta] - 2024-XX-XX

### Added - Initial Fork Release

#### Architecture & Core
- **API-first architecture** - Clean REST API for all panel functionality
- **Modular plugin system** - Install, enable, disable, update modules without core modifications
- **Modern tech stack** - PHP 8.3, Vue 3, Vite, Pinia, Tailwind CSS
- **Real-time capabilities** - WebSocket support for live dashboard updates
- **Database migrations** - Version-controlled schema management

#### Frontend (Vue 3 + Vite)
- **Admin Panel** - Complete management interface
  - Dashboard with real-time metrics and charts
  - Streams management (Live, VOD, Series, Radio)
  - Stream detail tabs: overview, real stats (viewers/connections/bandwidth +
    last source test), inline settings (source/type/category/EPG ID/enabled) and
    per-stream audit log (`stream_logs`)
  - **Stream monitoring**: per-channel interval, `online/offline` source checks
    from the scheduler, Check now / Restart actions and monitor state in Stats
    (migration `008_stream_monitor.sql`)
  - Streams list shows green **Online** / red **Offline** (or **Disabled**)
    from monitor state, with 30 s auto-refresh and online/offline filters
  - Movies & Series management with TMDB integration
  - Users & Lines management
  - Resellers management
  - EPG sources and channels
  - Bouquets & Categories
  - Settings (General, Streaming, Appearance, Security, Cluster, Modules, Backup, Logs)
  - Tools (Diagnostics, Database, Cache)
  - Modules marketplace
- **Responsive design** - Mobile-friendly with collapsible sidebar
- **Dark/Light mode** - System preference detection with manual toggle
- **Component library** - Reusable UI components (DataTable, Modals, Toasts, Forms)
- **Internationalization ready** - i18n structure in place

#### Backend (PHP 8.3)
- **Domain-driven design** - Clean separation of concerns
  - Core: HTTP, Routing, Middleware, Validation, Logging, Error Handling
  - Domain: Stream, User, Movie, Series, Reseller, EPG, Bouquet, Category, Module
  - Public: Controllers for Admin API, Player API, Panel API, Streaming endpoints
  - CLI: Commands, Cron Jobs, Daemons
- **Authentication & Authorization**
  - JWT-based authentication with refresh tokens
  - Role-based access control (Admin, Reseller, User)
  - Permission system with granular controls
  - Two-factor authentication support
  - **Line (subscription) management**: create/edit/delete lines, owner user,
    expiration date, connection limit, password reset and expiry enforcement on
    player login
- **Streaming Endpoints**
  - Live streaming (/live/{token})
  - VOD streaming (/movie/{token})
  - Series streaming (/series/{token})
  - **Xtream Codes compatibility**: `/player_api.php`, `/get.php`, `/xmltv.php`
    and `/live|movie|series/{username}/{password}/{id}.{ext}` for IPTV Smarters,
    XCIPTV and TiviMate (line credentials). HLS playlists/segments are proxied
    through the panel via signed `/stream/{token}` URLs, with per-session
    viewer/connection/bandwidth stats. See `docs/XTREAM-COMPAT.md`.
  - **Connection limit**: `user_lines.max_connections` is enforced by the
    gateway; active sessions are listed in the stream Stats tab with a **Kick**
    action (60 s token revocation).
  - EPG XML generation (/epg/{token}.xml)
  - Player API compatibility
  - Panel API (Ministra/STB) compatibility
- **Module System**
  - ModuleInterface for consistent module structure
  - ModuleManager for discovery, installation, lifecycle management
  - Module permissions, routes, menu items, settings schema
  - Cron jobs, CLI commands, dashboard widgets, API endpoints
  - Example module included
  - **TMDB metadata module** (`tmdb`): search movies/series on themoviedb.org and
    import poster, backdrop, synopsis, year, runtime, genres and rating when
    creating VOD entries. API key configurable in Settings → Streaming or
    Settings → Modules, or with `TMDB_API_KEY`; responses cached 24 h. Requires
    `003_tmdb_metadata.sql`.
  - **Library Scanner module** (`library`): scans local folders, parses movie
    and episode filenames, creates streams/series/episodes, tracks files in
    `library_files`, marks removed files as missing, supports dry runs, CLI
    (`library:scan`) and periodic scans. Optional TMDB enrichment. Requires
    `004_library_index.sql`.
  - Dynamic module routes (`api_routes` in `module.json`) loaded only while the
    module is enabled

#### Installation & Deployment
- **Automated installer** (install.py) - Single command deployment
  - System dependencies (nginx, MariaDB, Redis, PHP-FPM)
  - Database setup with secure credentials
  - Nginx reverse proxy configuration
  - PHP-FPM pool configuration
  - Systemd services (PHP, Worker, Scheduler, WebSocket)
  - Cron jobs and log rotation
  - SSL certificate generation (Certbot)
- **Terminal installer** (install.sh) - one-command bootstrap for Ubuntu/Debian
  - `curl -fsSL …/install.sh | sudo bash`
  - Dedicated PHP-FPM pool running as `fork_panel_xc` (fixes 500 on login when
    the default `www-data` pool could not read `config/.env`)
  - Optional `--ssl` (certbot), `--domain`, `--admin-password`, `--skip-frontend`
  - Reuses `config/.env` credentials on reinstall
- **`console.php user:password <user> <pass>`** - reset a panel password from the
  terminal without the UI
- **Frontend build pipeline** - Vite with optimized chunks
- **Development workflow** - Hot reload, linting, formatting

#### Developer Experience
- **Code quality tools** - PHPStan, PHP_CodeSniffer, Rector
- **Testing** - PHPUnit for backend, Playwright for E2E
- **Documentation** - Comprehensive guides in docs/
- **OpenAPI specs** - API documentation for all endpoints

### Security
- Input validation and sanitization (global + action-level)
- Rate limiting middleware
- CORS and security headers
- Secure password hashing (Argon2id)
- JWT with short expiry and refresh rotation
- SQL injection prevention via prepared statements
- XSS protection via output encoding

### Migration from XC_VM
- Database migration tool included
- Configuration migration assistant
- Stream/user/reseller data import scripts
- Compatible with XC_VM activation keys

---

## Upcoming Features (Post-Beta)

### Planned for 1.1.0
- [ ] Multi-language support (i18n)
- [ ] Advanced EPG merge/scheduling
- [ ] Transcoding profiles management UI
- [ ] Custom dashboard widgets
- [ ] Module marketplace integration
- [ ] Backup/restore UI

### Planned for 1.2.0
- [ ] Kubernetes deployment manifests
- [ ] Prometheus/Grafana metrics export
- [ ] Advanced analytics & reporting
- [ ] CDN integration helpers
- [ ] Mobile app (React Native/Capacitor)

### Planned for 2.0.0
- [ ] GraphQL API
- [ ] Plugin SDK with CLI tools
- [ ] Visual stream composition editor
- [ ] AI-powered content recommendations
- [ ] Federated cluster management

---

## Versioning

This project uses [Semantic Versioning](https://semver.org/). For the versions available, see the [tags on this repository](https://github.com/fork-panel-xc/fork-panel-xc/tags).

## License

This project is licensed under the AGPL-3.0 License - see the [LICENSE](LICENSE) file for details.

## Acknowledgments

- **Vateron Media** - Original XC_VM project
- **All contributors** to XC_VM and this fork
- **Open source libraries** used in this project