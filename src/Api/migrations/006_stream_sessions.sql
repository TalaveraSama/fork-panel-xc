-- Viewer sessions for the stream gateway (stats + connected lines)

CREATE TABLE IF NOT EXISTS stream_sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    session_key CHAR(32) NOT NULL,
    line_id INT UNSIGNED NOT NULL,
    stream_id INT UNSIGNED NOT NULL,
    ip VARCHAR(45) NOT NULL DEFAULT '',
    user_agent VARCHAR(255) NOT NULL DEFAULT '',
    bytes BIGINT NOT NULL DEFAULT 0,
    bitrate INT NOT NULL DEFAULT 0,
    started_at BIGINT NOT NULL,
    last_seen BIGINT NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uniq_stream_sessions_key (session_key),
    KEY idx_stream_sessions_stream (stream_id, last_seen),
    KEY idx_stream_sessions_line (line_id, last_seen),
    KEY idx_stream_sessions_seen (last_seen)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
