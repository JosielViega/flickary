CREATE TABLE user_series_episode_progress (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    source VARCHAR(16) NOT NULL,
    series_source_id BIGINT UNSIGNED NOT NULL,
    season_number INT UNSIGNED NOT NULL,
    episode_number INT UNSIGNED NOT NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_user_series_episode_progress (
        user_id,
        source,
        series_source_id,
        season_number,
        episode_number
    ),
    KEY idx_series_progress_lookup (
        user_id,
        source,
        series_source_id,
        season_number
    ),

    CONSTRAINT fk_series_progress_user
        FOREIGN KEY (user_id)
        REFERENCES users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
