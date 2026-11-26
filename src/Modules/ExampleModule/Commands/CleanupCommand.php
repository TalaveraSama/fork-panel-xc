<?php

declare(strict_types=1);

namespace ForkPanelXc\Modules\ExampleModule\Commands;

use XcVm\Cli\CommandInterface;

class CleanupCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'example:cleanup';
    }

    public function getDescription(): string
    {
        return 'Remove old Example Module records';
    }

    public function execute(array $args = []): int
    {
        $days = 30;
        $force = false;

        foreach ($args as $arg) {
            if (str_starts_with($arg, '--days=')) {
                $days = max(1, (int) substr($arg, 7));
            }

            if ($arg === '--force') {
                $force = true;
            }
        }

        echo "Cleaning Example Module data older than {$days} day(s)\n";

        if (!$force) {
            echo "Nothing deleted. Re-run with --force to confirm.\n";

            return 0;
        }

        echo "Cleanup completed: 0 record(s) deleted\n";

        return 0;
    }
}
