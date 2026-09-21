ALTER TABLE watch_history
    ADD COLUMN duration_minutes SMALLINT UNSIGNED NULL AFTER poster_path;
