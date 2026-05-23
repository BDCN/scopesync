-- =============================================================================
-- ScopeSync — Migration 0003: Submittal Output Path & Assembly Timestamp (Phase 5)
-- =============================================================================
-- Apply AFTER 0002_match_results.sql.
-- Run with `scopesync` selected as the active database.
-- =============================================================================

SET NAMES utf8mb4;

ALTER TABLE `submittal_jobs`
    ADD COLUMN `output_path`   VARCHAR(500) NULL AFTER `matching_status`,
    ADD COLUMN `assembled_at`  DATETIME     NULL AFTER `output_path`;
