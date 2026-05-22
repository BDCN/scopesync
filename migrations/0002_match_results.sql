-- =============================================================================
-- ScopeSync — Migration 0002: Match Results & Review Decisions (Phase 4)
-- =============================================================================
-- Apply AFTER 0001_initial_schema.sql.
-- Run with `scopesync` selected as the active database.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- Add matching_status to submittal_jobs
-- -----------------------------------------------------------------------------
ALTER TABLE `submittal_jobs`
    ADD COLUMN `matching_status`
        ENUM('pending','running','complete','failed') NULL
        AFTER `status`;

-- -----------------------------------------------------------------------------
-- MATCH_RESULTS — one row per (spec extraction × cut sheet extraction × catalog number)
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `match_results` (
    `id`                     INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `tenant_id`              INT UNSIGNED  NOT NULL,
    `submittal_job_id`       INT UNSIGNED  NOT NULL,
    `spec_extraction_id`     INT UNSIGNED  NOT NULL,
    `cutsheet_extraction_id` INT UNSIGNED  NOT NULL,
    `catalog_number`         VARCHAR(255)  NOT NULL,
    `product_category`       VARCHAR(255)  NULL,
    `overall_result`         ENUM('pass','partial','fail','unverifiable') NOT NULL,
    `attribute_results`      LONGTEXT      NOT NULL,
    `created_at`             DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_mr_submittal` (`tenant_id`, `submittal_job_id`),
    INDEX `idx_mr_spec`      (`spec_extraction_id`),
    INDEX `idx_mr_cutsheet`  (`cutsheet_extraction_id`),
    CONSTRAINT `fk_mr_tenant`    FOREIGN KEY (`tenant_id`)              REFERENCES `tenants`(`id`),
    CONSTRAINT `fk_mr_submittal` FOREIGN KEY (`submittal_job_id`)       REFERENCES `submittal_jobs`(`id`),
    CONSTRAINT `fk_mr_spec`      FOREIGN KEY (`spec_extraction_id`)     REFERENCES `extractions`(`id`),
    CONSTRAINT `fk_mr_cutsheet`  FOREIGN KEY (`cutsheet_extraction_id`) REFERENCES `extractions`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- REVIEW_DECISIONS — human approve / override / reject per match result
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `review_decisions` (
    `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `tenant_id`        INT UNSIGNED  NOT NULL,
    `submittal_job_id` INT UNSIGNED  NOT NULL,
    `match_result_id`  INT UNSIGNED  NOT NULL,
    `decision`         ENUM('approved','overridden','rejected') NOT NULL,
    `override_notes`   TEXT          NULL,
    `decided_by`       INT UNSIGNED  NOT NULL,
    `decided_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_decision_per_result` (`match_result_id`),
    INDEX `idx_rd_submittal` (`tenant_id`, `submittal_job_id`),
    CONSTRAINT `fk_rd_tenant`    FOREIGN KEY (`tenant_id`)       REFERENCES `tenants`(`id`),
    CONSTRAINT `fk_rd_submittal` FOREIGN KEY (`submittal_job_id`) REFERENCES `submittal_jobs`(`id`),
    CONSTRAINT `fk_rd_match`     FOREIGN KEY (`match_result_id`) REFERENCES `match_results`(`id`),
    CONSTRAINT `fk_rd_user`      FOREIGN KEY (`decided_by`)      REFERENCES `users`(`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
