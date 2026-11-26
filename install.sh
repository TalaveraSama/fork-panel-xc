#!/usr/bin/env bash
#
# Fork Panel XC — terminal installer for Ubuntu / Debian.
#
# Fresh install:
#   sudo bash install.sh
#   curl -fsSL https://raw.githubusercontent.com/TalaveraSama/fork-panel-xc/main/install.sh | sudo bash
#
# Options:
#   --domain <fqdn>           Public hostname for the panel (optional)
#   --email <address>         Let's Encrypt email (implies --ssl when used with --domain)
#   --ssl                     Request a Let's Encrypt certificate with certbot
#   --admin-password <pass>   Initial admin password (generated when omitted)
#   --tmdb-api-key <key>      TMDB v3 API key for movie/series metadata
#   --dir <path>              Install directory (default: /home/fork_panel_xc)
#   --branch <name>           Git branch to install (default: main)
#   --repo <url>              Git repository to clone when not running from a checkout
#   --skip-frontend           Reuse an existing frontend build instead of building
#   --force                   Continue on unsupported distributions
#   -h, --help                Show this help
#
set -euo pipefail

APP_USER="fork_panel_xc"
APP_DIR="${APP_DIR:-/home/fork_panel_xc}"
REPO_URL="${REPO_URL:-https://github.com/TalaveraSama/fork-panel-xc.git}"
BRANCH="${BRANCH:-main}"
DOMAIN=""
EMAIL=""
ADMIN_PASSWORD=""
TMDB_KEY=""
ENABLE_SSL=0
SKIP_FRONTEND=0
FORCE=0
NODE_MAJOR=20

# ---------------------------------------------------------------- output ----
if [ -t 1 ]; then
  C_RESET=$'\033[0m'; C_RED=$'\033[91m'; C_GREEN=$'\033[92m'
  C_YELLOW=$'\033[93m'; C_BLUE=$'\033[94m'; C_BOLD=$'\033[1m'
else
  C_RESET=""; C_RED=""; C_GREEN=""; C_YELLOW=""; C_BLUE=""; C_BOLD=""
fi

log()   { printf '%s▶ %s%s\n' "$C_BLUE" "$*" "$C_RESET"; }
ok()    { printf '%s✓ %s%s\n' "$C_GREEN" "$*" "$C_RESET"; }
warn()  { printf '%s⚠ %s%s\n' "$C_YELLOW" "$*" "$C_RESET"; }
die()   { printf '%s✗ %s%s\n' "$C_RED" "$*" "$C_RESET" >&2; exit 1; }

usage() {
  cat <<'USAGE'
Fork Panel XC — terminal installer (Ubuntu / Debian)

Usage:
  sudo bash install.sh [options]
  curl -fsSL https://raw.githubusercontent.com/TalaveraSama/fork-panel-xc/main/install.sh | sudo bash

Options:
  --domain <fqdn>           Public hostname for the panel (optional)
  --email <address>         Let's Encrypt email (default: admin@<domain>)
  --ssl                     Request a Let's Encrypt certificate with certbot
  --admin-password <pass>   Initial admin password (generated when omitted)
  --tmdb-api-key <key>      TMDB v3 API key for movie/series metadata
  --dir <path>              Install directory (default: /home/fork_panel_xc)
  --branch <name>           Git branch to install (default: main)
  --repo <url>              Repository to clone when not running from a checkout
  --skip-frontend           Reuse an existing frontend build instead of building
  --force                   Continue on unsupported distributions
  -h, --help                Show this help
USAGE
  exit 0
}

# ------------------------------------------------------------------ args ----
while [ $# -gt 0 ]; do
  case "$1" in
    --domain) DOMAIN="${2:-}"; shift 2 ;;
    --email) EMAIL="${2:-}"; shift 2 ;;
    --ssl) ENABLE_SSL=1; shift ;;
    --admin-password) ADMIN_PASSWORD="${2:-}"; shift 2 ;;
    --tmdb-api-key) TMDB_KEY="${2:-}"; shift 2 ;;
    --dir) APP_DIR="${2:-}"; shift 2 ;;
    --branch) BRANCH="${2:-}"; shift 2 ;;
    --repo) REPO_URL="${2:-}"; shift 2 ;;
    --skip-frontend) SKIP_FRONTEND=1; shift ;;
    --force) FORCE=1; shift ;;
    -h|--help) usage ;;
    *) die "Unknown option: $1 (see --help)" ;;
  esac
