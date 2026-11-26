<?php

declare(strict_types=1);

namespace ForkPanelXc\Modules\Library;

use ForkPanelXc\Core\Support\Db;
use ForkPanelXc\Modules\Tmdb\TmdbClient;

/**
 * Scans configured local folders and indexes movies/series into the panel.
 *
 * Naming conventions understood:
 *   Movies:  Matrix.1999.1080p.BluRay.x264.mkv
 *            Inception (2010) 720p.mp4
 *   Series:  Show/Season 01/Show.S01E02.1080p.mkv
 *            Show/Show - 1x02 - Episode title.mkv
 *
 * Files are recorded in `library_files` (unique by path hash), so a second scan
 * skips unchanged files. TMDB enrichment is optional and only used when the
 * tmdb module has an API key.
 */
final class LibraryScanner
{
    private const VIDEO_EXTENSIONS = ['mkv', 'mp4', 'avi', 'mov', 'm4v', 'webm', 'ts', 'mpg', 'mpeg'];

    /** @var array<string, array> */
    private array $seriesCache = [];

    private int $enrichUsed = 0;

    public function scan(array $options = []): array
    {
        @set_time_limit(0);

        $dryRun = !empty($options['dry_run']);
        $limit = max(0, (int) ($options['limit'] ?? 0));
        $settings = self::settings();
        $extensions = self::extensions($settings['extensions']);
        $enrich = (bool) self::bool($settings['enrich_tmdb']);
        $enrichLimit = max(0, (int) $settings['enrich_limit']);
        $markMissing = self::bool($settings['mark_missing']);
        $started = microtime(true);

        $tmdb = null;

        if ($enrich && class_exists(TmdbClient::class)) {
            $client = new TmdbClient();

            if ($client->hasApiKey()) {
                $tmdb = $client;
            }
        }

        $roots = [
            'movie' => $this->resolvePaths($settings['movie_paths'], 'movies'),
            'episode' => $this->resolvePaths($settings['series_paths'], 'series'),
        ];

        $report = [
            'started_at' => time(),
            'dry_run' => $dryRun,
            'roots' => $roots,
            'files_seen' => 0,
            'movies_added' => 0,
            'movies_updated' => 0,
            'episodes_added' => 0,
            'episodes_updated' => 0,
            'series_added' => 0,
            'skipped' => 0,
            'ignored' => 0,
            'missing' => 0,
            'errors' => [],
            'truncated' => false,
            'duration_ms' => 0,
        ];

        $candidates = [];
        $seenHashes = [];

        foreach ($roots as $kind => $paths) {
            foreach ($paths as $root) {
                if (!is_dir($root) || !is_readable($root)) {
                    $report['errors'][] = sprintf('Folder not readable: %s', $root);
                    continue;
                }

                try {
                    $iterator = new \RecursiveIteratorIterator(
                        new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
                        \RecursiveIteratorIterator::SELF_FIRST
                    );

                    foreach ($iterator as $file) {
                        if (!$file->isFile() || !$file->isReadable()) {
                            continue;
                        }

                        if (!in_array(strtolower($file->getExtension()), $extensions, true)) {
                            continue;
                        }

                        $path = $file->getRealPath() ?: $file->getPathname();
                        $report['files_seen']++;
                        $seenHashes[sha1($path)] = true;

                        $parsed = $kind === 'movie'
                            ? self::parseMovie($path)
                            : self::parseEpisode($path);

                        if ($parsed === null) {
                            $report['ignored']++;
                            continue;
                        }

                        $parsed['path'] = $path;
                        $parsed['media_type'] = $kind;
                        $parsed['size'] = (int) $file->getSize();
                        $parsed['mtime'] = (int) $file->getMTime();
                        $candidates[] = $parsed;

                        if ($limit > 0 && count($candidates) >= $limit) {
                            $report['truncated'] = true;
                            break 3;
                        }
                    }
                } catch (\Throwable $e) {
                    $report['errors'][] = sprintf('%s: %s', $root, $e->getMessage());
                }
            }
        }

        foreach ($candidates as $candidate) {
            try {
                $result = $this->indexCandidate($candidate, $dryRun, $tmdb, $enrichLimit, $report);

                if ($result !== null) {
                    $report[$result]++;
                }
            } catch (\Throwable $e) {
                $report['errors'][] = sprintf('%s: %s', $candidate['path'], $e->getMessage());

                if (!$dryRun) {
                    $this->recordFailure($candidate, $e->getMessage());
                }
            }
        }

        if (!$dryRun && $markMissing && $seenHashes !== [] && !$report['truncated'] && $limit === 0) {
            $report['missing'] = $this->markMissing($seenHashes);
        }

        $report['duration_ms'] = (int) round((microtime(true) - $started) * 1000);
        $report['finished_at'] = time();

        if (!$dryRun) {
            Db::execute(
                "INSERT INTO settings (section, setting_key, setting_value, updated_at) VALUES ('library', 'last_scan_at', ?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)",
                [(string) time(), time()]
            );
            Db::execute(
                "INSERT INTO settings (section, setting_key, setting_value, updated_at) VALUES ('library', 'last_report', ?, ?)
                 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)",
                [json_encode($report), time()]
            );
        }

