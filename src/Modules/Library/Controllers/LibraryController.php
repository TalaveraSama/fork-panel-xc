<?php

declare(strict_types=1);

namespace ForkPanelXc\Modules\Library\Controllers;

use ForkPanelXc\Core\Http\ApiRequest;
use ForkPanelXc\Core\Http\ApiResponse;
use ForkPanelXc\Core\Support\Db;
use ForkPanelXc\Modules\Library\LibraryScanner;
use ForkPanelXc\Public\Controllers\Api\BaseApiController;

class LibraryController extends BaseApiController
{
    public function getSettings(ApiRequest $request): ApiResponse
    {
        if (!$this->isAdmin($request)) {
            return ApiResponse::error('Insufficient permissions', 403);
        }

        $settings = LibraryScanner::settings();
        $lastReport = json_decode($settings['last_report'] ?: '{}', true);

        return ApiResponse::json([
            'data' => [
                'movie_paths' => $settings['movie_paths'],
                'series_paths' => $settings['series_paths'],
                'extensions' => $settings['extensions'],
                'scan_interval_minutes' => (int) $settings['scan_interval_minutes'],
                'enrich_tmdb' => in_array($settings['enrich_tmdb'], ['1', 'true'], true),
                'enrich_limit' => (int) $settings['enrich_limit'],
                'auto_enabled' => in_array($settings['auto_enabled'], ['1', 'true'], true),
                'mark_missing' => in_array($settings['mark_missing'], ['1', 'true'], true),
                'last_scan_at' => (int) $settings['last_scan_at'],
                'last_report' => is_array($lastReport) ? $lastReport : [],
                'default_movie_path' => dirname(__DIR__, 4) . '/media/movies',
                'default_series_path' => dirname(__DIR__, 4) . '/media/series',
                'stats' => (new LibraryScanner())->stats(),
                'tmdb_available' => class_exists(\ForkPanelXc\Modules\Tmdb\TmdbClient::class)
                    && (new \ForkPanelXc\Modules\Tmdb\TmdbClient())->hasApiKey(),
            ],
        ]);
    }

    public function saveSettings(ApiRequest $request): ApiResponse
    {
        if (!$this->isAdmin($request)) {
            return ApiResponse::error('Insufficient permissions', 403);
        }

        $data = $request->json();
        $paths = [];

        foreach (['movie_paths' => 'Movies', 'series_paths' => 'Series'] as $field => $label) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $normalized = [];
            $errors = [];

            foreach (preg_split('/\r\n|\n|\r/', (string) $data[$field]) ?: [] as $line) {
                $line = trim($line);

                if ($line === '') {
                    continue;
                }

                if (!str_starts_with($line, '/') && !preg_match('#^[A-Za-z]:[\\\\/]#', $line)) {
                    $errors[] = sprintf('%s: "%s" must be an absolute path', $label, $line);
                    continue;
                }

                $real = realpath($line);

                if ($real === false || !is_dir($real)) {
                    $errors[] = sprintf('%s: "%s" is not a readable directory', $label, $line);
                    continue;
                }

                $normalized[] = $real;
            }

            if ($errors !== []) {
                return ApiResponse::error('Invalid folders', 422, ['paths' => $errors]);
            }

            $paths[$field] = implode("\n", array_values(array_unique($normalized)));
        }

        $updates = $paths;

        if (array_key_exists('extensions', $data)) {
            $updates['extensions'] = implode(',', LibraryScanner::extensions((string) $data['extensions']));
        }

        if (array_key_exists('scan_interval_minutes', $data)) {
            $updates['scan_interval_minutes'] = (string) max(5, min(1440, (int) $data['scan_interval_minutes']));
        }

        if (array_key_exists('enrich_limit', $data)) {
            $updates['enrich_limit'] = (string) max(0, min(200, (int) $data['enrich_limit']));
        }

        foreach (['enrich_tmdb', 'auto_enabled', 'mark_missing'] as $flag) {
            if (array_key_exists($flag, $data)) {
                $updates[$flag] = !empty($data[$flag]) ? '1' : '0';
            }
        }

        foreach ($updates as $key => $value) {
            if (!preg_match('/^[a-z0-9_]+$/', $key)) {
                continue;
            }

            Db::execute(
                'INSERT INTO settings (section, setting_key, setting_value, updated_at) VALUES (?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)',
                ['library', $key, (string) $value, time()]
            );
        }

        return $this->getSettings($request);
    }

    public function status(ApiRequest $request): ApiResponse
    {
        if (!$this->isAdmin($request)) {
            return ApiResponse::error('Insufficient permissions', 403);
        }

        $settings = LibraryScanner::settings();
        $lastReport = json_decode($settings['last_report'] ?: '{}', true);

        $recent = Db::select(
            "SELECT path, media_type, title, season_num, episode_num, status, error, scanned_at
             FROM library_files ORDER BY id DESC LIMIT 20"
        );

        return ApiResponse::json([
            'data' => [
                'last_scan_at' => (int) $settings['last_scan_at'],
                'last_report' => is_array($lastReport) ? $lastReport : [],
                'stats' => (new LibraryScanner())->stats(),
                'recent' => $recent,
            ],
        ]);
    }

    public function scan(ApiRequest $request): ApiResponse
    {
        if (!$this->isAdmin($request)) {
            return ApiResponse::error('Insufficient permissions', 403);
        }

        $data = $request->json();
        $dryRun = !empty($data['dry_run']);
        $limit = max(0, min(5000, (int) ($data['limit'] ?? 0)));

        try {
            $report = (new LibraryScanner())->scan([
                'dry_run' => $dryRun,
                'limit' => $limit,
                'source' => 'api',
            ]);
        } catch (\Throwable $e) {
            return ApiResponse::error('Scan failed: ' . $e->getMessage(), 500);
        }

        return ApiResponse::json([
            'data' => $report,
            'stats' => (new LibraryScanner())->stats(),
        ]);
    }

    private function isAdmin(ApiRequest $request): bool
    {
        $user = $request->user();

        return $user !== null && $user->hasRole('admin');
    }
}
