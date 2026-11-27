-- Per-stream audit log (stream detail > Logs)

CREATE TABLE IF NOT EXISTS stream_logs (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stream_id INT UNSIGNED NOT NULL,
    level VARCHAR(16) NOT NULL DEFAULT 'info',
    message VARCHAR(500) NOT NULL,
    context LONGTEXT NULL,
    created_at BIGINT NOT NULL,
    PRIMARY KEY (id),
    KEY idx_stream_logs_stream (stream_id, id),
    KEY idx_stream_logs_created (created_at),
    CONSTRAINT fk_stream_logs_stream FOREIGN KEY (stream_id) REFERENCES streams (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
