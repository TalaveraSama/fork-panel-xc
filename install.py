#!/usr/bin/env python3
"""
Fork Panel XC installer.

Deploys the API + built frontend on Ubuntu 22.04/24.04 or Debian 12/13.
Run as root:  sudo python3 install.py
"""

import argparse
import getpass
import os
import re
import secrets
import shutil
import string
import subprocess
import sys
from pathlib import Path

BASE = Path('/home/fork_panel_xc')
APP_DIR = Path(__file__).resolve().parent


class C:
    RED = '\033[91m'
    GREEN = '\033[92m'
    YELLOW = '\033[93m'
    BLUE = '\033[94m'
    CYAN = '\033[96m'
    BOLD = '\033[1m'
    RESET = '\033[0m'


def say(text, color=C.CYAN):
    print(f"{color}{text}{C.RESET}")


def run(cmd, check=True, capture=False):
    result = subprocess.run(cmd, shell=True, capture_output=capture, text=True)
    if check and result.returncode != 0:
        say(f"Command failed: {cmd}", C.RED)
        if capture and result.stderr:
            print(result.stderr)
        sys.exit(1)
    return result


def password(length=32):
    alphabet = string.ascii_letters + string.digits
    return ''.join(secrets.choice(alphabet) for _ in range(length))


def require_root():
    if os.geteuid() != 0:
        say('Run this installer as root (sudo).', C.RED)
        sys.exit(1)


def supported_os():
    text = Path('/etc/os-release').read_text() if Path('/etc/os-release').exists() else ''
    return 'Ubuntu' in text or 'Debian' in text


def install_dependencies():
    say('▶ Installing system dependencies')
    packages = (
        'curl wget unzip git cron logrotate certbot python3-certbot-nginx '
        'nginx mariadb-server redis-server '
        'php-fpm php-cli php-mysql php-redis php-curl php-mbstring php-xml php-zip php-gd php-intl'
    )
    run('apt-get update -qq')
    run(f'DEBIAN_FRONTEND=noninteractive apt-get install -y -qq {packages}')
    say('✓ Dependencies installed', C.GREEN)


def php_version():
    out = run("php -r 'echo PHP_MAJOR_VERSION.\".\".PHP_MINOR_VERSION;'", capture=True).stdout.strip()
    return out or '8.3'


def create_user():
    say('▶ Creating system user')
    run('id -u fork_panel_xc >/dev/null 2>&1 || useradd -r -m -d /home/fork_panel_xc -s /bin/bash fork_panel_xc')
    run('usermod -a -G www-data fork_panel_xc')
    say('✓ User ready', C.GREEN)


def create_database():
    say('▶ Creating database')
    name = 'fork_panel_xc'
    user = 'fork_panel_xc'
    secret = password(40)

    sql = f"""
CREATE DATABASE IF NOT EXISTS {name} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '{user}'@'localhost' IDENTIFIED BY '{secret}';
ALTER USER '{user}'@'localhost' IDENTIFIED BY '{secret}';
GRANT ALL PRIVILEGES ON {name}.* TO '{user}'@'localhost';
FLUSH PRIVILEGES;
"""
    proc = subprocess.run('mysql -uroot', shell=True, input=sql, text=True)

    if proc.returncode != 0:
        say('Could not create the database. Is MariaDB running and root passwordless?', C.RED)
        sys.exit(1)

    say('✓ Database ready', C.GREEN)
    return name, user, secret


def deploy_files():
    say('▶ Deploying application files')
    for sub in ('src', 'config', 'storage/logs', 'storage/cache', 'storage/sessions'):
        (BASE / sub).mkdir(parents=True, exist_ok=True)

    for item in ('Api', 'Core', 'Domain', 'Public', 'Modules', 'routes'):
        src = APP_DIR / 'src' / item
        if src.exists():
            shutil.copytree(src, BASE / 'src' / item, dirs_exist_ok=True)

    for item in ('console.php',):
        if (APP_DIR / 'src' / item).exists():
            shutil.copy2(APP_DIR / 'src' / item, BASE / 'src' / item)
        elif (APP_DIR / item).exists():
            shutil.copy2(APP_DIR / item, BASE / item)

    # Frontend build output
    build = APP_DIR / 'frontend' / 'dist'
    if not build.exists():
        say('Frontend build missing: run "npm install && npm run build" in frontend/ first.', C.RED)
        sys.exit(1)

    shutil.copytree(build, BASE / 'frontend/public', dirs_exist_ok=True)

    run(f'chown -R fork_panel_xc:fork_panel_xc {BASE}')
    run(f'chmod -R 755 {BASE}')
    run(f'chmod -R 775 {BASE}/storage')
    say('✓ Files deployed', C.GREEN)


def write_env(db_name, db_user, db_pass, admin_password):
    say('▶ Writing configuration')
    (BASE / 'config').mkdir(parents=True, exist_ok=True)

    env = f"""APP_NAME="Fork Panel XC"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE={db_name}
DB_USERNAME={db_user}
DB_PASSWORD={db_pass}

REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_DB=0

JWT_SECRET={password(64)}
JWT_TTL=60
JWT_REFRESH_TTL=10080
STREAM_TOKEN_SECRET={password(64)}
STREAM_TOKEN_TTL=3600
APP_ADMIN_PASSWORD={admin_password}

STORAGE_PATH={BASE}/storage
CACHE_PATH={BASE}/storage/cache
LOG_PATH={BASE}/storage/logs
"""
    env_path = BASE / 'config/.env'
    env_path.write_text(env)
    run(f'chown fork_panel_xc:fork_panel_xc {env_path}')
    run(f'chmod 600 {env_path}')
    run(f'ln -sf {env_path} {BASE}/.env')
    say('✓ Configuration written', C.GREEN)


