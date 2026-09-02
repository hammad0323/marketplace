-- ============================================================================
-- QualityCore - FMCG Quality Management, QMS & Continuous Improvement Platform
-- COMPLETE SINGLE-FILE INSTALLER (schema + full dynamic tool library + demo data)
--
-- This file is the concatenation of, in this exact order:
--   1) schema.sql            - full database schema (creates + uses `fmcg_qms`)
--   2) tool_library.sql      - dynamic quality tool library, batch 1
--   3) tool_library_2.sql    - dynamic quality tool library, batch 2 (19 more tools)
--   4) seed_demo.sql         - realistic demo companies/users/data (OPTIONAL business data)
--
-- Usage:
--   mysql -u root -p < complete_install.sql
-- (No need to pre-create or select a database - this script creates and uses
--  `fmcg_qms` itself.)
--
-- To install WITHOUT demo business data (production/empty install), delete
-- everything in this file from the "SEED DEMO DATA" marker onward - schema.sql
-- and both tool_library files contain only reference/configuration data
-- (subscription plans, KPI definitions, certifications, the dynamic tool
-- catalog, and the default Super Admin account) and are safe for empty installs.
--
-- Demo login (only if demo data below is kept):
--   Super Admin:      superadmin@qualitycore.app / SuperAdmin@123
--   Company Manager:  manager@goldenharvest.demo / Manager@123
--   Employee (QC):    priya.rao@goldenharvest.demo / Employee@123
--   (all other demo employees use the same password)
--   Second tenant:    manager@everfresh.demo / Manager@123
-- ============================================================================

-- ============================================================================
-- PART 1 / 4: SCHEMA (source: schema.sql)
-- ============================================================================

-- ============================================================================
-- QualityCore - FMCG Quality Management, QMS & Continuous Improvement Platform
-- Full database schema (empty baseline + minimal reference/config data).
-- Multi-tenant: every company-owned table carries company_id for hard isolation.
-- Engine: InnoDB, Charset: utf8mb4
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE DATABASE IF NOT EXISTS `fmcg_qms` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `fmcg_qms`;

-- ============================================================================
-- PLATFORM / TENANCY
-- ============================================================================

CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `last_login` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `subscription_plans` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `employee_limit` INT UNSIGNED NOT NULL DEFAULT 10,
  `department_limit` INT UNSIGNED NOT NULL DEFAULT 5,
  `tool_limit` INT UNSIGNED NOT NULL DEFAULT 10,
  `storage_limit_mb` INT UNSIGNED NOT NULL DEFAULT 500,
  `ai_usage_limit` INT UNSIGNED NOT NULL DEFAULT 100,
  `report_limit` INT UNSIGNED NOT NULL DEFAULT 50,
  `price_monthly` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `features` TEXT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `companies` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `code` VARCHAR(30) NOT NULL UNIQUE,
  `logo` VARCHAR(255) NULL,
  `industry` VARCHAR(100) NULL,
  `address` VARCHAR(255) NULL,
  `city` VARCHAR(100) NULL,
  `country` VARCHAR(100) NULL,
  `contact_person` VARCHAR(150) NULL,
  `email` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(30) NULL,
  `website` VARCHAR(150) NULL,
  `employee_limit` INT UNSIGNED NOT NULL DEFAULT 10,
  `department_limit` INT UNSIGNED NOT NULL DEFAULT 5,
  `tool_limit` INT UNSIGNED NOT NULL DEFAULT 10,
  `storage_limit_mb` INT UNSIGNED NOT NULL DEFAULT 500,
  `ai_usage_limit` INT UNSIGNED NOT NULL DEFAULT 100,
  `subscription_plan_id` INT UNSIGNED NULL,
  `start_date` DATE NULL,
  `expiry_date` DATE NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `quality_score_weights` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_companies_status` (`status`),
  CONSTRAINT `fk_companies_plan` FOREIGN KEY (`subscription_plan_id`) REFERENCES `subscription_plans`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `company_settings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `setting_key` VARCHAR(100) NOT NULL,
  `setting_value` TEXT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_company_setting` (`company_id`, `setting_key`),
  CONSTRAINT `fk_csettings_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `company_subscriptions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `plan_id` INT UNSIGNED NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE NULL,
  `status` ENUM('active','expired','cancelled') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_csub_company` (`company_id`),
  CONSTRAINT `fk_csub_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
-- USERS / ROLES / PERMISSIONS
-- ============================================================================

CREATE TABLE IF NOT EXISTS `departments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_dept_company` (`company_id`),
  CONSTRAINT `fk_dept_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `shifts` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `start_time` TIME NULL,
  `end_time` TIME NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_shift_company` (`company_id`),
  CONSTRAINT `fk_shift_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `role` ENUM('manager','employee') NOT NULL DEFAULT 'employee',
  `employee_code` VARCHAR(50) NULL,
  `name` VARCHAR(150) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `phone` VARCHAR(30) NULL,
  `department_id` INT UNSIGNED NULL,
  `designation` VARCHAR(100) NULL,
  `shift_id` INT UNSIGNED NULL,
  `joining_date` DATE NULL,
  `profile_picture` VARCHAR(255) NULL,
  `username` VARCHAR(100) NULL,
  `password` VARCHAR(255) NOT NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `last_login` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_users_company` (`company_id`),
  KEY `idx_users_role` (`role`),
  KEY `idx_users_dept` (`department_id`),
  CONSTRAINT `fk_users_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_users_dept` FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_users_shift` FOREIGN KEY (`shift_id`) REFERENCES `shifts`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `employee_departments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `department_id` INT UNSIGNED NOT NULL,
  UNIQUE KEY `uq_emp_dept` (`user_id`, `department_id`),
  CONSTRAINT `fk_ed_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ed_dept` FOREIGN KEY (`department_id`) REFERENCES `departments`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `description` VARCHAR(255) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `permissions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `permission_key` VARCHAR(100) NOT NULL UNIQUE,
  `name` VARCHAR(150) NOT NULL,
  `module` VARCHAR(100) NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `user_permissions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  UNIQUE KEY `uq_user_permission` (`user_id`, `permission_id`),
  CONSTRAINT `fk_up_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_up_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
-- DYNAMIC QUALITY TOOL ENGINE
-- ============================================================================

CREATE TABLE IF NOT EXISTS `tool_categories` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(100) NOT NULL UNIQUE,
  `icon` VARCHAR(50) NULL,
  `sort_order` INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `tools` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NULL COMMENT 'NULL = global platform tool available to all companies',
  `category_id` INT UNSIGNED NULL,
  `name` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(150) NOT NULL,
  `subcategory` VARCHAR(100) NULL,
  `description` TEXT NULL,
  `icon` VARCHAR(50) NULL DEFAULT 'bi-clipboard-check',
  `instructions` TEXT NULL,
  `frequency` ENUM('daily','per_shift','per_batch','per_production_run','hourly','weekly','monthly','on_demand') NOT NULL DEFAULT 'daily',
  `department_id` INT UNSIGNED NULL,
  `tool_type` ENUM('form','matrix','diagram','calculator','checklist') NOT NULL DEFAULT 'form',
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `ai_instructions` TEXT NULL,
  `kpi_config` TEXT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_tools_company` (`company_id`),
  KEY `idx_tools_category` (`category_id`),
  KEY `idx_tools_status` (`status`),
  CONSTRAINT `fk_tools_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_tools_category` FOREIGN KEY (`category_id`) REFERENCES `tool_categories`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `tool_fields` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tool_id` INT UNSIGNED NOT NULL,
  `label` VARCHAR(150) NOT NULL,
  `field_name` VARCHAR(100) NOT NULL,
  `field_type` ENUM('text','number','decimal','date','time','datetime','dropdown','multiselect','radio','checkbox',
                     'yes_no','pass_fail','rating','percentage','measurement','formula','file','image','signature','long_text') NOT NULL,
  `placeholder` VARCHAR(150) NULL,
  `description` VARCHAR(255) NULL,
  `is_required` TINYINT(1) NOT NULL DEFAULT 0,
  `unit` VARCHAR(30) NULL,
  `min_value` DECIMAL(14,4) NULL,
  `max_value` DECIMAL(14,4) NULL,
  `warning_threshold_low` DECIMAL(14,4) NULL,
  `warning_threshold_high` DECIMAL(14,4) NULL,
  `critical_threshold_low` DECIMAL(14,4) NULL,
  `critical_threshold_high` DECIMAL(14,4) NULL,
  `default_value` VARCHAR(255) NULL,
  `formula` VARCHAR(255) NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `visibility` ENUM('visible','hidden') NOT NULL DEFAULT 'visible',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_fields_tool` (`tool_id`),
  CONSTRAINT `fk_fields_tool` FOREIGN KEY (`tool_id`) REFERENCES `tools`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `tool_field_options` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tool_field_id` INT UNSIGNED NOT NULL,
  `option_label` VARCHAR(150) NOT NULL,
  `option_value` VARCHAR(150) NOT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  KEY `idx_options_field` (`tool_field_id`),
  CONSTRAINT `fk_options_field` FOREIGN KEY (`tool_field_id`) REFERENCES `tool_fields`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `tool_assignments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `tool_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `assigned_by` INT UNSIGNED NULL,
  `status` ENUM('active','removed') NOT NULL DEFAULT 'active',
  `assigned_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_assign_company` (`company_id`),
  KEY `idx_assign_user` (`user_id`),
  KEY `idx_assign_tool` (`tool_id`),
  CONSTRAINT `fk_assign_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assign_tool` FOREIGN KEY (`tool_id`) REFERENCES `tools`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_assign_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `tool_submissions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `tool_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NOT NULL,
  `department_id` INT UNSIGNED NULL,
  `shift_id` INT UNSIGNED NULL,
  `batch_id` INT UNSIGNED NULL,
  `production_line_id` INT UNSIGNED NULL,
  `period_key` VARCHAR(50) NULL,
  `status` ENUM('submitted','missed','pending') NOT NULL DEFAULT 'submitted',
  `has_deviation` TINYINT(1) NOT NULL DEFAULT 0,
  `deviation_severity` ENUM('critical','high','medium','low','observation') NULL,
  `submitted_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_sub_company` (`company_id`),
  KEY `idx_sub_tool` (`tool_id`),
  KEY `idx_sub_user` (`user_id`),
  KEY `idx_sub_period` (`tool_id`, `user_id`, `period_key`),
  KEY `idx_sub_created` (`created_at`),
  CONSTRAINT `fk_sub_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sub_tool` FOREIGN KEY (`tool_id`) REFERENCES `tools`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sub_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `tool_submission_values` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `submission_id` INT UNSIGNED NOT NULL,
  `tool_field_id` INT UNSIGNED NOT NULL,
  `value_text` TEXT NULL,
  `value_number` DECIMAL(18,4) NULL,
  `value_date` DATETIME NULL,
  `file_path` VARCHAR(255) NULL,
  KEY `idx_val_submission` (`submission_id`),
  KEY `idx_val_field` (`tool_field_id`),
  CONSTRAINT `fk_val_submission` FOREIGN KEY (`submission_id`) REFERENCES `tool_submissions`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_val_field` FOREIGN KEY (`tool_field_id`) REFERENCES `tool_fields`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
-- PRODUCTS / PRODUCTION / BATCHES
-- ============================================================================

CREATE TABLE IF NOT EXISTS `production_lines` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(30) NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_line_company` (`company_id`),
  CONSTRAINT `fk_line_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `machines` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `production_line_id` INT UNSIGNED NULL,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(30) NULL,
  `status` ENUM('active','inactive','maintenance') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_machine_company` (`company_id`),
  CONSTRAINT `fk_machine_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_machine_line` FOREIGN KEY (`production_line_id`) REFERENCES `production_lines`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `product_categories` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(100) NOT NULL,
  KEY `idx_pcat_company` (`company_id`),
  CONSTRAINT `fk_pcat_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `products` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `sku` VARCHAR(50) NULL,
  `product_code` VARCHAR(50) NULL,
  `category_id` INT UNSIGNED NULL,
  `specification` TEXT NULL,
  `unit` VARCHAR(30) NULL,
  `production_line_id` INT UNSIGNED NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_products_company` (`company_id`),
  CONSTRAINT `fk_products_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`) REFERENCES `product_categories`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_products_line` FOREIGN KEY (`production_line_id`) REFERENCES `production_lines`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `product_specifications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT UNSIGNED NOT NULL,
  `spec_name` VARCHAR(100) NOT NULL,
  `spec_value` VARCHAR(150) NULL,
  `unit` VARCHAR(30) NULL,
  `min_value` DECIMAL(14,4) NULL,
  `max_value` DECIMAL(14,4) NULL,
  KEY `idx_spec_product` (`product_id`),
  CONSTRAINT `fk_spec_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `code` VARCHAR(30) NULL,
  `contact_person` VARCHAR(150) NULL,
  `email` VARCHAR(150) NULL,
  `phone` VARCHAR(30) NULL,
  `address` VARCHAR(255) NULL,
  `material_category` VARCHAR(100) NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_suppliers_company` (`company_id`),
  CONSTRAINT `fk_suppliers_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `batches` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `batch_number` VARCHAR(50) NOT NULL,
  `product_id` INT UNSIGNED NOT NULL,
  `production_date` DATE NULL,
  `expiry_date` DATE NULL,
  `production_line_id` INT UNSIGNED NULL,
  `shift_id` INT UNSIGNED NULL,
  `supplier_id` INT UNSIGNED NULL COMMENT 'primary raw material supplier for quick traceability',
  `quantity_produced` DECIMAL(14,2) NULL,
  `raw_materials` TEXT NULL COMMENT 'JSON list of {material, supplier, lot}',
  `status` ENUM('in_production','released','hold','recalled') NOT NULL DEFAULT 'in_production',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_batches_company` (`company_id`),
  KEY `idx_batches_product` (`product_id`),
  KEY `idx_batches_number` (`batch_number`),
  CONSTRAINT `fk_batches_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_batches_product` FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_batches_line` FOREIGN KEY (`production_line_id`) REFERENCES `production_lines`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_batches_shift` FOREIGN KEY (`shift_id`) REFERENCES `shifts`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_batches_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================================
-- QUALITY ISSUES / NCR / CAPA (Issue Engine + Workflow Engine)
-- ============================================================================

CREATE TABLE IF NOT EXISTS `quality_issues` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `issue_number` VARCHAR(30) NOT NULL,
  `source_type` ENUM('tool_submission','manual','audit','complaint','supplier') NOT NULL DEFAULT 'manual',
  `source_id` INT UNSIGNED NULL,
  `tool_id` INT UNSIGNED NULL,
  `department_id` INT UNSIGNED NULL,
  `product_id` INT UNSIGNED NULL,
  `batch_id` INT UNSIGNED NULL,
  `shift_id` INT UNSIGNED NULL,
  `defect_type` VARCHAR(150) NULL,
  `severity` ENUM('critical','high','medium','low','observation') NOT NULL DEFAULT 'medium',
  `status` ENUM('detected','assigned','investigation','containment','root_cause','corrective_action','preventive_action','verification','closed') NOT NULL DEFAULT 'detected',
  `description` TEXT NULL,
  `ai_recommendation` TEXT NULL,
  `detected_by` INT UNSIGNED NULL,
  `assigned_to` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `closed_at` DATETIME NULL,
  KEY `idx_issues_company` (`company_id`),
  KEY `idx_issues_status` (`status`),
  KEY `idx_issues_severity` (`severity`),
  KEY `idx_issues_created` (`created_at`),
  KEY `idx_issues_dept` (`department_id`),
  CONSTRAINT `fk_issues_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `issue_comments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `issue_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NULL,
  `comment` TEXT NOT NULL,
  `status_at_comment` VARCHAR(30) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_comment_issue` (`issue_id`),
  CONSTRAINT `fk_comment_issue` FOREIGN KEY (`issue_id`) REFERENCES `quality_issues`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `issue_attachments` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `issue_id` INT UNSIGNED NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255) NULL,
  `uploaded_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_attach_issue` (`issue_id`),
  CONSTRAINT `fk_attach_issue` FOREIGN KEY (`issue_id`) REFERENCES `quality_issues`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `ncr` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `ncr_number` VARCHAR(30) NOT NULL,
  `product_id` INT UNSIGNED NULL,
  `batch_id` INT UNSIGNED NULL,
  `department_id` INT UNSIGNED NULL,
  `process` VARCHAR(150) NULL,
  `issue_id` INT UNSIGNED NULL,
  `severity` ENUM('critical','high','medium','low','observation') NOT NULL DEFAULT 'medium',
  `description` TEXT NULL,
  `containment` TEXT NULL,
  `root_cause` TEXT NULL,
  `corrective_action` TEXT NULL,
  `preventive_action` TEXT NULL,
  `responsible_person` INT UNSIGNED NULL,
  `due_date` DATE NULL,
  `status` ENUM('open','investigation','containment','root_cause','corrective_action','closed') NOT NULL DEFAULT 'open',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `closed_at` DATETIME NULL,
  KEY `idx_ncr_company` (`company_id`),
  KEY `idx_ncr_status` (`status`),
  CONSTRAINT `fk_ncr_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `capa` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `capa_number` VARCHAR(30) NOT NULL,
  `source_type` ENUM('manual','ncr','issue','audit','complaint','supplier') NOT NULL DEFAULT 'manual',
  `source_id` INT UNSIGNED NULL,
  `problem_statement` TEXT NULL,
  `root_cause` TEXT NULL,
  `correction` TEXT NULL,
  `corrective_action` TEXT NULL,
  `preventive_action` TEXT NULL,
  `responsible_person` INT UNSIGNED NULL,
  `due_date` DATE NULL,
  `verification_notes` TEXT NULL,
  `effectiveness_notes` TEXT NULL,
  `status` ENUM('open','assigned','in_progress','pending_verification','effective','closed','rejected') NOT NULL DEFAULT 'open',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `closed_at` DATETIME NULL,
  KEY `idx_capa_company` (`company_id`),
  KEY `idx_capa_status` (`status`),
  KEY `idx_capa_due` (`due_date`),
  CONSTRAINT `fk_capa_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `capa_actions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `capa_id` INT UNSIGNED NOT NULL,
  `company_id` INT UNSIGNED NOT NULL,
  `action_text` TEXT NOT NULL,
  `responsible_user` INT UNSIGNED NULL,
  `due_date` DATE NULL,
  `status` ENUM('pending','in_progress','done') NOT NULL DEFAULT 'pending',
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_capaact_capa` (`capa_id`),
  CONSTRAINT `fk_capaact_capa` FOREIGN KEY (`capa_id`) REFERENCES `capa`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
