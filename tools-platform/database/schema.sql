-- ============================================================
-- Online Tools Platform — Database Schema
-- Core PHP + MySQLi. No ORM. Every FK is enforced at the app
-- layer via prepared statements (see includes/functions.php).
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- Admins & sessions
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin','editor') NOT NULL DEFAULT 'editor',
    status ENUM('active','disabled') NOT NULL DEFAULT 'active',
    reset_token VARCHAR(64) NULL,
    reset_expires DATETIME NULL,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS admin_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NOT NULL,
    session_token VARCHAR(128) NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME NOT NULL,
    INDEX (admin_id),
    INDEX (session_token),
    CONSTRAINT fk_admin_sessions_admin FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS activity_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_id INT UNSIGNED NULL,
    action VARCHAR(120) NOT NULL,
    entity_type VARCHAR(60) NULL,
    entity_id INT UNSIGNED NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (admin_id),
    INDEX (entity_type, entity_id),
    INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Categories / subcategories
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(170) NOT NULL UNIQUE,
    description TEXT NULL,
    icon VARCHAR(80) NULL DEFAULT 'bi-grid',
    image VARCHAR(255) NULL,
    color VARCHAR(20) NULL DEFAULT '#6366F1',
    profession_group VARCHAR(100) NULL,
    seo_title VARCHAR(160) NULL,
    meta_description VARCHAR(300) NULL,
    status ENUM('published','draft','hidden') NOT NULL DEFAULT 'published',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (status),
    INDEX (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS subcategories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(170) NOT NULL,
    description TEXT NULL,
    status ENUM('published','draft','hidden') NOT NULL DEFAULT 'published',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_cat_slug (category_id, slug),
    CONSTRAINT fk_subcat_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tags
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tags (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tool_tags (
    tool_id INT UNSIGNED NOT NULL,
    tag_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (tool_id, tag_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Tools (core)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tools (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NOT NULL,
    subcategory_id INT UNSIGNED NULL,
    name VARCHAR(180) NOT NULL,
    slug VARCHAR(200) NOT NULL UNIQUE,
    short_description VARCHAR(300) NULL,
    description TEXT NULL,
    icon VARCHAR(80) NULL DEFAULT 'bi-calculator',
    featured_image VARCHAR(255) NULL,
    tool_file VARCHAR(150) NOT NULL COMMENT 'logic include path relative to /tools',
    tool_type ENUM('calculator','converter','generator','formatter','validator','encoder','decoder','timer','counter','analyzer','image_tool','developer_tool','financial_tool','utility') NOT NULL DEFAULT 'calculator',
    status ENUM('published','draft','disabled','scheduled') NOT NULL DEFAULT 'published',
    publish_at DATETIME NULL,
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    is_popular TINYINT(1) NOT NULL DEFAULT 0,
    is_trending TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 0,
    views INT UNSIGNED NOT NULL DEFAULT 0,
    seo_score TINYINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (slug),
    INDEX (category_id),
    INDEX (status),
    INDEX (is_featured),
    INDEX (is_popular),
    INDEX (is_trending),
    INDEX (created_at),
    INDEX (updated_at),
    INDEX idx_cat_status (category_id, status),
    CONSTRAINT fk_tools_category FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rich content blocks per tool (kept separate from `tools` so the
-- admin "content" tab and "basic info" tab can save independently)
CREATE TABLE IF NOT EXISTS tool_content (
    tool_id INT UNSIGNED PRIMARY KEY,
    introduction TEXT NULL,
    how_to_use TEXT NULL,
    formula TEXT NULL,
    formula_explanation TEXT NULL,
    calculation_method TEXT NULL,
    benefits TEXT NULL,
    common_mistakes TEXT NULL,
    tips TEXT NULL,
    notes TEXT NULL,
    disclaimer TEXT NULL,
    CONSTRAINT fk_tool_content_tool FOREIGN KEY (tool_id) REFERENCES tools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tool_examples (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tool_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NULL,
    input_summary TEXT NULL,
    output_summary TEXT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    INDEX (tool_id),
    CONSTRAINT fk_tool_examples_tool FOREIGN KEY (tool_id) REFERENCES tools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tool_formulas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tool_id INT UNSIGNED NOT NULL,
    label VARCHAR(200) NULL,
    formula_text TEXT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    INDEX (tool_id),
    CONSTRAINT fk_tool_formulas_tool FOREIGN KEY (tool_id) REFERENCES tools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tool_faqs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tool_id INT UNSIGNED NOT NULL,
    question VARCHAR(300) NOT NULL,
    answer TEXT NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    status ENUM('published','draft') NOT NULL DEFAULT 'published',
    INDEX (tool_id),
    CONSTRAINT fk_tool_faqs_tool FOREIGN KEY (tool_id) REFERENCES tools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tool_related (
    tool_id INT UNSIGNED NOT NULL,
    related_tool_id INT UNSIGNED NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    PRIMARY KEY (tool_id, related_tool_id),
    CONSTRAINT fk_tool_related_tool FOREIGN KEY (tool_id) REFERENCES tools(id) ON DELETE CASCADE,
    CONSTRAINT fk_tool_related_target FOREIGN KEY (related_tool_id) REFERENCES tools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tool_views (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    tool_id INT UNSIGNED NOT NULL,
    view_date DATE NOT NULL,
    ip_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_tool_ip_day (tool_id, ip_hash, view_date),
    INDEX (tool_id, view_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SEO fields, 1:1 with any "seo-able" entity (tool / category / page / blog post)
-- via a polymorphic (entity_type, entity_id) pair — avoids 4 near-identical tables.
CREATE TABLE IF NOT EXISTS seo_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type ENUM('tool','category','page','blog_post') NOT NULL,
    entity_id INT UNSIGNED NOT NULL,
    seo_title VARCHAR(160) NULL,
    meta_description VARCHAR(320) NULL,
    focus_keyword VARCHAR(150) NULL,
    secondary_keywords VARCHAR(400) NULL,
    canonical_url VARCHAR(255) NULL,
    robots VARCHAR(60) NOT NULL DEFAULT 'index,follow',
    og_title VARCHAR(160) NULL,
    og_description VARCHAR(320) NULL,
    og_image VARCHAR(255) NULL,
    twitter_title VARCHAR(160) NULL,
    twitter_description VARCHAR(320) NULL,
    twitter_image VARCHAR(255) NULL,
    schema_type VARCHAR(60) NULL DEFAULT 'WebApplication',
    breadcrumb_title VARCHAR(150) NULL,
    image_alt_text VARCHAR(200) NULL,
    UNIQUE KEY uniq_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Redirects
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS redirects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    old_url VARCHAR(255) NOT NULL,
    new_url VARCHAR(255) NOT NULL,
    redirect_type SMALLINT UNSIGNED NOT NULL DEFAULT 301,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (old_url),
    INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Static pages (About/Contact/Privacy/...) CMS
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(200) NOT NULL,
    slug VARCHAR(220) NOT NULL UNIQUE,
    content MEDIUMTEXT NULL,
    status ENUM('published','draft') NOT NULL DEFAULT 'published',
    is_system TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = built-in (privacy/terms/etc), cannot be deleted',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (slug),
    INDEX (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- kept for spec-parity (`page_seo`) as a thin view-like table; the
-- canonical SEO data for pages actually lives in seo_settings above.
CREATE TABLE IF NOT EXISTS page_seo (
    page_id INT UNSIGNED PRIMARY KEY,
    CONSTRAINT fk_page_seo_page FOREIGN KEY (page_id) REFERENCES pages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Blog
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS blog_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(170) NOT NULL UNIQUE,
    description TEXT NULL,
    status ENUM('published','hidden') NOT NULL DEFAULT 'published'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS blog_posts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    blog_category_id INT UNSIGNED NULL,
    admin_id INT UNSIGNED NULL,
    title VARCHAR(220) NOT NULL,
    slug VARCHAR(240) NOT NULL UNIQUE,
    excerpt VARCHAR(400) NULL,
    content MEDIUMTEXT NULL,
    featured_image VARCHAR(255) NULL,
    status ENUM('published','draft','scheduled') NOT NULL DEFAULT 'draft',
    publish_at DATETIME NULL,
    views INT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX (slug),
    INDEX (status),
    INDEX (blog_category_id),
    CONSTRAINT fk_blog_posts_cat FOREIGN KEY (blog_category_id) REFERENCES blog_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS blog_post_tools (
    blog_post_id INT UNSIGNED NOT NULL,
    tool_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (blog_post_id, tool_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Media library
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS media (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    file_type VARCHAR(100) NULL,
    file_size INT UNSIGNED NULL,
    alt_text VARCHAR(200) NULL,
    title VARCHAR(200) NULL,
    caption VARCHAR(300) NULL,
    description TEXT NULL,
    uploaded_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Site-wide settings (key/value; typed by convention in code)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS site_settings (
    setting_key VARCHAR(120) PRIMARY KEY,
    setting_value LONGTEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Navigation (header mega-menu + footer columns)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS navigation (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    location ENUM('header','footer') NOT NULL,
    name VARCHAR(120) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS navigation_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    navigation_id INT UNSIGNED NOT NULL,
    label VARCHAR(150) NOT NULL,
    url VARCHAR(255) NOT NULL,
    icon VARCHAR(80) NULL,
    sort_order INT NOT NULL DEFAULT 0,
    CONSTRAINT fk_nav_items_nav FOREIGN KEY (navigation_id) REFERENCES navigation(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Users (optional accounts for favorites/history sync) & favorites
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    status ENUM('active','disabled') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS favorites (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    tool_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_tool (user_id, tool_id),
    CONSTRAINT fk_favorites_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_favorites_tool FOREIGN KEY (tool_id) REFERENCES tools(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_tool_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    tool_id INT UNSIGNED NOT NULL,
    summary VARCHAR(255) NULL COMMENT 'non-sensitive label only, e.g. "BMI: 22.4"',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (user_id),
    INDEX (tool_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Aggregate analytics snapshot (rollup written by a cron/cli script)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS analytics (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    stat_date DATE NOT NULL,
    total_views INT UNSIGNED NOT NULL DEFAULT 0,
    unique_visitors INT UNSIGNED NOT NULL DEFAULT 0,
    top_tool_id INT UNSIGNED NULL,
    top_category_id INT UNSIGNED NULL,
    UNIQUE KEY uniq_stat_date (stat_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
-- Currency exchange rates (backing the Currency Converter, section 95)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS currency_rates (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    base_currency CHAR(3) NOT NULL,
    target_currency CHAR(3) NOT NULL,
    rate DECIMAL(20,8) NOT NULL,
    fetched_at DATETIME NOT NULL,
    UNIQUE KEY uniq_pair (base_currency, target_currency)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;
