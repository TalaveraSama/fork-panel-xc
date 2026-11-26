<?php

declare(strict_types=1);

/**
 * Fork Panel XC console.
 *
 * Usage: php console.php <command> [args]
 */
require __DIR__ . '/src/Api/bootstrap.php';

use ForkPanelXc\Core\Support\Db;
use ForkPanelXc\Domain\Module\ModuleManager;

function fork_console_migrations(): array
{
    $directory = __DIR__ . '/src/Api/migrations';
    $applied = [];

    Db::pdo()->exec('CREATE TABLE IF NOT EXISTS migrations (
        name VARCHAR(190) PRIMARY KEY,
        applied_at BIGINT NOT NULL
    )');

    foreach (glob($directory . '/*.sql') ?: [] as $file) {
        $name = basename($file);

        if (Db::first('SELECT name FROM migrations WHERE name = ?', [$name]) !== null) {
            continue;
        }

        $sql = (string) file_get_contents($file);

        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            Db::pdo()->exec($statement);
        }

        Db::execute('INSERT INTO migrations (name, applied_at) VALUES (?, ?)', [$name, time()]);
        $applied[] = $name;
    }

    return $applied;
}

$command = $argv[1] ?? 'list';
$args = array_slice($argv, 2);

switch ($command) {
    case 'list':
        echo "Fork Panel XC console\n\n";
        echo "Commands:\n";
        echo "  list                 Show this help\n";
        echo "  db:migrate           Apply pending database migrations\n";
        echo "  db:status            Show migration status\n";
        echo "  module:list          List bundled modules and their status\n";
        echo "  module:install <id>  Install a module\n";
        echo "  module:enable <id>   Enable an installed module\n";
        echo "  module:disable <id>  Disable a module\n";
        echo "  module:uninstall <id> Uninstall a module\n";
        echo "  user:password <user> <pass>  Reset a panel user's password\n";
        echo "  schedule:run         Run scheduled maintenance once\n";
        echo "  schedule:daemon      Run scheduled maintenance every minute\n";
        echo "  queue:daemon         Process background jobs continuously\n";
        echo "  serve [port]         Start the PHP development server\n";
        exit(0);

    case 'db:migrate':
        $applied = fork_console_migrations();
        echo $applied === []
            ? "Nothing to migrate.\n"
            : 'Applied: ' . implode(', ', $applied) . "\n";

        if ((int) Db::column('SELECT COUNT(*) FROM users') === 0) {
            $password = (string) \ForkPanelXc\Core\Support\Env::get('APP_ADMIN_PASSWORD', 'admin');

            Db::insert(
                'INSERT INTO users (username, email, password_hash, enabled, is_admin, is_reseller, permissions, created_at, updated_at)
                 VALUES (?, ?, ?, 1, 1, 0, ?, ?, ?)',
                ['admin', '', password_hash($password, PASSWORD_ARGON2ID), json_encode([]), time(), time()]
            );

            echo "Created admin user 'admin' with the configured password.\n";
            echo "Change it immediately after the first login.\n";
        }

        exit(0);

    case 'db:status':
        $applied = array_column(Db::select('SELECT name FROM migrations'), 'name');
        $files = array_map('basename', glob(__DIR__ . '/src/Api/migrations/*.sql') ?: []);
        echo 'Applied: ' . count($applied) . '/' . count($files) . "\n";

        foreach (array_diff($files, $applied) as $pending) {
            echo "  pending: {$pending}\n";
        }
        exit(0);

    case 'module:list':
        $manager = new ModuleManager();
        $installed = [];

        foreach ($manager->installed() as $row) {
            $installed[$row['module_id']] = $row['status'];
        }

        foreach ($manager->discover() as $id => $module) {
            printf("  %-20s v%-8s %s\n", $id, $module['version'] ?? '1.0.0', $installed[$id] ?? 'available');
        }
        exit(0);

    case 'module:install':
    case 'module:enable':
    case 'module:disable':
    case 'module:uninstall':
        $id = $args[0] ?? '';

        if ($id === '') {
            fwrite(STDERR, "Module id required\n");
            exit(1);
        }

        $manager = new ModuleManager();
        $ok = match ($command) {
            'module:install' => $manager->install($id),
            'module:enable' => $manager->setStatus($id, 'enabled'),
            'module:disable' => $manager->setStatus($id, 'disabled'),
            'module:uninstall' => $manager->uninstall($id),
        };

        echo $ok ? "Done.\n" : "Failed.\n";
        exit($ok ? 0 : 1);

    case 'user:password':
        $username = $args[0] ?? '';
        $password = $args[1] ?? '';

        if ($username === '' || $password === '') {
            fwrite(STDERR, "Usage: user:password <username> <password>\n");
            exit(1);
        }

        if (strlen($password) < 8) {
            fwrite(STDERR, "Password must be at least 8 characters.\n");
            exit(1);
        }

        $updated = Db::execute(
            'UPDATE users SET password_hash = ?, updated_at = ? WHERE username = ?',
            [password_hash($password, PASSWORD_ARGON2ID), time(), $username]
        );

        if ($updated === 0) {
            fwrite(STDERR, "User '{$username}' not found.\n");
            exit(1);
        }

        echo "Password updated for '{$username}'.\n";
        exit(0);

    case 'schedule:run':
        $run = static function (): void {
            Db::execute('DELETE FROM password_resets WHERE expires_at < ?', [time()]);

            $connections = (int) Db::column('SELECT COUNT(*) FROM user_lines WHERE connected = 1');
            $bandwidth = (float) Db::column('SELECT COALESCE(SUM(bitrate), 0) FROM user_lines WHERE connected = 1');

            Db::execute(
                'INSERT INTO stats_hourly (hour_label, inbound, outbound, connections, created_at) VALUES (?, ?, ?, ?, ?)',
                [date('H:i'), $bandwidth, $bandwidth, $connections, time()]
            );

            Db::execute('DELETE FROM stats_hourly WHERE created_at < ?', [time() - 7 * 86400]);
        };

        $run();
        echo "Scheduled tasks executed.\n";
        exit(0);

    case 'schedule:daemon':
        echo "Scheduler started (every 60s).\n";

        while (true) {
            try {
                Db::execute('DELETE FROM password_resets WHERE expires_at < ?', [time()]);
                $connections = (int) Db::column('SELECT COUNT(*) FROM user_lines WHERE connected = 1');
                $bandwidth = (float) Db::column('SELECT COALESCE(SUM(bitrate), 0) FROM user_lines WHERE connected = 1');
                Db::execute(
                    'INSERT INTO stats_hourly (hour_label, inbound, outbound, connections, created_at) VALUES (?, ?, ?, ?, ?)',
                    [date('H:i'), $bandwidth, $bandwidth, $connections, time()]
                );
            } catch (\Throwable $e) {
                error_log('[fork-panel-xc] scheduler: ' . $e->getMessage());
            }

            sleep(60);
        }

    case 'queue:daemon':
        echo "Queue worker started.\n";

        while (true) {
            try {
                $job = Db::first("SELECT * FROM jobs WHERE status = 'queued' ORDER BY id LIMIT 1");

                if ($job !== null) {
                    Db::execute("UPDATE jobs SET status = 'processing', started_at = ? WHERE id = ?", [time(), (int) $job['id']]);

                    try {
                        // Job handlers are provided by modules; unknown jobs fail cleanly.
                        Db::execute("UPDATE jobs SET status = 'failed', error = ?, finished_at = ? WHERE id = ?", [
                            'No handler registered for ' . $job['name'],
                            time(),
                            (int) $job['id'],
                        ]);
                    } catch (\Throwable $e) {
                        Db::execute("UPDATE jobs SET status = 'failed', error = ?, finished_at = ? WHERE id = ?", [
                            $e->getMessage(),
                            time(),
                            (int) $job['id'],
                        ]);
                    }
                }
            } catch (\Throwable $e) {
                error_log('[fork-panel-xc] queue: ' . $e->getMessage());
            }

            sleep(3);
        }

    case 'serve':
        $port = (int) ($args[0] ?? 8080);
        echo "Serving http://127.0.0.1:{$port}\n";
        passthru(sprintf('%s -S 127.0.0.1:%d -t %s', PHP_BINARY, $port, escapeshellarg(__DIR__ . '/src/Api')));
        exit(0);

    default:
        fwrite(STDERR, "Unknown command: {$command}\n");
        exit(1);
}
