<?php

declare(strict_types=1);

namespace ForkPanelXc\Modules\ExampleModule\Commands;

use ForkPanelXc\Core\Support\Env;
use XcVm\Cli\CommandInterface;

class SyncCommand implements CommandInterface
{
    public function getName(): string
    {
        return 'example:sync';
    }

    public function getDescription(): string
    {
        return 'Manually trigger the Example Module sync';
    }

    public function execute(array $args = []): int
    {
        $dryRun = in_array('--dry-run', $args, true);
        $force = in_array('--force', $args, true);

        echo "Example Module sync\n";

        if ($dryRun) {
            echo "DRY RUN: no changes written.\n";

            return 0;
        }

        $apiKey = (string) Env::get('EXAMPLE_MODULE_API_KEY', '');

        if ($apiKey === '' && !$force) {
            echo "No API key configured. Use --force to continue anyway.\n";

            return 1;
        }

        echo "Sync completed: 0 item(s) processed\n";

        return 0;
    }
}