-- AUDITS & COMPLIANCE
-- ============================================================================

CREATE TABLE IF NOT EXISTS `compliance_frameworks` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(30) NOT NULL UNIQUE,
  `description` VARCHAR(255) NULL,
  `status` ENUM('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `compliance_framework_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `framework_id` INT UNSIGNED NOT NULL,
  `clause_code` VARCHAR(30) NULL,
  `requirement_text` VARCHAR(255) NOT NULL,
  `category` VARCHAR(100) NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  KEY `idx_cfi_framework` (`framework_id`),
  CONSTRAINT `fk_cfi_framework` FOREIGN KEY (`framework_id`) REFERENCES `compliance_frameworks`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `audits` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `audit_type` VARCHAR(100) NOT NULL COMMENT 'internal | layered_process | supplier | gmp | iso9001 | framework code',
  `framework_id` INT UNSIGNED NULL,
  `title` VARCHAR(200) NOT NULL,
  `department_id` INT UNSIGNED NULL,
  `auditor_id` INT UNSIGNED NULL,
  `score` DECIMAL(6,2) NULL,
  `max_score` DECIMAL(6,2) NULL,
  `status` ENUM('scheduled','in_progress','completed') NOT NULL DEFAULT 'scheduled',
  `scheduled_date` DATE NULL,
  `completed_date` DATE NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_audits_company` (`company_id`),
  CONSTRAINT `fk_audits_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `audit_checklists` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `audit_id` INT UNSIGNED NOT NULL,
  `item_text` VARCHAR(255) NOT NULL,
  `category` VARCHAR(100) NULL,
  `response` ENUM('conform','minor_nc','major_nc','not_applicable') NULL,
  `score` DECIMAL(6,2) NULL,
  `max_score` DECIMAL(6,2) NULL DEFAULT 5,
  `notes` VARCHAR(255) NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  KEY `idx_checklist_audit` (`audit_id`),
  CONSTRAINT `fk_checklist_audit` FOREIGN KEY (`audit_id`) REFERENCES `audits`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `audit_findings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `audit_id` INT UNSIGNED NOT NULL,
  `company_id` INT UNSIGNED NOT NULL,
  `finding_text` TEXT NOT NULL,
  `severity` ENUM('critical','high','medium','low','observation') NOT NULL DEFAULT 'medium',
  `corrective_action` TEXT NULL,
  `status` ENUM('open','closed') NOT NULL DEFAULT 'open',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_findings_audit` (`audit_id`),
  KEY `idx_findings_company` (`company_id`),
  CONSTRAINT `fk_findings_audit` FOREIGN KEY (`audit_id`) REFERENCES `audits`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
-- FMEA
-- ============================================================================

CREATE TABLE IF NOT EXISTS `fmea` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `fmea_type` ENUM('design','process') NOT NULL DEFAULT 'process',
  `product_id` INT UNSIGNED NULL,
  `department_id` INT UNSIGNED NULL,
  `created_by` INT UNSIGNED NULL,
  `status` ENUM('draft','active','closed') NOT NULL DEFAULT 'draft',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_fmea_company` (`company_id`),
  CONSTRAINT `fk_fmea_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `fmea_items` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `fmea_id` INT UNSIGNED NOT NULL,
  `process_step` VARCHAR(200) NULL,
  `failure_mode` VARCHAR(255) NOT NULL,
  `effect` VARCHAR(255) NULL,
  `cause` VARCHAR(255) NULL,
  `current_controls` VARCHAR(255) NULL,
  `severity` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `occurrence` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `detection` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `rpn` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
  `action_recommended` VARCHAR(255) NULL,
  `responsible_person` INT UNSIGNED NULL,
  `target_date` DATE NULL,
  `status` ENUM('open','in_progress','closed') NOT NULL DEFAULT 'open',
  KEY `idx_fmeaitem_fmea` (`fmea_id`),
  CONSTRAINT `fk_fmeaitem_fmea` FOREIGN KEY (`fmea_id`) REFERENCES `fmea`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
-- HACCP / FOOD SAFETY
-- ============================================================================

CREATE TABLE IF NOT EXISTS `haccp_plans` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NULL,
  `title` VARCHAR(200) NOT NULL,
  `team` VARCHAR(255) NULL,
  `scope` TEXT NULL,
  `status` ENUM('draft','active','under_review') NOT NULL DEFAULT 'draft',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_haccp_company` (`company_id`),
  CONSTRAINT `fk_haccp_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `haccp_hazards` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `haccp_plan_id` INT UNSIGNED NOT NULL,
  `process_step` VARCHAR(150) NOT NULL,
  `hazard_type` ENUM('biological','chemical','physical','allergen') NOT NULL,
  `hazard_description` VARCHAR(255) NULL,
  `severity` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `likelihood` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `risk_level` ENUM('low','medium','high','critical') NOT NULL DEFAULT 'low',
  `is_ccp` TINYINT(1) NOT NULL DEFAULT 0,
  `control_measure` VARCHAR(255) NULL,
  `critical_limit` VARCHAR(150) NULL,
  KEY `idx_hazard_plan` (`haccp_plan_id`),
  CONSTRAINT `fk_hazard_plan` FOREIGN KEY (`haccp_plan_id`) REFERENCES `haccp_plans`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `ccp_monitoring` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `haccp_hazard_id` INT UNSIGNED NOT NULL,
  `ccp_name` VARCHAR(150) NULL,
  `critical_limit` VARCHAR(150) NULL,
  `actual_reading` VARCHAR(100) NULL,
  `unit` VARCHAR(30) NULL,
  `reading_time` DATETIME NULL,
  `operator_id` INT UNSIGNED NULL,
  `result` ENUM('pass','fail') NOT NULL DEFAULT 'pass',
  `corrective_action` VARCHAR(255) NULL,
  `batch_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_ccp_company` (`company_id`),
  KEY `idx_ccp_hazard` (`haccp_hazard_id`),
  CONSTRAINT `fk_ccp_hazard` FOREIGN KEY (`haccp_hazard_id`) REFERENCES `haccp_hazards`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `allergen_controls` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `allergen` VARCHAR(100) NOT NULL,
  `product_id` INT UNSIGNED NULL,
  `area` VARCHAR(150) NULL,
  `risk_level` ENUM('low','medium','high') NOT NULL DEFAULT 'medium',
  `cleaning_procedure` VARCHAR(255) NULL,
  `verification_result` VARCHAR(150) NULL,
  `verified_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_allergen_company` (`company_id`),
  CONSTRAINT `fk_allergen_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `mock_recalls` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `recall_number` VARCHAR(30) NOT NULL,
  `batch_id` INT UNSIGNED NULL,
  `reason` VARCHAR(255) NULL,
  `affected_quantity` DECIMAL(14,2) NULL,
  `locations` VARCHAR(255) NULL,
  `customers_notified` INT UNSIGNED NULL,
  `quantity_recovered` DECIMAL(14,2) NULL,
  `effectiveness_percent` DECIMAL(5,2) NULL,
  `start_time` DATETIME NULL,
  `completion_time` DATETIME NULL,
  `status` ENUM('in_progress','completed') NOT NULL DEFAULT 'in_progress',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_recall_company` (`company_id`),
  CONSTRAINT `fk_recall_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
-- SUPPLIER & INCOMING QUALITY
-- ============================================================================

CREATE TABLE IF NOT EXISTS `supplier_scorecards` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `supplier_id` INT UNSIGNED NOT NULL,
  `period` VARCHAR(20) NOT NULL COMMENT 'e.g. 2026-08',
  `quality_score` DECIMAL(5,2) NULL,
  `delivery_score` DECIMAL(5,2) NULL,
  `cost_score` DECIMAL(5,2) NULL,
  `responsiveness_score` DECIMAL(5,2) NULL,
  `compliance_score` DECIMAL(5,2) NULL,
  `overall_score` DECIMAL(5,2) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_scorecard_company` (`company_id`),
  KEY `idx_scorecard_supplier` (`supplier_id`),
  CONSTRAINT `fk_scorecard_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `supplier_audits` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `supplier_id` INT UNSIGNED NOT NULL,
  `audit_date` DATE NULL,
  `auditor_id` INT UNSIGNED NULL,
  `score` DECIMAL(6,2) NULL,
  `max_score` DECIMAL(6,2) NULL,
  `status` ENUM('scheduled','completed') NOT NULL DEFAULT 'scheduled',
  `findings` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_supaudit_company` (`company_id`),
  CONSTRAINT `fk_supaudit_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `incoming_inspections` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `supplier_id` INT UNSIGNED NULL,
  `material_name` VARCHAR(150) NOT NULL,
  `batch_number` VARCHAR(50) NULL,
  `quantity` DECIMAL(14,2) NULL,
  `sample_size` INT UNSIGNED NULL,
  `accept_qty` INT UNSIGNED NULL,
  `reject_qty` INT UNSIGNED NULL,
  `result` ENUM('accepted','rejected','conditional') NOT NULL DEFAULT 'accepted',
  `inspector_id` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_incoming_company` (`company_id`),
  CONSTRAINT `fk_incoming_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `coa_records` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `supplier_id` INT UNSIGNED NULL,
  `material_name` VARCHAR(150) NOT NULL,
  `batch_number` VARCHAR(50) NULL,
  `specification` VARCHAR(255) NULL,
  `actual_result` VARCHAR(255) NULL,
  `result` ENUM('pass','fail') NOT NULL DEFAULT 'pass',
  `file_path` VARCHAR(255) NULL,
  `reviewed_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_coa_company` (`company_id`),
  CONSTRAINT `fk_coa_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
-- VOICE OF CUSTOMER
-- ============================================================================

CREATE TABLE IF NOT EXISTS `customer_complaints` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `complaint_number` VARCHAR(30) NOT NULL,
  `customer_name` VARCHAR(150) NULL,
  `product_id` INT UNSIGNED NULL,
  `batch_id` INT UNSIGNED NULL,
  `complaint_type` VARCHAR(100) NULL,
  `severity` ENUM('critical','high','medium','low') NOT NULL DEFAULT 'medium',
  `description` TEXT NULL,
  `investigation` TEXT NULL,
  `root_cause` TEXT NULL,
  `action_taken` TEXT NULL,
  `status` ENUM('open','investigation','closed') NOT NULL DEFAULT 'open',
  `nps_score` TINYINT NULL,
  `region` VARCHAR(100) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `closed_at` DATETIME NULL,
  KEY `idx_complaints_company` (`company_id`),
  KEY `idx_complaints_product` (`product_id`),
  CONSTRAINT `fk_complaints_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
-- SPC / STATISTICAL PROCESS CONTROL
-- ============================================================================

CREATE TABLE IF NOT EXISTS `spc_records` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NULL,
  `department_id` INT UNSIGNED NULL,
  `chart_type` ENUM('xbar_r','xbar_s','p','np','c','u') NOT NULL DEFAULT 'xbar_r',
  `parameter_name` VARCHAR(150) NOT NULL,
  `subgroup_size` TINYINT UNSIGNED NOT NULL DEFAULT 5,
  `usl` DECIMAL(14,4) NULL,
  `lsl` DECIMAL(14,4) NULL,
  `target` DECIMAL(14,4) NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_spc_company` (`company_id`),
  CONSTRAINT `fk_spc_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `control_chart_data` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `spc_record_id` INT UNSIGNED NOT NULL,
  `subgroup_no` INT UNSIGNED NOT NULL,
  `value1` DECIMAL(14,4) NULL,
  `value2` DECIMAL(14,4) NULL,
  `value3` DECIMAL(14,4) NULL,
  `value4` DECIMAL(14,4) NULL,
  `value5` DECIMAL(14,4) NULL,
  `mean_value` DECIMAL(14,4) NULL,
  `range_value` DECIMAL(14,4) NULL,
  `recorded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_ccd_spc` (`spc_record_id`),
  CONSTRAINT `fk_ccd_spc` FOREIGN KEY (`spc_record_id`) REFERENCES `spc_records`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `capability_studies` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `spc_record_id` INT UNSIGNED NULL,
  `product_id` INT UNSIGNED NULL,
  `parameter_name` VARCHAR(150) NULL,
  `usl` DECIMAL(14,4) NULL,
  `lsl` DECIMAL(14,4) NULL,
  `mean_value` DECIMAL(14,4) NULL,
  `std_dev` DECIMAL(14,4) NULL,
  `sample_size` INT UNSIGNED NULL,
  `cp` DECIMAL(6,3) NULL,
  `cpk` DECIMAL(6,3) NULL,
  `pp` DECIMAL(6,3) NULL,
  `ppk` DECIMAL(6,3) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_capstudy_company` (`company_id`),
  CONSTRAINT `fk_capstudy_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `gauge_rr` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `part_count` TINYINT UNSIGNED NULL,
  `operator_count` TINYINT UNSIGNED NULL,
  `trial_count` TINYINT UNSIGNED NULL,
  `repeatability` DECIMAL(8,4) NULL,
  `reproducibility` DECIMAL(8,4) NULL,
  `grr_percent` DECIMAL(6,2) NULL,
  `study_variation_percent` DECIMAL(6,2) NULL,
  `distinct_categories` TINYINT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_grr_company` (`company_id`),
  CONSTRAINT `fk_grr_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
-- OEE / SCRAP / REWORK / COST OF QUALITY
-- ============================================================================

CREATE TABLE IF NOT EXISTS `oee_records` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `production_line_id` INT UNSIGNED NULL,
  `shift_id` INT UNSIGNED NULL,
  `record_date` DATE NOT NULL,
  `planned_minutes` DECIMAL(10,2) NULL,
  `downtime_minutes` DECIMAL(10,2) NULL,
  `ideal_cycle_time` DECIMAL(10,4) NULL,
  `total_count` INT UNSIGNED NULL,
  `good_count` INT UNSIGNED NULL,
  `availability` DECIMAL(5,2) NULL,
  `performance` DECIMAL(5,2) NULL,
  `quality` DECIMAL(5,2) NULL,
  `oee` DECIMAL(5,2) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_oee_company` (`company_id`),
  KEY `idx_oee_date` (`record_date`),
  CONSTRAINT `fk_oee_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `scrap_records` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NULL,
  `batch_id` INT UNSIGNED NULL,
  `department_id` INT UNSIGNED NULL,
  `quantity` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `reason` VARCHAR(255) NULL,
  `cost` DECIMAL(12,2) NULL,
  `record_date` DATE NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_scrap_company` (`company_id`),
  CONSTRAINT `fk_scrap_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `rework_records` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `product_id` INT UNSIGNED NULL,
  `batch_id` INT UNSIGNED NULL,
  `department_id` INT UNSIGNED NULL,
  `quantity` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `reason` VARCHAR(255) NULL,
  `cost` DECIMAL(12,2) NULL,
  `record_date` DATE NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_rework_company` (`company_id`),
  CONSTRAINT `fk_rework_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `cost_of_quality` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `period` VARCHAR(20) NOT NULL,
  `prevention_cost` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `appraisal_cost` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `internal_failure_cost` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `external_failure_cost` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_coq_period` (`company_id`, `period`),
  CONSTRAINT `fk_coq_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
-- LEAN / VISUAL MANAGEMENT
-- ============================================================================

CREATE TABLE IF NOT EXISTS `kaizen_events` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `problem` TEXT NULL,
  `idea` TEXT NULL,
  `team` VARCHAR(255) NULL,
  `current_state` TEXT NULL,
  `improvement` TEXT NULL,
  `result` TEXT NULL,
  `savings` DECIMAL(12,2) NULL,
  `status` ENUM('planned','in_progress','completed') NOT NULL DEFAULT 'planned',
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_kaizen_company` (`company_id`),
  CONSTRAINT `fk_kaizen_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `five_s_audits` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `area` VARCHAR(150) NOT NULL,
  `sort_score` TINYINT UNSIGNED NULL,
  `set_in_order_score` TINYINT UNSIGNED NULL,
  `shine_score` TINYINT UNSIGNED NULL,
  `standardize_score` TINYINT UNSIGNED NULL,
  `sustain_score` TINYINT UNSIGNED NULL,
  `total_score` DECIMAL(5,2) NULL,
  `auditor_id` INT UNSIGNED NULL,
  `audit_date` DATE NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_5s_company` (`company_id`),
  CONSTRAINT `fk_5s_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `gemba_walks` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `area` VARCHAR(150) NOT NULL,
  `observer_id` INT UNSIGNED NULL,
  `finding` TEXT NULL,
  `photo_path` VARCHAR(255) NULL,
  `issue_id` INT UNSIGNED NULL,
  `action` VARCHAR(255) NULL,
  `responsible_person` INT UNSIGNED NULL,
  `due_date` DATE NULL,
  `status` ENUM('open','closed') NOT NULL DEFAULT 'open',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_gemba_company` (`company_id`),
  CONSTRAINT `fk_gemba_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `andon_events` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `production_line_id` INT UNSIGNED NULL,
  `status` ENUM('green','yellow','red') NOT NULL DEFAULT 'green',
  `event_type` ENUM('line_stop','quality_alert','maintenance_alert','material_alert') NULL,
  `description` VARCHAR(255) NULL,
  `raised_by` INT UNSIGNED NULL,
  `resolved_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `resolved_at` DATETIME NULL,
  KEY `idx_andon_company` (`company_id`),
  CONSTRAINT `fk_andon_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `visual_management` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `board_name` VARCHAR(150) NOT NULL,
  `production_line_id` INT UNSIGNED NULL,
  `department_id` INT UNSIGNED NULL,
  `data_json` TEXT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_vm_company` (`company_id`),
  CONSTRAINT `fk_vm_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
-- MAINTENANCE
-- ============================================================================

CREATE TABLE IF NOT EXISTS `preventive_maintenance` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `machine_id` INT UNSIGNED NULL,
  `maintenance_type` VARCHAR(100) NULL,
  `frequency` VARCHAR(50) NULL,
  `due_date` DATE NULL,
  `responsible_person` INT UNSIGNED NULL,
  `status` ENUM('scheduled','done','overdue') NOT NULL DEFAULT 'scheduled',
  `completed_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_pm_company` (`company_id`),
  CONSTRAINT `fk_pm_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
-- NOTIFICATIONS / EMAIL
-- ============================================================================

CREATE TABLE IF NOT EXISTS `notifications` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NULL,
  `type` VARCHAR(50) NOT NULL,
  `title` VARCHAR(200) NOT NULL,
  `message` VARCHAR(500) NULL,
  `link` VARCHAR(255) NULL,
  `severity` ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_notif_user` (`user_id`, `is_read`),
  KEY `idx_notif_company` (`company_id`),
  CONSTRAINT `fk_notif_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `email_templates` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NULL COMMENT 'NULL = global default template',
  `event_key` VARCHAR(50) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `body_html` TEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_tpl_event` (`event_key`),
  KEY `idx_tpl_company` (`company_id`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `email_queue` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NULL,
  `to_email` VARCHAR(150) NOT NULL,
  `to_name` VARCHAR(150) NULL,
  `subject` VARCHAR(255) NOT NULL,
  `body_html` TEXT NOT NULL,
  `event_key` VARCHAR(50) NULL,
  `status` ENUM('pending','sent','failed') NOT NULL DEFAULT 'pending',
  `attempts` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `sent_at` DATETIME NULL,
  KEY `idx_queue_status` (`status`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `email_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NULL,
  `to_email` VARCHAR(150) NOT NULL,
  `subject` VARCHAR(255) NULL,
  `event_key` VARCHAR(50) NULL,
  `status` VARCHAR(20) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_emaillog_company` (`company_id`)
) ENGINE=InnoDB;

-- ============================================================================
-- AI ENGINE
-- ============================================================================

CREATE TABLE IF NOT EXISTS `ai_settings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `provider` VARCHAR(50) NOT NULL DEFAULT 'none',
  `api_key` VARCHAR(255) NULL,
  `api_endpoint` VARCHAR(255) NULL,
  `model` VARCHAR(100) NULL,
  `temperature` DECIMAL(3,2) NOT NULL DEFAULT 0.40,
  `token_limit` INT UNSIGNED NOT NULL DEFAULT 800,
  `enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `ai_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NULL,
  `user_id` INT UNSIGNED NULL,
  `feature` VARCHAR(50) NOT NULL,
  `prompt` TEXT NULL,
  `response` TEXT NULL,
  `tokens_used` INT UNSIGNED NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_ailog_company` (`company_id`),
  KEY `idx_ailog_created` (`created_at`)
) ENGINE=InnoDB;

-- ============================================================================
-- KPI ENGINE
-- ============================================================================

CREATE TABLE IF NOT EXISTS `kpi_definitions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NULL COMMENT 'NULL = global default weighting, company row overrides',
  `kpi_key` VARCHAR(50) NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `target_value` DECIMAL(8,2) NULL,
  `weight` DECIMAL(4,3) NOT NULL DEFAULT 0.100,
  `green_threshold` DECIMAL(8,2) NOT NULL DEFAULT 90,
  `amber_threshold` DECIMAL(8,2) NOT NULL DEFAULT 75,
  `direction` ENUM('higher_better','lower_better') NOT NULL DEFAULT 'higher_better',
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  UNIQUE KEY `uq_kpidef_company_key` (`company_id`, `kpi_key`),
  KEY `idx_kpidef_company` (`company_id`),
  KEY `idx_kpidef_key` (`kpi_key`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `kpi_records` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `kpi_key` VARCHAR(50) NOT NULL,
  `period_date` DATE NOT NULL,
  `value` DECIMAL(10,2) NULL,
  `rag` ENUM('green','amber','red') NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_kpirec_company` (`company_id`, `kpi_key`, `period_date`),
  CONSTRAINT `fk_kpirec_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
-- REPORTS / FILES / IMPORTS
-- ============================================================================

CREATE TABLE IF NOT EXISTS `reports` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `report_type` VARCHAR(50) NOT NULL,
  `title` VARCHAR(200) NULL,
  `filters_json` TEXT NULL,
  `generated_by` INT UNSIGNED NULL,
  `file_path` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_reports_company` (`company_id`),
  CONSTRAINT `fk_reports_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `files` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `module` VARCHAR(50) NOT NULL,
  `record_id` INT UNSIGNED NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `original_name` VARCHAR(255) NULL,
  `mime_type` VARCHAR(100) NULL,
  `size` INT UNSIGNED NULL,
  `uploaded_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_files_module` (`module`, `record_id`),
  KEY `idx_files_company` (`company_id`),
  CONSTRAINT `fk_files_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `csv_imports` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NOT NULL,
  `import_type` VARCHAR(50) NOT NULL,
  `filename` VARCHAR(255) NULL,
  `total_rows` INT UNSIGNED NOT NULL DEFAULT 0,
  `imported` INT UNSIGNED NOT NULL DEFAULT 0,
  `failed` INT UNSIGNED NOT NULL DEFAULT 0,
  `skipped` INT UNSIGNED NOT NULL DEFAULT 0,
  `error_log` TEXT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_import_company` (`company_id`),
  CONSTRAINT `fk_import_company` FOREIGN KEY (`company_id`) REFERENCES `companies`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================================
-- AUDIT TRAIL / SYSTEM LOGS
-- ============================================================================

CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `company_id` INT UNSIGNED NULL,
  `user_id` INT UNSIGNED NULL,
  `action` VARCHAR(50) NOT NULL,
  `module` VARCHAR(50) NOT NULL,
  `record_id` INT UNSIGNED NULL,
  `description` VARCHAR(500) NULL,
  `ip_address` VARCHAR(45) NULL,
  `user_agent` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_activity_company` (`company_id`),
  KEY `idx_activity_user` (`user_id`),
  KEY `idx_activity_created` (`created_at`)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS `system_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `level` ENUM('info','warning','error','critical') NOT NULL DEFAULT 'info',
  `message` VARCHAR(500) NOT NULL,
  `context_json` TEXT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_syslog_level` (`level`),
  KEY `idx_syslog_created` (`created_at`)
) ENGINE=InnoDB;

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

CREATE TABLE IF NOT EXISTS `platform_settings` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT NULL,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- REFERENCE / CONFIGURATION DATA (not demo business data - safe for empty installs)
-- ============================================================================

-- Default Super Admin: email superadmin@qualitycore.app / password SuperAdmin@123
INSERT INTO `admins` (`name`, `email`, `password`, `status`) VALUES
('Platform Owner', 'superadmin@qualitycore.app', '$2y$12$ISAmpxMB.uTyik2OxZFFDOuO2F1J/DwwPIIIw.eH3QtOZ7AeAeGLe', 'active');

INSERT INTO `subscription_plans` (`name`, `employee_limit`, `department_limit`, `tool_limit`, `storage_limit_mb`, `ai_usage_limit`, `report_limit`, `price_monthly`, `features`, `status`) VALUES
('Starter', 15, 5, 15, 500, 100, 50, 199.00, 'Core QMS, NCR/CAPA, 5 dynamic tools, email alerts', 'active'),
('Professional', 75, 15, 60, 5000, 1000, 300, 599.00, 'Full tool library, SPC, HACCP, Supplier Quality, AI Assistant', 'active'),
('Enterprise', 0, 0, 0, 50000, 10000, 0, 1499.00, 'Unlimited users/tools, API access, custom compliance frameworks, dedicated support', 'active');

INSERT INTO `ai_settings` (`provider`, `api_key`, `api_endpoint`, `model`, `temperature`, `token_limit`, `enabled`) VALUES
('none', '', '', '', 0.40, 800, 0);

INSERT INTO `tool_categories` (`name`, `slug`, `icon`, `sort_order`) VALUES
('Root Cause & Problem Solving', 'root-cause-problem-solving', 'bi-diagram-3', 1),
('Statistical Process Control', 'spc', 'bi-graph-up', 2),
('Quality Planning & Design', 'quality-planning-design', 'bi-clipboard-data', 3),
('Lean & Six Sigma', 'lean-six-sigma', 'bi-lightning-charge', 4),
('Food & Consumer Safety', 'food-consumer-safety', 'bi-shield-check', 5),
('Supplier & Incoming Quality', 'supplier-incoming-quality', 'bi-truck', 6),
('Audit & Compliance', 'audit-compliance', 'bi-clipboard-check', 7),
('Basic 7 QC Tools', 'basic-7-qc', 'bi-tools', 8),
('FMCG Production Floor', 'fmcg-production-floor', 'bi-gear-wide-connected', 9),
('Maintenance & Reliability', 'maintenance-reliability', 'bi-wrench-adjustable', 10),
('Voice of Customer', 'voice-of-customer', 'bi-chat-dots', 11),
('Visual & Floor Management', 'visual-floor-management', 'bi-kanban', 12);

INSERT INTO `permissions` (`permission_key`, `name`, `module`) VALUES
('tools.submit', 'Submit Quality Tools', 'tools'),
('issues.manage', 'Manage Quality Issues', 'issues'),
('ncr.manage', 'Manage NCR', 'ncr'),
('capa.manage', 'Manage CAPA', 'capa'),
('audits.manage', 'Manage Audits', 'audits'),
('reports.view', 'View Reports', 'reports'),
('reports.export', 'Export Reports', 'reports'),
('ai.use', 'Use AI Quality Assistant', 'ai');

INSERT INTO `email_templates` (`company_id`, `event_key`, `name`, `subject`, `body_html`) VALUES
(NULL, 'employee_created', 'Employee Account Created', 'Welcome to {{company_name}} on {{platform_name}}',
 '<p>Hi {{employee_name}},</p><p>Your account has been created on {{platform_name}} for <strong>{{company_name}}</strong>.</p><p>Department: {{department_name}}<br>Login URL: <a href="{{login_url}}">{{login_url}}</a><br>Username: {{username}}<br>Temporary Password: {{temp_password}}</p><p>Please log in and change your password. Your assigned quality tools will appear on your dashboard.</p>'),
(NULL, 'tool_assigned', 'Quality Tool Assigned', 'New Quality Tool Assigned: {{tool_name}}',
 '<p>Hi {{employee_name}},</p><p>You have been assigned the quality tool <strong>{{tool_name}}</strong>. Please complete it according to the configured frequency.</p><p><a href="{{login_url}}">Go to Dashboard</a></p>'),
(NULL, 'daily_reminder', 'Daily Submission Reminder', 'Reminder: {{tool_name}} due today',
 '<p>Hi {{employee_name}},</p><p>This is a reminder that <strong>{{tool_name}}</strong> is due by {{deadline}} today.</p>'),
(NULL, 'missed_submission', 'Missed Submission Alert', 'Missed Submission: {{tool_name}}',
 '<p>Hi {{employee_name}},</p><p>You missed the deadline ({{deadline}}) for <strong>{{tool_name}}</strong> on {{date}}. This has been logged and your manager has been notified.</p>'),
(NULL, 'quality_issue', 'Quality Issue Notification', 'Quality Issue Detected: {{issue_name}}',
 '<p>A quality issue <strong>{{issue_name}}</strong> was detected on {{tool_name}} at {{date}}.</p><p><a href="{{login_url}}">Review Issue</a></p>'),
(NULL, 'critical_issue', 'Critical Quality Issue', 'CRITICAL: {{issue_name}} requires immediate attention',
 '<p style="color:#dc2626"><strong>Critical quality issue detected: {{issue_name}}</strong></p><p>Tool: {{tool_name}} | Date: {{date}}</p><p><a href="{{login_url}}">Review Now</a></p>'),
(NULL, 'ncr_created', 'NCR Created', 'New NCR Raised: {{issue_name}}',
 '<p>A Non-Conformance Report <strong>{{issue_name}}</strong> has been raised and requires your attention.</p>'),
(NULL, 'capa_assigned', 'CAPA Assigned', 'CAPA Assigned: {{issue_name}}',
 '<p>Hi {{employee_name}},</p><p>A CAPA <strong>{{issue_name}}</strong> has been assigned to you, due {{deadline}}.</p>'),
(NULL, 'action_assigned', 'Action Assigned', 'Action Assigned: {{issue_name}}',
 '<p>Hi {{employee_name}},</p><p>An action <strong>{{issue_name}}</strong> has been assigned to you, due {{deadline}}.</p>'),
(NULL, 'action_overdue', 'Action Overdue', 'Overdue Action: {{issue_name}}',
 '<p>Hi {{employee_name}},</p><p>The action <strong>{{issue_name}}</strong> was due {{deadline}} and is now overdue. Please update its status.</p>'),
(NULL, 'audit_finding', 'Audit Finding', 'New Audit Finding Recorded',
 '<p>A new finding was recorded during a recent audit at {{company_name}}. Please review and assign corrective action.</p>'),
(NULL, 'issue_resolved', 'Issue Resolved', 'Resolved: {{issue_name}}',
 '<p>The quality issue <strong>{{issue_name}}</strong> has been verified and closed.</p>');

INSERT INTO `kpi_definitions` (`company_id`, `kpi_key`, `name`, `target_value`, `weight`, `green_threshold`, `amber_threshold`, `direction`) VALUES
(NULL, 'inspection_compliance', 'Inspection Compliance', 95, 0.150, 95, 80, 'higher_better'),
(NULL, 'defect_rate', 'Defect Rate', 3, 0.150, 3, 8, 'lower_better'),
(NULL, 'fpy', 'First Pass Yield', 95, 0.150, 95, 85, 'higher_better'),
(NULL, 'ncr_rate', 'NCR Rate', 2, 0.100, 2, 6, 'lower_better'),
(NULL, 'capa_closure_rate', 'CAPA Closure Rate', 90, 0.100, 90, 70, 'higher_better'),
(NULL, 'complaint_rate', 'Complaint Rate', 1, 0.100, 1, 3, 'lower_better'),
(NULL, 'audit_score', 'Audit Score', 90, 0.100, 90, 75, 'higher_better'),
(NULL, 'supplier_score', 'Supplier Quality Score', 90, 0.075, 90, 75, 'higher_better'),
(NULL, 'oee', 'OEE', 85, 0.075, 85, 65, 'higher_better');

INSERT INTO `platform_settings` (`setting_key`, `setting_value`) VALUES
('platform_name', 'QualityCore'),
('logo', ''),
('favicon', ''),
('primary_color', '#2563EB'),
('support_email', 'support@qualitycore.app'),
('timezone', 'UTC'),
('date_format', 'd M Y'),
('smtp_host', ''),
('smtp_port', '587'),
('smtp_username', ''),
('smtp_password', ''),
('smtp_secure', 'tls'),
('smtp_from_email', 'no-reply@qualitycore.app'),
('smtp_from_name', 'QualityCore'),
('notif_daily_reminder_enabled', '1'),
('notif_missed_submission_enabled', '1');

INSERT INTO `compliance_frameworks` (`name`, `code`, `description`, `status`) VALUES
('ISO 9001', 'ISO9001', 'Quality Management Systems', 'active'),
('ISO 22000', 'ISO22000', 'Food Safety Management Systems', 'active'),
('FSSC 22000', 'FSSC22000', 'Food Safety System Certification', 'active'),
('BRCGS', 'BRCGS', 'Global Standard for Food Safety', 'active'),
('SQF', 'SQF', 'Safe Quality Food Program', 'active'),
('IFS', 'IFS', 'International Featured Standards', 'active'),
('GFSI', 'GFSI', 'Global Food Safety Initiative benchmark', 'active'),
('HACCP', 'HACCP', 'Hazard Analysis Critical Control Points', 'active'),
('GMP', 'GMP', 'Good Manufacturing Practice', 'active'),
('Halal', 'HALAL', 'Halal Certification', 'active'),
('Kosher', 'KOSHER', 'Kosher Certification', 'active'),
('ISO 17025', 'ISO17025', 'Testing and Calibration Laboratories', 'active');

-- ============================================================================
-- PART 2 / 4: DYNAMIC TOOL LIBRARY - BATCH 1 (source: tool_library.sql)
-- ============================================================================

-- ============================================================================
-- Dynamic Quality Tool Library - initial platform tool set.
-- These are GLOBAL tools (company_id IS NULL) built entirely through the
-- Dynamic Tool Engine (tools + tool_fields + tool_field_options) - no
-- dedicated one-off pages required. Run after schema.sql (before or without
-- seed_demo.sql - this is reference/config data, safe for empty installs too).
--
-- NOTE: MySQL's LAST_INSERT_ID() after a multi-row INSERT returns the id
-- generated for the FIRST row of that insert. Field option blocks below
-- reference "LAST_INSERT_ID() + offset" where offset = (row position - 1)
-- of the target field within its preceding tool_fields INSERT.
-- ============================================================================

USE `fmcg_qms`;

-- 1. 5 WHYS -------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status, ai_instructions)
VALUES ((SELECT id FROM tool_categories WHERE slug='root-cause-problem-solving'), '5 Whys', '5-whys',
 'Interactive five-level root cause analysis.', 'bi-question-circle', 'State the problem, then ask "why" up to five times to reach the root cause.',
 'on_demand', 'form', 'active', 'Suggest plausible contributing factors for each "why" level without declaring an unverified root cause as fact.');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Problem Statement','problem_statement','long_text',1,1),
(@t,'Why 1','why_1','long_text',1,2),
(@t,'Why 2','why_2','long_text',0,3),
(@t,'Why 3','why_3','long_text',0,4),
(@t,'Why 4','why_4','long_text',0,5),
(@t,'Why 5 (Root Cause)','why_5','long_text',0,6);

-- 2. FISHBONE / ISHIKAWA --------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status, ai_instructions)
VALUES ((SELECT id FROM tool_categories WHERE slug='root-cause-problem-solving'), 'Fishbone / Ishikawa Diagram', 'fishbone-ishikawa',
 'Interactive Cause & Effect diagram across Man, Machine, Method, Material, Measurement, Environment.', 'bi-diagram-2',
 'List potential causes under each category contributing to the effect (problem).', 'on_demand', 'form', 'active',
 'Suggest additional candidate causes per category based on similar historical issues.');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Effect / Problem','effect','text',1,1),
(@t,'Man','man','long_text',0,2),
(@t,'Machine','machine','long_text',0,3),
(@t,'Method','method','long_text',0,4),
(@t,'Material','material','long_text',0,5),
(@t,'Measurement','measurement_cause','long_text',0,6),
(@t,'Environment','environment','long_text',0,7);

-- 3. PARETO DEFECT LOG ------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status, ai_instructions)
VALUES ((SELECT id FROM tool_categories WHERE slug='basic-7-qc'), 'Pareto Defect Log', 'pareto-defect-log',
 'Log defect occurrences to build 80/20 Pareto analysis on the Reports page.', 'bi-bar-chart-steps',
 'Select the defect type observed and quantity for this record.', 'per_batch', 'form', 'active', NULL);
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Defect Type','defect_type','dropdown',1,1),
(@t,'Quantity','quantity','number',1,2),
(@t,'Notes','notes','text',0,3);
SET @f := LAST_INSERT_ID();
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f,'Underweight','underweight',1),(@f,'Overweight','overweight',2),(@f,'Seal Defect','seal_defect',3),
(@f,'Label Misprint','label_misprint',4),(@f,'Foreign Material','foreign_material',5),
(@f,'Contamination','contamination',6),(@f,'Packaging Damage','packaging_damage',7),
(@f,'Color Variation','color_variation',8),(@f,'Texture Issue','texture_issue',9),(@f,'Other','other',10);

-- 4. IS / IS NOT ANALYSIS ----------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='root-cause-problem-solving'), 'Is / Is Not Analysis', 'is-is-not-analysis',
 'Compare What/Where/When/Extent is vs is not to scope a problem.', 'bi-columns-gap', 'Complete both columns for each dimension.', 'on_demand', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'What Is','what_is','long_text',0,1),(@t,'What Is Not','what_is_not','long_text',0,2),
(@t,'Where','where_is','long_text',0,3),(@t,'Where Not','where_is_not','long_text',0,4),
(@t,'When','when_is','long_text',0,5),(@t,'When Not','when_is_not','long_text',0,6),
(@t,'Extent','extent_is','long_text',0,7),(@t,'Extent Not','extent_is_not','long_text',0,8);

-- 5. 8D PROBLEM SOLVING -------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='root-cause-problem-solving'), '8D Problem Solving', '8d-problem-solving',
 'Eight Disciplines structured problem solving.', 'bi-8-circle', 'Work through D1-D8 sequentially.', 'on_demand', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'D1 - Team','d1_team','text',1,1),(@t,'D2 - Problem Description','d2_problem','long_text',1,2),
(@t,'D3 - Containment','d3_containment','long_text',0,3),(@t,'D4 - Root Cause','d4_root_cause','long_text',0,4),
(@t,'D5 - Corrective Actions','d5_corrective','long_text',0,5),(@t,'D6 - Implementation','d6_implementation','long_text',0,6),
(@t,'D7 - Prevent Recurrence','d7_prevent','long_text',0,7),(@t,'D8 - Recognition / Closure','d8_closure','long_text',0,8);

-- 6. A3 PROBLEM SOLVING --------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='lean-six-sigma'), 'A3 Problem Solving', 'a3-problem-solving',
 'Single-page Lean storyboard for structured problem solving.', 'bi-file-earmark-text', 'Complete each A3 section concisely.', 'on_demand', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Background','background','long_text',1,1),(@t,'Current Condition','current_condition','long_text',0,2),
(@t,'Goal','goal','long_text',0,3),(@t,'Root Cause','root_cause','long_text',0,4),
(@t,'Countermeasures','countermeasures','long_text',0,5),(@t,'Implementation Plan','implementation','long_text',0,6),
(@t,'Follow-up','follow_up','long_text',0,7),(@t,'Results','results','long_text',0,8);

-- 7. TEMPERATURE / COLD CHAIN MONITORING ---------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status, ai_instructions)
VALUES ((SELECT id FROM tool_categories WHERE slug='fmcg-production-floor'), 'Cold Chain Temperature Monitoring', 'cold-chain-temperature',
 'Record temperature readings for cold storage / cold chain locations.', 'bi-thermometer-snow', 'Record the reading exactly as shown on the calibrated device.',
 'per_shift', 'form', 'active', 'If a reading is outside 2-8C, suggest checking door seals, refrigeration unit status and recent door-open events.');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, unit, min_value, max_value, warning_threshold_low, warning_threshold_high, critical_threshold_low, critical_threshold_high, sort_order) VALUES
(@t,'Location','location','text',1,NULL,NULL,NULL,NULL,NULL,NULL,NULL,1),
(@t,'Equipment','equipment','text',0,NULL,NULL,NULL,NULL,NULL,NULL,NULL,2),
(@t,'Temperature Reading','temperature','measurement',1,'C',2,8,3,7,0,10,3);

-- 8. METAL DETECTION / X-RAY -----------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='fmcg-production-floor'), 'Metal Detection / X-Ray Check', 'metal-detection-xray',
 'Verify metal detector / x-ray performance using test pieces.', 'bi-magnet', 'Pass all three test standards (Fe, Non-Fe, SS) then run reject test.', 'per_shift', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Test Standard','test_standard','dropdown',1,1),
(@t,'Detection Result','detection_result','pass_fail',1,2),
(@t,'Reject Test Result','reject_test','pass_fail',1,3);
SET @f := LAST_INSERT_ID();
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f,'Ferrous 2.0mm','fe_2mm',1),(@f,'Non-Ferrous 2.5mm','nfe_2_5mm',2),(@f,'Stainless Steel 3.0mm','ss_3mm',3);

-- 9. CHECKWEIGHER -----------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='fmcg-production-floor'), 'Checkweigher Verification', 'checkweigher-verification',
 'Verify pack weight against target and tolerance.', 'bi-speedometer2', 'Weigh a sample pack and record against the configured target.', 'hourly', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, unit, sort_order) VALUES
(@t,'Target Weight','target_weight','number',1,'g',1),
(@t,'Actual Weight','actual_weight','measurement',1,'g',2),
(@t,'Tolerance (+/-)','tolerance','number',0,'g',3),
(@t,'Pass / Fail','pass_fail_result','pass_fail',1,NULL,4);

-- 10. ENVIRONMENTAL MONITORING PROGRAM ---------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='food-consumer-safety'), 'Environmental Monitoring (EMP)', 'environmental-monitoring',
 'Environmental swab/air sampling program for pathogen and hygiene indicator control.', 'bi-droplet-half', 'Record sample location, organism tested and result against limit.', 'weekly', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Area','area','text',1,1),(@t,'Sample Location','sample_location','text',1,2),
(@t,'Sample Type','sample_type','dropdown',1,3),(@t,'Organism Tested','organism','text',1,4),
(@t,'Result (CFU)','result_value','number',1,5),(@t,'Limit (CFU)','limit_value','number',0,6),
(@t,'Status','status_result','pass_fail',1,7);
SET @f := LAST_INSERT_ID() + 2;
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f,'Zone 1 - Product Contact','zone1',1),(@f,'Zone 2 - Near Product','zone2',2),
(@f,'Zone 3 - Environment','zone3',3),(@f,'Zone 4 - Remote','zone4',4);

-- 11. PEST CONTROL / IPM ------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='food-consumer-safety'), 'Pest Control / IPM Inspection', 'pest-control-ipm',
 'Integrated Pest Management station inspection.', 'bi-bug', 'Inspect each station and record activity level and action taken.', 'weekly', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Station / Location','location','text',1,1),(@t,'Pest Activity','pest_activity','dropdown',1,2),
(@t,'Action Taken','action_taken','long_text',0,3),(@t,'Chemical / Method Used','method_used','text',0,4),
(@t,'Status','status_result','pass_fail',1,5);
SET @f := LAST_INSERT_ID() + 1;
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f,'None','none',1),(@f,'Low','low',2),(@f,'Medium','medium',3),(@f,'High','high',4);

-- 12. SENSORY EVALUATION -------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='fmcg-production-floor'), 'Sensory Evaluation', 'sensory-evaluation',
 'Panel-based sensory evaluation of finished product.', 'bi-emoji-smile', 'Rate each attribute from 1 (poor) to 5 (excellent).', 'per_batch', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, min_value, max_value, sort_order) VALUES
(@t,'Taste','taste','rating',1,1,5,1),(@t,'Smell','smell','rating',1,1,5,2),
(@t,'Texture','texture','rating',1,1,5,3),(@t,'Appearance','appearance','rating',1,1,5,4),
(@t,'Overall Score','overall_score','rating',1,1,5,5);

-- 13. WATER ACTIVITY / pH TESTING ------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status, ai_instructions)
VALUES ((SELECT id FROM tool_categories WHERE slug='fmcg-production-floor'), 'Water Activity / pH Testing', 'water-activity-ph',
 'Record pH and Aw readings against product specification.', 'bi-eyedropper', 'Calibrate meter before testing.', 'per_batch', 'form', 'active',
 'If pH is outside 6.5-7.5, suggest verifying calibration and checking for formulation or process drift.');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, unit, min_value, max_value, warning_threshold_low, warning_threshold_high, critical_threshold_low, critical_threshold_high, sort_order) VALUES
(@t,'pH','ph_value','decimal',1,'pH',6.5,7.5,6.6,7.3,6.4,7.6,1),
(@t,'Water Activity (Aw)','aw_value','decimal',0,'Aw',0.60,0.85,NULL,NULL,NULL,NULL,2);

-- 14. MICRO TESTING -----------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='food-consumer-safety'), 'Micro Testing', 'micro-testing',
 'Microbiological testing of samples against limits.', 'bi-virus', 'Record organism tested and result vs specification limit.', 'weekly', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Sample','sample_name','text',1,1),(@t,'Organism','organism','text',1,2),
(@t,'Result (CFU/g)','result_value','number',1,3),(@t,'Limit (CFU/g)','limit_value','number',0,4),
(@t,'Status','status_result','pass_fail',1,5);

-- 15. GMP AUDIT CHECKLIST ---------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='audit-compliance'), 'GMP Audit Checklist', 'gmp-audit-checklist',
 'Checklist-based Good Manufacturing Practice inspection.', 'bi-clipboard2-check', 'Mark each category Pass/Fail and add notes for any failures.', 'weekly', 'checklist', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Personnel Hygiene','personnel_hygiene','pass_fail',1,1),(@t,'Facility','facility','pass_fail',1,2),
(@t,'Equipment','equipment_gmp','pass_fail',1,3),(@t,'Storage','storage','pass_fail',1,4),
(@t,'Production','production','pass_fail',1,5),(@t,'Documentation','documentation','pass_fail',1,6),
(@t,'Notes','notes','long_text',0,7);

-- 16. 5S AUDIT -----------------------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='lean-six-sigma'), '5S Audit', '5s-audit',
 'Workplace organization audit: Sort, Set in Order, Shine, Standardize, Sustain.', 'bi-grid-3x3-gap', 'Score each pillar 1 (poor) to 5 (excellent).', 'weekly', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, min_value, max_value, sort_order) VALUES
(@t,'Sort','sort_score','rating',1,1,5,1),(@t,'Set in Order','set_order_score','rating',1,1,5,2),
(@t,'Shine','shine_score','rating',1,1,5,3),(@t,'Standardize','standardize_score','rating',1,1,5,4),
(@t,'Sustain','sustain_score','rating',1,1,5,5);

-- 17. KAIZEN IDEA LOG ---------------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='lean-six-sigma'), 'Kaizen Idea Log', 'kaizen-idea-log',
 'Capture continuous improvement ideas from the shop floor.', 'bi-lightbulb', 'Describe the problem, your idea and the expected improvement.', 'on_demand', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Problem','problem','long_text',1,1),(@t,'Idea','idea','long_text',1,2),
(@t,'Expected Result','expected_result','long_text',0,3);

-- 18. SCRAP / REWORK ENTRY ---------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='fmcg-production-floor'), 'Scrap & Rework Entry', 'scrap-rework-entry',
 'Log scrap and rework quantities with reason and cost.', 'bi-recycle', 'Record quantity, reason and estimated cost impact.', 'per_shift', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Quantity Scrapped','quantity_scrapped','number',0,1),(@t,'Quantity Reworked','quantity_reworked','number',0,2),
(@t,'Reason','reason','dropdown',1,3),(@t,'Estimated Cost','cost','number',0,4);
SET @f := LAST_INSERT_ID() + 2;
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f,'Process Deviation','process_deviation',1),(@f,'Material Defect','material_defect',2),
(@f,'Machine Fault','machine_fault',3),(@f,'Operator Error','operator_error',4),(@f,'Other','other',5);

-- 19. SUPPLIER SCORECARD ENTRY -------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='supplier-incoming-quality'), 'Supplier Scorecard Entry', 'supplier-scorecard-entry',
 'Monthly supplier performance scoring across five dimensions.', 'bi-star-half', 'Score each dimension 0-100 for the period.', 'monthly', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, min_value, max_value, sort_order) VALUES
(@t,'Quality Score','quality_score','percentage',1,0,100,1),(@t,'Delivery Score','delivery_score','percentage',1,0,100,2),
(@t,'Cost Score','cost_score','percentage',1,0,100,3),(@t,'Responsiveness Score','responsiveness_score','percentage',1,0,100,4),
(@t,'Compliance Score','compliance_score','percentage',1,0,100,5);

-- 20. OEE SHIFT ENTRY ------------------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='lean-six-sigma'), 'OEE Shift Entry', 'oee-shift-entry',
 'Record shift production data to calculate Availability, Performance, Quality and OEE.', 'bi-speedometer', 'Enter planned time, downtime, cycle time and counts for the shift.', 'per_shift', 'calculator', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, unit, sort_order) VALUES
(@t,'Planned Minutes','planned_minutes','number',1,'min',1),(@t,'Downtime Minutes','downtime_minutes','number',1,'min',2),
(@t,'Ideal Cycle Time','ideal_cycle_time','decimal',1,'min/unit',3),(@t,'Total Count','total_count','number',1,'units',4),
(@t,'Good Count','good_count','number',1,'units',5);

-- 21. CHECK SHEET (Basic 7 QC) ---------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='basic-7-qc'), 'Check Sheet', 'check-sheet',
 'Simple tally check sheet for defect/event frequency data collection.', 'bi-ui-checks', 'Select category and record tally count.', 'per_shift', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Category','category','dropdown',1,1),(@t,'Tally Count','tally_count','number',1,2);
SET @f := LAST_INSERT_ID();
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f,'Machine','machine',1),(@f,'Operator','operator',2),(@f,'Material','material',3),(@f,'Method','method',4);

-- 22. STRATIFICATION ------------------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='basic-7-qc'), 'Stratification', 'stratification',
 'Break down a data set by machine, operator, shift or material to isolate a source of variation.', 'bi-layers', 'Select the stratification category and record the observed value.', 'on_demand', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Stratification Factor','factor','dropdown',1,1),(@t,'Value','value','number',1,2);
SET @f := LAST_INSERT_ID();
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f,'Machine','machine',1),(@f,'Operator','operator',2),(@f,'Shift','shift',3),(@f,'Material Lot','material_lot',4);

-- 23. LAYERED PROCESS AUDIT (LPA) ------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='audit-compliance'), 'Layered Process Audit', 'layered-process-audit',
 'Different management layers perform short, frequent process observations.', 'bi-layers-half', 'Record observation and immediate result.', 'weekly', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Audit Layer','layer','dropdown',1,1),(@t,'Area','area','text',1,2),
(@t,'Observation','observation','long_text',0,3),(@t,'Result','result','pass_fail',1,4);
SET @f := LAST_INSERT_ID();
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f,'Operator','operator',1),(@f,'Supervisor','supervisor',2),(@f,'Plant Manager','manager',3),(@f,'Executive','executive',4);

-- 24. ALLERGEN VERIFICATION SWAB -------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='food-consumer-safety'), 'Allergen Verification Swab', 'allergen-verification-swab',
 'Post-cleaning allergen swab verification before changeover to an allergen-free product.', 'bi-exclamation-octagon',
 'Swab equipment contact surfaces after cleaning and before running an allergen-sensitive product.', 'per_batch', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Area / Equipment','area','text',1,1),(@t,'Allergen Tested','allergen','text',1,2),
(@t,'Swab Result','swab_result','pass_fail',1,3);

-- ============================================================================
-- PART 3 / 4: DYNAMIC TOOL LIBRARY - BATCH 2 (source: tool_library_2.sql)
-- ============================================================================

-- ============================================================================
-- Dynamic Quality Tool Library - BATCH 2 (19 more tools, same pattern as
-- tool_library.sql). Run after tool_library.sql. Global tools (company_id NULL).
-- ============================================================================

USE `fmcg_qms`;

-- 25. FAULT TREE ANALYSIS (FTA) ---------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='root-cause-problem-solving'), 'Fault Tree Analysis', 'fault-tree-analysis',
 'Top event -> intermediate event -> basic event fault tree with AND/OR gates.', 'bi-diagram-3',
 'Log each branch of the fault tree as a row: top event, the intermediate event leading to it, the basic (root) event, and how they combine.', 'on_demand', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Top Event','top_event','text',1,1),
(@t,'Intermediate Event','intermediate_event','text',0,2),
(@t,'Basic Event','basic_event','text',1,3),
(@t,'Gate Type','gate_type','dropdown',1,4),
(@t,'Probability (0-1)','probability','decimal',0,5);
SET @f := LAST_INSERT_ID() + 3;
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f,'AND Gate','and',1),(@f,'OR Gate','or',2);

-- 26. APQP TRACKER -----------------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='quality-planning-design'), 'APQP Tracker', 'apqp-tracker',
 'Track Advanced Product Quality Planning tasks across the five APQP phases.', 'bi-kanban', 'Log each task under its APQP stage with owner and target date.', 'on_demand', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Stage','stage','dropdown',1,1),
(@t,'Task','task','text',1,2),
(@t,'Status','status','dropdown',1,3),
(@t,'Target Date','target_date','date',0,4),
(@t,'Owner','owner','text',0,5);
SET @f1 := LAST_INSERT_ID();
SET @f2 := LAST_INSERT_ID() + 2;
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f1,'Planning','planning',1),(@f1,'Product Design','product_design',2),(@f1,'Process Design','process_design',3),
(@f1,'Validation','validation',4),(@f1,'Feedback / Improvement','feedback_improvement',5);
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f2,'Not Started','not_started',1),(@f2,'In Progress','in_progress',2),(@f2,'Complete','complete',3),(@f2,'At Risk','at_risk',4);

-- 27. PPAP SUBMISSION --------------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='quality-planning-design'), 'PPAP Submission', 'ppap-submission',
 'Production Part Approval Process element tracking and sign-off.', 'bi-file-earmark-check', 'Record the submission level, PPAP element and pass/fail status for the part.', 'on_demand', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Part Number','part_number','text',1,1),
(@t,'Part Name','part_name','text',1,2),
(@t,'Submission Level','submission_level','dropdown',1,3),
(@t,'PPAP Element','ppap_element','dropdown',1,4),
(@t,'Status','status_result','pass_fail',1,5),
(@t,'Notes','notes','long_text',0,6);
SET @f1 := LAST_INSERT_ID() + 2;
SET @f2 := LAST_INSERT_ID() + 3;
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f1,'Level 1','level_1',1),(@f1,'Level 2','level_2',2),(@f1,'Level 3','level_3',3),(@f1,'Level 4','level_4',4),(@f1,'Level 5','level_5',5);
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f2,'Design Records','design_records',1),(@f2,'Process Flow Diagram','process_flow',2),(@f2,'Control Plan','control_plan',3),
(@f2,'FMEA','fmea',4),(@f2,'Inspection Results','inspection_results',5),(@f2,'CoA','coa',6),(@f2,'Sample Approval','sample_approval',7),(@f2,'PSW','psw',8);

-- 28. CONTROL PLAN ENTRY -----------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='quality-planning-design'), 'Control Plan Entry', 'control-plan-entry',
 'Document process control plan characteristics and reaction plans.', 'bi-list-check', 'One row per controlled characteristic.', 'on_demand', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Process','process_name','text',1,1),
(@t,'Operation','operation','text',0,2),
(@t,'Specification','specification','text',1,3),
(@t,'Measurement Method','measurement_method','text',0,4),
(@t,'Frequency','frequency_text','text',0,5),
(@t,'Responsible Person','responsible_person_text','text',0,6),
(@t,'Reaction Plan','reaction_plan','long_text',0,7);

-- 29. QFD - HOUSE OF QUALITY ENTRY --------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='quality-planning-design'), 'QFD - House of Quality', 'qfd-house-of-quality',
 'Map customer requirements to technical requirements with relationship strength and priority.', 'bi-grid-1x2', 'One row per customer-to-technical requirement relationship.', 'on_demand', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, min_value, max_value, sort_order) VALUES
(@t,'Customer Requirement','customer_requirement','text',1,NULL,NULL,1),
(@t,'Technical Requirement','technical_requirement','text',1,NULL,NULL,2),
(@t,'Relationship Strength','relationship_strength','dropdown',1,NULL,NULL,3),
(@t,'Priority (1-5)','priority','rating',0,1,5,4);
SET @f := LAST_INSERT_ID() + 2;
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f,'Strong','strong',1),(@f,'Medium','medium',2),(@f,'Weak','weak',3),(@f,'None','none',4);

-- 30. DOE RUN LOG -------------------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='quality-planning-design'), 'DOE Run Log', 'doe-run-log',
 'Design of Experiments run log: factors, levels and measured response.', 'bi-sliders', 'Record each experimental run with its factor settings and response.', 'on_demand', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Run Number','run_number','number',1,1),
(@t,'Factor A','factor_a','text',0,2),
(@t,'Factor B','factor_b','text',0,3),
(@t,'Factor C','factor_c','text',0,4),
(@t,'Response','response_value','number',1,5),
(@t,'Notes','notes','text',0,6);

-- 31. VALUE STREAM MAPPING ENTRY -----------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='lean-six-sigma'), 'Value Stream Mapping Entry', 'value-stream-mapping',
 'Log process/wait time and inventory per process step to build a value stream map.', 'bi-arrow-left-right', 'One row per process step in the value stream.', 'on_demand', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, unit, sort_order) VALUES
(@t,'Process Step','process_step','text',1,NULL,1),
(@t,'Process Time','process_time','number',0,'min',2),
(@t,'Wait Time','wait_time','number',0,'min',3),
(@t,'Inventory','inventory','number',0,'units',4),
(@t,'Value Added?','value_added','yes_no',0,NULL,5);

-- 32. SMED CHANGEOVER LOG ---------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='lean-six-sigma'), 'SMED Changeover Log', 'smed-changeover-log',
 'Single-Minute Exchange of Die: log changeover tasks and time before/after improvement.', 'bi-stopwatch', 'Classify each task as internal (machine stopped) or external (done while running).', 'on_demand', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, unit, sort_order) VALUES
(@t,'Changeover Task','changeover_task','text',1,NULL,1),
(@t,'Type','changeover_type','dropdown',1,NULL,2),
(@t,'Time Before','time_before','number',0,'min',3),
(@t,'Time After','time_after','number',0,'min',4);
SET @f := LAST_INSERT_ID() + 1;
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f,'Internal (machine stopped)','internal',1),(@f,'External (while running)','external',2);

-- 33. POKA-YOKE RECORD --------------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='lean-six-sigma'), 'Poka-Yoke Record', 'poka-yoke-record',
 'Mistake-proofing device verification record.', 'bi-shield-plus', 'Verify the poka-yoke device correctly prevents/detects the mistake.', 'per_shift', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Process Step','process_step','text',1,1),
(@t,'Mistake Prevented','mistake_prevented','text',1,2),
(@t,'Method','method','dropdown',0,3),
(@t,'Verification Result','verification_result','pass_fail',1,4);
SET @f := LAST_INSERT_ID() + 2;
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f,'Contact Method','contact',1),(@f,'Fixed Value Method','fixed_value',2),(@f,'Motion Step Method','motion_step',3);

-- 34. KANBAN CARD UPDATE ------------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='lean-six-sigma'), 'Kanban Card Update', 'kanban-card-update',
 'Visual pull-system card status update.', 'bi-kanban-fill', 'Update the stage of a Kanban card as work progresses.', 'on_demand', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Card ID','card_id','text',1,1),
(@t,'Stage','stage','dropdown',1,2),
(@t,'Item','item','text',0,3),
(@t,'Quantity','quantity','number',0,4);
SET @f := LAST_INSERT_ID() + 1;
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f,'To Do','to_do',1),(@f,'In Progress','in_progress',2),(@f,'Done','done',3);

-- 35. TPM EQUIPMENT LOG -------------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='maintenance-reliability'), 'TPM Equipment Log', 'tpm-equipment-log',
 'Total Productive Maintenance: log equipment availability, breakdowns and losses.', 'bi-gear-wide-connected', 'Record shift-level equipment availability and any breakdown/loss.', 'per_shift', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, unit, min_value, max_value, sort_order) VALUES
(@t,'Equipment','equipment','text',1,NULL,NULL,NULL,1),
(@t,'Availability','availability','percentage',1,'%',0,100,2),
(@t,'Breakdowns','breakdowns','number',0,NULL,NULL,NULL,3),
(@t,'Maintenance Actions','maintenance_actions','long_text',0,NULL,NULL,NULL,4),
(@t,'Losses','losses','long_text',0,NULL,NULL,NULL,5);

-- 36. PREDICTIVE MAINTENANCE READING -------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='maintenance-reliability'), 'Predictive Maintenance Reading', 'predictive-maintenance-reading',
 'Condition-based monitoring reading (vibration, temperature, etc.) with trend and risk assessment.', 'bi-activity', 'Record the condition parameter reading and assess trend/risk.', 'weekly', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Equipment','equipment','text',1,1),
(@t,'Condition Parameter','condition_parameter','text',1,2),
(@t,'Measurement','measurement_value','number',1,3),
(@t,'Trend','trend','dropdown',0,4),
(@t,'Risk','risk_level','dropdown',0,5);
SET @f1 := LAST_INSERT_ID() + 3;
SET @f2 := LAST_INSERT_ID() + 4;
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f1,'Stable','stable',1),(@f1,'Increasing','increasing',2),(@f1,'Decreasing','decreasing',3);
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f2,'Low','low',1),(@f2,'Medium','medium',2),(@f2,'High','high',3);

-- 37. AUTONOMOUS MAINTENANCE CHECKLIST -------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='maintenance-reliability'), 'Autonomous Maintenance Checklist', 'autonomous-maintenance-checklist',
 'Operator-performed Clean-Inspect-Lubricate-Tighten checklist.', 'bi-tools', 'Operator completes basic equipment care at shift start.', 'per_shift', 'checklist', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Equipment','equipment','text',1,1),
(@t,'Clean','clean','pass_fail',1,2),
(@t,'Inspect','inspect','pass_fail',1,3),
(@t,'Lubricate','lubricate','pass_fail',1,4),
(@t,'Tighten','tighten','pass_fail',1,5),
(@t,'Notes','notes','text',0,6);

-- 38. NPS SURVEY ENTRY --------------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='voice-of-customer'), 'NPS Survey Entry', 'nps-survey-entry',
 'Net Promoter Score entry linked to a customer.', 'bi-emoji-heart-eyes', 'Record the 0-10 likelihood-to-recommend score and any comments.', 'on_demand', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, min_value, max_value, sort_order) VALUES
(@t,'Customer','customer_name','text',1,NULL,NULL,1),
(@t,'Score (0-10)','score','rating',1,0,10,2),
(@t,'Comments','comments','long_text',0,NULL,NULL,3);

-- 39. BRCGS COMPLIANCE CHECKLIST ------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='audit-compliance'), 'BRCGS Compliance Checklist', 'brcgs-compliance-checklist',
 'BRCGS Global Standard for Food Safety self-assessment checklist.', 'bi-patch-check', 'Assess each BRCGS fundamental area.', 'monthly', 'checklist', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Senior Management Commitment','senior_management','pass_fail',1,1),
(@t,'Food Safety Plan (HACCP)','food_safety_plan','pass_fail',1,2),
(@t,'Site Standards','site_standards','pass_fail',1,3),
(@t,'Product Control','product_control','pass_fail',1,4),
(@t,'Process Control','process_control','pass_fail',1,5),
(@t,'Personnel','personnel','pass_fail',1,6),
(@t,'Notes','notes','long_text',0,7);

-- 40. SQF COMPLIANCE CHECKLIST --------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='audit-compliance'), 'SQF Compliance Checklist', 'sqf-compliance-checklist',
 'Safe Quality Food Program self-assessment checklist.', 'bi-patch-check-fill', 'Assess each SQF code element.', 'monthly', 'checklist', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Food Safety Fundamentals','food_safety_fundamentals','pass_fail',1,1),
(@t,'Food Safety Plan','food_safety_plan','pass_fail',1,2),
(@t,'Food Safety System','food_safety_system','pass_fail',1,3),
(@t,'Site/Product Requirements','site_product_requirements','pass_fail',1,4),
(@t,'Notes','notes','long_text',0,5);

-- 41. ISO 22000 COMPLIANCE CHECKLIST ---------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='audit-compliance'), 'ISO 22000 Compliance Checklist', 'iso22000-compliance-checklist',
 'ISO 22000 Food Safety Management System self-assessment checklist.', 'bi-award', 'Assess each ISO 22000 core requirement.', 'monthly', 'checklist', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Hazard Analysis','hazard_analysis','pass_fail',1,1),
(@t,'Prerequisite Programs (PRP)','prp','pass_fail',1,2),
(@t,'Traceability System','traceability','pass_fail',1,3),
(@t,'Emergency Preparedness','emergency_preparedness','pass_fail',1,4),
(@t,'Notes','notes','long_text',0,5);

-- 42. SHELF-LIFE TESTING -------------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='fmcg-production-floor'), 'Shelf-Life Testing', 'shelf-life-testing',
 'Product shelf-life / expiry assessment under a given storage condition.', 'bi-hourglass-split', 'Record test results and assess whether the declared shelf life still holds.', 'monthly', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, sort_order) VALUES
(@t,'Product','product_name','text',1,1),
(@t,'Batch','batch_number','text',1,2),
(@t,'Test Date','test_date','date',0,3),
(@t,'Storage Condition','storage_condition','dropdown',1,4),
(@t,'Result','result_text','text',0,5),
(@t,'Expiry Assessment','expiry_assessment','dropdown',1,6);
SET @f1 := LAST_INSERT_ID() + 3;
SET @f2 := LAST_INSERT_ID() + 5;
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f1,'Ambient','ambient',1),(@f1,'Refrigerated','refrigerated',2),(@f1,'Frozen','frozen',3),(@f1,'Accelerated','accelerated',4);
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f2,'Pass','pass',1),(@f2,'Extend','extend',2),(@f2,'Fail','fail',3);

-- 43. MOCK RECALL EXERCISE LOG --------------------------------------------------------------------
INSERT INTO tools (category_id, name, slug, description, icon, instructions, frequency, tool_type, status)
VALUES ((SELECT id FROM tool_categories WHERE slug='food-consumer-safety'), 'Mock Recall Exercise', 'mock-recall-exercise',
 'Simulated recall exercise to test traceability speed and effectiveness.', 'bi-arrow-counterclockwise',
 'Trace a batch end-to-end and record how long it took and what percentage of affected product was accounted for.', 'on_demand', 'form', 'active');
SET @t := LAST_INSERT_ID();
INSERT INTO tool_fields (tool_id, label, field_name, field_type, is_required, unit, min_value, max_value, sort_order) VALUES
(@t,'Recall Scenario','recall_scenario','text',1,NULL,NULL,NULL,1),
(@t,'Batch Traced','batch_traced','text',1,NULL,NULL,NULL,2),
(@t,'Time to Trace','time_to_trace','number',1,'min',NULL,NULL,3),
(@t,'Quantity Traced','quantity_traced','percentage',1,'%',0,100,4),
(@t,'Effectiveness','effectiveness','dropdown',1,NULL,NULL,NULL,5);
SET @f := LAST_INSERT_ID() + 4;
INSERT INTO tool_field_options (tool_field_id, option_label, option_value, sort_order) VALUES
(@f,'Pass (>95% traced, target time met)','pass',1),(@f,'Fail','fail',2);

-- ============================================================================
-- PART 4 / 4: SEED DEMO DATA (source: seed_demo.sql) -- OPTIONAL, delete from here down for an empty production install
-- ============================================================================

-- ============================================================================
-- DEMO DATA - realistic dummy dataset for evaluation/demo purposes.
-- Run AFTER schema.sql and tool_library.sql. Safe to skip entirely for a
-- clean/empty installation (do not run this file if you want zero business data).
-- Login: manager@goldenharvest.demo / Manager@123
--        priya.rao@goldenharvest.demo (and other employees) / Employee@123
-- ============================================================================

USE `fmcg_qms`;

-- ============================================================================
-- COMPANY 1: Golden Harvest Foods Ltd (rich demo data)
-- ============================================================================

INSERT INTO companies (name, code, industry, address, city, country, contact_person, email, phone, website,
  employee_limit, department_limit, tool_limit, storage_limit_mb, ai_usage_limit, subscription_plan_id,
  start_date, expiry_date, status)
VALUES ('Golden Harvest Foods Ltd', 'GHF-001', 'Dairy & Snacks Manufacturing', '42 Industrial Estate Road', 'Pune', 'India',
  'Rakesh Mehta', 'contact@goldenharvest.demo', '+91-20-55512300', 'https://goldenharvest.demo',
  75, 15, 60, 5000, 1000, (SELECT id FROM subscription_plans WHERE name='Professional'),
  DATE_SUB(CURDATE(), INTERVAL 8 MONTH), DATE_ADD(CURDATE(), INTERVAL 4 MONTH), 'active');
SET @c1 := LAST_INSERT_ID();

INSERT INTO company_settings (company_id, setting_key, setting_value) VALUES
(@c1, 'submission_deadline', '17:00'),
(@c1, 'reminder_time', '15:00'),
(@c1, 'final_reminder_time', '16:30'),
(@c1, 'quality_target_defect_rate', '3'),
(@c1, 'quality_target_fpy', '95');

INSERT INTO departments (company_id, name, description) VALUES
(@c1, 'Production', 'Manufacturing operations'),
(@c1, 'Quality Assurance', 'Quality systems and compliance'),
(@c1, 'Quality Control', 'In-line and finished goods inspection'),
(@c1, 'Food Safety', 'HACCP, allergen and hygiene control'),
(@c1, 'Laboratory', 'Micro and chemical testing'),
(@c1, 'Warehouse', 'Raw material and finished goods storage'),
(@c1, 'Packaging', 'Packaging line operations'),
(@c1, 'Maintenance', 'Equipment reliability and upkeep');

INSERT INTO shifts (company_id, name, start_time, end_time) VALUES
(@c1, 'Morning', '06:00:00', '14:00:00'),
(@c1, 'Evening', '14:00:00', '22:00:00'),
(@c1, 'Night', '22:00:00', '06:00:00');

INSERT INTO production_lines (company_id, name, code) VALUES
(@c1, 'Dairy Line 1', 'DL1'),
(@c1, 'Snacks Line 2', 'SL2'),
(@c1, 'Packaging Line 3', 'PL3');

INSERT INTO machines (company_id, production_line_id, name, code, status) VALUES
(@c1, (SELECT id FROM production_lines WHERE company_id=@c1 AND code='DL1'), 'Pasteurizer PA-1', 'PA-1', 'active'),
(@c1, (SELECT id FROM production_lines WHERE company_id=@c1 AND code='DL1'), 'Filler FL-1', 'FL-1', 'active'),
(@c1, (SELECT id FROM production_lines WHERE company_id=@c1 AND code='SL2'), 'Fryer FR-2', 'FR-2', 'active'),
(@c1, (SELECT id FROM production_lines WHERE company_id=@c1 AND code='PL3'), 'Checkweigher CW-3', 'CW-3', 'active'),
(@c1, (SELECT id FROM production_lines WHERE company_id=@c1 AND code='PL3'), 'Metal Detector MD-3', 'MD-3', 'active');

-- Manager
INSERT INTO users (company_id, role, employee_code, name, email, phone, department_id, designation, joining_date, username, password, status)
VALUES (@c1, 'manager', 'GHF-MGR-001', 'Anita Sharma', 'manager@goldenharvest.demo', '+91-98220-11223',
  (SELECT id FROM departments WHERE company_id=@c1 AND name='Quality Assurance'), 'Quality Manager',
  DATE_SUB(CURDATE(), INTERVAL 3 YEAR), 'anita.sharma', '$2y$12$uywqf3hC5mYeEPnKgRF3YuU5Uk7mzQ1S1RCweLNlbbST57JWSs5pS', 'active');
SET @mgr1 := LAST_INSERT_ID();

-- Employees (password for all: Employee@123)
INSERT INTO users (company_id, role, employee_code, name, email, phone, department_id, designation, shift_id, joining_date, username, password, status) VALUES
(@c1,'employee','GHF-EMP-001','Priya Rao','priya.rao@goldenharvest.demo','+91-98220-11001',(SELECT id FROM departments WHERE company_id=@c1 AND name='Quality Control'),'QC Inspector',(SELECT id FROM shifts WHERE company_id=@c1 AND name='Morning'),DATE_SUB(CURDATE(), INTERVAL 2 YEAR),'priya.rao','$2y$12$Qdyf3KPIlkfWswdLWvi4D.yTYx/DEZd5nFbP29p.L.trkXBg2p6Rq','active'),
(@c1,'employee','GHF-EMP-002','Vikram Singh','vikram.singh@goldenharvest.demo','+91-98220-11002',(SELECT id FROM departments WHERE company_id=@c1 AND name='Production'),'Line Operator',(SELECT id FROM shifts WHERE company_id=@c1 AND name='Morning'),DATE_SUB(CURDATE(), INTERVAL 18 MONTH),'vikram.singh','$2y$12$Qdyf3KPIlkfWswdLWvi4D.yTYx/DEZd5nFbP29p.L.trkXBg2p6Rq','active'),
(@c1,'employee','GHF-EMP-003','Sunita Patil','sunita.patil@goldenharvest.demo','+91-98220-11003',(SELECT id FROM departments WHERE company_id=@c1 AND name='Food Safety'),'Food Safety Officer',(SELECT id FROM shifts WHERE company_id=@c1 AND name='Morning'),DATE_SUB(CURDATE(), INTERVAL 4 YEAR),'sunita.patil','$2y$12$Qdyf3KPIlkfWswdLWvi4D.yTYx/DEZd5nFbP29p.L.trkXBg2p6Rq','active'),
(@c1,'employee','GHF-EMP-004','Ramesh Kulkarni','ramesh.kulkarni@goldenharvest.demo','+91-98220-11004',(SELECT id FROM departments WHERE company_id=@c1 AND name='Packaging'),'Packaging Operator',(SELECT id FROM shifts WHERE company_id=@c1 AND name='Evening'),DATE_SUB(CURDATE(), INTERVAL 1 YEAR),'ramesh.kulkarni','$2y$12$Qdyf3KPIlkfWswdLWvi4D.yTYx/DEZd5nFbP29p.L.trkXBg2p6Rq','active'),
(@c1,'employee','GHF-EMP-005','Neha Joshi','neha.joshi@goldenharvest.demo','+91-98220-11005',(SELECT id FROM departments WHERE company_id=@c1 AND name='Laboratory'),'Lab Technician',(SELECT id FROM shifts WHERE company_id=@c1 AND name='Morning'),DATE_SUB(CURDATE(), INTERVAL 2 YEAR),'neha.joshi','$2y$12$Qdyf3KPIlkfWswdLWvi4D.yTYx/DEZd5nFbP29p.L.trkXBg2p6Rq','active'),
(@c1,'employee','GHF-EMP-006','Arjun Nair','arjun.nair@goldenharvest.demo','+91-98220-11006',(SELECT id FROM departments WHERE company_id=@c1 AND name='Maintenance'),'Maintenance Technician',(SELECT id FROM shifts WHERE company_id=@c1 AND name='Night'),DATE_SUB(CURDATE(), INTERVAL 3 YEAR),'arjun.nair','$2y$12$Qdyf3KPIlkfWswdLWvi4D.yTYx/DEZd5nFbP29p.L.trkXBg2p6Rq','active'),
(@c1,'employee','GHF-EMP-007','Kavita Deshmukh','kavita.deshmukh@goldenharvest.demo','+91-98220-11007',(SELECT id FROM departments WHERE company_id=@c1 AND name='Warehouse'),'Warehouse Supervisor',(SELECT id FROM shifts WHERE company_id=@c1 AND name='Morning'),DATE_SUB(CURDATE(), INTERVAL 5 YEAR),'kavita.deshmukh','$2y$12$Qdyf3KPIlkfWswdLWvi4D.yTYx/DEZd5nFbP29p.L.trkXBg2p6Rq','active'),
(@c1,'employee','GHF-EMP-008','Suresh Iyer','suresh.iyer@goldenharvest.demo','+91-98220-11008',(SELECT id FROM departments WHERE company_id=@c1 AND name='Production'),'Line Operator',(SELECT id FROM shifts WHERE company_id=@c1 AND name='Night'),DATE_SUB(CURDATE(), INTERVAL 9 MONTH),'suresh.iyer','$2y$12$Qdyf3KPIlkfWswdLWvi4D.yTYx/DEZd5nFbP29p.L.trkXBg2p6Rq','active');

-- Product categories & products
INSERT INTO product_categories (company_id, name) VALUES (@c1,'Dairy'), (@c1,'Snacks'), (@c1,'Beverages');
INSERT INTO products (company_id, name, sku, product_code, category_id, specification, unit, production_line_id, status) VALUES
(@c1,'Fresh Yogurt 500g','SKU-YOG-500','GHF-P001',(SELECT id FROM product_categories WHERE company_id=@c1 AND name='Dairy'),'Fat 3.5%, pH 4.2-4.6','g',(SELECT id FROM production_lines WHERE company_id=@c1 AND code='DL1'),'active'),
(@c1,'Cheese Slices 200g','SKU-CHS-200','GHF-P002',(SELECT id FROM product_categories WHERE company_id=@c1 AND name='Dairy'),'Moisture <45%','g',(SELECT id FROM production_lines WHERE company_id=@c1 AND code='DL1'),'active'),
(@c1,'Potato Chips 150g','SKU-CHP-150','GHF-P003',(SELECT id FROM product_categories WHERE company_id=@c1 AND name='Snacks'),'Moisture <2%, Oil <35%','g',(SELECT id FROM production_lines WHERE company_id=@c1 AND code='SL2'),'active'),
(@c1,'Mixed Fruit Juice 1L','SKU-JUI-1L','GHF-P004',(SELECT id FROM product_categories WHERE company_id=@c1 AND name='Beverages'),'Brix 11-13, pH 3.6-3.9','L',(SELECT id FROM production_lines WHERE company_id=@c1 AND code='SL2'),'active'),
(@c1,'Salted Butter 100g','SKU-BUT-100','GHF-P005',(SELECT id FROM product_categories WHERE company_id=@c1 AND name='Dairy'),'Fat >80%','g',(SELECT id FROM production_lines WHERE company_id=@c1 AND code='DL1'),'active');

-- Suppliers
INSERT INTO suppliers (company_id, name, code, contact_person, email, phone, material_category, status) VALUES
(@c1,'Deccan Dairy Farms','SUP-001','Mahesh Pawar','sales@deccandairy.demo','+91-98111-22001','Raw Milk','active'),
(@c1,'PackMat Industries','SUP-002','Rina Shah','orders@packmat.demo','+91-98111-22002','Packaging Material','active'),
(@c1,'FlavorTech Ingredients','SUP-003','Ajay Kapoor','sales@flavortech.demo','+91-98111-22003','Flavors & Additives','active'),
(@c1,'GreenLeaf Potato Suppliers','SUP-004','Farida Khan','contact@greenleaf.demo','+91-98111-22004','Raw Potato','active');

INSERT INTO supplier_scorecards (company_id, supplier_id, period, quality_score, delivery_score, cost_score, responsiveness_score, compliance_score, overall_score) VALUES
(@c1,(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-001'),DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 2 MONTH),'%Y-%m'),96,94,88,90,97,93.0),
(@c1,(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-001'),DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'%Y-%m'),95,95,89,92,96,93.4),
(@c1,(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-002'),DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'%Y-%m'),90,85,92,80,88,87.0),
(@c1,(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-003'),DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'%Y-%m'),78,70,75,65,72,72.0),
(@c1,(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-004'),DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'%Y-%m'),88,91,85,86,90,88.0);

INSERT INTO supplier_audits (company_id, supplier_id, audit_date, auditor_id, score, max_score, status, findings) VALUES
(@c1,(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-001'),DATE_SUB(CURDATE(), INTERVAL 45 DAY),@mgr1,92,100,'completed','Minor documentation gap in CIP records; overall strong hygiene practices.'),
(@c1,(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-003'),DATE_SUB(CURDATE(), INTERVAL 20 DAY),@mgr1,68,100,'completed','Allergen segregation inadequate; corrective action requested.');

INSERT INTO incoming_inspections (company_id, supplier_id, material_name, batch_number, quantity, sample_size, accept_qty, reject_qty, result, inspector_id, created_at) VALUES
(@c1,(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-001'),'Raw Milk','RM-88231',5000,20,20,0,'accepted',(SELECT id FROM users WHERE email='priya.rao@goldenharvest.demo'), DATE_SUB(NOW(), INTERVAL 6 DAY)),
(@c1,(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-004'),'Raw Potato','RP-44120',3000,32,30,2,'conditional',(SELECT id FROM users WHERE email='priya.rao@goldenharvest.demo'), DATE_SUB(NOW(), INTERVAL 4 DAY)),
(@c1,(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-003'),'Fruit Flavor Concentrate','FC-77812',200,13,10,3,'rejected',(SELECT id FROM users WHERE email='priya.rao@goldenharvest.demo'), DATE_SUB(NOW(), INTERVAL 2 DAY));

INSERT INTO coa_records (company_id, supplier_id, material_name, batch_number, specification, actual_result, result, reviewed_by, created_at) VALUES
(@c1,(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-001'),'Raw Milk','RM-88231','Fat >=3.2%, SNF >=8.3%','Fat 3.6%, SNF 8.5%','pass',@mgr1, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(@c1,(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-003'),'Fruit Flavor Concentrate','FC-77812','Microbial count <100 CFU/g','Microbial count 340 CFU/g','fail',@mgr1, DATE_SUB(NOW(), INTERVAL 2 DAY));

-- Batches
INSERT INTO batches (company_id, batch_number, product_id, production_date, expiry_date, production_line_id, shift_id, supplier_id, quantity_produced, status, created_at) VALUES
(@c1,'B-YOG-0901',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-YOG-500'),DATE_SUB(CURDATE(), INTERVAL 27 DAY),DATE_ADD(CURDATE(), INTERVAL 3 DAY),(SELECT id FROM production_lines WHERE company_id=@c1 AND code='DL1'),(SELECT id FROM shifts WHERE company_id=@c1 AND name='Morning'),(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-001'),12000,'released',DATE_SUB(NOW(), INTERVAL 27 DAY)),
(@c1,'B-YOG-0912',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-YOG-500'),DATE_SUB(CURDATE(), INTERVAL 16 DAY),DATE_ADD(CURDATE(), INTERVAL 14 DAY),(SELECT id FROM production_lines WHERE company_id=@c1 AND code='DL1'),(SELECT id FROM shifts WHERE company_id=@c1 AND name='Morning'),(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-001'),11800,'released',DATE_SUB(NOW(), INTERVAL 16 DAY)),
(@c1,'B-CHS-0630',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-CHS-200'),DATE_SUB(CURDATE(), INTERVAL 21 DAY),DATE_ADD(CURDATE(), INTERVAL 159 DAY),(SELECT id FROM production_lines WHERE company_id=@c1 AND code='DL1'),(SELECT id FROM shifts WHERE company_id=@c1 AND name='Evening'),(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-001'),6000,'released',DATE_SUB(NOW(), INTERVAL 21 DAY)),
(@c1,'B-CHP-1140',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-CHP-150'),DATE_SUB(CURDATE(), INTERVAL 12 DAY),DATE_ADD(CURDATE(), INTERVAL 78 DAY),(SELECT id FROM production_lines WHERE company_id=@c1 AND code='SL2'),(SELECT id FROM shifts WHERE company_id=@c1 AND name='Night'),(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-004'),9000,'released',DATE_SUB(NOW(), INTERVAL 12 DAY)),
(@c1,'B-CHP-1155',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-CHP-150'),DATE_SUB(CURDATE(), INTERVAL 5 DAY),DATE_ADD(CURDATE(), INTERVAL 85 DAY),(SELECT id FROM production_lines WHERE company_id=@c1 AND code='SL2'),(SELECT id FROM shifts WHERE company_id=@c1 AND name='Night'),(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-004'),9200,'hold',DATE_SUB(NOW(), INTERVAL 5 DAY)),
(@c1,'B-JUI-0788',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-JUI-1L'),DATE_SUB(CURDATE(), INTERVAL 9 DAY),DATE_ADD(CURDATE(), INTERVAL 171 DAY),(SELECT id FROM production_lines WHERE company_id=@c1 AND code='SL2'),(SELECT id FROM shifts WHERE company_id=@c1 AND name='Evening'),(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-003'),7000,'released',DATE_SUB(NOW(), INTERVAL 9 DAY)),
(@c1,'B-BUT-0321',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-BUT-100'),DATE_SUB(CURDATE(), INTERVAL 3 DAY),DATE_ADD(CURDATE(), INTERVAL 87 DAY),(SELECT id FROM production_lines WHERE company_id=@c1 AND code='DL1'),(SELECT id FROM shifts WHERE company_id=@c1 AND name='Morning'),(SELECT id FROM suppliers WHERE company_id=@c1 AND code='SUP-001'),4000,'in_production',DATE_SUB(NOW(), INTERVAL 3 DAY));

-- ============================================================================
-- TOOL ASSIGNMENTS (global tools -> employees)
-- ============================================================================
INSERT INTO tool_assignments (company_id, tool_id, user_id, assigned_by, status, assigned_at)
SELECT @c1, t.id, u.id, @mgr1, 'active', DATE_SUB(NOW(), INTERVAL 60 DAY)
FROM tools t JOIN users u ON u.company_id = @c1
WHERE t.company_id IS NULL AND (
  (t.slug='cold-chain-temperature' AND u.email='priya.rao@goldenharvest.demo') OR
  (t.slug='checkweigher-verification' AND u.email='ramesh.kulkarni@goldenharvest.demo') OR
  (t.slug='metal-detection-xray' AND u.email='ramesh.kulkarni@goldenharvest.demo') OR
  (t.slug='gmp-audit-checklist' AND u.email='sunita.patil@goldenharvest.demo') OR
  (t.slug='sensory-evaluation' AND u.email='neha.joshi@goldenharvest.demo') OR
  (t.slug='water-activity-ph' AND u.email='neha.joshi@goldenharvest.demo') OR
  (t.slug='micro-testing' AND u.email='neha.joshi@goldenharvest.demo') OR
  (t.slug='pareto-defect-log' AND u.email='priya.rao@goldenharvest.demo') OR
  (t.slug='5-whys' AND u.email='priya.rao@goldenharvest.demo') OR
  (t.slug='environmental-monitoring' AND u.email='sunita.patil@goldenharvest.demo') OR
  (t.slug='pest-control-ipm' AND u.email='kavita.deshmukh@goldenharvest.demo') OR
  (t.slug='allergen-verification-swab' AND u.email='sunita.patil@goldenharvest.demo') OR
  (t.slug='5s-audit' AND u.email='vikram.singh@goldenharvest.demo') OR
  (t.slug='oee-shift-entry' AND u.email='vikram.singh@goldenharvest.demo') OR
  (t.slug='scrap-rework-entry' AND u.email='vikram.singh@goldenharvest.demo') OR
  (t.slug='kaizen-idea-log' AND u.email='arjun.nair@goldenharvest.demo') OR
  (t.slug='layered-process-audit' AND u.email='sunita.patil@goldenharvest.demo')
);

-- ============================================================================
-- TOOL SUBMISSIONS (Cold Chain temperature - 6 days incl. 1 deviation)
-- ============================================================================
SET @tool_coldchain := (SELECT id FROM tools WHERE slug='cold-chain-temperature');
SET @field_temp := (SELECT id FROM tool_fields WHERE tool_id=@tool_coldchain AND field_name='temperature');
SET @field_loc := (SELECT id FROM tool_fields WHERE tool_id=@tool_coldchain AND field_name='location');
SET @priya := (SELECT id FROM users WHERE email='priya.rao@goldenharvest.demo');
SET @dept_qc := (SELECT id FROM departments WHERE company_id=@c1 AND name='Quality Control');

INSERT INTO tool_submissions (company_id, tool_id, user_id, department_id, status, has_deviation, deviation_severity, submitted_at, created_at) VALUES
(@c1,@tool_coldchain,@priya,@dept_qc,'submitted',0,NULL,DATE_SUB(NOW(), INTERVAL 6 DAY),DATE_SUB(NOW(), INTERVAL 6 DAY)),
(@c1,@tool_coldchain,@priya,@dept_qc,'submitted',0,NULL,DATE_SUB(NOW(), INTERVAL 5 DAY),DATE_SUB(NOW(), INTERVAL 5 DAY)),
(@c1,@tool_coldchain,@priya,@dept_qc,'submitted',1,'critical',DATE_SUB(NOW(), INTERVAL 4 DAY),DATE_SUB(NOW(), INTERVAL 4 DAY)),
(@c1,@tool_coldchain,@priya,@dept_qc,'submitted',0,NULL,DATE_SUB(NOW(), INTERVAL 3 DAY),DATE_SUB(NOW(), INTERVAL 3 DAY)),
(@c1,@tool_coldchain,@priya,@dept_qc,'missed',0,NULL,NULL,DATE_SUB(NOW(), INTERVAL 2 DAY)),
(@c1,@tool_coldchain,@priya,@dept_qc,'submitted',0,NULL,DATE_SUB(NOW(), INTERVAL 1 DAY),DATE_SUB(NOW(), INTERVAL 1 DAY));

INSERT INTO tool_submission_values (submission_id, tool_field_id, value_number, value_text)
SELECT id, @field_temp, v.temp, v.temp FROM tool_submissions
JOIN (SELECT DATE_SUB(NOW(), INTERVAL 6 DAY) dt, 4.8 temp UNION ALL SELECT DATE_SUB(NOW(), INTERVAL 5 DAY),5.1
      UNION ALL SELECT DATE_SUB(NOW(), INTERVAL 4 DAY),12.4 UNION ALL SELECT DATE_SUB(NOW(), INTERVAL 3 DAY),5.6
      UNION ALL SELECT DATE_SUB(NOW(), INTERVAL 1 DAY),4.9) v ON DATE(created_at)=DATE(v.dt)
WHERE tool_id=@tool_coldchain;
INSERT INTO tool_submission_values (submission_id, tool_field_id, value_text)
SELECT id, @field_loc, 'Cold Storage Room 1' FROM tool_submissions WHERE tool_id=@tool_coldchain;

-- Manually mirror what the Threshold/Issue Engine would auto-create for the 12.4C deviation
INSERT INTO quality_issues (company_id, issue_number, source_type, tool_id, department_id, product_id, severity, status, defect_type, description, ai_recommendation, detected_by, created_at)
VALUES (@c1,'QI-2026-0041','tool_submission',@tool_coldchain,@dept_qc,(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-YOG-500'),'critical','investigation','Temperature Reading',
 'Automatic deviation detected on Cold Chain Temperature Monitoring: 12.4C recorded in Cold Storage Room 1, exceeding the critical limit of 10C (spec 2-8C).',
 'Temperature is significantly outside the configured specification. Verify refrigeration unit status, door seals and recent access logs; consider relocating affected stock and re-checking calibration.',
 @priya, DATE_SUB(NOW(), INTERVAL 4 DAY));

-- ============================================================================
-- OEE RECORDS (last 14 days, 2 lines) - explicit values, availability/performance/
-- quality/oee computed the same way calc_oee()/calc_availability() etc. would.
-- ============================================================================
SET @line_dl1 := (SELECT id FROM production_lines WHERE company_id=@c1 AND code='DL1');
SET @line_sl2 := (SELECT id FROM production_lines WHERE company_id=@c1 AND code='SL2');
SET @shift_morning := (SELECT id FROM shifts WHERE company_id=@c1 AND name='Morning');

INSERT INTO oee_records (company_id, production_line_id, shift_id, record_date, planned_minutes, downtime_minutes, ideal_cycle_time, total_count, good_count, availability, performance, quality, oee) VALUES
(@c1,@line_dl1,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 13 DAY),480,35,0.42,940,905,92.71,82.25,96.28,73.44),
(@c1,@line_dl1,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 12 DAY),480,40,0.42,915,880,91.67,81.90,96.17,72.24),
(@c1,@line_dl1,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 11 DAY),480,28,0.42,960,930,94.17,83.20,96.88,75.87),
(@c1,@line_dl1,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 10 DAY),480,50,0.42,890,845,89.58,84.90,94.94,72.19),
(@c1,@line_dl1,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 9 DAY),480,32,0.42,955,922,93.33,83.94,96.55,75.63),
(@c1,@line_dl1,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 8 DAY),480,45,0.42,905,860,90.63,84.75,95.03,71.94),
(@c1,@line_dl1,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 7 DAY),480,38,0.42,930,900,92.08,82.65,96.77,73.61),
(@c1,@line_dl1,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 6 DAY),480,30,0.42,965,935,93.75,83.36,96.89,76.24),
(@c1,@line_dl1,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 5 DAY),480,55,0.42,875,820,88.54,86.32,93.71,71.60),
(@c1,@line_dl1,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 4 DAY),480,42,0.42,920,878,91.25,83.18,95.43,72.44),
(@c1,@line_dl1,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 3 DAY),480,33,0.42,948,918,93.13,84.01,96.84,75.72),
(@c1,@line_dl1,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 2 DAY),480,29,0.42,972,942,93.96,83.55,96.91,76.13),
(@c1,@line_dl1,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 1 DAY),480,36,0.42,935,905,92.50,83.15,96.79,74.44),
(@c1,@line_dl1,@shift_morning,CURDATE(),480,31,0.42,958,930,93.54,83.65,97.08,75.99),
(@c1,@line_sl2,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 13 DAY),480,60,0.30,1040,975,87.50,74.29,93.75,60.94),
(@c1,@line_sl2,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 11 DAY),480,52,0.30,1085,1010,89.17,76.10,93.09,63.14),
(@c1,@line_sl2,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 9 DAY),480,70,0.30,995,915,85.42,72.80,91.96,57.21),
(@c1,@line_sl2,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 7 DAY),480,48,0.30,1102,1040,90.00,76.53,94.37,65.00),
(@c1,@line_sl2,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 5 DAY),480,65,0.30,1015,935,86.46,73.42,92.12,58.49),
(@c1,@line_sl2,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 3 DAY),480,44,0.30,1120,1058,90.83,77.06,94.46,66.09),
(@c1,@line_sl2,@shift_morning,DATE_SUB(CURDATE(), INTERVAL 1 DAY),480,58,0.30,1050,975,87.92,74.65,92.86,60.94);

-- ============================================================================
-- SCRAP & REWORK RECORDS
-- ============================================================================
INSERT INTO scrap_records (company_id, product_id, batch_id, department_id, quantity, reason, cost, record_date) VALUES
(@c1,(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-CHP-150'),(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-CHP-1140'),(SELECT id FROM departments WHERE company_id=@c1 AND name='Production'),120,'Process Deviation',3600,DATE_SUB(CURDATE(), INTERVAL 12 DAY)),
(@c1,(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-YOG-500'),(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-YOG-0912'),(SELECT id FROM departments WHERE company_id=@c1 AND name='Production'),80,'Material Defect',1600,DATE_SUB(CURDATE(), INTERVAL 16 DAY)),
(@c1,(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-CHS-200'),(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-CHS-0630'),(SELECT id FROM departments WHERE company_id=@c1 AND name='Packaging'),45,'Machine Fault',900,DATE_SUB(CURDATE(), INTERVAL 21 DAY));

INSERT INTO rework_records (company_id, product_id, batch_id, department_id, quantity, reason, cost, record_date) VALUES
(@c1,(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-CHP-150'),(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-CHP-1155'),(SELECT id FROM departments WHERE company_id=@c1 AND name='Packaging'),200,'Label Misprint',1200,DATE_SUB(CURDATE(), INTERVAL 5 DAY)),
(@c1,(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-JUI-1L'),(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-JUI-0788'),(SELECT id FROM departments WHERE company_id=@c1 AND name='Packaging'),90,'Seal Defect',750,DATE_SUB(CURDATE(), INTERVAL 9 DAY));

INSERT INTO cost_of_quality (company_id, period, prevention_cost, appraisal_cost, internal_failure_cost, external_failure_cost) VALUES
(@c1, DATE_FORMAT(DATE_SUB(CURDATE(), INTERVAL 1 MONTH),'%Y-%m'), 45000, 62000, 38000, 21000),
(@c1, DATE_FORMAT(CURDATE(),'%Y-%m'), 48000, 60000, 29000, 14000);

-- ============================================================================
-- QUALITY ISSUES (additional manual/varied)
-- ============================================================================
INSERT INTO quality_issues (company_id, issue_number, source_type, department_id, product_id, batch_id, severity, status, defect_type, description, detected_by, assigned_to, created_at, closed_at) VALUES
(@c1,'QI-2026-0032','manual',(SELECT id FROM departments WHERE company_id=@c1 AND name='Packaging'),(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-CHP-150'),(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-CHP-1140'),'medium','closed','Seal Defect','Intermittent weak seal observed on chip packets during visual inspection.',@priya,(SELECT id FROM users WHERE email='ramesh.kulkarni@goldenharvest.demo'),DATE_SUB(NOW(), INTERVAL 11 DAY),DATE_SUB(NOW(), INTERVAL 8 DAY)),
(@c1,'QI-2026-0035','manual',(SELECT id FROM departments WHERE company_id=@c1 AND name='Production'),(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-YOG-500'),(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-YOG-0912'),'high','closed','Underweight','Random weight check found 3 units below lower tolerance.',(SELECT id FROM users WHERE email='ramesh.kulkarni@goldenharvest.demo'),@priya,DATE_SUB(NOW(), INTERVAL 15 DAY),DATE_SUB(NOW(), INTERVAL 10 DAY)),
(@c1,'QI-2026-0038','manual',(SELECT id FROM departments WHERE company_id=@c1 AND name='Food Safety'),(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-JUI-1L'),(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-JUI-0788'),'high','root_cause','Foreign Material','Trace plastic fragment reported by packaging operator during changeover.',(SELECT id FROM users WHERE email='sunita.patil@goldenharvest.demo'),(SELECT id FROM users WHERE email='sunita.patil@goldenharvest.demo'),DATE_SUB(NOW(), INTERVAL 9 DAY),NULL),
(@c1,'QI-2026-0044','manual',(SELECT id FROM departments WHERE company_id=@c1 AND name='Quality Control'),(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-CHP-150'),(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-CHP-1155'),'medium','containment','Overweight','Checkweigher trend showing consistent overfill on Line 2.',@priya,(SELECT id FROM users WHERE email='vikram.singh@goldenharvest.demo'),DATE_SUB(NOW(), INTERVAL 3 DAY),NULL),
(@c1,'QI-2026-0046','manual',(SELECT id FROM departments WHERE company_id=@c1 AND name='Laboratory'),(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-CHS-200'),(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-CHS-0630'),'observation','detected','Texture Issue','Slight texture variation noted in routine sensory panel.',(SELECT id FROM users WHERE email='neha.joshi@goldenharvest.demo'),NULL,DATE_SUB(NOW(), INTERVAL 1 DAY),NULL);

-- ============================================================================
-- NCR
-- ============================================================================
INSERT INTO ncr (company_id, ncr_number, product_id, batch_id, department_id, process, severity, description, containment, root_cause, corrective_action, preventive_action, responsible_person, due_date, status, created_at, closed_at) VALUES
(@c1,'NCR-2026-0011',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-YOG-500'),(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-YOG-0912'),(SELECT id FROM departments WHERE company_id=@c1 AND name='Production'),'Filling','high','3 units found below minimum fill weight during in-line check.','Segregated affected pallet; 100% weight re-check performed.','Filler valve calibration drift.','Recalibrated filler valve; re-verified with 30-unit sample.','Added filler calibration check to shift start-up checklist.',@priya,DATE_SUB(CURDATE(), INTERVAL 10 DAY),'closed',DATE_SUB(NOW(), INTERVAL 15 DAY),DATE_SUB(NOW(), INTERVAL 10 DAY)),
(@c1,'NCR-2026-0014',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-JUI-1L'),(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-JUI-0788'),(SELECT id FROM departments WHERE company_id=@c1 AND name='Food Safety'),'Packaging Changeover','high','Foreign material (plastic fragment) reported by operator.','Batch placed on hold pending investigation.','Under investigation - suspected damaged conveyor guide.',NULL,NULL,(SELECT id FROM users WHERE email='sunita.patil@goldenharvest.demo'),DATE_ADD(CURDATE(), INTERVAL 2 DAY),'root_cause',DATE_SUB(NOW(), INTERVAL 9 DAY),NULL),
(@c1,'NCR-2026-0016',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-CHP-150'),(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-CHP-1155'),(SELECT id FROM departments WHERE company_id=@c1 AND name='Quality Control'),'Checkweighing','medium','Consistent overfill trend beyond target+tolerance on Line 2.','Increased sampling frequency to every 30 minutes.',NULL,NULL,NULL,(SELECT id FROM users WHERE email='vikram.singh@goldenharvest.demo'),DATE_ADD(CURDATE(), INTERVAL 5 DAY),'containment',DATE_SUB(NOW(), INTERVAL 3 DAY),NULL);

-- ============================================================================
-- CAPA (incl. overdue)
-- ============================================================================
INSERT INTO capa (company_id, capa_number, source_type, problem_statement, root_cause, correction, corrective_action, preventive_action, responsible_person, due_date, status, created_at, closed_at) VALUES
(@c1,'CAPA-2026-0009','ncr','Filler valve calibration drift caused underweight units on Dairy Line 1.','Calibration interval too long relative to valve wear characteristics.','Recalibrated filler valve immediately.','Reduced calibration interval from monthly to bi-weekly.','Added automated calibration reminder in maintenance schedule.',(SELECT id FROM users WHERE email='arjun.nair@goldenharvest.demo'),DATE_SUB(CURDATE(), INTERVAL 8 DAY),'closed',DATE_SUB(NOW(), INTERVAL 14 DAY),DATE_SUB(NOW(), INTERVAL 8 DAY)),
(@c1,'CAPA-2026-0012','ncr','Foreign material contamination risk from damaged conveyor guide.',NULL,'Batch quarantined pending root cause confirmation.',NULL,NULL,(SELECT id FROM users WHERE email='arjun.nair@goldenharvest.demo'),DATE_SUB(CURDATE(), INTERVAL 1 DAY),'in_progress',DATE_SUB(NOW(), INTERVAL 8 DAY),NULL),
(@c1,'CAPA-2026-0013','issue','Recurring overweight trend on Checkweigher CW-3.',NULL,NULL,NULL,NULL,(SELECT id FROM users WHERE email='vikram.singh@goldenharvest.demo'),DATE_SUB(CURDATE(), INTERVAL 2 DAY),'open',DATE_SUB(NOW(), INTERVAL 3 DAY),NULL),
(@c1,'CAPA-2026-0015','audit','Supplier FlavorTech allergen segregation inadequate.','Shared storage area for allergen and non-allergen additives.','Immediate physical segregation implemented.','Dedicated allergen storage cage installed and signed off.','Added quarterly allergen segregation check to supplier audit checklist.',(SELECT id FROM users WHERE email='sunita.patil@goldenharvest.demo'),DATE_ADD(CURDATE(), INTERVAL 10 DAY),'pending_verification',DATE_SUB(NOW(), INTERVAL 18 DAY),NULL);

INSERT INTO capa_actions (capa_id, company_id, action_text, responsible_user, due_date, status, created_by, created_at) VALUES
((SELECT id FROM capa WHERE company_id=@c1 AND capa_number='CAPA-2026-0012'),@c1,'Inspect and replace damaged conveyor guide on Packaging Line 3.',(SELECT id FROM users WHERE email='arjun.nair@goldenharvest.demo'),DATE_SUB(CURDATE(), INTERVAL 1 DAY),'in_progress',@mgr1,DATE_SUB(NOW(), INTERVAL 7 DAY)),
((SELECT id FROM capa WHERE company_id=@c1 AND capa_number='CAPA-2026-0013'),@c1,'Recalibrate Checkweigher CW-3 and verify with 50-unit sample.',(SELECT id FROM users WHERE email='vikram.singh@goldenharvest.demo'),DATE_SUB(CURDATE(), INTERVAL 2 DAY),'pending',@mgr1,DATE_SUB(NOW(), INTERVAL 3 DAY));

-- ============================================================================
-- AUDITS
-- ============================================================================
INSERT INTO audits (company_id, audit_type, title, department_id, auditor_id, score, max_score, status, scheduled_date, completed_date, created_at) VALUES
(@c1,'gmp','Monthly GMP Audit - Production Floor',(SELECT id FROM departments WHERE company_id=@c1 AND name='Production'),@mgr1,88,100,'completed',DATE_SUB(CURDATE(), INTERVAL 20 DAY),DATE_SUB(CURDATE(), INTERVAL 20 DAY),DATE_SUB(NOW(), INTERVAL 20 DAY)),
(@c1,'iso9001','ISO 9001 Internal Audit Q3',(SELECT id FROM departments WHERE company_id=@c1 AND name='Quality Assurance'),@mgr1,91,100,'completed',DATE_SUB(CURDATE(), INTERVAL 35 DAY),DATE_SUB(CURDATE(), INTERVAL 35 DAY),DATE_SUB(NOW(), INTERVAL 35 DAY)),
(@c1,'layered_process','Layered Process Audit - Packaging Line 3',(SELECT id FROM departments WHERE company_id=@c1 AND name='Packaging'),(SELECT id FROM users WHERE email='sunita.patil@goldenharvest.demo'),76,100,'completed',DATE_SUB(CURDATE(), INTERVAL 6 DAY),DATE_SUB(CURDATE(), INTERVAL 6 DAY),DATE_SUB(NOW(), INTERVAL 6 DAY)),
(@c1,'internal','Internal Quality Audit - Warehouse',(SELECT id FROM departments WHERE company_id=@c1 AND name='Warehouse'),@mgr1,NULL,100,'scheduled',DATE_ADD(CURDATE(), INTERVAL 5 DAY),NULL,DATE_SUB(NOW(), INTERVAL 2 DAY));

INSERT INTO audit_findings (audit_id, company_id, finding_text, severity, corrective_action, status, created_at) VALUES
((SELECT id FROM audits WHERE company_id=@c1 AND title='Monthly GMP Audit - Production Floor'),@c1,'Hairnet compliance at 92% - two operators found without proper hairnet coverage.','medium','Retraining conducted; spot-checks increased.','closed',DATE_SUB(NOW(), INTERVAL 20 DAY)),
((SELECT id FROM audits WHERE company_id=@c1 AND title='Layered Process Audit - Packaging Line 3'),@c1,'Changeover cleaning verification record incomplete for 2 of 5 changeovers reviewed.','high','CAPA-2026-0015 raised for allergen segregation and changeover verification.','open',DATE_SUB(NOW(), INTERVAL 6 DAY));

-- ============================================================================
-- FMEA
-- ============================================================================
INSERT INTO fmea (company_id, title, fmea_type, product_id, department_id, created_by, status, created_at)
VALUES (@c1,'Process FMEA - Yogurt Filling Line','process',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-YOG-500'),(SELECT id FROM departments WHERE company_id=@c1 AND name='Production'),@mgr1,'active',DATE_SUB(NOW(), INTERVAL 40 DAY));
SET @fmea1 := LAST_INSERT_ID();
INSERT INTO fmea_items (fmea_id, process_step, failure_mode, effect, cause, current_controls, severity, occurrence, detection, rpn, action_recommended, responsible_person, target_date, status) VALUES
(@fmea1,'Filling','Underfill','Customer complaint, regulatory non-compliance','Valve calibration drift','Weekly calibration check',8,4,3,96,'Increase calibration frequency to bi-weekly',(SELECT id FROM users WHERE email='arjun.nair@goldenharvest.demo'),DATE_ADD(CURDATE(), INTERVAL 14 DAY),'in_progress'),
(@fmea1,'Sealing','Weak seal','Product leakage, shelf-life reduction','Seal bar temperature variance','Visual seal check per batch',7,5,4,140,'Install continuous seal temperature monitoring',(SELECT id FROM users WHERE email='arjun.nair@goldenharvest.demo'),DATE_ADD(CURDATE(), INTERVAL 30 DAY),'open'),
(@fmea1,'Cold Storage','Temperature excursion','Microbial growth risk','Door left open / refrigeration fault','Twice-daily temperature log',9,3,2,54,'Install continuous temperature data logger with alarm',(SELECT id FROM users WHERE email='priya.rao@goldenharvest.demo'),DATE_ADD(CURDATE(), INTERVAL 21 DAY),'open');

-- ============================================================================
-- HACCP
-- ============================================================================
INSERT INTO haccp_plans (company_id, product_id, title, team, scope, status, created_at)
VALUES (@c1,(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-YOG-500'),'HACCP Plan - Fresh Yogurt 500g','A. Sharma, S. Patil, N. Joshi','Raw milk receipt through finished goods dispatch','active',DATE_SUB(NOW(), INTERVAL 90 DAY));
SET @haccp1 := LAST_INSERT_ID();
INSERT INTO haccp_hazards (haccp_plan_id, process_step, hazard_type, hazard_description, severity, likelihood, risk_level, is_ccp, control_measure, critical_limit) VALUES
(@haccp1,'Pasteurization','biological','Survival of pathogenic bacteria',9,3,'critical',1,'Time-temperature controlled pasteurization','85C for 30 min'),
(@haccp1,'Cold Storage','biological','Microbial growth due to temperature abuse',8,4,'high',1,'Continuous refrigeration monitoring','2-8C'),
(@haccp1,'Metal Detection','physical','Metal fragment contamination',7,2,'medium',1,'100% metal detection before packing','Fe 2.0mm / Non-Fe 2.5mm / SS 3.0mm');

INSERT INTO ccp_monitoring (company_id, haccp_hazard_id, ccp_name, critical_limit, actual_reading, unit, reading_time, operator_id, result, corrective_action, batch_id, created_at) VALUES
(@c1,(SELECT id FROM haccp_hazards WHERE haccp_plan_id=@haccp1 AND process_step='Pasteurization'),'Pasteurization Temp/Time','85C / 30min','86.2C / 31min','C/min',DATE_SUB(NOW(), INTERVAL 27 DAY),(SELECT id FROM users WHERE email='vikram.singh@goldenharvest.demo'),'pass',NULL,(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-YOG-0901'),DATE_SUB(NOW(), INTERVAL 27 DAY)),
(@c1,(SELECT id FROM haccp_hazards WHERE haccp_plan_id=@haccp1 AND process_step='Cold Storage'),'Cold Storage Temp','2-8C','12.4C','C',DATE_SUB(NOW(), INTERVAL 4 DAY),@priya,'fail','Product relocated to backup chiller; refrigeration unit inspected and repaired.',(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-YOG-0912'),DATE_SUB(NOW(), INTERVAL 4 DAY)),
(@c1,(SELECT id FROM haccp_hazards WHERE haccp_plan_id=@haccp1 AND process_step='Metal Detection'),'Metal Detection','Fe 2.0/NFe 2.5/SS 3.0mm','All test pieces detected','mm',DATE_SUB(NOW(), INTERVAL 1 DAY),(SELECT id FROM users WHERE email='ramesh.kulkarni@goldenharvest.demo'),'pass',NULL,(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-YOG-0912'),DATE_SUB(NOW(), INTERVAL 1 DAY));

INSERT INTO allergen_controls (company_id, allergen, product_id, area, risk_level, cleaning_procedure, verification_result, verified_at) VALUES
(@c1,'Milk',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-JUI-1L'),'Snacks Line 2 shared equipment','medium','Full CIP wash + allergen swab test','Pass - below detection limit',DATE_SUB(NOW(), INTERVAL 9 DAY)),
(@c1,'Peanut',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-CHP-150'),'Snacks Line 2 seasoning station','high','Dry clean + full wash before allergen-free run','Pass',DATE_SUB(NOW(), INTERVAL 12 DAY));

-- ============================================================================
-- CUSTOMER COMPLAINTS
-- ============================================================================
INSERT INTO customer_complaints (company_id, complaint_number, customer_name, product_id, batch_id, complaint_type, severity, description, investigation, root_cause, action_taken, status, nps_score, region, created_at, closed_at) VALUES
(@c1,'CMP-2026-021','Modern Retail Mart',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-CHP-150'),(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-CHP-1140'),'Packaging',	'medium','Packet seal not intact upon delivery.','Reviewed CCTV and seal bar temperature logs for the batch.','Seal bar temperature marginally below target during changeover window.','Adjusted seal bar temperature profile.','closed',7,'West',DATE_SUB(NOW(), INTERVAL 18 DAY),DATE_SUB(NOW(), INTERVAL 12 DAY)),
(@c1,'CMP-2026-024','QuickMart Superstores',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-YOG-500'),(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-YOG-0912'),'Quality',	'high','Sour taste reported by end consumer.','Investigating cold chain logs for the batch.','Suspected correlation with cold storage temperature excursion.',NULL,'investigation',3,'West',DATE_SUB(NOW(), INTERVAL 5 DAY),NULL),
(@c1,'CMP-2026-026','FreshBasket Retail',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-JUI-1L'),(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-JUI-0788'),'Foreign Material',	'critical','Consumer reported small plastic fragment in product.','Linked to NCR-2026-0014 investigation.',NULL,NULL,'open',2,'North',DATE_SUB(NOW(), INTERVAL 4 DAY),NULL),
(@c1,'CMP-2026-018','Value Grocers',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-CHS-200'),(SELECT id FROM batches WHERE company_id=@c1 AND batch_number='B-CHS-0630'),'Packaging',	'low','Label slightly misaligned.','Minor cosmetic issue, no food safety impact.','Label applicator alignment drift.','Applicator realigned and verified.','closed',8,'South',DATE_SUB(NOW(), INTERVAL 25 DAY),DATE_SUB(NOW(), INTERVAL 22 DAY)),
(@c1,'CMP-2026-028','Metro Wholesale',(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-BUT-100'),NULL,'Quality',	'low','Texture slightly softer than expected.','Under review.',NULL,NULL,'open',6,'East',DATE_SUB(NOW(), INTERVAL 2 DAY),NULL);

-- ============================================================================
-- SPC / CAPABILITY
-- ============================================================================
INSERT INTO spc_records (company_id, product_id, department_id, chart_type, parameter_name, subgroup_size, usl, lsl, target, created_by, created_at)
VALUES (@c1,(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-CHP-150'),(SELECT id FROM departments WHERE company_id=@c1 AND name='Quality Control'),'xbar_r','Pack Weight (g)',5,158,142,150,@priya,DATE_SUB(NOW(), INTERVAL 20 DAY));
SET @spc1 := LAST_INSERT_ID();
INSERT INTO control_chart_data (spc_record_id, subgroup_no, value1, value2, value3, value4, value5, mean_value, range_value, recorded_at) VALUES
(@spc1,1,149.2,150.1,148.9,150.5,149.8,149.70,1.6,DATE_SUB(NOW(), INTERVAL 20 DAY)),
(@spc1,2,150.3,149.7,151.0,150.2,149.5,150.14,1.5,DATE_SUB(NOW(), INTERVAL 18 DAY)),
(@spc1,3,148.8,149.9,150.4,149.1,150.0,149.64,1.6,DATE_SUB(NOW(), INTERVAL 16 DAY)),
(@spc1,4,151.5,152.0,150.8,151.2,151.9,151.48,1.2,DATE_SUB(NOW(), INTERVAL 14 DAY)),
(@spc1,5,149.5,150.0,149.8,150.3,149.9,149.90,0.8,DATE_SUB(NOW(), INTERVAL 12 DAY)),
(@spc1,6,150.1,149.6,150.9,150.2,149.7,150.10,1.3,DATE_SUB(NOW(), INTERVAL 10 DAY)),
(@spc1,7,156.2,155.8,157.1,156.5,156.0,156.32,1.3,DATE_SUB(NOW(), INTERVAL 5 DAY)),
(@spc1,8,150.0,149.8,150.5,150.1,149.9,150.06,0.7,DATE_SUB(NOW(), INTERVAL 2 DAY));

INSERT INTO capability_studies (company_id, spc_record_id, product_id, parameter_name, usl, lsl, mean_value, std_dev, sample_size, cp, cpk, pp, ppk, created_at)
VALUES (@c1,@spc1,(SELECT id FROM products WHERE company_id=@c1 AND sku='SKU-CHP-150'),'Pack Weight (g)',158,142,150.4,1.85,40,1.44,1.32,1.30,1.19,DATE_SUB(NOW(), INTERVAL 2 DAY));

INSERT INTO gauge_rr (company_id, title, part_count, operator_count, trial_count, repeatability, reproducibility, grr_percent, study_variation_percent, distinct_categories, created_at)
VALUES (@c1,'Checkweigher CW-3 Gauge R&R',10,3,2,0.42,0.18,12.5,18.3,7,DATE_SUB(NOW(), INTERVAL 30 DAY));

-- ============================================================================
-- LEAN / VISUAL MANAGEMENT
-- ============================================================================
INSERT INTO kaizen_events (company_id, title, problem, idea, team, current_state, improvement, result, savings, status, created_by, created_at) VALUES
(@c1,'Reduce Changeover Time on Line 2','Changeover between flavors takes 45 minutes, limiting line utilization.','Pre-stage tooling and use quick-release clamps (SMED principles).','Vikram Singh, Arjun Nair','45 min average changeover','Reduced to 26 min average','42% changeover time reduction, +65 min/day capacity',185000,'completed',@mgr1,DATE_SUB(NOW(), INTERVAL 40 DAY)),
(@c1,'Reduce Cold Storage Door Open Time','Frequent door openings contributing to temperature fluctuation risk.','Install strip curtains and staging area for outgoing pallets.','Priya Rao, Kavita Deshmukh',NULL,NULL,NULL,NULL,'in_progress',@mgr1,DATE_SUB(NOW(), INTERVAL 6 DAY));

INSERT INTO five_s_audits (company_id, area, sort_score, set_in_order_score, shine_score, standardize_score, sustain_score, total_score, auditor_id, audit_date, created_at) VALUES
(@c1,'Production Floor - Dairy Line 1',4,4,3,4,3,72.0,(SELECT id FROM users WHERE email='vikram.singh@goldenharvest.demo'),DATE_SUB(CURDATE(), INTERVAL 14 DAY),DATE_SUB(NOW(), INTERVAL 14 DAY)),
(@c1,'Packaging Line 3',3,3,4,3,3,64.0,(SELECT id FROM users WHERE email='vikram.singh@goldenharvest.demo'),DATE_SUB(CURDATE(), INTERVAL 7 DAY),DATE_SUB(NOW(), INTERVAL 7 DAY));

INSERT INTO gemba_walks (company_id, area, observer_id, finding, issue_id, action, responsible_person, due_date, status, created_at) VALUES
(@c1,'Packaging Line 3',@mgr1,'Observed inconsistent glove changing frequency between changeovers.',NULL,'Reinforce glove-change SOP at shift briefing',(SELECT id FROM users WHERE email='ramesh.kulkarni@goldenharvest.demo'),DATE_ADD(CURDATE(), INTERVAL 3 DAY),'open',DATE_SUB(NOW(), INTERVAL 6 DAY));

INSERT INTO andon_events (company_id, production_line_id, status, event_type, description, raised_by, resolved_by, created_at, resolved_at) VALUES
(@c1,(SELECT id FROM production_lines WHERE company_id=@c1 AND code='SL2'),'green','line_stop','Scheduled changeover',(SELECT id FROM users WHERE email='vikram.singh@goldenharvest.demo'),(SELECT id FROM users WHERE email='vikram.singh@goldenharvest.demo'),DATE_SUB(NOW(), INTERVAL 2 DAY),DATE_SUB(NOW(), INTERVAL 2 DAY)),
(@c1,(SELECT id FROM production_lines WHERE company_id=@c1 AND code='DL1'),'red','quality_alert','Cold storage temperature excursion',@priya,@priya,DATE_SUB(NOW(), INTERVAL 4 DAY),DATE_SUB(NOW(), INTERVAL 4 DAY)),
(@c1,(SELECT id FROM production_lines WHERE company_id=@c1 AND code='PL3'),'yellow','maintenance_alert','Checkweigher drift under investigation',(SELECT id FROM users WHERE email='vikram.singh@goldenharvest.demo'),NULL,DATE_SUB(NOW(), INTERVAL 3 DAY),NULL);

INSERT INTO preventive_maintenance (company_id, machine_id, maintenance_type, frequency, due_date, responsible_person, status) VALUES
(@c1,(SELECT id FROM machines WHERE company_id=@c1 AND code='PA-1'),'Calibration','Bi-weekly',DATE_ADD(CURDATE(), INTERVAL 4 DAY),(SELECT id FROM users WHERE email='arjun.nair@goldenharvest.demo'),'scheduled'),
(@c1,(SELECT id FROM machines WHERE company_id=@c1 AND code='CW-3'),'Calibration','Weekly',DATE_SUB(CURDATE(), INTERVAL 1 DAY),(SELECT id FROM users WHERE email='arjun.nair@goldenharvest.demo'),'overdue'),
(@c1,(SELECT id FROM machines WHERE company_id=@c1 AND code='MD-3'),'Sensitivity Test','Daily',CURDATE(),(SELECT id FROM users WHERE email='ramesh.kulkarni@goldenharvest.demo'),'scheduled');

-- ============================================================================
-- NOTIFICATIONS / AI LOGS / ACTIVITY
-- ============================================================================
INSERT INTO notifications (company_id, user_id, type, title, message, link, severity, is_read, created_at) VALUES
(@c1,@mgr1,'quality_issue','Critical Quality Issue Detected','QI-2026-0041 - Cold Chain Temperature Monitoring: Temperature Reading out of specification.','manager/issue-view',	'danger',0,DATE_SUB(NOW(), INTERVAL 4 DAY)),
(@c1,@mgr1,'capa','CAPA Overdue','CAPA-2026-0013 is overdue.','manager/capa-view',	'warning',0,DATE_SUB(NOW(), INTERVAL 1 DAY)),
(@c1,@mgr1,'complaint','New Critical Complaint','CMP-2026-026 - Foreign material reported.','manager/complaints',	'danger',0,DATE_SUB(NOW(), INTERVAL 4 DAY)),
(@c1,@priya,'action_assigned','Issue Assigned To You','QI-2026-0044 has been assigned to you.','employee/my-issues',	'info',1,DATE_SUB(NOW(), INTERVAL 3 DAY));

INSERT INTO ai_logs (company_id, user_id, feature, prompt, response, tokens_used, created_at) VALUES
(@c1,@priya,'form_assistance','Cold Chain Temperature Monitoring reading 12.4C','Temperature is significantly outside the configured specification (2-8C). Verify refrigeration unit status and door seals before continuing production.',0,DATE_SUB(NOW(), INTERVAL 4 DAY)),
(@c1,@mgr1,'chat','Which department has the highest defect rate?','Production has the highest number of recorded issues this period, driven mainly by weight-related deviations on the filling line.',0,DATE_SUB(NOW(), INTERVAL 1 DAY));

INSERT INTO activity_logs (company_id, user_id, action, module, record_id, description, ip_address, created_at) VALUES
(@c1,@mgr1,'login','admin',NULL,'Manager logged in','203.0.113.24',DATE_SUB(NOW(), INTERVAL 1 DAY)),
(@c1,@priya,'create','quality_issue',NULL,'Auto-created issue QI-2026-0041 (severity: critical)','203.0.113.55',DATE_SUB(NOW(), INTERVAL 4 DAY)),
(@c1,@mgr1,'create','capa',NULL,'Created CAPA-2026-0015','203.0.113.24',DATE_SUB(NOW(), INTERVAL 18 DAY));

-- ============================================================================
-- COMPANY 2: Everfresh Beverages Inc (lightweight, proves multi-tenant isolation)
-- ============================================================================
INSERT INTO companies (name, code, industry, address, city, country, contact_person, email, phone,
  employee_limit, department_limit, tool_limit, storage_limit_mb, ai_usage_limit, subscription_plan_id,
  start_date, expiry_date, status)
VALUES ('Everfresh Beverages Inc', 'EFB-002', 'Beverage Manufacturing', '18 Riverside Business Park', 'Austin', 'USA',
  'Laura Bennett', 'contact@everfresh.demo', '+1-512-555-0142',
  15, 5, 15, 500, 100, (SELECT id FROM subscription_plans WHERE name='Starter'),
  DATE_SUB(CURDATE(), INTERVAL 2 MONTH), DATE_ADD(CURDATE(), INTERVAL 10 MONTH), 'active');
SET @c2 := LAST_INSERT_ID();

INSERT INTO departments (company_id, name) VALUES (@c2,'Production'), (@c2,'Quality Assurance'), (@c2,'Warehouse');
INSERT INTO shifts (company_id, name, start_time, end_time) VALUES (@c2,'Day','08:00:00','16:00:00');
INSERT INTO production_lines (company_id, name, code) VALUES (@c2,'Bottling Line 1','BL1');

INSERT INTO users (company_id, role, employee_code, name, email, department_id, designation, joining_date, username, password, status)
VALUES (@c2,'manager','EFB-MGR-001','Laura Bennett','manager@everfresh.demo',(SELECT id FROM departments WHERE company_id=@c2 AND name='Quality Assurance'),'Quality Manager',DATE_SUB(CURDATE(), INTERVAL 1 YEAR),'laura.bennett','$2y$12$uywqf3hC5mYeEPnKgRF3YuU5Uk7mzQ1S1RCweLNlbbST57JWSs5pS','active');
SET @mgr2 := LAST_INSERT_ID();

INSERT INTO users (company_id, role, employee_code, name, email, department_id, designation, shift_id, joining_date, username, password, status) VALUES
(@c2,'employee','EFB-EMP-001','Carlos Mendez','carlos.mendez@everfresh.demo',(SELECT id FROM departments WHERE company_id=@c2 AND name='Production'),'Line Operator',(SELECT id FROM shifts WHERE company_id=@c2 AND name='Day'),DATE_SUB(CURDATE(), INTERVAL 8 MONTH),'carlos.mendez','$2y$12$Qdyf3KPIlkfWswdLWvi4D.yTYx/DEZd5nFbP29p.L.trkXBg2p6Rq','active'),
(@c2,'employee','EFB-EMP-002','Emily Zhao','emily.zhao@everfresh.demo',(SELECT id FROM departments WHERE company_id=@c2 AND name='Quality Assurance'),'QA Analyst',(SELECT id FROM shifts WHERE company_id=@c2 AND name='Day'),DATE_SUB(CURDATE(), INTERVAL 5 MONTH),'emily.zhao','$2y$12$Qdyf3KPIlkfWswdLWvi4D.yTYx/DEZd5nFbP29p.L.trkXBg2p6Rq','active');

INSERT INTO products (company_id, name, sku, product_code, unit, production_line_id, status) VALUES
(@c2,'Sparkling Lemon Water 500ml','SKU-SLW-500','EFB-P001','ml',(SELECT id FROM production_lines WHERE company_id=@c2 AND code='BL1'),'active');

INSERT INTO batches (company_id, batch_number, product_id, production_date, expiry_date, production_line_id, quantity_produced, status) VALUES
(@c2,'B-SLW-2201',(SELECT id FROM products WHERE company_id=@c2 AND sku='SKU-SLW-500'),DATE_SUB(CURDATE(), INTERVAL 5 DAY),DATE_ADD(CURDATE(), INTERVAL 175 DAY),(SELECT id FROM production_lines WHERE company_id=@c2 AND code='BL1'),8000,'released');

INSERT INTO suppliers (company_id, name, code, material_category, status) VALUES (@c2,'Texas Spring Water Co','SUP-201','Water Source','active');

INSERT INTO quality_issues (company_id, issue_number, source_type, department_id, product_id, severity, status, defect_type, description, detected_by, created_at) VALUES
(@c2,'QI-2026-0002','manual',(SELECT id FROM departments WHERE company_id=@c2 AND name='Quality Assurance'),(SELECT id FROM products WHERE company_id=@c2 AND sku='SKU-SLW-500'),'low','closed','Carbonation','Slightly low carbonation on one sample.',@mgr2,DATE_SUB(NOW(), INTERVAL 10 DAY));

INSERT INTO notifications (company_id, user_id, type, title, message, severity, is_read, created_at) VALUES
(@c2,@mgr2,'quality_issue','Quality Issue Logged','QI-2026-0002 recorded.','info',1,DATE_SUB(NOW(), INTERVAL 10 DAY));
