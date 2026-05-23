-- =============================================================================
-- ScopeSync — Migration 0004: Add 'complete' to submittal_jobs.status ENUM
-- =============================================================================
-- Apply AFTER 0003_submittal_output.sql.
-- 'complete' = package PDF generated and downloadable.
-- 'delivered' (already present) = reserved for future email-to-GC step.
-- =============================================================================

SET NAMES utf8mb4;

ALTER TABLE `submittal_jobs`
    MODIFY COLUMN `status`
        ENUM('draft','uploading','extracting','matching','review','assembling','complete','delivered','failed')
        NOT NULL DEFAULT 'draft';
