-- Connection revocation for the stream gateway (admin kick + max_connections)

ALTER TABLE stream_sessions
    ADD COLUMN revoked TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN revoked_at BIGINT NULL;
