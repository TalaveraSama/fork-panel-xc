-- Library scanner: index local media folders into movies/series

CREATE TABLE IF NOT EXISTS library_files (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    path VARCHAR(1024) NOT NULL,
    path_hash CHAR(40) NOT NULL,
    media_type VARCHAR(16) NOT NULL,
    title VARCHAR(255) NOT NULL DEFAULT '',
    year SMALLINT NULL,
    season_num INT NULL,
    episode_num INT NULL,
    stream_id INT UNSIGNED NULL,
    series_id INT UNSIGNED NULL,
    episode_id INT UNSIGNED NULL,
    tmdb_id INT UNSIGNED NULL,
    size BIGINT NOT NULL DEFAULT 0,
    mtime BIGINT NOT NULL DEFAULT 0,
    status VARCHAR(16) NOT NULL DEFAULT 'indexed',
    error VARCHAR(500) NOT NULL DEFAULT '',
    scanned_at BIGINT NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_library_path_hash (path_hash),
    KEY idx_library_media (media_type),
    KEY idx_library_stream (stream_id),
    KEY idx_library_series (series_id),
    KEY idx_library_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO settings (section, setting_key, setting_value, updated_at) VALUES
('library', 'movie_paths', '', UNIX_TIMESTAMP()),
('library', 'series_paths', '', UNIX_TIMESTAMP()),
('library', 'extensions', 'mkv,mp4,avi,mov,m4v,webm,ts', UNIX_TIMESTAMP()),
('library', 'scan_interval_minutes', '60', UNIX_TIMESTAMP()),
('library', 'enrich_tmdb', '1', UNIX_TIMESTAMP()),
('library', 'enrich_limit', '25', UNIX_TIMESTAMP()),
('library', 'auto_enabled', '1', UNIX_TIMESTAMP()),
('library', 'mark_missing', '1', UNIX_TIMESTAMP()),
('library', 'last_scan_at', '0', UNIX_TIMESTAMP()),
('library', 'last_report', '{}', UNIX_TIMESTAMP());
