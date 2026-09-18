CREATE TABLE user_media (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    source VARCHAR(16) NOT NULL,
    media_type VARCHAR(16) NOT NULL,
    source_id BIGINT UNSIGNED NOT NULL,
    status VARCHAR(24) NOT NULL,
    title VARCHAR(255) NOT NULL,
    original_title VARCHAR(255) NULL,
    release_date DATE NULL,
    poster_path VARCHAR(255) NULL,
    backdrop_path VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_media_identity (user_id, source, media_type, source_id),
    KEY idx_user_media_status_updated (user_id, status, updated_at),
    KEY idx_user_media_type_updated (user_id, media_type, updated_at),
    CONSTRAINT fk_user_media_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
