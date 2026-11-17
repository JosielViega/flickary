ALTER TABLE users
    ADD COLUMN is_admin BOOLEAN NOT NULL DEFAULT FALSE AFTER email;

CREATE TABLE social_reports (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    reporter_user_id BIGINT UNSIGNED NOT NULL,
    reported_user_id BIGINT UNSIGNED NULL,
    target_type VARCHAR(32) NOT NULL,
    target_id BIGINT UNSIGNED NOT NULL,
    reason VARCHAR(32) NOT NULL,
    details VARCHAR(1000) NULL,
    target_username_snapshot VARCHAR(50) NULL,
    target_display_name_snapshot VARCHAR(100) NULL,
    content_snapshot TEXT NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'pending',
    dedupe_key VARCHAR(96) NULL,
    reviewed_by_user_id BIGINT UNSIGNED NULL,
    reviewed_at TIMESTAMP NULL DEFAULT NULL,
    moderator_note VARCHAR(1000) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    UNIQUE KEY uq_social_reports_pending (reporter_user_id, dedupe_key),
    KEY idx_social_reports_status_created (status, created_at, id),
    KEY idx_social_reports_reporter_created (reporter_user_id, created_at, id),
    KEY idx_social_reports_reported_created (reported_user_id, created_at, id),

    CONSTRAINT fk_social_reports_reporter FOREIGN KEY (reporter_user_id) REFERENCES users (id) ON DELETE CASCADE,
    CONSTRAINT fk_social_reports_reported FOREIGN KEY (reported_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_social_reports_reviewer FOREIGN KEY (reviewed_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
