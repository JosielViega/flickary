CREATE TABLE social_activity_comments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    history_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    body VARCHAR(500) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_social_activity_comments_history (history_id, created_at, id),
    KEY idx_social_activity_comments_user (user_id, created_at, id),

    CONSTRAINT fk_social_activity_comments_history
        FOREIGN KEY (history_id) REFERENCES watch_history (id) ON DELETE CASCADE,
    CONSTRAINT fk_social_activity_comments_user
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
