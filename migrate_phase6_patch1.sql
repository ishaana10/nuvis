-- ============================================================
-- nub5-dev — Phase 6 Patch 1: Column migration
-- Run this if nu_workflows already existed before install_phase6.sql
-- Each ALTER uses IF NOT EXISTS so it is safe to re-run.
-- ============================================================

-- Add columns that a pre-existing nu_workflows table may be missing
ALTER TABLE `nu_workflows`
    ADD COLUMN IF NOT EXISTS `wf_form_code`  VARCHAR(64)  DEFAULT NULL COMMENT 'optional: bind to a form' AFTER `wf_description`,
    ADD COLUMN IF NOT EXISTS `wf_created_by` INT          DEFAULT NULL AFTER `wf_active`,
    ADD COLUMN IF NOT EXISTS `wf_updated_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `wf_created_at`;

-- Add index if missing (ignore error if already exists)
ALTER TABLE `nu_workflows`
    ADD UNIQUE KEY IF NOT EXISTS `uq_wf_code`   (`wf_code`),
    ADD        KEY IF NOT EXISTS `idx_wf_active` (`wf_active`);

SELECT 'Phase 6 Patch 1 — column migration complete.' AS status;
