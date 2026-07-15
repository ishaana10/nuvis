-- nuBuilder Next - Consolidated Database Schema & Seed Data
-- Compatible with MySQL 5.7+ / MariaDB 10.3+
-- Unified Schema including Action Hooks, Role-Based Access Control, Email settings, and more.

SET FOREIGN_KEY_CHECKS = 0;

-- ─── 1. USERS ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nu_users` (
    `usr_id` INT AUTO_INCREMENT PRIMARY KEY,
    `usr_username` VARCHAR(50) NOT NULL UNIQUE,
    `usr_password` VARCHAR(255) NOT NULL,
    `usr_email` VARCHAR(100),
    `usr_role` VARCHAR(30) DEFAULT 'user',
    `usr_active` TINYINT(1) DEFAULT 1,
    `usr_2fa_secret` VARCHAR(32),
    `usr_failed_attempts` INT DEFAULT 0,
    `usr_last_attempt` DATETIME,
    `usr_password_changed_at` DATETIME DEFAULT NULL,
    `usr_must_change_password` TINYINT(1) NOT NULL DEFAULT 0,
    `usr_created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `usr_updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 2. ROLES ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nu_roles` (
    `role_id` INT AUTO_INCREMENT PRIMARY KEY,
    `role_code` VARCHAR(80) NOT NULL UNIQUE,
    `role_name` VARCHAR(120) NOT NULL,
    `role_description` VARCHAR(255) NOT NULL DEFAULT '',
    `role_active` TINYINT(1) NOT NULL DEFAULT 1,
    `role_is_system` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = protected system role, cannot be deleted',
    `role_created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 3. PERMISSIONS ──────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nu_permissions` (
    `perm_id` INT AUTO_INCREMENT PRIMARY KEY,
    `perm_code` VARCHAR(50) NOT NULL UNIQUE,
    `perm_name` VARCHAR(50) NOT NULL,
    `perm_category` VARCHAR(30),
    `perm_description` TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 4. ROLE-PERMISSION MAPPING ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nu_role_permissions` (
    `rp_id` INT AUTO_INCREMENT PRIMARY KEY,
    `rp_role_id` INT NOT NULL,
    `rp_perm_id` INT NOT NULL,
    UNIQUE KEY `unique_role_perm` (`rp_role_id`, `rp_perm_id`),
    FOREIGN KEY (`rp_role_id`) REFERENCES `nu_roles` (`role_id`) ON DELETE CASCADE,
    FOREIGN KEY (`rp_perm_id`) REFERENCES `nu_permissions` (`perm_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 5. USER META ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nu_user_meta` (
    `umeta_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `umeta_user_id` INT NOT NULL,
    `umeta_key` VARCHAR(80) NOT NULL COMMENT 'Matches key defined in config.user_fields.php',
    `umeta_value` VARCHAR(500) NOT NULL DEFAULT '',
    PRIMARY KEY (`umeta_id`),
    UNIQUE KEY `uq_user_meta` (`umeta_user_id`, `umeta_key`),
    KEY `idx_umeta_key` (`umeta_key`),
    FOREIGN KEY (`umeta_user_id`) REFERENCES `nu_users` (`usr_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Stores arbitrary key/value meta for each user';

-- ─── 6. ROLE FORM PERMISSIONS ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nu_role_form_permissions` (
    `rfp_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `rfp_role_code` VARCHAR(80) NOT NULL COMMENT 'Matches nu_roles.role_code',
    `rfp_form_code` VARCHAR(120) NOT NULL COMMENT 'Matches nu_forms.form_code, or * for wildcard default',
    `rfp_can_view` TINYINT(1) NOT NULL DEFAULT 0,
    `rfp_can_add` TINYINT(1) NOT NULL DEFAULT 0,
    `rfp_can_edit` TINYINT(1) NOT NULL DEFAULT 0,
    `rfp_can_delete` TINYINT(1) NOT NULL DEFAULT 0,
    `rfp_can_export` TINYINT(1) NOT NULL DEFAULT 0,
    PRIMARY KEY (`rfp_id`),
    UNIQUE KEY `uq_role_form` (`rfp_role_code`, `rfp_form_code`),
    KEY `idx_rfp_role` (`rfp_role_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Per-role, per-form CRUD permissions';

-- ─── 7. API TOKENS ───────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nu_api_tokens` (
    `token_id` INT AUTO_INCREMENT PRIMARY KEY,
    `token_key` VARCHAR(64) NOT NULL UNIQUE,
    `token_user_id` INT NOT NULL,
    `token_name` VARCHAR(50),
    `token_active` TINYINT(1) DEFAULT 1,
    `token_expires` DATETIME,
    `token_last_used` DATETIME,
    `token_created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`token_user_id`) REFERENCES `nu_users` (`usr_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 8. API USAGE ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nu_api_usage` (
    `usage_id` INT AUTO_INCREMENT PRIMARY KEY,
    `usage_user_id` INT NOT NULL,
    `usage_hour` DATETIME NOT NULL,
    `usage_count` INT DEFAULT 1,
    UNIQUE KEY `unique_user_hour` (`usage_user_id`, `usage_hour`),
    FOREIGN KEY (`usage_user_id`) REFERENCES `nu_users` (`usr_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 9. AUDIT LOG ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nu_audit_log` (
    `audit_id` INT AUTO_INCREMENT PRIMARY KEY,
    `audit_action` VARCHAR(30) NOT NULL,
    `audit_table` VARCHAR(50) NOT NULL,
    `audit_record_id` INT,
    `audit_old_data` JSON,
    `audit_new_data` JSON,
    `audit_user_id` INT,
    `audit_username` VARCHAR(50),
    `audit_ip` VARCHAR(45),
    `audit_user_agent` VARCHAR(255),
    `audit_timestamp` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_action` (`audit_action`),
    INDEX `idx_table` (`audit_table`),
    INDEX `idx_user` (`audit_user_id`),
    INDEX `idx_timestamp` (`audit_timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 10. FILE UPLOADS ─────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nu_files` (
    `file_id` INT AUTO_INCREMENT PRIMARY KEY,
    `file_name` VARCHAR(255) NOT NULL,
    `file_original_name` VARCHAR(255),
    `file_mime_type` VARCHAR(100),
    `file_size` INT,
    `file_path` VARCHAR(500),
    `file_table` VARCHAR(50),
    `file_record_id` INT,
    `file_field` VARCHAR(50),
    `file_uploaded_by` INT,
    `file_uploaded_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_table_record` (`file_table`, `file_record_id`),
    FOREIGN KEY (`file_uploaded_by`) REFERENCES `nu_users` (`usr_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 11. FORMS ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nu_forms` (
    `form_id` INT AUTO_INCREMENT PRIMARY KEY,
    `form_code` VARCHAR(50) NOT NULL UNIQUE,
    `form_name` VARCHAR(100) NOT NULL,
    `form_table` VARCHAR(50),
    `form_description` TEXT,
    `form_layout` JSON,
    `form_settings` JSON,
    `form_active` TINYINT(1) DEFAULT 1,
    `form_pk_type` VARCHAR(20) DEFAULT 'autoincrement',
    `form_table_mode` VARCHAR(20) DEFAULT 'new',
    `form_custom_js` TEXT,
    `form_js_before_save` TEXT,
    `form_js_after_save` TEXT,
    `form_custom_php` TEXT,
    `form_custom_css` TEXT,
    `browse_sql` TEXT,
    `browse_columns` VARCHAR(500),
    `browse_search_enabled` TINYINT(1) DEFAULT 0,
    `browse_search_placeholder` VARCHAR(255),
    `browse_search_fields` VARCHAR(500),
    `browse_page_size` INT DEFAULT 20,
    `browse_default_sort` VARCHAR(255),
    `browse_display_mode` VARCHAR(20) DEFAULT 'inline',
    `browse_php` MEDIUMTEXT DEFAULT NULL,
    `form_email_notify` TINYINT(1) NOT NULL DEFAULT 0,
    `form_email_notify_on` ENUM('new','all') NOT NULL DEFAULT 'new',
    `form_email_to` VARCHAR(500) DEFAULT NULL,
    `form_email_template` VARCHAR(100) DEFAULT 'form_submission',
    `form_created_by` INT,
    `form_created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `form_updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`form_created_by`) REFERENCES `nu_users` (`usr_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 12. REPORTS ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nu_reports` (
    `report_id` INT AUTO_INCREMENT PRIMARY KEY,
    `report_code` VARCHAR(50) NOT NULL UNIQUE,
    `report_name` VARCHAR(100) NOT NULL,
    `report_type` ENUM('table','chart','summary') DEFAULT 'table',
    `report_view_mode` VARCHAR(20) NOT NULL DEFAULT 'table',
    `report_sql` TEXT,
    `report_columns` JSON,
    `report_filters` JSON,
    `report_settings` JSON,
    `report_active` TINYINT(1) DEFAULT 1,
    `report_created_by` INT,
    `report_created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `report_updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_report_code` (`report_code`),
    INDEX `idx_report_active` (`report_active`),
    FOREIGN KEY (`report_created_by`) REFERENCES `nu_users` (`usr_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 13. QUERIES ──────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nu_queries` (
    `query_id` INT AUTO_INCREMENT PRIMARY KEY,
    `query_code` VARCHAR(50) NOT NULL UNIQUE,
    `query_name` VARCHAR(100) NOT NULL,
    `query_sql` TEXT NOT NULL,
    `query_description` TEXT,
    `query_parameters` JSON,
    `query_active` TINYINT(1) DEFAULT 1,
    `query_created_by` INT,
    `query_created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `query_updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`query_created_by`) REFERENCES `nu_users` (`usr_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 14. MENUS ────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nu_menus` (
    `menu_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `menu_label` VARCHAR(120) NOT NULL DEFAULT '',
    `menu_type` ENUM('form','report','query','url','group','divider') NOT NULL DEFAULT 'form',
    `menu_target` VARCHAR(255) NOT NULL DEFAULT '',
    `menu_parent_id` INT UNSIGNED NOT NULL DEFAULT 0,
    `menu_order` SMALLINT NOT NULL DEFAULT 0,
    `menu_roles` VARCHAR(500) NOT NULL DEFAULT '',
    `menu_active` TINYINT(1) NOT NULL DEFAULT 1,
    `menu_icon` VARCHAR(60) NOT NULL DEFAULT 'default',
    `menu_open_mode` VARCHAR(30) NOT NULL DEFAULT 'inline|browse',
    `menu_browse_mode` VARCHAR(10) NOT NULL DEFAULT 'inline',
    `menu_preview_mode` VARCHAR(10) NOT NULL DEFAULT 'inline',
    `menu_default_view` VARCHAR(10) NOT NULL DEFAULT 'browse',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`menu_id`),
    UNIQUE KEY `uq_menu_label_type_target` (`menu_label`(80), `menu_type`, `menu_target`(80))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Sidebar navigation items';

-- ─── 15. DOCUMENTS ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nu_documents` (
    `doc_id` INT AUTO_INCREMENT PRIMARY KEY,
    `doc_title` VARCHAR(255) NOT NULL,
    `doc_description` TEXT,
    `doc_file_id` INT,
    `doc_category` VARCHAR(50),
    `doc_status` ENUM('draft','pending','approved','rejected','archived') DEFAULT 'draft',
    `doc_created_by` INT,
    `doc_created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `doc_updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`doc_file_id`) REFERENCES `nu_files` (`file_id`) ON DELETE SET NULL,
    FOREIGN KEY (`doc_created_by`) REFERENCES `nu_users` (`usr_id`) ON DELETE SET NULL,
    INDEX `idx_status` (`doc_status`),
    INDEX `idx_category` (`doc_category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 16. SIGNATURES ────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nu_signatures` (
    `sig_id` INT AUTO_INCREMENT PRIMARY KEY,
    `sig_document_id` INT NOT NULL,
    `sig_user_id` INT NOT NULL,
    `sig_data` LONGTEXT NOT NULL,
    `sig_ip` VARCHAR(45),
    `sig_user_agent` VARCHAR(255),
    `sig_signed_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`sig_document_id`) REFERENCES `nu_documents` (`doc_id`) ON DELETE CASCADE,
    FOREIGN KEY (`sig_user_id`) REFERENCES `nu_users` (`usr_id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_doc_user` (`sig_document_id`, `sig_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 17. CALENDAR EVENTS ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nu_calendar_events` (
    `event_id` INT AUTO_INCREMENT PRIMARY KEY,
    `event_title` VARCHAR(255) NOT NULL,
    `event_description` TEXT,
    `event_start` DATETIME NOT NULL,
    `event_end` DATETIME,
    `event_type` ENUM('meeting','task','reminder','deadline') DEFAULT 'meeting',
    `event_color` VARCHAR(7) DEFAULT '#0ea5e9',
    `event_user_id` INT,
    `event_active` TINYINT(1) DEFAULT 1,
    `event_created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`event_user_id`) REFERENCES `nu_users` (`usr_id`) ON DELETE SET NULL,
    INDEX `idx_start` (`event_start`),
    INDEX `idx_user` (`event_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 18. WEBHOOKS ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nu_webhooks` (
    `webhook_id` INT AUTO_INCREMENT PRIMARY KEY,
    `webhook_name` VARCHAR(100) NOT NULL,
    `webhook_url` VARCHAR(500) NOT NULL,
    `webhook_events` VARCHAR(255),
    `webhook_secret` VARCHAR(255),
    `webhook_active` TINYINT(1) DEFAULT 1,
    `webhook_last_triggered` DATETIME,
    `webhook_created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_active` (`webhook_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 19. DASHBOARD LAYOUTS ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nu_dashboard_layouts` (
    `layout_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `layout_role` VARCHAR(60) NOT NULL,
    `layout_name` VARCHAR(120) NOT NULL DEFAULT 'Default Layout',
    `layout_is_default` TINYINT(1) NOT NULL DEFAULT 1,
    `layout_created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`layout_id`),
    UNIQUE KEY `uq_role` (`layout_role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 20. DASHBOARD WIDGETS ────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nu_dashboard_widgets` (
    `widget_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `widget_user_id` INT UNSIGNED DEFAULT NULL,
    `widget_role` VARCHAR(60) DEFAULT NULL,
    `widget_type` VARCHAR(30) NOT NULL,
    `widget_title` VARCHAR(120) NOT NULL DEFAULT '',
    `widget_icon` VARCHAR(120) DEFAULT NULL,
    `widget_config` JSON NOT NULL,
    `widget_width` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `widget_height` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `widget_position` SMALLINT NOT NULL DEFAULT 0,
    `widget_active` TINYINT(1) NOT NULL DEFAULT 1,
    `widget_created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `widget_updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`widget_id`),
    INDEX `idx_user` (`widget_user_id`, `widget_active`),
    INDEX `idx_role` (`widget_role`, `widget_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 21. EMAIL SETTINGS ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nu_email_settings` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `setting_key` VARCHAR(100) NOT NULL UNIQUE,
    `setting_value` TEXT NOT NULL,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 22. EMAIL TEMPLATES ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nu_email_templates` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(150) NOT NULL,
    `slug` VARCHAR(100) NOT NULL UNIQUE COMMENT 'Machine-readable key used in code: e.g. form_submission',
    `description` TEXT,
    `subject` VARCHAR(255) NOT NULL,
    `body` LONGTEXT NOT NULL COMMENT 'HTML body, supports {{variable}} placeholders',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 23. EMAIL LOG ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nu_email_log` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `recipient` VARCHAR(500) NOT NULL,
    `subject` VARCHAR(255) NOT NULL,
    `status` ENUM('SENT','FAIL') NOT NULL DEFAULT 'SENT',
    `error_message` VARCHAR(1000) DEFAULT NULL,
    `template_slug` VARCHAR(100) DEFAULT NULL,
    `sent_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_status` (`status`),
    INDEX `idx_sent_at` (`sent_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 24. ERROR LOG ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nu_error_log` (
    `errlog_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `errlog_type` ENUM('PHP','SQL','JS','APP') NOT NULL DEFAULT 'PHP' COMMENT 'Error source type',
    `errlog_severity` ENUM('debug','info','warning','error','fatal') NOT NULL DEFAULT 'error',
    `errlog_message` VARCHAR(2000) NOT NULL DEFAULT '',
    `errlog_context` TEXT DEFAULT NULL COMMENT 'JSON: extra data, SQL, params, etc.',
    `errlog_trace` TEXT DEFAULT NULL COMMENT 'Stack trace or JS stack string',
    `errlog_file` VARCHAR(500) DEFAULT NULL COMMENT 'Source file (NU_ROOT stripped)',
    `errlog_line` SMALLINT UNSIGNED DEFAULT NULL,
    `errlog_request_uri` VARCHAR(500) DEFAULT NULL,
    `errlog_request_method` VARCHAR(10) DEFAULT NULL,
    `errlog_user_id` INT UNSIGNED DEFAULT NULL,
    `errlog_user_name` VARCHAR(100) DEFAULT NULL,
    `errlog_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`errlog_id`),
    KEY `idx_type` (`errlog_type`),
    KEY `idx_severity` (`errlog_severity`),
    KEY `idx_created` (`errlog_created_at`),
    KEY `idx_type_sev` (`errlog_type`, `errlog_severity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Centralised PHP / SQL / JS / APP error log';

-- ─── 25. PASSWORD POLICY ────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nu_password_policy` (
    `policy_id` INT NOT NULL DEFAULT 1,
    `policy_min_length` INT NOT NULL DEFAULT 8,
    `policy_require_uppercase` TINYINT(1) NOT NULL DEFAULT 1,
    `policy_require_lowercase` TINYINT(1) NOT NULL DEFAULT 1,
    `policy_require_number` TINYINT(1) NOT NULL DEFAULT 1,
    `policy_require_special` TINYINT(1) NOT NULL DEFAULT 0,
    `policy_disallow_username` TINYINT(1) NOT NULL DEFAULT 1,
    `policy_history_count` INT NOT NULL DEFAULT 5,
    `policy_expiry_days` INT NOT NULL DEFAULT 0,
    `policy_expiry_warning_days` INT NOT NULL DEFAULT 7,
    `policy_force_change_on_first_login` TINYINT(1) NOT NULL DEFAULT 1,
    `policy_updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`policy_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 26. PASSWORD HISTORY ───────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nu_password_history` (
    `ph_id` INT AUTO_INCREMENT PRIMARY KEY,
    `ph_user_id` INT NOT NULL,
    `ph_hash` VARCHAR(255) NOT NULL,
    `ph_created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_ph_user` (`ph_user_id`),
    FOREIGN KEY (`ph_user_id`) REFERENCES `nu_users` (`usr_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 27. WORKFLOW DEFINITIONS ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nu_workflows` (
    `wf_id` INT NOT NULL AUTO_INCREMENT,
    `wf_code` VARCHAR(64) NOT NULL,
    `wf_name` VARCHAR(128) NOT NULL,
    `wf_description` TEXT,
    `wf_form_code` VARCHAR(64) DEFAULT NULL COMMENT 'optional: bind to a form',
    `wf_active` TINYINT(1) NOT NULL DEFAULT 1,
    `wf_created_by` INT DEFAULT NULL,
    `wf_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `wf_updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`wf_id`),
    UNIQUE KEY `uq_wf_code` (`wf_code`),
    KEY `idx_wf_active` (`wf_active`),
    FOREIGN KEY (`wf_created_by`) REFERENCES `nu_users` (`usr_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 28. WORKFLOW STAGES ──────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nu_workflow_stages` (
    `wfs_id` INT NOT NULL AUTO_INCREMENT,
    `wfs_wf_id` INT NOT NULL,
    `wfs_code` VARCHAR(64)   NOT NULL,
    `wfs_name` VARCHAR(128)  NOT NULL,
    `wfs_description` TEXT,
    `wfs_color` VARCHAR(16) NOT NULL DEFAULT '#6366f1',
    `wfs_is_start` TINYINT(1) NOT NULL DEFAULT 0,
    `wfs_is_end` TINYINT(1) NOT NULL DEFAULT 0,
    `wfs_order` SMALLINT NOT NULL DEFAULT 0,
    `wfs_sla_hours` SMALLINT DEFAULT NULL COMMENT 'SLA in hours, NULL = no limit',
    `wfs_role` VARCHAR(64) DEFAULT NULL COMMENT 'role that acts on this stage',
    `wfs_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`wfs_id`),
    KEY `idx_wfs_wf_id` (`wfs_wf_id`),
    KEY `idx_wfs_order` (`wfs_wf_id`, `wfs_order`),
    CONSTRAINT `fk_wfs_wf` FOREIGN KEY (`wfs_wf_id`) REFERENCES `nu_workflows` (`wf_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 29. WORKFLOW TRANSITIONS ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nu_workflow_transitions` (
    `wft_id` INT NOT NULL AUTO_INCREMENT,
    `wft_wf_id` INT NOT NULL,
    `wft_from_id` INT NOT NULL,
    `wft_to_id` INT NOT NULL,
    `wft_action` VARCHAR(64) NOT NULL DEFAULT 'advance' COMMENT 'advance|reject|escalate|return',
    `wft_label` VARCHAR(128) NOT NULL DEFAULT 'Advance',
    `wft_condition` TEXT DEFAULT NULL COMMENT 'optional JSON condition',
    `wft_hook` TEXT DEFAULT NULL COMMENT 'JSON configuration for custom transition Action Hooks (send_email, call_webhook, update_record)',
    `wft_created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`wft_id`),
    KEY `idx_wft_wf` (`wft_wf_id`),
    KEY `idx_wft_from` (`wft_from_id`),
    CONSTRAINT `fk_wft_wf` FOREIGN KEY (`wft_wf_id`) REFERENCES `nu_workflows` (`wf_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_wft_from` FOREIGN KEY (`wft_from_id`) REFERENCES `nu_workflow_stages` (`wfs_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_wft_to` FOREIGN KEY (`wft_to_id`) REFERENCES `nu_workflow_stages` (`wfs_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 30. WORKFLOW INSTANCES ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nu_workflow_instances` (
    `wfi_id` INT NOT NULL AUTO_INCREMENT,
    `wfi_wf_id` INT NOT NULL,
    `wfi_stage_id` INT NOT NULL COMMENT 'current stage',
    `wfi_record_table` VARCHAR(64) DEFAULT NULL,
    `wfi_record_id` VARCHAR(64) DEFAULT NULL,
    `wfi_status` ENUM('active','completed','rejected','cancelled') NOT NULL DEFAULT 'active',
    `wfi_started_by` INT DEFAULT NULL,
    `wfi_started_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `wfi_completed_at` DATETIME DEFAULT NULL,
    `wfi_meta` TEXT DEFAULT NULL COMMENT 'JSON metadata (TEXT for broad MariaDB compat)',
    PRIMARY KEY (`wfi_id`),
    KEY `idx_wfi_wf` (`wfi_wf_id`),
    KEY `idx_wfi_stage` (`wfi_stage_id`),
    KEY `idx_wfi_status` (`wfi_status`),
    KEY `idx_wfi_record` (`wfi_record_table`, `wfi_record_id`),
    CONSTRAINT `fk_wfi_wf` FOREIGN KEY (`wfi_wf_id`) REFERENCES `nu_workflows` (`wf_id`) ON DELETE CASCADE,
    CONSTRAINT `fk_wfi_stage` FOREIGN KEY (`wfi_stage_id`) REFERENCES `nu_workflow_stages` (`wfs_id`),
    FOREIGN KEY (`wfi_started_by`) REFERENCES `nu_users` (`usr_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── 31. WORKFLOW HISTORY ─────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `nu_workflow_history` (
    `wfh_id` INT NOT NULL AUTO_INCREMENT,
    `wfh_wfi_id` INT NOT NULL,
    `wfh_from_id` INT DEFAULT NULL,
    `wfh_to_id` INT NOT NULL,
    `wfh_action` VARCHAR(64) NOT NULL,
    `wfh_actor_id` INT DEFAULT NULL,
    `wfh_comment` TEXT DEFAULT NULL,
    `wfh_acted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`wfh_id`),
    KEY `idx_wfh_wfi` (`wfh_wfi_id`),
    KEY `idx_wfh_actor` (`wfh_actor_id`),
    CONSTRAINT `fk_wfh_wfi` FOREIGN KEY (`wfh_wfi_id`) REFERENCES `nu_workflow_instances` (`wfi_id`) ON DELETE CASCADE,
    FOREIGN KEY (`wfh_actor_id`) REFERENCES `nu_users` (`usr_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── SEED DATA ───────────────────────────────────────────────────────────────
INSERT IGNORE INTO `nu_roles` (`role_code`, `role_name`, `role_description`, `role_is_system`) VALUES
('globeadmin', 'Globe Admin', 'System administrator - full access, cannot be deleted', 1),
('admin', 'Admin', 'Administrative access', 0),
('user', 'User', 'Standard user access', 0),
('viewer', 'Viewer', 'Read-only access', 0);

INSERT IGNORE INTO `nu_permissions` (`perm_code`, `perm_name`, `perm_category`) VALUES
('users.view', 'View Users', 'Users'),
('users.create', 'Create Users', 'Users'),
('users.edit', 'Edit Users', 'Users'),
('users.delete', 'Delete Users', 'Users'),
('roles.view', 'View Roles', 'Roles'),
('roles.manage', 'Manage Roles', 'Roles'),
('forms.view', 'View Forms', 'Forms'),
('forms.build', 'Build Forms', 'Forms'),
('reports.view', 'View Reports', 'Reports'),
('reports.build', 'Build Reports', 'Reports'),
('queries.view', 'View Queries', 'Queries'),
('queries.build', 'Build Queries', 'Queries'),
('audit.view', 'View Audit Trail', 'Audit'),
('files.view', 'View Files', 'Files'),
('files.upload', 'Upload Files', 'Files'),
('api.view', 'View API Tokens', 'API'),
('api.manage', 'Manage API Tokens', 'API'),
('system.config', 'System Configuration', 'System'),
('password.change', 'Change Own Password', 'Users');

INSERT IGNORE INTO `nu_role_permissions` (`rp_role_id`, `rp_perm_id`)
SELECT r.role_id, p.perm_id FROM `nu_roles` r CROSS JOIN `nu_permissions` p
WHERE r.role_code = 'globeadmin';

INSERT IGNORE INTO `nu_role_permissions` (`rp_role_id`, `rp_perm_id`)
SELECT r.role_id, p.perm_id FROM `nu_roles` r CROSS JOIN `nu_permissions` p
WHERE r.role_code = 'admin';

-- Default password: password (change immediately after first login)
INSERT IGNORE INTO `nu_users` (`usr_username`, `usr_password`, `usr_email`, `usr_role`, `usr_active`) VALUES
('globeadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@nubuilder.local', 'globeadmin', 1);

-- Default Password Policy
INSERT IGNORE INTO `nu_password_policy` (`policy_id`) VALUES (1);

-- Consolidated nu_menus Seed
INSERT IGNORE INTO `nu_menus`
  (`menu_label`, `menu_type`, `menu_target`, `menu_parent_id`, `menu_order`, `menu_roles`, `menu_active`, `menu_icon`, `menu_open_mode`, `menu_browse_mode`, `menu_preview_mode`, `menu_default_view`)
VALUES
  ('Main', 'group', '', 0, 10, '', 1, 'group', 'inline|browse', 'inline', 'inline', 'browse');

SET @main_group = LAST_INSERT_ID();

SELECT @main_group := `menu_id`
FROM   `nu_menus`
WHERE  `menu_label` = 'Main'
  AND  `menu_type`  = 'group'
  AND  `menu_target` = ''
LIMIT 1;

INSERT IGNORE INTO `nu_menus`
  (`menu_label`, `menu_type`, `menu_target`, `menu_parent_id`, `menu_order`, `menu_roles`, `menu_active`, `menu_icon`, `menu_open_mode`, `menu_browse_mode`, `menu_preview_mode`, `menu_default_view`)
VALUES
  ('Dashboard',    'form', 'dashboard',    @main_group, 10, '', 1, 'dashboard', 'inline|browse', 'inline', 'inline', 'browse'),
  ('Forms',        'form', 'forms',         @main_group, 20, '', 1, 'forms', 'inline|browse', 'inline', 'inline', 'browse'),
  ('Reports',      'form', 'reports',       @main_group, 30, '', 1, 'reports', 'inline|browse', 'inline', 'inline', 'browse'),
  ('Queries',      'form', 'queries',       @main_group, 40, '', 1, 'queries', 'inline|browse', 'inline', 'inline', 'browse'),
  ('Calendar',     'form', 'calendar',      @main_group, 50, '', 1, 'calendar', 'inline|browse', 'inline', 'inline', 'browse'),
  ('AI Assistant', 'form', 'ai',            @main_group, 60, '', 1, 'ai', 'inline|browse', 'inline', 'inline', 'browse'),
  ('Integrations', 'form', 'integrations',  @main_group, 70, '', 1, 'link', 'inline|browse', 'inline', 'inline', 'browse');

INSERT IGNORE INTO `nu_menus`
  (`menu_label`, `menu_type`, `menu_target`, `menu_parent_id`, `menu_order`, `menu_roles`, `menu_active`, `menu_icon`, `menu_open_mode`, `menu_browse_mode`, `menu_preview_mode`, `menu_default_view`)
VALUES
  ('Admin Tools', 'group', '', 0, 80, 'globeadmin,admin', 1, 'group', 'inline|browse', 'inline', 'inline', 'browse');

SET @admin_group = LAST_INSERT_ID();

SELECT @admin_group := `menu_id`
FROM   `nu_menus`
WHERE  `menu_label` = 'Admin Tools'
  AND  `menu_type`  = 'group'
  AND  `menu_target` = ''
LIMIT 1;

INSERT IGNORE INTO `nu_menus`
  (`menu_label`, `menu_type`, `menu_target`, `menu_parent_id`, `menu_order`, `menu_roles`, `menu_active`, `menu_icon`, `menu_open_mode`, `menu_browse_mode`, `menu_preview_mode`, `menu_default_view`)
VALUES
  ('Menus',                 'form', 'menus',             @admin_group,    10,  'globeadmin,admin',  1, 'menus', 'inline|browse', 'inline', 'inline', 'browse'),
  ('Users',                 'form', 'users',             @admin_group,    20,  'globeadmin,admin',  1, 'users', 'inline|browse', 'inline', 'inline', 'browse'),
  ('Roles',                 'form', 'roles',             @admin_group,    25,  'globeadmin,admin',  1, 'roles', 'inline|browse', 'inline', 'inline', 'browse'),
  ('Audit Trail',           'form', 'audit',             @admin_group,    28,  'globeadmin,admin',  1, 'audit', 'inline|browse', 'inline', 'inline', 'browse'),
  ('Files',                 'form', 'files',             @admin_group,    30,  'globeadmin,admin',  1, 'files', 'inline|browse', 'inline', 'inline', 'browse'),
  ('Workflow',              'form', 'workflow',          @admin_group,    40,  'globeadmin,admin',  1, 'workflow', 'inline|browse', 'inline', 'inline', 'browse'),
  ('DB & Server Inspector', 'form', 'inspector',         @admin_group,    50,  'globeadmin,admin',  1, 'inspector', 'inline|browse', 'inline', 'inline', 'browse'),
  ('Error Log',             'form', 'errorlog',          @admin_group,    60,  'globeadmin,admin',  1, 'alert', 'inline|browse', 'inline', 'inline', 'browse'),
  ('Password Policy',       'form', 'password_policy',   @admin_group,    70,  'globeadmin,admin',  1, 'shield', 'inline|browse', 'inline', 'inline', 'browse'),
  ('App Cloner',            'form', 'appcloner',         @admin_group,    80,  'globeadmin,admin',  1, 'copy', 'inline|browse', 'inline', 'inline', 'browse'),
  ('System Updater',        'form', 'updater',           @admin_group,    90,  'globeadmin,admin',  1, 'refresh', 'inline|browse', 'inline', 'inline', 'browse');

INSERT IGNORE INTO `nu_menus`
  (`menu_label`, `menu_type`, `menu_target`, `menu_parent_id`, `menu_order`, `menu_roles`, `menu_active`, `menu_icon`, `menu_open_mode`, `menu_browse_mode`, `menu_preview_mode`, `menu_default_view`)
VALUES
  ('Personal', 'group', '', 0, 90, '', 1, 'group', 'inline|browse', 'inline', 'inline', 'browse');

SET @personal_group = LAST_INSERT_ID();

SELECT @personal_group := `menu_id`
FROM   `nu_menus`
WHERE  `menu_label` = 'Personal'
  AND  `menu_type`  = 'group'
  AND  `menu_target` = ''
LIMIT 1;

INSERT IGNORE INTO `nu_menus`
  (`menu_label`, `menu_type`, `menu_target`, `menu_parent_id`, `menu_order`, `menu_roles`, `menu_active`, `menu_icon`, `menu_open_mode`, `menu_browse_mode`, `menu_preview_mode`, `menu_default_view`)
VALUES
  ('Change Password', 'form',  'password',  @personal_group,  10,  '',  1, 'password', 'inline|browse', 'inline', 'inline', 'browse');

-- Seed Dashboard Widgets & Layout presets
INSERT IGNORE INTO `nu_dashboard_widgets`
    (`widget_user_id`, `widget_role`, `widget_type`, `widget_title`, `widget_icon`, `widget_config`, `widget_width`, `widget_height`, `widget_position`)
VALUES
-- KPI: available forms
(NULL, 'user', 'stat', 'Available Forms', 'forms',
 '{"source":"query","sql":"SELECT COUNT(*) as value FROM nu_forms WHERE form_active=1","color":"primary"}',
 1, 1, 10),
-- KPI: available reports
(NULL, 'user', 'stat', 'Available Reports', 'reports',
 '{"source":"query","sql":"SELECT COUNT(*) as value FROM nu_reports WHERE report_active=1","color":"success"}',
 1, 1, 20),
-- Quick links list
(NULL, 'user', 'list', 'Quick Links', NULL,
 '{"items":[{"label":"Open Forms","module":"forms"},{"label":"Open Reports","module":"reports"},{"label":"Calendar","module":"calendar"},{"label":"Change Password","module":"password"}]}',
 2, 1, 30),
-- My recent activity table
(NULL, 'user', 'table', 'My Recent Activity', NULL,
 '{"source":"query","sql":"SELECT audit_action AS Action, audit_table AS Area, DATE_FORMAT(audit_timestamp, ''%b %e %l:%i %p'') AS Time FROM nu_audit_log WHERE audit_user_id={{user_id}} ORDER BY audit_timestamp DESC LIMIT 6","columns":["Action","Area","Time"]}',
 4, 2, 40);

INSERT IGNORE INTO `nu_dashboard_widgets`
    (`widget_user_id`, `widget_role`, `widget_type`, `widget_title`, `widget_icon`, `widget_config`, `widget_width`, `widget_height`, `widget_position`)
VALUES
(NULL, 'manager', 'stat', 'Total Users', 'users',
 '{"source":"query","sql":"SELECT COUNT(*) as value FROM nu_users","color":"primary"}',
 1, 1, 10),
(NULL, 'manager', 'stat', 'Active Forms', 'forms',
 '{"source":"query","sql":"SELECT COUNT(*) as value FROM nu_forms WHERE form_active=1","color":"success"}',
 1, 1, 20),
(NULL, 'manager', 'chart_bar', 'Activity This Week', NULL,
 '{"source":"query","sql":"SELECT DATE(audit_timestamp) AS label, COUNT(*) AS value FROM nu_audit_log WHERE audit_timestamp >= DATE_SUB(CURDATE(),INTERVAL 7 DAY) GROUP BY DATE(audit_timestamp) ORDER BY label"}',
 2, 2, 30),
(NULL, 'manager', 'chart_pie', 'Actions Breakdown', NULL,
 '{"source":"query","sql":"SELECT audit_action AS label, COUNT(*) AS value FROM nu_audit_log WHERE audit_timestamp >= DATE_SUB(CURDATE(),INTERVAL 30 DAY) GROUP BY audit_action ORDER BY value DESC LIMIT 6"}',
 2, 2, 40);

INSERT IGNORE INTO `nu_dashboard_layouts` (`layout_role`, `layout_name`) VALUES
('user',       'Default User Layout'),
('manager',    'Manager Layout'),
('supervisor', 'Supervisor Layout'),
('admin',      'Admin Layout');

-- Seed Default SMTP settings
INSERT IGNORE INTO `nu_email_settings` (`setting_key`, `setting_value`) VALUES
  ('driver',        'mail'),
  ('smtp_host',     ''),
  ('smtp_port',     '587'),
  ('smtp_secure',   'tls'),
  ('smtp_auth',     '1'),
  ('smtp_username', ''),
  ('smtp_password', ''),
  ('from_email',    'noreply@example.com'),
  ('from_name',     'nub5-dev');

-- Seed Email templates
INSERT IGNORE INTO `nu_email_templates` (`name`, `slug`, `subject`, `body`, `description`) VALUES
(
  'Form Submission Notification',
  'form_submission',
  'New form submission: {{form_name}}',
  '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;max-width:600px;margin:auto">\n<h2 style="color:#2d7dd2">New Form Submission</h2>\n<p>A new submission was received for the form <strong>{{form_name}}</strong>.</p>\n<table style="width:100%;border-collapse:collapse">\n  <tr><td style="padding:8px;border:1px solid #ddd"><strong>Submitted by</strong></td><td style="padding:8px;border:1px solid #ddd">{{submitted_by}}</td></tr>\n  <tr><td style="padding:8px;border:1px solid #ddd"><strong>Date</strong></td><td style="padding:8px;border:1px solid #ddd">{{submitted_at}}</td></tr>\n  <tr><td style="padding:8px;border:1px solid #ddd"><strong>Record ID</strong></td><td style="padding:8px;border:1px solid #ddd">{{record_id}}</td></tr>\n</table>\n<p style="margin-top:16px"><a href="{{review_url}}" style="background:#2d7dd2;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px">Review Submission</a></p>\n<hr/><p style="font-size:12px;color:#888">This is an automated notification from nub5-dev.</p>\n</body></html>',
  'Sent when a form is submitted. Variables: {{form_name}}, {{submitted_by}}, {{submitted_at}}, {{record_id}}, {{review_url}}'
),
(
  'Welcome / Account Created',
  'user_welcome',
  'Welcome to {{app_name}}, {{user_name}}!',
  '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;max-width:600px;margin:auto">\n<h2 style="color:#2d7dd2">Welcome, {{user_name}}!</h2>\n<p>Your account on <strong>{{app_name}}</strong> has been created.</p>\n<p><strong>Username:</strong> {{username}}<br/><strong>Temporary Password:</strong> {{temp_password}}</p>\n<p>Please log in and change your password immediately.</p>\n<p><a href="{{login_url}}" style="background:#2d7dd2;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px">Log In Now</a></p>\n<hr/><p style="font-size:12px;color:#888">nub5-dev Automated Notification</p>\n</body></html>',
  'Sent when a new user account is created. Variables: {{user_name}}, {{username}}, {{app_name}}, {{temp_password}}, {{login_url}}'
),
(
  'Password Reset',
  'password_reset',
  'Password Reset Request - {{app_name}}',
  '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;max-width:600px;margin:auto">\n<h2 style="color:#2d7dd2">Password Reset</h2>\n<p>Hi {{user_name}}, we received a request to reset your password.</p>\n<p><a href="{{reset_url}}" style="background:#e63946;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px">Reset My Password</a></p>\n<p style="color:#888;font-size:12px">This link expires in 1 hour. If you did not request this, ignore this email.</p>\n<hr/><p style="font-size:12px;color:#888">nub5-dev Automated Notification</p>\n</body></html>',
  'Password reset link email. Variables: {{user_name}}, {{app_name}}, {{reset_url}}'
),
(
  'Workflow Action Notification',
  'workflow_notification',
  'Action Required: {{workflow_name}} - Step {{step_name}}',
  '<!DOCTYPE html><html><body style="font-family:Arial,sans-serif;max-width:600px;margin:auto">\n<h2 style="color:#2d7dd2">Workflow Notification</h2>\n<p>Hi {{recipient_name}},</p>\n<p>The workflow <strong>{{workflow_name}}</strong> requires your attention at step: <strong>{{step_name}}</strong>.</p>\n<p><strong>Details:</strong> {{message}}</p>\n<p><a href="{{action_url}}" style="background:#2d7dd2;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px">Take Action</a></p>\n<hr/><p style="font-size:12px;color:#888">nub5-dev Automated Notification</p>\n</body></html>',
  'Workflow step notification. Variables: {{recipient_name}}, {{workflow_name}}, {{step_name}}, {{message}}, {{action_url}}'
);

-- Seed App Cloner Form Definition
INSERT IGNORE INTO `nu_forms` (
    `form_code`,
    `form_name`,
    `form_table`,
    `form_description`,
    `form_layout`,
    `form_settings`,
    `form_active`,
    `form_pk_type`,
    `form_table_mode`
) VALUES (
    'appcloner',
    'App Cloner',
    NULL,
    'Clone or export the nuBuilder 5 application database and files.',
    NULL,
    '{"type": "custom", "custom_module": "appcloner"}',
    1,
    'autoincrement',
    'existing'
);


SET FOREIGN_KEY_CHECKS = 1;
