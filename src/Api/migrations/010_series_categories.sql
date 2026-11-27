-- Series categories (Xtream VOD parity)

ALTER TABLE series
    ADD COLUMN category_id INT UNSIGNED NULL,
    ADD KEY idx_series_category (category_id);
