-- =============================================================================
-- ScopeSync — Initial Database Schema (Phase 1)
-- =============================================================================
-- Target: MariaDB 10.6+ on RHEL/Rocky/AlmaLinux
-- Multi-tenant: shared DB with tenant_id columns on all tenant-owned tables.
-- All tables use InnoDB + utf8mb4 + utf8mb4_unicode_ci.
--
-- HOW TO APPLY (phpMyAdmin):
--   1. Log in to phpMyAdmin as a user with CREATE privileges
--   2. Click "New" on left sidebar, create database `scopesync` (utf8mb4)
--   3. Select `scopesync`, click "SQL" tab
--   4. Paste this entire file, click "Go"
--   5. Verify all 12 tables created under the `scopesync` database
--
-- HOW TO APPLY (CLI on server):
--   mysql -u root -p -e "CREATE DATABASE scopesync CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
--   mysql -u root -p -e "CREATE USER 'scopesync'@'localhost' IDENTIFIED BY '<strong-password>';"
--   mysql -u root -p -e "GRANT ALL PRIVILEGES ON scopesync.* TO 'scopesync'@'localhost'; FLUSH PRIVILEGES;"
--   mysql -u scopesync -p scopesync < schema.sql
--
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------------------------------
-- TENANTS — top-level org/company. One row per customer organization.
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `tenants`;
CREATE TABLE `tenants` (
    `id`                INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `slug`              VARCHAR(64)       NOT NULL,
    `name`              VARCHAR(255)      NOT NULL,
    `plan`              ENUM('starter','pro','team') NOT NULL DEFAULT 'starter',
    `status`            ENUM('active','suspended','cancelled') NOT NULL DEFAULT 'active',
    `industry_default`  VARCHAR(64)       NOT NULL DEFAULT 'electrical',
    `trial_ends_at`     DATETIME          NULL,
    `created_at`        DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_tenants_slug` (`slug`),
    KEY `idx_tenants_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- USERS — belong to exactly one tenant. (Future: invite-to-multiple-tenants.)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id`              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `tenant_id`       INT UNSIGNED      NOT NULL,
    `email`           VARCHAR(255)      NOT NULL,
    `password_hash`   VARCHAR(255)      NOT NULL,
    `name`            VARCHAR(255)      NOT NULL,
    `role`            ENUM('owner','admin','member') NOT NULL DEFAULT 'member',
    `status`          ENUM('active','invited','disabled') NOT NULL DEFAULT 'active',
    `last_login_at`   DATETIME          NULL,
    `created_at`      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_users_tenant_email` (`tenant_id`,`email`),
    KEY `idx_users_tenant_status` (`tenant_id`,`status`),
    CONSTRAINT `fk_users_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- CI_SESSIONS — required by CodeIgniter 3 session driver=database
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `ci_sessions`;
CREATE TABLE `ci_sessions` (
    `id`             VARCHAR(128)      NOT NULL,
    `ip_address`     VARCHAR(45)       NOT NULL,
    `timestamp`      INT UNSIGNED      NOT NULL DEFAULT 0,
    `data`           BLOB              NOT NULL,
    PRIMARY KEY (`id`,`ip_address`),
    KEY `idx_ci_sessions_timestamp` (`timestamp`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- INDUSTRIES — predefined verticals (electrical first, more to come)
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `industries`;
CREATE TABLE `industries` (
    `id`           INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `slug`         VARCHAR(64)       NOT NULL,
    `name`         VARCHAR(255)      NOT NULL,
    `csi_division` VARCHAR(8)        NULL,
    `description`  TEXT              NULL,
    `created_at`   DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_industries_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `industries` (`slug`,`name`,`csi_division`,`description`) VALUES
    ('electrical',      'Electrical',      '26', 'CSI Division 26 — Electrical (launch vertical)'),
    ('mechanical',      'Mechanical/HVAC', '23', 'CSI Division 23 — Heating, Ventilating, and Air Conditioning'),
    ('plumbing',        'Plumbing',        '22', 'CSI Division 22 — Plumbing'),
    ('fire_protection', 'Fire Protection', '21', 'CSI Division 21 — Fire Suppression');

-- -----------------------------------------------------------------------------
-- TENANT_SETTINGS — per-tenant branding and preferences
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `tenant_settings`;
CREATE TABLE `tenant_settings` (
    `tenant_id`         INT UNSIGNED      NOT NULL,
    `company_name`      VARCHAR(255)      NULL,
    `logo_path`         VARCHAR(500)      NULL,
    `cover_sheet_text`  TEXT              NULL,
    `primary_color`     CHAR(7)           NOT NULL DEFAULT '#1A73E8',
    `contact_email`     VARCHAR(255)      NULL,
    `contact_phone`     VARCHAR(64)       NULL,
    `address`           TEXT              NULL,
    `updated_at`        DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`tenant_id`),
    CONSTRAINT `fk_settings_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- PROJECTS — top-level construction projects within a tenant
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `projects`;
CREATE TABLE `projects` (
    `id`              INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `tenant_id`       INT UNSIGNED      NOT NULL,
    `name`            VARCHAR(255)      NOT NULL,
    `project_number`  VARCHAR(64)       NULL,
    `gc_name`         VARCHAR(255)      NULL,
    `architect_name`  VARCHAR(255)      NULL,
    `location`        VARCHAR(255)      NULL,
    `industry_id`     INT UNSIGNED      NULL,
    `status`          ENUM('active','archived') NOT NULL DEFAULT 'active',
    `created_by`      INT UNSIGNED      NOT NULL,
    `created_at`      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`      DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_projects_tenant_status` (`tenant_id`,`status`),
    KEY `idx_projects_industry` (`industry_id`),
    KEY `idx_projects_creator` (`created_by`),
    CONSTRAINT `fk_projects_tenant`   FOREIGN KEY (`tenant_id`)  REFERENCES `tenants`(`id`)    ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT `fk_projects_creator`  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`)      ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_projects_industry` FOREIGN KEY (`industry_id`) REFERENCES `industries`(`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- DIVISIONS — CSI MasterFormat divisions within a project
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `divisions`;
CREATE TABLE `divisions` (
    `id`            INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `tenant_id`     INT UNSIGNED      NOT NULL,
    `project_id`    INT UNSIGNED      NOT NULL,
    `code`          VARCHAR(16)       NOT NULL,
    `name`          VARCHAR(255)      NOT NULL,
    `created_at`    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_divisions_project_code` (`project_id`,`code`),
    KEY `idx_divisions_tenant` (`tenant_id`),
    CONSTRAINT `fk_divisions_tenant`  FOREIGN KEY (`tenant_id`)  REFERENCES `tenants`(`id`)  ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_divisions_project` FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- SUBMITTAL_JOBS — individual submittal packages being assembled
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `submittal_jobs`;
CREATE TABLE `submittal_jobs` (
    `id`                INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `tenant_id`         INT UNSIGNED      NOT NULL,
    `project_id`        INT UNSIGNED      NOT NULL,
    `division_id`       INT UNSIGNED      NULL,
    `name`              VARCHAR(255)      NOT NULL,
    `submittal_number`  VARCHAR(64)       NULL,
    `spec_section`      VARCHAR(64)       NULL COMMENT 'e.g., 26 27 26',
    `status`            ENUM('draft','uploading','extracting','matching','review','assembling','delivered','failed') NOT NULL DEFAULT 'draft',
    `status_message`    TEXT              NULL,
    `created_by`        INT UNSIGNED      NOT NULL,
    `created_at`        DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `delivered_at`      DATETIME          NULL,
    PRIMARY KEY (`id`),
    KEY `idx_subs_tenant_status` (`tenant_id`,`status`),
    KEY `idx_subs_project`       (`project_id`),
    KEY `idx_subs_division`      (`division_id`),
    KEY `idx_subs_creator`       (`created_by`),
    CONSTRAINT `fk_subs_tenant`   FOREIGN KEY (`tenant_id`)   REFERENCES `tenants`(`id`)     ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT `fk_subs_project`  FOREIGN KEY (`project_id`)  REFERENCES `projects`(`id`)    ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT `fk_subs_division` FOREIGN KEY (`division_id`) REFERENCES `divisions`(`id`)   ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT `fk_subs_creator`  FOREIGN KEY (`created_by`)  REFERENCES `users`(`id`)       ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- DOCUMENTS — uploaded inputs and generated outputs
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `documents`;
CREATE TABLE `documents` (
    `id`                INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `tenant_id`         INT UNSIGNED      NOT NULL,
    `submittal_job_id`  INT UNSIGNED      NOT NULL,
    `doc_type`          ENUM('spec_section','cut_sheet','shop_drawing','output_package','other') NOT NULL,
    `original_filename` VARCHAR(500)      NOT NULL,
    `storage_path`      VARCHAR(1000)     NOT NULL,
    `mime_type`         VARCHAR(100)      NULL,
    `size_bytes`        BIGINT UNSIGNED   NULL,
    `page_count`        INT UNSIGNED      NULL,
    `sha256`            CHAR(64)          NULL,
    `uploaded_by`       INT UNSIGNED      NULL,
    `created_at`        DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_docs_submittal_type` (`submittal_job_id`,`doc_type`),
    KEY `idx_docs_tenant`         (`tenant_id`),
    KEY `idx_docs_sha256`         (`sha256`),
    CONSTRAINT `fk_docs_tenant`    FOREIGN KEY (`tenant_id`)        REFERENCES `tenants`(`id`)         ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT `fk_docs_submittal` FOREIGN KEY (`submittal_job_id`) REFERENCES `submittal_jobs`(`id`)  ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT `fk_docs_uploader`  FOREIGN KEY (`uploaded_by`)      REFERENCES `users`(`id`)           ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- EXTRACTIONS — one row per Claude API call against a document
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `extractions`;
CREATE TABLE `extractions` (
    `id`                INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `tenant_id`         INT UNSIGNED      NOT NULL,
    `submittal_job_id`  INT UNSIGNED      NOT NULL,
    `document_id`       INT UNSIGNED      NOT NULL,
    `extraction_type`   ENUM('spec_section','cut_sheet') NOT NULL,
    `status`            ENUM('pending','running','completed','failed') NOT NULL DEFAULT 'pending',
    `model_used`        VARCHAR(64)       NULL,
    `prompt_version`    VARCHAR(32)       NULL,
    `input_tokens`      INT UNSIGNED      NULL,
    `output_tokens`     INT UNSIGNED      NULL,
    `raw_response`      LONGTEXT          NULL,
    `structured_data`   JSON              NULL,
    `confidence`        ENUM('high','medium','low') NULL,
    `error_message`     TEXT              NULL,
    `started_at`        DATETIME          NULL,
    `completed_at`      DATETIME          NULL,
    `created_at`        DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_extr_submittal_status` (`submittal_job_id`,`status`),
    KEY `idx_extr_document`         (`document_id`),
    KEY `idx_extr_pending_queue`    (`status`,`created_at`),
    KEY `idx_extr_tenant`           (`tenant_id`),
    CONSTRAINT `fk_extr_tenant`    FOREIGN KEY (`tenant_id`)        REFERENCES `tenants`(`id`)        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_extr_submittal` FOREIGN KEY (`submittal_job_id`) REFERENCES `submittal_jobs`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_extr_document`  FOREIGN KEY (`document_id`)      REFERENCES `documents`(`id`)      ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- AUDIT_LOG — security / compliance audit trail
-- -----------------------------------------------------------------------------
DROP TABLE IF EXISTS `audit_log`;
CREATE TABLE `audit_log` (
    `id`            BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `tenant_id`     INT UNSIGNED      NOT NULL,
    `user_id`       INT UNSIGNED      NULL,
    `entity_type`   VARCHAR(64)       NOT NULL,
    `entity_id`     INT UNSIGNED      NULL,
    `action`        VARCHAR(64)       NOT NULL,
    `metadata`      JSON              NULL,
    `ip_address`    VARCHAR(45)       NULL,
    `user_agent`    VARCHAR(500)      NULL,
    `created_at`    DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_audit_tenant_created` (`tenant_id`,`created_at`),
    KEY `idx_audit_entity`         (`entity_type`,`entity_id`),
    KEY `idx_audit_user`           (`user_id`),
    CONSTRAINT `fk_audit_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants`(`id`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_audit_user`   FOREIGN KEY (`user_id`)   REFERENCES `users`(`id`)   ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
-- OPTIONAL: Dev seed data (do NOT run in production)
-- =============================================================================
-- Uncomment to create a test tenant + admin user for local development.
-- The password below hashes to 'changeme' — change immediately after first login.
--
-- INSERT INTO `tenants` (`slug`,`name`,`plan`,`industry_default`)
-- VALUES ('acme-electric','Acme Electric','pro','electrical');
--
-- INSERT INTO `users` (`tenant_id`,`email`,`password_hash`,`name`,`role`)
-- VALUES (
--     LAST_INSERT_ID(),
--     'admin@acme-electric.test',
--     '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',  -- 'changeme'
--     'Acme Admin',
--     'owner'
-- );
--
-- INSERT INTO `tenant_settings` (`tenant_id`,`company_name`,`primary_color`)
-- VALUES (1, 'Acme Electric Corp', '#1A73E8');

-- =============================================================================
-- POST-INSTALL VERIFICATION
-- =============================================================================
-- Run these to verify everything created correctly:
--
--   SELECT TABLE_NAME, ENGINE, TABLE_COLLATION
--   FROM information_schema.TABLES
--   WHERE TABLE_SCHEMA = 'scopesync'
--   ORDER BY TABLE_NAME;
--
--   SELECT COUNT(*) AS industry_count FROM industries;  -- expect 4
--
-- =============================================================================
