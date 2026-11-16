CREATE TABLE social_activity_likes (
    history_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (history_id, user_id),
    KEY idx_social_activity_likes_user (user_id, created_at),

    CONSTRAINT fk_social_activity_likes_history
        FOREIGN KEY (history_id) REFERENCES watch_history (id) ON DELETE CASCADE,
    CONSTRAINT fk_social_activity_likes_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
