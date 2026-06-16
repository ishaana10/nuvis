-- migrate_widget_icon.sql
-- Safely adds widget_icon column if it does not already exist.
-- Safe to run multiple times (uses stored procedure trick for IF NOT EXISTS on ALTER).
-- Also widens the column to VARCHAR(120) so full FA class strings fit.

DROP PROCEDURE IF EXISTS nu_add_widget_icon_col;

DELIMITER //
CREATE PROCEDURE nu_add_widget_icon_col()
BEGIN
    -- Add column only if it does not exist
    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME   = 'nu_dashboard_widgets'
          AND COLUMN_NAME  = 'widget_icon'
    ) THEN
        ALTER TABLE nu_dashboard_widgets
            ADD COLUMN widget_icon VARCHAR(120) DEFAULT NULL
            AFTER widget_title;
    ELSE
        -- Column exists — ensure it is wide enough for full FA class strings
        ALTER TABLE nu_dashboard_widgets
            MODIFY COLUMN widget_icon VARCHAR(120) DEFAULT NULL;
    END IF;
END //
DELIMITER ;

CALL nu_add_widget_icon_col();
DROP PROCEDURE IF EXISTS nu_add_widget_icon_col;
