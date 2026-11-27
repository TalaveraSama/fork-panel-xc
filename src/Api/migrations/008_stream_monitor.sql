-- Per-stream source monitoring (XUI-style monitor)

ALTER TABLE streams
    ADD COLUMN monitor_enabled TINYINT(1) NOT NULL DEFAULT 1,
    ADD COLUMN monitor_interval INT NOT NULL DEFAULT 60,
    ADD COLUMN monitor_last_check BIGINT NULL,
    ADD COLUMN monitor_last_status TINYINT NULL,
    ADD COLUMN monitor_last_error VARCHAR(500) NULL;