done

if [ "$ENABLE_SSL" -eq 1 ] && [ -z "$DOMAIN" ]; then
  die "--ssl requires --domain <fqdn>"
fi

if [ -z "$EMAIL" ] && [ -n "$DOMAIN" ]; then
  EMAIL="admin@${DOMAIN}"
fi

# ------------------------------------------------------------- pre-flight ----
[ "$(id -u)" -eq 0 ] || die "Run this installer as root (sudo bash install.sh)."

if [ -f /etc/os-release ]; then
  # shellcheck disable=SC1091
  . /etc/os-release
  case "${ID:-} ${ID_LIKE:-}" in
    *ubuntu*|*debian*) : ;;
    *)
      if [ "$FORCE" -eq 1 ]; then
        warn "Unsupported distribution '${ID:-unknown}', continuing because of --force."
      else
        die "Unsupported distribution '${ID:-unknown}'. Ubuntu/Debian only (use --force to override)."
      fi
      ;;
  esac
else
  die "Cannot detect the distribution (/etc/os-release missing)."
fi

command -v curl >/dev/null 2>&1 || {
  apt-get update -qq && apt-get install -y -qq curl ca-certificates
}

# ------------------------------------------------------------ dependencies ----
log "Installing system packages"
export DEBIAN_FRONTEND=noninteractive
apt-get update -qq
apt-get install -y -qq --no-install-recommends \
  ca-certificates curl git unzip cron logrotate \
  nginx mariadb-server redis-server \
  php-fpm php-cli php-mysql php-redis php-curl php-mbstring php-xml php-zip php-gd php-intl

if [ "$ENABLE_SSL" -eq 1 ]; then
  apt-get install -y -qq --no-install-recommends certbot python3-certbot-nginx
fi
ok "Packages installed"

PHP_VERSION="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;' 2>/dev/null || echo 8.3)"
if [ ! -d "/etc/php/${PHP_VERSION}/fpm" ]; then
  PHP_VERSION="$(ls -1 /etc/php 2>/dev/null | sort -V | tail -n1 || echo "$PHP_VERSION")"
fi
PHP_SOCKET="/run/php/php${PHP_VERSION}-fpm.sock"
APP_PHP_SOCKET="/run/php/php${PHP_VERSION}-fork_panel_xc.sock"

install_node() {
  local current=""
  if command -v node >/dev/null 2>&1; then
    current="$(node -p 'process.versions.node.split(".")[0]' 2>/dev/null || echo 0)"
  fi

  if [ "${current:-0}" -ge 18 ] 2>/dev/null; then
    ok "Node.js $(node -v) already available"
    return
  fi

  log "Installing Node.js ${NODE_MAJOR}.x (needed to build the frontend)"
  curl -fsSL "https://deb.nodesource.com/setup_${NODE_MAJOR}.x" | bash -
  apt-get install -y -qq nodejs
  ok "Node.js $(node -v) installed"
}

# ------------------------------------------------------------------ source ----
SCRIPT_SOURCE="${BASH_SOURCE[0]:-}"
SCRIPT_DIR=""
if [ -n "$SCRIPT_SOURCE" ]; then
  SCRIPT_DIR="$(cd "$(dirname "$SCRIPT_SOURCE")" 2>/dev/null && pwd || true)"
fi
SRC_DIR=""
CLEANUP_DIR=""

if [ -n "$SCRIPT_DIR" ] && [ -f "$SCRIPT_DIR/src/Api/bootstrap.php" ]; then
  SRC_DIR="$SCRIPT_DIR"
  log "Using local checkout: $SRC_DIR"
