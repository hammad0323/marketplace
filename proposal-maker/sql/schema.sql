-- ============================================================
--  Proposal Maker — TABLES ONLY (no data)
--  The installer runs: schema.sql → starter.sql → demo.sql (optional)
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `users` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `email` VARCHAR(190) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin','editor') NOT NULL DEFAULT 'admin',
  `status` TINYINT(1) NOT NULL DEFAULT 1,
  `last_login` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `company_settings` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `company_name` VARCHAR(190) NOT NULL DEFAULT 'Your Company',
  `logo` VARCHAR(255) NULL,
  `logo_light` VARCHAR(255) NULL,
  `tagline` VARCHAR(255) NULL,
  `phone` VARCHAR(60) NULL,
  `whatsapp` VARCHAR(60) NULL,
  `email` VARCHAR(190) NULL,
  `website` VARCHAR(190) NULL,
  `address` VARCHAR(255) NULL,
  `city` VARCHAR(120) NULL,
  `country` VARCHAR(120) NULL,
  `postal_code` VARCHAR(30) NULL,
  `facebook` VARCHAR(255) NULL,
  `instagram` VARCHAR(255) NULL,
  `linkedin` VARCHAR(255) NULL,
  `youtube` VARCHAR(255) NULL,
  `twitter` VARCHAR(255) NULL,
  `primary_color` VARCHAR(20) NOT NULL DEFAULT '#015957',
  `secondary_color` VARCHAR(20) NOT NULL DEFAULT '#0f172a',
  `accent_color` VARCHAR(20) NOT NULL DEFAULT '#f59e0b',
  `heading_color` VARCHAR(20) NOT NULL DEFAULT '#0f172a',
  `text_color` VARCHAR(20) NOT NULL DEFAULT '#374151',
  `background_color` VARCHAR(20) NOT NULL DEFAULT '#ffffff',
  `button_color` VARCHAR(20) NOT NULL DEFAULT '#015957',
  `default_font` VARCHAR(60) NOT NULL DEFAULT 'Inter',
  `heading_font` VARCHAR(60) NOT NULL DEFAULT 'Montserrat',
  `default_template` INT NOT NULL DEFAULT 1,
  `default_currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
  `proposal_prefix` VARCHAR(20) NOT NULL DEFAULT 'PROP',
  `default_validity_days` INT NOT NULL DEFAULT 30,
  `smtp_enabled` TINYINT(1) NOT NULL DEFAULT 0,
  `smtp_host` VARCHAR(190) NULL,
  `smtp_port` INT NULL DEFAULT 587,
  `smtp_user` VARCHAR(190) NULL,
  `smtp_pass` VARCHAR(255) NULL,
  `smtp_secure` VARCHAR(10) NULL DEFAULT 'tls',
  `smtp_from_email` VARCHAR(190) NULL,
  `smtp_from_name` VARCHAR(190) NULL,
  `email_subject` VARCHAR(255) NULL,
  `email_body` TEXT NULL,
  `notify_email` VARCHAR(190) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `brand_presets` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(120) NOT NULL,
  `colors` TEXT NOT NULL,
  `font_heading` VARCHAR(60) NULL,
  `font_body` VARCHAR(60) NULL,
  `is_default` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `service_templates` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(190) NOT NULL,
  `slug` VARCHAR(190) NOT NULL,
  `service` VARCHAR(120) NULL,
  `description` VARCHAR(255) NULL,
  `icon` VARCHAR(60) NULL,
  `structure` LONGTEXT NOT NULL,
  `is_system` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_st_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `proposals` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `proposal_number` VARCHAR(60) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `service_type` VARCHAR(120) NULL,
  `client_name` VARCHAR(190) NULL,
  `client_company` VARCHAR(190) NULL,
  `client_email` VARCHAR(190) NULL,
  `client_phone` VARCHAR(60) NULL,
  `client_address` VARCHAR(255) NULL,
  `client_website` VARCHAR(190) NULL,
  `template_id` INT NOT NULL DEFAULT 1,
  `colors` TEXT NULL,
  `font_heading` VARCHAR(60) NULL,
  `font_body` VARCHAR(60) NULL,
  `settings` LONGTEXT NULL,
  `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
  `currency_symbol` VARCHAR(10) NOT NULL DEFAULT '$',
  `subtotal` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `discount` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `tax` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `total` DECIMAL(14,2) NOT NULL DEFAULT 0,
  `status` ENUM('draft','sent','viewed','accepted','rejected','expired') NOT NULL DEFAULT 'draft',
  `public_token` VARCHAR(64) NOT NULL,
  `proposal_date` DATE NULL,
  `expiry_date` DATE NULL,
  `views` INT UNSIGNED NOT NULL DEFAULT 0,
  `first_viewed_at` DATETIME NULL,
  `last_viewed_at` DATETIME NULL,
  `sent_at` DATETIME NULL,
  `responded_at` DATETIME NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_prop_token` (`public_token`),
  KEY `idx_prop_status` (`status`),
  KEY `idx_prop_number` (`proposal_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `proposal_pages` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `proposal_id` INT UNSIGNED NOT NULL,
  `uid` VARCHAR(40) NOT NULL,
  `title` VARCHAR(190) NOT NULL DEFAULT 'Page',
  `page_type` ENUM('cover','content') NOT NULL DEFAULT 'content',
  `sort_order` INT NOT NULL DEFAULT 0,
  `settings` TEXT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_pages_proposal` (`proposal_id`, `sort_order`),
  CONSTRAINT `fk_pages_proposal` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `proposal_blocks` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `proposal_id` INT UNSIGNED NOT NULL,
  `page_id` INT UNSIGNED NOT NULL,
  `uid` VARCHAR(40) NOT NULL,
  `type` VARCHAR(40) NOT NULL,
  `data` LONGTEXT NULL,
  `sort_order` INT NOT NULL DEFAULT 0,
  `is_hidden` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_blocks_page` (`page_id`, `sort_order`),
  KEY `idx_blocks_proposal` (`proposal_id`),
  CONSTRAINT `fk_blocks_page` FOREIGN KEY (`page_id`) REFERENCES `proposal_pages` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_blocks_proposal` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `proposal_responses` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `proposal_id` INT UNSIGNED NOT NULL,
  `response` ENUM('accepted','rejected') NOT NULL,
  `name` VARCHAR(190) NULL,
  `email` VARCHAR(190) NULL,
  `comment` TEXT NULL,
  `signature_type` VARCHAR(20) NULL,
  `signature_data` VARCHAR(255) NULL,
  `signature_text` VARCHAR(190) NULL,
  `ip_address` VARCHAR(60) NULL,
  `user_agent` VARCHAR(255) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_resp_proposal` (`proposal_id`),
  CONSTRAINT `fk_resp_proposal` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `proposal_activity` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `proposal_id` INT UNSIGNED NOT NULL,
  `user_id` INT UNSIGNED NULL,
  `action` VARCHAR(60) NOT NULL,
  `details` VARCHAR(255) NULL,
  `ip_address` VARCHAR(60) NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_act_proposal` (`proposal_id`),
  CONSTRAINT `fk_act_proposal` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `proposal_versions` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `proposal_id` INT UNSIGNED NOT NULL,
  `label` VARCHAR(190) NULL,
  `snapshot` LONGTEXT NOT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ver_proposal` (`proposal_id`),
  CONSTRAINT `fk_ver_proposal` FOREIGN KEY (`proposal_id`) REFERENCES `proposals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `content_library` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(190) NOT NULL,
  `category` VARCHAR(120) NULL,
  `description` VARCHAR(255) NULL,
  `blocks` LONGTEXT NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
