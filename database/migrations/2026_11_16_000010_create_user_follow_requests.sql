CREATE TABLE user_follow_requests (
    requester_user_id BIGINT UNSIGNED NOT NULL,
    target_user_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (requester_user_id, target_user_id),
    KEY idx_user_follow_requests_target (target_user_id, requester_user_id),
    CONSTRAINT fk_user_follow_requests_requester FOREIGN KEY (requester_user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_user_follow_requests_target FOREIGN KEY (target_user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO user_follow_requests (requester_user_id, target_user_id, created_at)
SELECT f.follower_user_id, f.followed_user_id, f.created_at
FROM user_follows f
INNER JOIN user_profiles p ON p.user_id = f.followed_user_id
WHERE p.is_private = 1;

DELETE f
FROM user_follows f
INNER JOIN user_profiles p ON p.user_id = f.followed_user_id
WHERE p.is_private = 1;
