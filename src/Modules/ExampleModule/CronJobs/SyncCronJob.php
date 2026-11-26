<?php

declare(strict_types=1);

namespace ForkPanelXc\Modules\ExampleModule\CronJobs;

use ForkPanelXc\Core\Support\Env;

/**
 * Example module cron job.
 *
 * Scheduled by the panel crontab when the module is installed:
 *   *\/15 * * * * php /home/fork_panel_xc/console.php cron:example_module_sync
 */
class SyncCronJob
{
    public function getName(): string
    {
        return 'cron:example_module_sync';
    }

    public function getDescription(): string
    {
        return 'Sync data from the example external service';
    }

    public function execute(array $args = []): int
    {
        echo "Starting Example Module sync...\n";

        $apiKey = (string) Env::get('EXAMPLE_MODULE_API_KEY', '');

        if ($apiKey === '') {
            echo "No API key configured, nothing to sync.\n";

            return 0;
        }

        try {
            $synced = $this->syncData($apiKey);
            echo "Sync completed: {$synced} item(s) processed\n";

            return 0;
        } catch (\Throwable $e) {
            echo 'Sync failed: ' . $e->getMessage() . "\n";

            return 1;
        }
    }

    private function syncData(string $apiKey): int
    {
        // Replace with the real client call. Kept side-effect free in the
        // example so installing the module never contacts a third party.
        return 0;
    }
}
