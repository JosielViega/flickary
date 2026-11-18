ALTER TABLE users
    ADD COLUMN suspended_until TIMESTAMP NULL DEFAULT NULL AFTER is_admin,
    ADD KEY idx_users_suspended_until (suspended_until);

CREATE TABLE moderation_actions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    action_type VARCHAR(32) NOT NULL,
    report_id BIGINT UNSIGNED NULL,
    target_user_id BIGINT UNSIGNED NULL,
    performed_by_user_id BIGINT UNSIGNED NULL,
    target_type VARCHAR(32) NULL,
    target_id BIGINT UNSIGNED NULL,
    target_username_snapshot VARCHAR(50) NULL,
    target_display_name_snapshot VARCHAR(100) NULL,
    content_snapshot TEXT NULL,
    duration_minutes INT UNSIGNED NULL,
    previous_suspended_until TIMESTAMP NULL DEFAULT NULL,
    effective_until TIMESTAMP NULL DEFAULT NULL,
    note VARCHAR(1000) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_moderation_actions_target_created (target_user_id, created_at, id),
    KEY idx_moderation_actions_admin_created (performed_by_user_id, created_at, id),
    KEY idx_moderation_actions_report (report_id),
    KEY idx_moderation_actions_type_created (action_type, created_at, id),
    KEY idx_moderation_actions_created (created_at, id),

    CONSTRAINT fk_moderation_actions_report FOREIGN KEY (report_id) REFERENCES social_reports (id) ON DELETE SET NULL,
    CONSTRAINT fk_moderation_actions_target FOREIGN KEY (target_user_id) REFERENCES users (id) ON DELETE SET NULL,
    CONSTRAINT fk_moderation_actions_admin FOREIGN KEY (performed_by_user_id) REFERENCES users (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