else
  log "Cloning $REPO_URL ($BRANCH)"
  CLEANUP_DIR="$(mktemp -d)"
  trap 'rm -rf "$CLEANUP_DIR"' EXIT
  git clone --depth 1 --branch "$BRANCH" "$REPO_URL" "$CLEANUP_DIR/repo" >/dev/null
  SRC_DIR="$CLEANUP_DIR/repo"
fi

# ---------------------------------------------------------------- database ----
log "Preparing database"

DB_NAME="fork_panel_xc"
DB_USER="fork_panel_xc"
DB_PASSWORD=""

if [ -f "$APP_DIR/config/.env" ]; then
  DB_PASSWORD="$(grep -E '^DB_PASSWORD=' "$APP_DIR/config/.env" | head -n1 | cut -d= -f2- | tr -d '"' || true)"
  if [ -z "$ADMIN_PASSWORD" ]; then
    ADMIN_PASSWORD="$(grep -E '^APP_ADMIN_PASSWORD=' "$APP_DIR/config/.env" | head -n1 | cut -d= -f2- | tr -d '"' || true)"
  fi
  if [ -z "$TMDB_KEY" ]; then
    TMDB_KEY="$(grep -E '^TMDB_API_KEY=' "$APP_DIR/config/.env" | head -n1 | cut -d= -f2- | tr -d '"' || true)"
  fi
fi

[ -n "$DB_PASSWORD" ] || DB_PASSWORD="$(openssl rand -hex 24 2>/dev/null || head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n')"
[ -n "$ADMIN_PASSWORD" ] || ADMIN_PASSWORD="$(openssl rand -base64 18 2>/dev/null | tr -dc 'A-Za-z0-9' | head -c 20)"

systemctl enable --now mariadb >/dev/null 2>&1 || true

if ! mysql -uroot -e 'SELECT 1' >/dev/null 2>&1; then
  die "Cannot connect to MariaDB as root. Is the server running?"
fi

mysql -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
ok "Database ready"

# ------------------------------------------------------------------- user ----
log "Preparing system user"
if ! id -u "$APP_USER" >/dev/null 2>&1; then
  useradd -r -m -d "$APP_DIR" -s /bin/bash "$APP_USER"
fi
usermod -a -G www-data "$APP_USER"
ok "User $APP_USER ready"

# ----------------------------------------------------------------- deploy ----
log "Deploying application to $APP_DIR"
mkdir -p \
  "$APP_DIR/src" "$APP_DIR/config" "$APP_DIR/frontend/public" \
  "$APP_DIR/storage/logs" "$APP_DIR/storage/cache" "$APP_DIR/storage/sessions"

cp -a "$SRC_DIR/src/." "$APP_DIR/src/"
cp -a "$SRC_DIR/console.php" "$APP_DIR/console.php"
for extra in docs README.md CHANGELOG.md LICENSE; do
  [ -e "$SRC_DIR/$extra" ] && cp -a "$SRC_DIR/$extra" "$APP_DIR/" || true
done

# Frontend: use a prebuilt dist when present, otherwise build it.
if [ -f "$SRC_DIR/frontend/dist/index.html" ]; then
  log "Using prebuilt frontend from $SRC_DIR/frontend/dist"
  cp -a "$SRC_DIR/frontend/dist/." "$APP_DIR/frontend/public/"
elif [ "$SKIP_FRONTEND" -eq 1 ]; then
  [ -f "$APP_DIR/frontend/public/index.html" ] || \
    die "--skip-frontend was given but $APP_DIR/frontend/public/index.html does not exist."
  warn "Reusing the existing frontend build"
else
  install_node
  log "Building the frontend (this can take a minute)"
  (
    cd "$SRC_DIR/frontend"
    npm ci --no-audit --no-fund >/dev/null 2>&1 || npm install --no-audit --no-fund >/dev/null
    npm run build >/dev/null
  )
  cp -a "$SRC_DIR/frontend/dist/." "$APP_DIR/frontend/public/"
fi

chown -R "$APP_USER:$APP_USER" "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 755 {} +
find "$APP_DIR/storage" -type d -exec chmod 775 {} +
ok "Files deployed"

