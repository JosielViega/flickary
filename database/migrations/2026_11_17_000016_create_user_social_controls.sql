CREATE TABLE IF NOT EXISTS user_blocks (
    blocker_user_id BIGINT UNSIGNED NOT NULL,
    blocked_user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (blocker_user_id, blocked_user_id),
    KEY idx_user_blocks_blocked (blocked_user_id, blocker_user_id),
    CONSTRAINT fk_user_blocks_blocker FOREIGN KEY (blocker_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_blocks_blocked FOREIGN KEY (blocked_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_mutes (
    user_id BIGINT UNSIGNED NOT NULL,
    muted_user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, muted_user_id),
    KEY idx_user_mutes_muted (muted_user_id, user_id),
    CONSTRAINT fk_user_mutes_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_mutes_muted FOREIGN KEY (muted_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
