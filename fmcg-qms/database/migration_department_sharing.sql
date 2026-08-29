-- ============================================================================
-- Migration: Cross-Department Data Sharing
-- Safe to run against an existing installation (CREATE TABLE IF NOT EXISTS -
-- adds nothing if you already loaded the current schema.sql). Adds the ability
-- for a manager to grant one department read-only visibility into another
-- department's quality data (e.g. Supply Chain can view Production's data).
-- ============================================================================
USE `fmcg_qms`;

CREATE TABLE IF NOT EXISTS `department_data_shares` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `viewer_department_id` INT UNSIGNED NOT NULL COMMENT 'this department gets read-only visibility...',
  `source_department_id` INT UNSIGNED NOT NULL COMMENT '...into this department''s data',
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_dept_share` (`company_id`, `viewer_department_id`, `source_department_id`),
  KEY `idx_share_company` (`company_id`),
  CONSTRAINT `fk_share_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_share_viewer_dept` FOREIGN KEY (`viewer_department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_share_source_dept` FOREIGN KEY (`source_department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;
