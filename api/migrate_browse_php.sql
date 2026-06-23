-- Run once to add the browse_php column to both possible form table names.
-- Safe to run multiple times (uses IF NOT EXISTS pattern via SHOW COLUMNS).

-- For nu_forms (nub5-dev default)
ALTER TABLE `nu_forms`
    ADD COLUMN IF NOT EXISTS `browse_php` MEDIUMTEXT NULL DEFAULT NULL
    COMMENT 'PHP snippet executed before browse query. Set $nuSql / $nuWhere / $nuOrder to customise results per user.';

-- For nuforms (legacy nubuilder4 schema)
ALTER TABLE `nuforms`
    ADD COLUMN IF NOT EXISTS `browsephp` MEDIUMTEXT NULL DEFAULT NULL
    COMMENT 'PHP snippet executed before browse query.';
