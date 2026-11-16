ALTER TABLE user_profiles
    ADD COLUMN share_watch_activity BOOLEAN NOT NULL DEFAULT FALSE AFTER is_private,
    ADD COLUMN share_watch_activity_since TIMESTAMP NULL DEFAULT NULL AFTER share_watch_activity;

ALTER TABLE watch_history
    ADD KEY idx_watch_history_social_feed (user_id, created_at, id);
