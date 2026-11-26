-- TMDB metadata columns for movies (streams) and series

ALTER TABLE streams
    ADD COLUMN overview TEXT NULL,
    ADD COLUMN backdrop VARCHAR(500) NOT NULL DEFAULT '',
    ADD COLUMN rating DECIMAL(3,1) NULL,
    ADD COLUMN runtime INT NULL,
    ADD COLUMN genres VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN original_title VARCHAR(255) NOT NULL DEFAULT '';

ALTER TABLE series
    ADD COLUMN overview TEXT NULL,
    ADD COLUMN backdrop VARCHAR(500) NOT NULL DEFAULT '',
    ADD COLUMN rating DECIMAL(3,1) NULL,
    ADD COLUMN genres VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN original_title VARCHAR(255) NOT NULL DEFAULT '',
    ADD COLUMN first_air_date VARCHAR(20) NOT NULL DEFAULT '';
