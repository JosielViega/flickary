CREATE TABLE watch_history (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    source VARCHAR(16) NOT NULL,
    entry_type VARCHAR(16) NOT NULL,
    source_id BIGINT UNSIGNED NOT NULL,
    season_number INT UNSIGNED NULL,
    episode_number INT UNSIGNED NULL,
    title VARCHAR(255) NOT NULL,
    original_title VARCHAR(255) NULL,
    episode_title VARCHAR(255) NULL,
    content_date DATE NULL,
    poster_path VARCHAR(255) NULL,
    watched_on DATE NOT NULL,
    request_key CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_watch_history_request (user_id, request_key),
    KEY idx_watch_history_chronology (user_id, watched_on, id),
    KEY idx_watch_history_type (user_id, entry_type, watched_on, id),
    KEY idx_watch_history_source (user_id, source, source_id, watched_on, id),

    CONSTRAINT fk_watch_history_user
        FOREIGN KEY (user_id)
        REFERENCES users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
