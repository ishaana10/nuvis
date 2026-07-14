-- Migration: Add wft_hook to nu_workflow_transitions
-- Supports Action Hooks on workflow transitions (e.g. send_email, call_webhook, update_record)
-- Safe to run multiple times.

ALTER TABLE `nu_workflow_transitions`
    ADD COLUMN IF NOT EXISTS `wft_hook` TEXT NULL DEFAULT NULL
    COMMENT 'JSON configuration for custom transition Action Hooks (send_email, call_webhook, update_record)';
