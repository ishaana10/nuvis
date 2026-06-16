-- ============================================================
-- Migration 004: Dynamic role + per-form permission tables
-- Compatible with MySQL 5.7+ / MariaDB 10.3+
-- ============================================================
-- RUN IN ORDER. If nu_roles already exists from install.sql,
-- start from STEP 2. If fresh install, run all steps.
-- ============================================================

-- STEP 1: Create nu_roles if it doesn't exist yet
CREATE TABLE IF NOT EXISTS `nu_roles` (
    `role_id`          INT AUTO_INCREMENT   NOT NULL,
    `role_code`        VARCHAR(80)          NOT NULL,
    `role_name`        VARCHAR(120)         NOT NULL,
    `role_description` VARCHAR(255)         NOT NULL DEFAULT '',
    `role_active`      TINYINT(1)           NOT NULL DEFAULT 1,
    `role_is_system`   TINYINT(1)           NOT NULL DEFAULT 0
                           COMMENT '1 = protected system role, cannot be deleted',
    `role_created_at`  DATETIME             DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`role_id`),
    UNIQUE KEY `uq_role_code` (`role_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- STEP 2: Add role_is_system to EXISTING installs
-- NOTE: MySQL 5.7 does not support ADD COLUMN IF NOT EXISTS.
-- Run this manually only if the column does not already exist.
-- To check: SHOW COLUMNS FROM nu_roles LIKE 'role_is_system';
ALTER TABLE `nu_roles`
    ADD COLUMN `role_is_system` TINYINT(1) NOT NULL DEFAULT 0
    COMMENT '1 = protected system role';

-- STEP 3: Seed globeadmin (safe re-run via INSERT IGNORE)
INSERT IGNORE INTO `nu_roles` (`role_code`, `role_name`, `role_description`, `role_is_system`)
VALUES ('globeadmin', 'Globe Admin', 'System administrator - full access, cannot be deleted', 1);

-- STEP 4: Ensure globeadmin is flagged on existing rows
UPDATE `nu_roles` SET `role_is_system` = 1 WHERE `role_code` = 'globeadmin';

-- STEP 5: Create per-form permission table
CREATE TABLE IF NOT EXISTS `nu_role_form_permissions` (
    `rfp_id`         INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `rfp_role_code`  VARCHAR(80)   NOT NULL
                         COMMENT 'Matches nu_roles.role_code',
    `rfp_form_code`  VARCHAR(120)  NOT NULL
                         COMMENT 'Matches nu_forms.form_code, or * for wildcard default',
    `rfp_can_view`   TINYINT(1)    NOT NULL DEFAULT 0,
    `rfp_can_add`    TINYINT(1)    NOT NULL DEFAULT 0,
    `rfp_can_edit`   TINYINT(1)    NOT NULL DEFAULT 0,
    `rfp_can_delete` TINYINT(1)    NOT NULL DEFAULT 0,
    `rfp_can_export` TINYINT(1)    NOT NULL DEFAULT 0,
    PRIMARY KEY (`rfp_id`),
    UNIQUE KEY `uq_role_form` (`rfp_role_code`, `rfp_form_code`),
    KEY `idx_rfp_role` (`rfp_role_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Per-role, per-form CRUD permissions. rfp_form_code=* is the wildcard default.';