def write_nginx():
    say('▶ Configuring nginx')
    config = f"""server {{
    listen 80;
    listen [::]:80;
    server_name _;

    root {BASE}/frontend/public;
    index index.html;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;

    location / {{
        try_files $uri $uri/ /index.html;
    }}

    location /assets/ {{
        expires 30d;
        add_header Cache-Control "public, immutable";
        try_files $uri =404;
    }}

    location ~ ^/(api|player_api|panel_api)/ {{
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME {BASE}/src/Api/index.php;
        fastcgi_param REQUEST_URI $request_uri;
        fastcgi_pass unix:/run/php/php{php_version()}-fpm.sock;
        fastcgi_read_timeout 300s;
    }}

    location ~ ^/(live|movie|series|epg)/ {{
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME {BASE}/src/Api/index.php;
        fastcgi_param REQUEST_URI $request_uri;
        fastcgi_pass unix:/run/php/php{php_version()}-fpm.sock;
        fastcgi_buffering off;
        fastcgi_read_timeout 3600s;
    }}
}}
"""
    path = Path('/etc/nginx/sites-available/fork_panel_xc')
    path.write_text(config)
    run(f'ln -sf {path} /etc/nginx/sites-enabled/fork_panel_xc')
    if Path('/etc/nginx/sites-enabled/default').exists():
        Path('/etc/nginx/sites-enabled/default').unlink()

    run('nginx -t')
    run('systemctl reload nginx')
    say('✓ nginx configured', C.GREEN)


def write_services():
    say('▶ Configuring services')

    worker = f"""[Unit]
Description=Fork Panel XC queue worker
After=network.target mariadb.service redis.service

[Service]
Type=simple
User=fork_panel_xc
Group=fork_panel_xc
WorkingDirectory={BASE}
ExecStart=/usr/bin/php {BASE}/console.php queue:daemon
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
"""
    Path('/etc/systemd/system/fork-panel-xc-worker.service').write_text(worker)

    scheduler = f"""[Unit]
Description=Fork Panel XC scheduler
After=network.target mariadb.service redis.service

[Service]
Type=simple
User=fork_panel_xc
Group=fork_panel_xc
WorkingDirectory={BASE}
ExecStart=/usr/bin/php {BASE}/console.php schedule:daemon
Restart=always
RestartSec=5

[Install]
WantedBy=multi-user.target
"""
    Path('/etc/systemd/system/fork-panel-xc-scheduler.service').write_text(scheduler)

    cron = f"""# Fork Panel XC maintenance
0 3 * * * fork_panel_xc /usr/bin/php {BASE}/console.php schedule:run >> {BASE}/storage/logs/maintenance.log 2>&1
0 0 1 * * root certbot renew --quiet --nginx >> /var/log/certbot-renewal.log 2>&1
"""
    Path('/etc/cron.d/fork-panel-xc').write_text(cron)
    run('chmod 644 /etc/cron.d/fork-panel-xc')

    logrotate = f"""{BASE}/storage/logs/*.log {{
    daily
    missingok
    rotate 14
    compress
    delaycompress
    notifempty
    create 640 fork_panel_xc fork_panel_xc
}}
"""
    Path('/etc/logrotate.d/fork-panel-xc').write_text(logrotate)

    run('systemctl daemon-reload')
    run('systemctl enable --now fork-panel-xc-worker fork-panel-xc-scheduler')
    say('✓ Services configured', C.GREEN)


def migrate():
    say('▶ Applying database migrations')
    result = subprocess.run(
        f'cd {BASE} && sudo -u fork_panel_xc /usr/bin/php {BASE}/console.php db:migrate',
        shell=True, capture_output=True, text=True
    )
    print(result.stdout.strip())
    if result.returncode != 0:
        say(result.stderr.strip(), C.RED)
        say('Migration failed', C.RED)
        sys.exit(1)
    say('✓ Database ready', C.GREEN)


def main():
    parser = argparse.ArgumentParser(description='Fork Panel XC installer')
    parser.add_argument('--admin-password', default=None, help='password for the initial admin user')
    parser.add_argument('--skip-deps', action='store_true', help='skip apt package installation')
    args = parser.parse_args()

    print()
    say('=' * 60)
    say('  Fork Panel XC installer', C.CYAN + C.BOLD)
    say('=' * 60)
    print()

    require_root()

    if not supported_os():
        say('Warning: this installer targets Ubuntu/Debian.', C.YELLOW)

    admin_password = args.admin_password or password(16)

    if not args.skip_deps:
        install_dependencies()

    create_user()
    db_name, db_user, db_pass = create_database()
    deploy_files()
    write_env(db_name, db_user, db_pass, admin_password)
    write_nginx()
    write_services()
    migrate()

    run(f'systemctl restart php{php_version()}-fpm')
    run('systemctl restart nginx')

    print()
    say('=' * 60)
    say('  Installation complete', C.GREEN + C.BOLD)
    say('=' * 60)
    print(f"  Panel URL:   http://<server-ip>/")
    print(f"  Admin user:  admin")
    print(f"  Admin pass:  {admin_password}")
    print()
    print(f"  Config:      {BASE}/config/.env")
    print(f"  Logs:        {BASE}/storage/logs/")
    print(f"  Console:     sudo -u fork_panel_xc php {BASE}/console.php list")
    print()
    say('  Change the admin password after the first login.', C.YELLOW)
    print()


if __name__ == '__main__':
    try:
        main()
    except KeyboardInterrupt:
        say('\nInstallation cancelled.', C.RED)
        sys.exit(1)
