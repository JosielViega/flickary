ALTER TABLE user_media
    ADD COLUMN is_anime TINYINT(1) NULL AFTER backdrop_path,
    ADD KEY idx_user_media_anime_updated (user_id, is_anime, updated_at);

ALTER TABLE watch_history
    ADD COLUMN is_anime TINYINT(1) NULL AFTER duration_minutes,
    ADD KEY idx_watch_history_anime_chronology (user_id, is_anime, watched_on, id);

ALTER TABLE user_schedule
    ADD COLUMN is_anime TINYINT(1) NULL AFTER poster_path,
    ADD KEY idx_user_schedule_anime_chronology (user_id, is_anime, scheduled_on, id);
