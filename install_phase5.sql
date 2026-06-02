-- nuBuilder Next — Phase 5: Reports module migration
-- Compatible with MySQL 5.7+ and MariaDB 10.3+
-- Safe to run multiple times — checks information_schema before each ALTER

-- ─── HELPER PROCEDURE ────────────────────────────────────────────────────────
-- Drops + recreates a safe-add procedure to avoid duplicate column errors.
DROP PROCEDURE IF EXISTS nu_add_column_if_missing;

DELIMITER $$
CREATE PROCEDURE nu_add_column_if_missing(
    IN tbl  VARCHAR(64),
    IN col  VARCHAR(64),
    IN ddl  TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM   information_schema.COLUMNS
        WHERE  TABLE_SCHEMA = DATABASE()
          AND  TABLE_NAME   = tbl
          AND  COLUMN_NAME  = col
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', tbl, '` ADD COLUMN ', ddl);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

-- ─── ADD MISSING COLUMNS ─────────────────────────────────────────────────────

CALL nu_add_column_if_missing(
    'nu_reports',
    'report_view_mode',
    '`report_view_mode` VARCHAR(20) NOT NULL DEFAULT ''table'' AFTER `report_type`'
);

CALL nu_add_column_if_missing(
    'nu_reports',
    'report_filters',
    '`report_filters` JSON AFTER `report_columns`'
);

CALL nu_add_column_if_missing(
    'nu_reports',
    'report_settings',
    '`report_settings` JSON AFTER `report_filters`'
);

CALL nu_add_column_if_missing(
    'nu_reports',
    'report_created_by',
    '`report_created_by` INT DEFAULT NULL AFTER `report_active`'
);

CALL nu_add_column_if_missing(
    'nu_reports',
    'report_updated_at',
    '`report_updated_at` DATETIME DEFAULT NULL AFTER `report_created_by`'
);

-- ─── ADD MISSING INDEXES (MySQL-compatible) ──────────────────────────────────
DROP PROCEDURE IF EXISTS nu_add_index_if_missing;

DELIMITER $$
CREATE PROCEDURE nu_add_index_if_missing(
    IN tbl  VARCHAR(64),
    IN idx  VARCHAR(64),
    IN ddl  TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM   information_schema.STATISTICS
        WHERE  TABLE_SCHEMA = DATABASE()
          AND  TABLE_NAME   = tbl
          AND  INDEX_NAME   = idx
    ) THEN
        SET @sql = CONCAT('ALTER TABLE `', tbl, '` ADD INDEX `', idx, '` ', ddl);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

CALL nu_add_index_if_missing('nu_reports', 'idx_report_code',   '(`report_code`)');
CALL nu_add_index_if_missing('nu_reports', 'idx_report_active', '(`report_active`)');

-- ─── CLEANUP ─────────────────────────────────────────────────────────────────
DROP PROCEDURE IF EXISTS nu_add_column_if_missing;
DROP PROCEDURE IF EXISTS nu_add_index_if_missing;

SELECT 'Phase 5 migration complete.' AS status;