# --------------------------------------------------------------- env file ----
log "Writing configuration"
JWT_SECRET="$(openssl rand -hex 32 2>/dev/null || head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n')"
STREAM_SECRET="$(openssl rand -hex 32 2>/dev/null || head -c 32 /dev/urandom | od -An -tx1 | tr -d ' \n')"

cat > "$APP_DIR/config/.env" <<EOF
APP_NAME="Fork Panel XC"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://${DOMAIN:-localhost}

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=${DB_NAME}
DB_USERNAME=${DB_USER}
DB_PASSWORD=${DB_PASSWORD}

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_DB=0

JWT_SECRET=${JWT_SECRET}
JWT_TTL=60
JWT_REFRESH_TTL=10080
STREAM_TOKEN_SECRET=${STREAM_SECRET}
STREAM_TOKEN_TTL=3600
APP_ADMIN_PASSWORD=${ADMIN_PASSWORD}

TMDB_API_KEY=${TMDB_KEY}

STORAGE_PATH=${APP_DIR}/storage
CACHE_PATH=${APP_DIR}/storage/cache
LOG_PATH=${APP_DIR}/storage/logs
EOF

chown "$APP_USER:www-data" "$APP_DIR/config/.env"
chmod 640 "$APP_DIR/config/.env"
ln -sfn "$APP_DIR/config/.env" "$APP_DIR/.env"
ok "Configuration written"

# ------------------------------------------------------------------ nginx ----
log "Configuring nginx"
SERVER_NAME="${DOMAIN:-_}"
cat > /etc/nginx/sites-available/fork_panel_xc <<EOF
server {
    listen 80;
    listen [::]:80;
    server_name ${SERVER_NAME};

    root ${APP_DIR}/frontend/public;
    index index.html;

    client_max_body_size 100M;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / {
        try_files \$uri \$uri/ /index.html;
    }

    location /assets/ {
        expires 30d;
        add_header Cache-Control "public, immutable";
        try_files \$uri =404;
    }

    location ~ ^/(api|player_api|panel_api)/ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME ${APP_DIR}/src/Api/index.php;
        fastcgi_param REQUEST_URI \$request_uri;
        fastcgi_pass unix:${APP_PHP_SOCKET};
        fastcgi_read_timeout 300s;
    }

    location ~ ^/(live|movie|series|epg)/ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME ${APP_DIR}/src/Api/index.php;
        fastcgi_param REQUEST_URI \$request_uri;
        fastcgi_pass unix:${APP_PHP_SOCKET};
        fastcgi_buffering off;
        fastcgi_read_timeout 3600s;
    }
}
EOF

ln -sfn /etc/nginx/sites-available/fork_panel_xc /etc/nginx/sites-enabled/fork_panel_xc
[ -e /etc/nginx/sites-enabled/default ] && rm -f /etc/nginx/sites-enabled/default
nginx -t >/dev/null
ok "nginx configured"

# --------------------------------------------------------------- services ----
log "Configuring PHP-FPM pool"
mkdir -p "/etc/php/${PHP_VERSION}/fpm/pool.d"
cat > "/etc/php/${PHP_VERSION}/fpm/pool.d/fork_panel_xc.conf" <<EOF
[fork_panel_xc]
user = ${APP_USER}
group = ${APP_USER}
listen = ${APP_PHP_SOCKET}
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500
request_terminate_timeout = 300
php_admin_value[memory_limit] = 512M
php_admin_value[upload_max_filesize] = 100M
php_admin_value[post_max_size] = 100M
php_admin_value[error_log] = ${APP_DIR}/storage/logs/php-fpm-error.log
php_admin_flag[log_errors] = on
php_value[session.save_handler] = files
php_value[session.save_path] = /tmp
EOF
ok "PHP-FPM pool configured"

log "Installing systemd units"
cat > /etc/systemd/system/fork-panel-xc-worker.service <<EOF
[Unit]
Description=Fork Panel XC queue worker
After=network.target mariadb.service redis.service

