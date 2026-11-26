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
- **Streaming Endpoints**
  - Live streaming (/live/{token})
  - VOD streaming (/movie/{token})
  - Series streaming (/series/{token})
  - EPG XML generation (/epg/{token}.xml)
  - Player API compatibility
  - Panel API (Ministra/STB) compatibility
- **Module System**
  - ModuleInterface for consistent module structure
  - ModuleManager for discovery, installation, lifecycle management
  - Module permissions, routes, menu items, settings schema
  - Cron jobs, CLI commands, dashboard widgets, API endpoints
  - Example module included

#### Installation & Deployment
- **Automated installer** (install.py) - Single command deployment
  - System dependencies (nginx, MariaDB, Redis, PHP-FPM)
  - Database setup with secure credentials
  - Nginx reverse proxy configuration
  - PHP-FPM pool configuration
  - Systemd services (PHP, Worker, Scheduler, WebSocket)
  - Cron jobs and log rotation
  - SSL certificate generation (Certbot)
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