CREATE TABLE social_notifications (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    recipient_user_id BIGINT UNSIGNED NOT NULL,
    actor_user_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(32) NOT NULL,
    history_id BIGINT UNSIGNED NULL,
    comment_id BIGINT UNSIGNED NULL,
    read_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_social_notifications_recipient (recipient_user_id, read_at, created_at, id),
    KEY idx_social_notifications_actor (actor_user_id),
    KEY idx_social_notifications_history (history_id),
    KEY idx_social_notifications_comment (comment_id),

    CONSTRAINT fk_social_notifications_recipient FOREIGN KEY (recipient_user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_social_notifications_actor FOREIGN KEY (actor_user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_social_notifications_history FOREIGN KEY (history_id) REFERENCES watch_history (id) ON DELETE CASCADE,
    CONSTRAINT fk_social_notifications_comment FOREIGN KEY (comment_id) REFERENCES social_activity_comments (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO social_notifications (recipient_user_id, actor_user_id, type, created_at)
SELECT target_user_id, requester_user_id, 'follow_request', created_at
FROM user_follow_requests;