[Service]
Type=simple
User=${APP_USER}
Group=${APP_USER}
WorkingDirectory=${APP_DIR}
ExecStart=/usr/bin/php ${APP_DIR}/console.php queue:daemon
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

cat > /etc/systemd/system/fork-panel-xc-scheduler.service <<EOF
[Unit]
Description=Fork Panel XC scheduler
After=network.target mariadb.service redis.service

[Service]
Type=simple
User=${APP_USER}
Group=${APP_USER}
WorkingDirectory=${APP_DIR}
ExecStart=/usr/bin/php ${APP_DIR}/console.php schedule:daemon
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
EOF

cat > /etc/cron.d/fork-panel-xc <<EOF
# Fork Panel XC maintenance
0 3 * * * ${APP_USER} /usr/bin/php ${APP_DIR}/console.php schedule:run >> ${APP_DIR}/storage/logs/maintenance.log 2>&1
EOF
chmod 644 /etc/cron.d/fork-panel-xc

cat > /etc/logrotate.d/fork-panel-xc <<EOF
${APP_DIR}/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 640 ${APP_USER} ${APP_USER}
}
EOF

systemctl daemon-reload
systemctl enable --now fork-panel-xc-worker fork-panel-xc-scheduler >/dev/null 2>&1
ok "Services installed"

# -------------------------------------------------------------- migrations ----
log "Applying database migrations"
(
  cd "$APP_DIR"
  runuser -u "$APP_USER" -- php "$APP_DIR/console.php" db:migrate
)

log "Installing bundled TMDB module"
(
  cd "$APP_DIR"
  runuser -u "$APP_USER" -- php "$APP_DIR/console.php" module:install tmdb || \
    warn "TMDB module could not be installed automatically; install it from Modules."
)

if [ -n "$TMDB_KEY" ]; then
  ok "TMDB metadata enabled"
else
  warn "TMDB API key not set: add it in Settings → Modules or rerun with --tmdb-api-key."
fi

# ------------------------------------------------------------------- start ----
log "Starting services"
systemctl enable --now mariadb redis-server >/dev/null 2>&1 || true
systemctl restart "php${PHP_VERSION}-fpm" >/dev/null 2>&1 || systemctl restart php-fpm >/dev/null 2>&1 || true
systemctl restart nginx
systemctl restart fork-panel-xc-worker fork-panel-xc-scheduler

if [ "$ENABLE_SSL" -eq 1 ]; then
  log "Requesting TLS certificate for $DOMAIN"
  if certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos -m "$EMAIL" --redirect >/dev/null 2>&1; then
    ok "TLS enabled"
  else
    warn "certbot failed; the panel keeps serving over HTTP."
  fi
fi

# ----------------------------------------------------------------- summary ----
URL="http://${DOMAIN:-$(hostname -I 2>/dev/null | awk '{print $1}')}"
[ -n "${URL#http://}" ] || URL="http://$(ip route get 1.1.1.1 2>/dev/null | awk '{print $7; exit}')"
[ "$ENABLE_SSL" -eq 1 ] && URL="https://${DOMAIN}"

cat <<EOF

${C_GREEN}${C_BOLD}============================================================${C_RESET}
${C_GREEN}${C_BOLD}  Fork Panel XC installed${C_RESET}
${C_GREEN}${C_BOLD}============================================================${C_RESET}

  Panel URL:    ${URL}
  Admin user:   admin
  Admin pass:   ${ADMIN_PASSWORD}

  Install dir:  ${APP_DIR}
  Config:       ${APP_DIR}/config/.env
  Logs:         ${APP_DIR}/storage/logs/

  CLI:
    sudo -u ${APP_USER} php ${APP_DIR}/console.php list
    sudo -u ${APP_USER} php ${APP_DIR}/console.php db:status

  Services:
    systemctl status php${PHP_VERSION}-fpm nginx mariadb redis-server
    systemctl status fork-panel-xc-worker fork-panel-xc-scheduler

${C_YELLOW}Change the admin password after the first sign-in.${C_RESET}

EOF
