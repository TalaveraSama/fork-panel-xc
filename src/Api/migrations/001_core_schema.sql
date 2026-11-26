-- Fork Panel XC core schema
-- Target: MariaDB 11.x / MySQL 8.x

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(64) NOT NULL,
    email VARCHAR(190) NOT NULL DEFAULT '',
    password_hash VARCHAR(255) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    is_reseller TINYINT(1) NOT NULL DEFAULT 0,
    reseller_id INT UNSIGNED NULL,
    credits INT NOT NULL DEFAULT 0,
    permissions LONGTEXT NULL,
    two_factor_secret VARCHAR(191) NULL,
    last_login BIGINT NULL,
    created_at BIGINT NOT NULL,
    updated_at BIGINT NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_users_username (username),
    KEY idx_users_email (email),
    KEY idx_users_reseller (reseller_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NOT NULL,
    token VARCHAR(64) NOT NULL,
    created_at BIGINT NOT NULL,
    expires_at BIGINT NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_password_resets_token (token),
    KEY idx_password_resets_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS activity_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    user VARCHAR(64) NULL,
    event VARCHAR(190) NOT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'info',
    created_at BIGINT NOT NULL,
    PRIMARY KEY (id),
    KEY idx_activity_user (user_id),
    KEY idx_activity_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_name VARCHAR(190) NOT NULL,
    category_type VARCHAR(32) NOT NULL DEFAULT 'live',
    sort_order INT NOT NULL DEFAULT 0,
    created_at BIGINT NOT NULL,
    PRIMARY KEY (id),
    KEY idx_categories_type (category_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS streams (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    stream_display_name VARCHAR(255) NOT NULL,
    stream_source TEXT NOT NULL,
    stream_icon VARCHAR(500) NOT NULL DEFAULT '',
    stream_type VARCHAR(32) NOT NULL DEFAULT 'live',
    category_id INT UNSIGNED NULL,
    epg_channel_id VARCHAR(191) NULL,
    year SMALLINT NULL,
    tmdb_id INT UNSIGNED NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'offline',
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at BIGINT NOT NULL,
    updated_at BIGINT NOT NULL,
    PRIMARY KEY (id),
    KEY idx_streams_type (stream_type),
    KEY idx_streams_category (category_id),
    KEY idx_streams_enabled (enabled),
    CONSTRAINT fk_streams_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS series (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    cover VARCHAR(500) NOT NULL DEFAULT '',
    tmdb_id INT UNSIGNED NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at BIGINT NOT NULL,
    updated_at BIGINT NOT NULL,
    PRIMARY KEY (id),
    KEY idx_series_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS episodes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    series_id INT UNSIGNED NOT NULL,
    season_num INT NOT NULL DEFAULT 1,
    episode_num INT NOT NULL DEFAULT 1,
    title VARCHAR(255) NOT NULL,
    source TEXT NOT NULL,
    duration INT NOT NULL DEFAULT 0,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at BIGINT NOT NULL,
    PRIMARY KEY (id),
    KEY idx_episodes_series (series_id, season_num, episode_num),
    CONSTRAINT fk_episodes_series FOREIGN KEY (series_id) REFERENCES series (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS lines (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id INT UNSIGNED NULL,
    username VARCHAR(64) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    exp_date BIGINT NULL,
    max_connections INT NOT NULL DEFAULT 1,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    connected TINYINT(1) NOT NULL DEFAULT 0,
    bitrate INT NOT NULL DEFAULT 0,
    created_at BIGINT NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_lines_username (username),
    KEY idx_lines_user (user_id),
    KEY idx_lines_connected (connected)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bouquets (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(190) NOT NULL,
    description VARCHAR(500) NOT NULL DEFAULT '',
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at BIGINT NOT NULL,
    updated_at BIGINT NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bouquet_streams (
    bouquet_id INT UNSIGNED NOT NULL,
    stream_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (bouquet_id, stream_id),
    KEY idx_bouquet_streams_stream (stream_id),
    CONSTRAINT fk_bouquet_streams_bouquet FOREIGN KEY (bouquet_id) REFERENCES bouquets (id) ON DELETE CASCADE,
    CONSTRAINT fk_bouquet_streams_stream FOREIGN KEY (stream_id) REFERENCES streams (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_bouquets (
    user_id INT UNSIGNED NOT NULL,
    bouquet_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (user_id, bouquet_id),
    CONSTRAINT fk_user_bouquets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_user_bouquets_bouquet FOREIGN KEY (bouquet_id) REFERENCES bouquets (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS epg_sources (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(190) NOT NULL,
    url VARCHAR(1000) NOT NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    last_sync BIGINT NULL,
    created_at BIGINT NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS epg_channels (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    source_id INT UNSIGNED NULL,
    name VARCHAR(190) NOT NULL,
    epg_id VARCHAR(191) NOT NULL,
    icon VARCHAR(500) NOT NULL DEFAULT '',
    PRIMARY KEY (id),
    UNIQUE KEY uniq_epg_channels_epg_id (epg_id),
    KEY idx_epg_channels_source (source_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS epg_programmes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    channel_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    start_time BIGINT NOT NULL,
    end_time BIGINT NOT NULL,
    PRIMARY KEY (id),
    KEY idx_epg_programmes_channel (channel_id, start_time),
    CONSTRAINT fk_epg_programmes_channel FOREIGN KEY (channel_id) REFERENCES epg_channels (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stream_stats (
    stream_id INT UNSIGNED NOT NULL,
    current_viewers INT NOT NULL DEFAULT 0,
    total_connections BIGINT NOT NULL DEFAULT 0,
    bandwidth BIGINT NOT NULL DEFAULT 0,
    PRIMARY KEY (stream_id),
    CONSTRAINT fk_stream_stats_stream FOREIGN KEY (stream_id) REFERENCES streams (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS stats_hourly (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    hour_label VARCHAR(16) NOT NULL,
    inbound DECIMAL(12, 2) NOT NULL DEFAULT 0,
    outbound DECIMAL(12, 2) NOT NULL DEFAULT 0,
    connections INT NOT NULL DEFAULT 0,
    created_at BIGINT NOT NULL,
    PRIMARY KEY (id),
    KEY idx_stats_hourly_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS modules (
    module_id VARCHAR(190) NOT NULL,
    name VARCHAR(190) NOT NULL,
    version VARCHAR(32) NOT NULL,
    description VARCHAR(500) NOT NULL DEFAULT '',
    author VARCHAR(190) NOT NULL DEFAULT '',
    status VARCHAR(32) NOT NULL DEFAULT 'installed',
    created_at BIGINT NOT NULL,
    updated_at BIGINT NOT NULL,
    PRIMARY KEY (module_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    section VARCHAR(64) NOT NULL,
    setting_key VARCHAR(190) NOT NULL,
    setting_value LONGTEXT NULL,
    updated_at BIGINT NOT NULL,
    PRIMARY KEY (section, setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS backups (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    size BIGINT NOT NULL DEFAULT 0,
    status VARCHAR(32) NOT NULL DEFAULT 'queued',
    created_at BIGINT NOT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS panel_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    type VARCHAR(32) NOT NULL,
    log_message TEXT NOT NULL,
    file VARCHAR(500) NOT NULL DEFAULT '',
    line INT NOT NULL DEFAULT 0,
    time BIGINT NOT NULL,
    PRIMARY KEY (id),
    KEY idx_panel_logs_type (type),
    KEY idx_panel_logs_time (time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS jobs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(190) NOT NULL,
    payload LONGTEXT NULL,
    status VARCHAR(32) NOT NULL DEFAULT 'queued',
    error TEXT NULL,
    attempts INT NOT NULL DEFAULT 0,
    started_at BIGINT NULL,
    finished_at BIGINT NULL,
    created_at BIGINT NOT NULL,
    PRIMARY KEY (id),
    KEY idx_jobs_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mag_devices (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    mac VARCHAR(32) NOT NULL,
    user_id INT UNSIGNED NULL,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    last_seen BIGINT NULL,
    created_at BIGINT NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_mag_devices_mac (mac)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
