CREATE TABLE user_schedule (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    source VARCHAR(16) NOT NULL,
    entry_type VARCHAR(16) NOT NULL,
    source_id BIGINT UNSIGNED NOT NULL,
    season_number INT UNSIGNED NOT NULL DEFAULT 0,
    episode_number INT UNSIGNED NOT NULL DEFAULT 0,
    title VARCHAR(255) NOT NULL,
    original_title VARCHAR(255) NULL,
    episode_title VARCHAR(255) NULL,
    content_date DATE NULL,
    poster_path VARCHAR(255) NULL,
    scheduled_on DATE NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_user_schedule_identity (
        user_id, source, entry_type, source_id, season_number, episode_number
    ),
    KEY idx_user_schedule_chronology (user_id, scheduled_on, id),
    KEY idx_user_schedule_type (user_id, entry_type, scheduled_on, id),

    CONSTRAINT fk_user_schedule_user
        FOREIGN KEY (user_id)
        REFERENCES users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
