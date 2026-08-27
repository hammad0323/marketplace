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
