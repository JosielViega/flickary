CREATE TABLE user_media_preferences (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    source VARCHAR(16) NOT NULL,
    media_type VARCHAR(16) NOT NULL,
    source_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    original_title VARCHAR(255) NULL,
    release_date DATE NULL,
    poster_path VARCHAR(255) NULL,
    is_anime TINYINT(1) NULL,
    is_favorite TINYINT(1) NOT NULL DEFAULT 0,
    rating TINYINT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_user_media_preferences_identity (user_id, source, media_type, source_id),
    KEY idx_preferences_favorite_type (user_id, is_favorite, media_type, updated_at),
    KEY idx_preferences_favorite_anime (user_id, is_favorite, is_anime, updated_at),
    KEY idx_preferences_rating_type (user_id, rating, media_type, updated_at),

    CONSTRAINT fk_user_media_preferences_user
        FOREIGN KEY (user_id)
        REFERENCES users (id)
        ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
