CREATE TABLE user_follows (
    follower_user_id BIGINT UNSIGNED NOT NULL,
    followed_user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (follower_user_id, followed_user_id),
    KEY idx_user_follows_followed (followed_user_id, follower_user_id),

    CONSTRAINT fk_user_follows_follower
        FOREIGN KEY (follower_user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_user_follows_followed
        FOREIGN KEY (followed_user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
