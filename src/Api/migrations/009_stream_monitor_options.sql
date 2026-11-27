-- Monitor options (failure threshold + auto-restart)

ALTER TABLE streams
    ADD COLUMN monitor_fail_threshold INT NOT NULL DEFAULT 1,
    ADD COLUMN monitor_fail_count INT NOT NULL DEFAULT 0,
    ADD COLUMN monitor_autorestart TINYINT(1) NOT NULL DEFAULT 0;