        return $report;
    }

    public function stats(): array
    {
        return [
            'files' => (int) Db::column('SELECT COUNT(*) FROM library_files'),
            'movies' => (int) Db::column("SELECT COUNT(*) FROM library_files WHERE media_type = 'movie'"),
            'episodes' => (int) Db::column("SELECT COUNT(*) FROM library_files WHERE media_type = 'episode'"),
            'missing' => (int) Db::column("SELECT COUNT(*) FROM library_files WHERE status = 'missing'"),
            'errors' => (int) Db::column("SELECT COUNT(*) FROM library_files WHERE status = 'error'"),
        ];
    }

    public static function settings(): array
    {
        $defaults = [
            'movie_paths' => '',
            'series_paths' => '',
            'extensions' => implode(',', self::VIDEO_EXTENSIONS),
            'scan_interval_minutes' => '60',
            'enrich_tmdb' => '1',
            'enrich_limit' => '25',
            'auto_enabled' => '1',
            'mark_missing' => '1',
            'last_scan_at' => '0',
            'last_report' => '{}',
        ];

        $rows = Db::select("SELECT setting_key, setting_value FROM settings WHERE section = 'library'");

        foreach ($rows as $row) {
            $defaults[$row['setting_key']] = (string) $row['setting_value'];
        }

        return $defaults;
    }

    public static function moduleEnabled(): bool
    {
        try {
            return Db::first("SELECT module_id FROM modules WHERE module_id = 'library' AND status = 'enabled'") !== null;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Called by the panel scheduler once per minute.
     */
    public static function runIfDue(): void
    {
        if (!self::moduleEnabled()) {
            return;
        }

        $settings = self::settings();

        if (!self::bool($settings['auto_enabled'])) {
            return;
        }

        $interval = max(5, (int) $settings['scan_interval_minutes']) * 60;

        if (time() - (int) $settings['last_scan_at'] < $interval) {
            return;
        }

        (new self())->scan(['source' => 'scheduler']);
    }

    private static function bool(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'true', 'yes', 'on'], true);
    }

    public static function extensions(string $value): array
    {        $extensions = array_filter(array_map(
            static fn (string $extension): string => strtolower(trim($extension, " .\t")),
            explode(',', $value)
        ), static fn (string $extension): bool => (bool) preg_match('/^[a-z0-9]{1,8}$/', $extension));

        return $extensions === [] ? self::VIDEO_EXTENSIONS : array_values(array_unique($extensions));
    }

    public static function parseMovie(string $path): ?array
    {
        $name = pathinfo($path, PATHINFO_FILENAME);
        $name = str_replace(['_', '.'], ' ', $name);
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;

        $year = null;

        if (preg_match('/\((19|20)\d{2}\)/', $name, $match)) {
            $year = (int) trim($match[0], '()');
            $name = trim(preg_replace('/\((19|20)\d{2}\)/', ' ', $name) ?? $name);
        } elseif (preg_match('/\b(19|20)\d{2}\b/', $name, $match)) {
            $year = (int) $match[0];
            $name = trim(preg_replace('/\b(19|20)\d{2}\b.*$/', '', $name) ?? $name);
        }

        $name = self::cleanTitle($name);

        if (mb_strlen($name) < 2) {
            return null;
        }

        return [
            'title' => $name,
            'year' => $year,
            'season_num' => null,
            'episode_num' => null,
            'series_name' => null,
        ];
    }

    public static function parseEpisode(string $path): ?array
    {
        $filename = pathinfo($path, PATHINFO_FILENAME);
        $name = str_replace(['_', '.'], ' ', $filename);
        $name = preg_replace('/\s+/', ' ', $name) ?? $name;

        $season = null;
        $episode = null;
        $title = '';

        if (preg_match('/\bS(\d{1,2})\s*E(\d{1,3})\b/i', $name, $match)) {
            $season = (int) $match[1];
            $episode = (int) $match[2];
            $title = trim(substr($name, (int) (strpos($name, $match[0]) + strlen($match[0]))));
            $prefix = trim(substr($name, 0, (int) strpos($name, $match[0])));
        } elseif (preg_match('/\b(\d{1,2})x(\d{1,3})\b/i', $name, $match)) {
            $season = (int) $match[1];
            $episode = (int) $match[2];
            $title = trim(substr($name, (int) (strpos($name, $match[0]) + strlen($match[0]))));
            $prefix = trim(substr($name, 0, (int) strpos($name, $match[0])));
        } else {
            return null;
        }

        $seriesName = self::seriesNameFromPath($path, $prefix);

        if ($seriesName === null || $seriesName === '') {
            return null;
        }

        $title = self::cleanTitle(preg_replace('/^[\s\-–—:]+/', '', $title) ?? $title);

        return [
            'title' => $title !== '' ? $title : sprintf('Episode %d', $episode),
            'year' => null,
            'season_num' => max(1, $season),
            'episode_num' => max(1, $episode),
            'series_name' => $seriesName,
        ];
    }

    private function indexCandidate(array $candidate, bool $dryRun, ?TmdbClient $tmdb, int $enrichLimit, array &$report): ?string
    {
        $hash = sha1($candidate['path']);
        $existing = Db::first('SELECT * FROM library_files WHERE path_hash = ?', [$hash]);

        if ($existing !== null
            && (int) $existing['size'] === $candidate['size']
            && (int) $existing['mtime'] === $candidate['mtime']
            && $existing['status'] === 'indexed') {
            $report['skipped']++;

            return null;
        }

        if ($candidate['media_type'] === 'movie') {
            return $this->indexMovie($candidate, $existing, $hash, $dryRun, $tmdb, $enrichLimit, $report);
        }

        return $this->indexEpisode($candidate, $existing, $hash, $dryRun, $tmdb, $enrichLimit, $report);
    }

    private function indexMovie(array $file, ?array $existing, string $hash, bool $dryRun, ?TmdbClient $tmdb, int $enrichLimit, array &$report): string
    {
        $meta = null;

        if ($tmdb !== null && $enrichLimit > 0 && $this->enrichUsed < $enrichLimit) {
            $this->enrichUsed++;

            try {
                $results = $tmdb->search($file['title'], 'movie', $file['year']);

                if ($results !== []) {
                    $meta = $tmdb->details('movie', (int) $results[0]['id']);
                }
            } catch (\Throwable $e) {
                $report['errors'][] = sprintf('TMDB %s: %s', $file['title'], $e->getMessage());
            }
        }

        $title = $meta['title'] ?? $file['title'];
        $source = 'file://' . $file['path'];

        if ($dryRun) {
            return $existing === null ? 'movies_added' : 'movies_updated';
        }

        $streamId = $existing['stream_id'] ?? null;

        if ($streamId === null) {
            $duplicate = Db::first(
                "SELECT id FROM streams WHERE stream_type = 'movie' AND (stream_source = ? OR (stream_display_name = ? AND year <=> ?)) LIMIT 1",
                [$source, $title, $meta['year'] ?? $file['year']]
            );

            if ($duplicate !== null) {
                $streamId = (int) $duplicate['id'];
            }
        }

        // Never replace a manually configured (HTTP/stream) source with a local path.
        if ($streamId !== null) {
            $current = Db::first('SELECT stream_source FROM streams WHERE id = ?', [$streamId]);

            if ($current !== null && !str_starts_with((string) $current['stream_source'], 'file://')) {
                $source = (string) $current['stream_source'];
            }
        }

        $payload = [
            'stream_display_name' => $title,
            'stream_source' => $source,
            'stream_icon' => (string) ($meta['poster_url'] ?? ''),
            'backdrop' => (string) ($meta['backdrop_url'] ?? ''),
            'overview' => (string) ($meta['overview'] ?? ''),
            'rating' => isset($meta['rating']) ? (float) $meta['rating'] : null,
            'runtime' => isset($meta['runtime']) && (int) $meta['runtime'] > 0 ? (int) $meta['runtime'] : null,
            'genres' => isset($meta['genres']) && is_array($meta['genres']) ? implode(', ', $meta['genres']) : '',
            'original_title' => (string) ($meta['original_title'] ?? ''),
            'year' => (int) ($meta['year'] ?? $file['year'] ?? 0) ?: null,
            'tmdb_id' => isset($meta['id']) ? (int) $meta['id'] : null,
        ];

        if ($streamId === null) {
            $streamId = Db::insert(
                'INSERT INTO streams (stream_display_name, stream_source, stream_icon, stream_type, category_id, year, tmdb_id,
                                      overview, backdrop, rating, runtime, genres, original_title, enabled, created_at, updated_at)
                 VALUES (?, ?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)',
                [
                    $payload['stream_display_name'], $payload['stream_source'], $payload['stream_icon'], 'movie',
                    $payload['year'], $payload['tmdb_id'], $payload['overview'], $payload['backdrop'],
                    $payload['rating'], $payload['runtime'], $payload['genres'], $payload['original_title'],
                    time(), time(),
                ]
            );
            $result = 'movies_added';
        } else {
            Db::execute(
                'UPDATE streams SET stream_display_name = ?, stream_source = ?, stream_icon = COALESCE(NULLIF(?, \'\'), stream_icon),
                        backdrop = COALESCE(NULLIF(?, \'\'), backdrop), overview = COALESCE(NULLIF(?, \'\'), overview),
                        rating = COALESCE(?, rating), runtime = COALESCE(?, runtime), genres = COALESCE(NULLIF(?, \'\'), genres),
                        original_title = COALESCE(NULLIF(?, \'\'), original_title), year = COALESCE(?, year),
                        tmdb_id = COALESCE(?, tmdb_id), updated_at = ?
                 WHERE id = ?',
                [
                    $payload['stream_display_name'], $payload['stream_source'], $payload['stream_icon'],
                    $payload['backdrop'], $payload['overview'], $payload['rating'], $payload['runtime'],
                    $payload['genres'], $payload['original_title'], $payload['year'], $payload['tmdb_id'],
                    time(), $streamId,
                ]
            );
            $result = 'movies_updated';
        }

        $this->upsertFile($file, $hash, [
            'title' => $title, 'year' => $payload['year'], 'stream_id' => $streamId,
            'tmdb_id' => $payload['tmdb_id'], 'status' => 'indexed', 'error' => '',
        ]);

        return $result;
    }

    private function indexEpisode(array $file, ?array $existing, string $hash, bool $dryRun, ?TmdbClient $tmdb, int $enrichLimit, array &$report): string
    {
        $seriesKey = mb_strtolower($file['series_name']);
        $series = $this->seriesCache[$seriesKey] ?? null;

        if ($series === null) {
            $series = Db::first('SELECT * FROM series WHERE name = ? LIMIT 1', [$file['series_name']]);
            $this->seriesCache[$seriesKey] = $series;
        }

        if ($dryRun) {
            return $existing === null ? ($series === null && !isset($this->seriesCache[$seriesKey]) ? 'series_added' : 'episodes_added') : 'episodes_updated';
        }

        if ($series === null) {
            $meta = null;

            if ($tmdb !== null && $enrichLimit > 0 && $this->enrichUsed < $enrichLimit) {
                $this->enrichUsed++;

                try {
                    $results = $tmdb->search($file['series_name'], 'tv');

                    if ($results !== []) {
                        $meta = $tmdb->details('tv', (int) $results[0]['id']);
                    }
                } catch (\Throwable $e) {
                    $report['errors'][] = sprintf('TMDB %s: %s', $file['series_name'], $e->getMessage());
                }
            }

            $seriesId = Db::insert(
                'INSERT INTO series (name, description, cover, tmdb_id, overview, backdrop, rating, genres, original_title, first_air_date, enabled, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)',
                [
                    (string) ($meta['title'] ?? $file['series_name']),
                    (string) ($meta['overview'] ?? ''),
                    (string) ($meta['poster_url'] ?? ''),
                    isset($meta['id']) ? (int) $meta['id'] : null,
                    (string) ($meta['overview'] ?? ''),
                    (string) ($meta['backdrop_url'] ?? ''),
                    isset($meta['rating']) ? (float) $meta['rating'] : null,
                    isset($meta['genres']) && is_array($meta['genres']) ? implode(', ', $meta['genres']) : '',
                    (string) ($meta['original_title'] ?? ''),
                    (string) ($meta['release_date'] ?? ''),
                    time(), time(),
                ]
            );

            $series = Db::first('SELECT * FROM series WHERE id = ?', [$seriesId]);
            $this->seriesCache[$seriesKey] = $series;
            $report['series_added']++;
        }

        $source = 'file://' . $file['path'];
        $episodeId = $existing['episode_id'] ?? null;

        if ($episodeId === null) {
            $duplicate = Db::first(
                'SELECT id, source FROM episodes WHERE series_id = ? AND season_num = ? AND episode_num = ? LIMIT 1',
                [(int) $series['id'], (int) $file['season_num'], (int) $file['episode_num']]
            );

            if ($duplicate !== null) {
                $episodeId = (int) $duplicate['id'];

                if (!str_starts_with((string) $duplicate['source'], 'file://')) {
                    $source = (string) $duplicate['source'];
                }
            }
        }

        if ($episodeId === null) {
            $episodeId = Db::insert(
                'INSERT INTO episodes (series_id, season_num, episode_num, title, source, duration, enabled, created_at)
                 VALUES (?, ?, ?, ?, ?, 0, 1, ?)',
                [(int) $series['id'], (int) $file['season_num'], (int) $file['episode_num'], $file['title'], $source, time()]
            );
            $result = 'episodes_added';
        } else {
            Db::execute(
                'UPDATE episodes SET title = ?, source = ?, enabled = 1 WHERE id = ?',
                [$file['title'], $source, $episodeId]
            );
            $result = 'episodes_updated';
        }

        $this->upsertFile($file, $hash, [
            'title' => $file['title'], 'year' => null, 'series_id' => (int) $series['id'],
            'episode_id' => $episodeId, 'tmdb_id' => $series['tmdb_id'] ?? null, 'status' => 'indexed', 'error' => '',
            'season_num' => (int) $file['season_num'], 'episode_num' => (int) $file['episode_num'],
        ]);

        return $result;
    }

    private function upsertFile(array $file, string $hash, array $fields): void
    {
        Db::execute(
            'INSERT INTO library_files (path, path_hash, media_type, title, year, season_num, episode_num,
                                        stream_id, series_id, episode_id, tmdb_id, size, mtime, status, error, scanned_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE title = VALUES(title), year = VALUES(year), season_num = VALUES(season_num),
                episode_num = VALUES(episode_num), stream_id = VALUES(stream_id), series_id = VALUES(series_id),
                episode_id = VALUES(episode_id), tmdb_id = VALUES(tmdb_id), size = VALUES(size), mtime = VALUES(mtime),
                status = VALUES(status), error = VALUES(error), scanned_at = VALUES(scanned_at)',
            [
                $file['path'], $hash, $file['media_type'], $fields['title'], $fields['year'] ?? null,
                $fields['season_num'] ?? $file['season_num'] ?? null, $fields['episode_num'] ?? $file['episode_num'] ?? null,
                $fields['stream_id'] ?? null, $fields['series_id'] ?? null, $fields['episode_id'] ?? null,
                $fields['tmdb_id'] ?? null, $file['size'], $file['mtime'], $fields['status'], $fields['error'], time(),
            ]
        );
    }

    private function recordFailure(array $file, string $error): void
    {
        $hash = sha1($file['path']);

        try {
            Db::execute(
                'INSERT INTO library_files (path, path_hash, media_type, title, size, mtime, status, error, scanned_at)
                 VALUES (?, ?, ?, ?, ?, ?, \'error\', ?, ?)
                 ON DUPLICATE KEY UPDATE status = VALUES(status), error = VALUES(error), scanned_at = VALUES(scanned_at)',
                [$file['path'], $hash, $file['media_type'], $file['title'], $file['size'], $file['mtime'], mb_substr($error, 0, 500), time()]
            );
        } catch (\Throwable) {
            // never let bookkeeping break the scan
        }
    }

    private function markMissing(array $seenHashes): int
    {
        $rows = Db::select("SELECT id, path_hash FROM library_files WHERE status = 'indexed'");
        $missing = 0;

        foreach ($rows as $row) {
            if (isset($seenHashes[$row['path_hash']])) {
                continue;
            }

            Db::execute("UPDATE library_files SET status = 'missing', scanned_at = ? WHERE id = ?", [time(), (int) $row['id']]);
            $missing++;
        }

        return $missing;
    }

    private function resolvePaths(string $configured, string $fallback): array
    {
        $paths = [];

        foreach (preg_split('/\r\n|\n|\r/', $configured) ?: [] as $line) {
            $line = trim($line);

            if ($line !== '') {
                $paths[] = rtrim($line, '/\\');
            }
        }

        if ($paths === []) {
            $default = dirname(__DIR__, 3) . DIRECTORY_SEPARATOR . 'media' . DIRECTORY_SEPARATOR . $fallback;

            if (is_dir($default)) {
                $paths[] = $default;
            }
        }

        return array_values(array_unique($paths));
    }

    private static function seriesNameFromPath(string $path, string $prefix): ?string
    {
        $prefix = self::cleanTitle($prefix);

        if ($prefix !== '' && !preg_match('/^(season|series|s)\s*\d+$/i', $prefix)) {
            return $prefix;
        }

        $directory = dirname($path);
        $previous = null;

        while ($directory !== '' && $directory !== '.' && $directory !== DIRECTORY_SEPARATOR) {
            $name = basename($directory);

            if (!preg_match('/^(season|series|s)\s*\d+$/i', $name) && !preg_match('/^(movies?|tv|shows?|series)$/i', $name)) {
                return self::cleanTitle($name);
            }

            $previous = $directory;
            $parent = dirname($directory);

            if ($parent === $directory) {
                break;
            }

            $directory = $parent;
        }

        return null;
    }

    private static function cleanTitle(string $title): string
    {
        $title = preg_replace('/\b(2160p|1080p|720p|480p|4k|uhd|bluray|blu-ray|brrip|webrip|web-dl|webdl|hdtv|dvdrip|x264|x265|h264|h265|hevc|avc|aac|ac3|dts|ddp?5\.?1|proper|repack|extended|remastered|multi|castellano|latino|dual)\b.*$/i', '', $title) ?? $title;
        $title = preg_replace('/[\[\]{}()]/', ' ', $title) ?? $title;
        $title = preg_replace('/[\s._-]+/', ' ', $title) ?? $title;

        return trim($title, " \t.-_");
    }
}

