<?php

declare(strict_types=1);

/**
 * Bootstrap for the Fork Panel XC API.
 *
 * The API ships with its own PSR-4 autoloader so it works even when the
 * committed vendor/ directory was generated for the legacy panel.
 */
$root = dirname(__DIR__);

spl_autoload_register(static function (string $class) use ($root): void {
    $prefixes = [
        'ForkPanelXc\\' => $root . '/',
        'XcVm\\' => $root . '/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relative = substr($class, strlen($prefix));
        $path = $baseDir . str_replace('\\', '/', $relative) . '.php';

        if (is_file($path)) {
            require $path;
        }

        return;
    }
});

require $root . '/Core/Support/Env.php';

\ForkPanelXc\Core\Support\Env::load();
