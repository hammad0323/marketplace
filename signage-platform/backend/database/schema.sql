-- =============================================================================
--  Digital Signage Platform — MySQL schema
--  Phase 1 deliverable
--
--  Target   : MySQL 8.0.16+  (also verified on MariaDB 10.11)
--  Engine   : InnoDB
--  Charset  : utf8mb4 / utf8mb4_unicode_ci
--
--  Conventions
--  -----------
--  * Every primary key is BIGINT UNSIGNED AUTO_INCREMENT (INT for small lookups).
--  * Timestamps are stored in UTC (the PHP layer runs SET time_zone = '+00:00').
--  * Soft-deletable tables carry `deleted_at DATETIME NULL`. Rows with a
--    non-NULL deleted_at are hidden from every API query.
--  * Limit columns use:   NULL = inherit from plan,   -1 = unlimited,   N >= 0 = hard cap.
--    (plans themselves never use NULL: -1 = unlimited)
--  * Widget geometry is NORMALISED (0.0 – 1.0 of the canvas width/height),
--    never raw device pixels. See docs/ARCHITECTURE.md §6.
--  * Secrets (passwords, API tokens, device tokens, pairing codes, reset tokens)
--    are stored ONLY as hashes.
-- =============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS emergency_messages;
DROP TABLE IF EXISTS playback_logs;
DROP TABLE IF EXISTS device_commands;
DROP TABLE IF EXISTS device_pairing_codes;
DROP TABLE IF EXISTS device_logs;
DROP TABLE IF EXISTS device_playlists;
DROP TABLE IF EXISTS devices;
DROP TABLE IF EXISTS locations;
DROP TABLE IF EXISTS playlist_publications;
DROP TABLE IF EXISTS playlist_schedules;
DROP TABLE IF EXISTS playlist_items;
DROP TABLE IF EXISTS playlists;
DROP TABLE IF EXISTS screen_publications;
DROP TABLE IF EXISTS widget_assets;
DROP TABLE IF EXISTS screen_widgets;
DROP TABLE IF EXISTS screens;
DROP TABLE IF EXISTS screen_folders;
DROP TABLE IF EXISTS media_library;
DROP TABLE IF EXISTS media_folders;
DROP TABLE IF EXISTS user_recent_widgets;
DROP TABLE IF EXISTS widget_types;
DROP TABLE IF EXISTS template_favorites;
DROP TABLE IF EXISTS screen_templates;
DROP TABLE IF EXISTS template_categories;
DROP TABLE IF EXISTS rate_limits;
DROP TABLE IF EXISTS password_resets;
DROP TABLE IF EXISTS api_tokens;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS user_limits;
DROP TABLE IF EXISTS user_subscriptions;
DROP TABLE IF EXISTS user_settings;
DROP TABLE IF EXISTS user_permissions;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS plans;
DROP TABLE IF EXISTS role_permissions;
DROP TABLE IF EXISTS permissions;
DROP TABLE IF EXISTS roles;
DROP TABLE IF EXISTS app_settings;

SET FOREIGN_KEY_CHECKS = 1;

-- =============================================================================
--  1. AUTHENTICATION & ADMINISTRATION
-- =============================================================================

CREATE TABLE roles (
    role_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    role_key     VARCHAR(50)  NOT NULL,              -- 'super_admin', 'user'  (stable code identifier)
    role_name    VARCHAR(100) NOT NULL,
    description  VARCHAR(255) NULL,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (role_id),
    UNIQUE KEY uq_roles_key (role_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
    permission_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    permission_key  VARCHAR(100) NOT NULL,           -- e.g. 'screens.create', 'media.upload_video'
    permission_name VARCHAR(150) NOT NULL,
    module          VARCHAR(50)  NOT NULL DEFAULT 'general',
    description     VARCHAR(255) NULL,
    PRIMARY KEY (permission_id),
    UNIQUE KEY uq_permissions_key (permission_key),
    KEY idx_permissions_module (module)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
    id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    role_id       INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_role_permission (role_id, permission_id),
    KEY idx_rp_permission (permission_id),
    CONSTRAINT fk_rp_role       FOREIGN KEY (role_id)       REFERENCES roles (role_id)             ON DELETE CASCADE,
    CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions (permission_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
--  Plans (defined before users because users reference nothing here, but
--  subscriptions + limits reference both)
-- -----------------------------------------------------------------------------
CREATE TABLE plans (
    plan_id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    plan_key         VARCHAR(50)  NOT NULL,          -- 'free', 'starter', ...
    plan_name        VARCHAR(100) NOT NULL,
    description      VARCHAR(255) NULL,
    price_monthly    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    price_yearly     DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    currency         CHAR(3)      NOT NULL DEFAULT 'USD',
    max_screens      INT          NOT NULL DEFAULT 1,     -- -1 = unlimited
    max_playlists    INT          NOT NULL DEFAULT 1,     -- -1 = unlimited
    max_devices      INT          NOT NULL DEFAULT 1,     -- -1 = unlimited
    max_storage_mb   INT          NOT NULL DEFAULT 100,   -- -1 = unlimited
    max_upload_mb    INT          NOT NULL DEFAULT 20,    -- largest single file
    features_json    JSON         NULL,  -- {"video_upload":bool,"premium_templates":bool,"allowed_widgets":["*"]|[keys], ...}
    sort_order       INT          NOT NULL DEFAULT 0,
    is_default       TINYINT(1)   NOT NULL DEFAULT 0,     -- plan given to newly created users
    status           ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (plan_id),
    UNIQUE KEY uq_plans_key (plan_key),
    KEY idx_plans_status (status),
    CONSTRAINT chk_plans_limits CHECK (max_screens >= -1 AND max_playlists >= -1 AND max_devices >= -1 AND max_storage_mb >= -1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    user_id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    role_id            INT UNSIGNED    NOT NULL,
    full_name          VARCHAR(150)    NOT NULL,
    email              VARCHAR(190)    NOT NULL,
    phone              VARCHAR(30)     NULL,
    password_hash      VARCHAR(255)    NOT NULL,          -- password_hash(PASSWORD_DEFAULT)
    profile_image      VARCHAR(255)    NULL,              -- relative path under uploads/
    business_name      VARCHAR(150)    NULL,
    timezone           VARCHAR(64)     NOT NULL DEFAULT 'UTC',
    status             ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
    must_change_password TINYINT(1)    NOT NULL DEFAULT 0, -- set when admin generates credentials
    account_expires_at DATETIME        NULL,              -- NULL = never expires
    last_login_at      DATETIME        NULL,
    created_by         BIGINT UNSIGNED NULL,              -- admin who created the account
    created_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at         DATETIME        NULL,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_users_email (email),
    KEY idx_users_role (role_id),
    KEY idx_users_status (status, deleted_at),
    KEY idx_users_created_by (created_by),
    CONSTRAINT fk_users_role       FOREIGN KEY (role_id)    REFERENCES roles (role_id) ON DELETE RESTRICT,
    CONSTRAINT fk_users_created_by FOREIGN KEY (created_by) REFERENCES users (user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Per-user permission overrides on top of role permissions.
--   allowed = 1 → grant even if role lacks it;  allowed = 0 → revoke even if role has it.
CREATE TABLE user_permissions (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id       BIGINT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED    NOT NULL,
    allowed       TINYINT(1)      NOT NULL DEFAULT 1,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_user_permission (user_id, permission_id),
    KEY idx_up_permission (permission_id),
    CONSTRAINT fk_up_user       FOREIGN KEY (user_id)       REFERENCES users (user_id)             ON DELETE CASCADE,
    CONSTRAINT fk_up_permission FOREIGN KEY (permission_id) REFERENCES permissions (permission_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_settings (
    setting_id    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id       BIGINT UNSIGNED NOT NULL,
    setting_key   VARCHAR(100)    NOT NULL,              -- 'theme', 'language', 'brand_logo_media_id', ...
    setting_value TEXT            NULL,
    updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_id),
    UNIQUE KEY uq_user_setting (user_id, setting_key),
    CONSTRAINT fk_us_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  2. PLANS & LIMITS
-- =============================================================================

CREATE TABLE user_subscriptions (
    subscription_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         BIGINT UNSIGNED NOT NULL,
    plan_id         INT UNSIGNED    NOT NULL,
    start_date      DATE            NOT NULL,
    end_date        DATE            NULL,                 -- NULL = open-ended
    status          ENUM('active','expired','cancelled','pending') NOT NULL DEFAULT 'active',
    notes           VARCHAR(255)    NULL,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (subscription_id),
    KEY idx_sub_user_status (user_id, status),
    KEY idx_sub_plan (plan_id),
    CONSTRAINT fk_sub_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE,
    CONSTRAINT fk_sub_plan FOREIGN KEY (plan_id) REFERENCES plans (plan_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Admin overrides. Each column: NULL = inherit from active plan, -1 = unlimited, N = cap.
CREATE TABLE user_limits (
    limit_id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id              BIGINT UNSIGNED NOT NULL,
    max_screens          INT  NULL,
    max_playlists        INT  NULL,
    max_devices          INT  NULL,
    max_storage_mb       INT  NULL,
    max_upload_mb        INT  NULL,
    allowed_widgets_json JSON NULL,     -- NULL = inherit plan; ["*"] = all; ["image","text",...]
    can_upload_video     TINYINT(1) NULL,  -- NULL = inherit plan feature 'video_upload'
    can_use_premium_templates TINYINT(1) NULL,
    updated_by           BIGINT UNSIGNED NULL,
    updated_at           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (limit_id),
    UNIQUE KEY uq_user_limits_user (user_id),
    KEY idx_ul_updated_by (updated_by),
    CONSTRAINT fk_ul_user       FOREIGN KEY (user_id)    REFERENCES users (user_id) ON DELETE CASCADE,
    CONSTRAINT fk_ul_updated_by FOREIGN KEY (updated_by) REFERENCES users (user_id) ON DELETE SET NULL,
    CONSTRAINT chk_ul_limits CHECK (
        (max_screens    IS NULL OR max_screens    >= -1) AND
        (max_playlists  IS NULL OR max_playlists  >= -1) AND
        (max_devices    IS NULL OR max_devices    >= -1) AND
        (max_storage_mb IS NULL OR max_storage_mb >= -1) AND
        (max_upload_mb  IS NULL OR max_upload_mb  >= -1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  3. SESSION / SECURITY SUPPORT
-- =============================================================================

CREATE TABLE api_tokens (
    token_id     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id      BIGINT UNSIGNED NOT NULL,
    token_hash   CHAR(64)        NOT NULL,           -- SHA-256 hex of the random bearer token
    device_label VARCHAR(150)    NULL,               -- "Pixel 8 / Android 15"
    ip_address   VARCHAR(45)     NULL,
    user_agent   VARCHAR(255)    NULL,
    last_used_at DATETIME        NULL,
    expires_at   DATETIME        NOT NULL,
    revoked_at   DATETIME        NULL,
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (token_id),
    UNIQUE KEY uq_api_tokens_hash (token_hash),
    KEY idx_api_tokens_user (user_id, revoked_at),
    KEY idx_api_tokens_expiry (expires_at),
    CONSTRAINT fk_at_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
    reset_id    BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NOT NULL,
    token_hash  CHAR(64)        NOT NULL,            -- SHA-256 of emailed token / OTP
    expires_at  DATETIME        NOT NULL,
    used_at     DATETIME        NULL,
    ip_address  VARCHAR(45)     NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (reset_id),
    UNIQUE KEY uq_pr_token (token_hash),
    KEY idx_pr_user (user_id),
    CONSTRAINT fk_pr_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fixed-window rate limiting: one row per (bucket, window).
CREATE TABLE rate_limits (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    bucket_key   VARCHAR(190)    NOT NULL,           -- 'login:ip:1.2.3.4', 'pair:user:12'
    window_start DATETIME        NOT NULL,
    hits         INT UNSIGNED    NOT NULL DEFAULT 1,
    PRIMARY KEY (id),
    UNIQUE KEY uq_rate_bucket_window (bucket_key, window_start),
    KEY idx_rate_window (window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  4. TEMPLATES & WIDGET TYPES
-- =============================================================================

CREATE TABLE template_categories (
    category_id   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_key  VARCHAR(60)  NOT NULL,
    category_name VARCHAR(100) NOT NULL,
    description   VARCHAR(255) NULL,
    icon          VARCHAR(60)  NULL,                 -- Material icon name used by Flutter
    sort_order    INT          NOT NULL DEFAULT 0,
    status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    PRIMARY KEY (category_id),
    UNIQUE KEY uq_tc_key (category_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE screen_templates (
    template_id   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    category_id   INT UNSIGNED    NOT NULL,
    template_key  VARCHAR(80)     NOT NULL,
    template_name VARCHAR(150)    NOT NULL,
    description   VARCHAR(255)    NULL,
    orientation   ENUM('landscape','portrait','square','custom') NOT NULL DEFAULT 'landscape',
    width         INT UNSIGNED    NOT NULL DEFAULT 1920,
    height        INT UNSIGNED    NOT NULL DEFAULT 1080,
    preview_image VARCHAR(255)    NULL,              -- NULL → client renders live preview from template_json
    template_json JSON            NOT NULL,          -- layout document (see ARCHITECTURE.md §6)
    tags          VARCHAR(255)    NULL,
    is_premium    TINYINT(1)      NOT NULL DEFAULT 0,
    usage_count   INT UNSIGNED    NOT NULL DEFAULT 0,
    created_by    BIGINT UNSIGNED NULL,              -- NULL = system; user id = "saved as template"
    is_public     TINYINT(1)      NOT NULL DEFAULT 1,  -- 0 = private user template
    sort_order    INT             NOT NULL DEFAULT 0,
    status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at    DATETIME        NULL,
    PRIMARY KEY (template_id),
    UNIQUE KEY uq_template_key (template_key),
    KEY idx_tpl_category (category_id, status),
    KEY idx_tpl_orientation (orientation),
    KEY idx_tpl_created_by (created_by),
    CONSTRAINT fk_tpl_category   FOREIGN KEY (category_id) REFERENCES template_categories (category_id) ON DELETE RESTRICT,
    CONSTRAINT fk_tpl_created_by FOREIGN KEY (created_by)  REFERENCES users (user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE template_favorites (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NOT NULL,
    template_id BIGINT UNSIGNED NOT NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tf_user_template (user_id, template_id),
    KEY idx_tf_template (template_id),
    CONSTRAINT fk_tf_user     FOREIGN KEY (user_id)     REFERENCES users (user_id)                ON DELETE CASCADE,
    CONSTRAINT fk_tf_template FOREIGN KEY (template_id) REFERENCES screen_templates (template_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registry of widget kinds. Adding a widget = 1 row here + 1 renderer/editor in Flutter.
CREATE TABLE widget_types (
    widget_type_id      INT UNSIGNED NOT NULL AUTO_INCREMENT,
    widget_key          VARCHAR(60)  NOT NULL,        -- stable identifier stored in screen_widgets.widget_type
    widget_name         VARCHAR(100) NOT NULL,
    description         VARCHAR(255) NULL,
    category            ENUM('media','text','time','data','business','embed','layout','system') NOT NULL DEFAULT 'media',
    icon                VARCHAR(60)  NULL,
    default_config_json JSON         NOT NULL,
    default_width       DECIMAL(7,6) NOT NULL DEFAULT 0.400000,
    default_height      DECIMAL(7,6) NOT NULL DEFAULT 0.300000,
    requires_network    TINYINT(1)   NOT NULL DEFAULT 0,
    is_premium          TINYINT(1)   NOT NULL DEFAULT 0,
    availability        ENUM('stable','beta','planned') NOT NULL DEFAULT 'stable', -- 'planned' hidden from editor
    sort_order          INT          NOT NULL DEFAULT 0,
    status              ENUM('active','inactive') NOT NULL DEFAULT 'active',
    PRIMARY KEY (widget_type_id),
    UNIQUE KEY uq_widget_key (widget_key),
    KEY idx_wt_status (status, availability)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_recent_widgets (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NOT NULL,
    widget_key  VARCHAR(60)     NOT NULL,
    use_count   INT UNSIGNED    NOT NULL DEFAULT 1,
    last_used_at DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_urw (user_id, widget_key),
    KEY idx_urw_widget (widget_key),
    CONSTRAINT fk_urw_user   FOREIGN KEY (user_id)    REFERENCES users (user_id)         ON DELETE CASCADE,
    CONSTRAINT fk_urw_widget FOREIGN KEY (widget_key) REFERENCES widget_types (widget_key) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  5. MEDIA
-- =============================================================================

CREATE TABLE media_folders (
    folder_id        BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id          BIGINT UNSIGNED NOT NULL,
    folder_name      VARCHAR(150)    NOT NULL,
    parent_folder_id BIGINT UNSIGNED NULL,
    created_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (folder_id),
    UNIQUE KEY uq_mf_name (user_id, parent_folder_id, folder_name),
    KEY idx_mf_parent (parent_folder_id),
    CONSTRAINT fk_mf_user   FOREIGN KEY (user_id)          REFERENCES users (user_id)           ON DELETE CASCADE,
    CONSTRAINT fk_mf_parent FOREIGN KEY (parent_folder_id) REFERENCES media_folders (folder_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE media_library (
    media_id       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id        BIGINT UNSIGNED NULL,              -- NULL only for global (admin) assets
    folder_id      BIGINT UNSIGNED NULL,
    file_name      VARCHAR(190)    NOT NULL,          -- random unique stored name, e.g. 9f2c...e1.jpg
    original_name  VARCHAR(255)    NOT NULL,          -- user-visible name (renamable)
    file_path      VARCHAR(255)    NOT NULL,          -- relative to storage root: images/2026/11/9f2c...e1.jpg
    thumbnail_path VARCHAR(255)    NULL,
    file_type      ENUM('image','video','document','logo','audio') NOT NULL,
    mime_type      VARCHAR(100)    NOT NULL,
    file_size      BIGINT UNSIGNED NOT NULL DEFAULT 0, -- bytes
    width          INT UNSIGNED    NULL,
    height         INT UNSIGNED    NULL,
    duration_ms    INT UNSIGNED    NULL,              -- videos / audio
    checksum_sha256 CHAR(64)       NULL,              -- player verifies downloads against this
    storage_driver VARCHAR(20)     NOT NULL DEFAULT 'local',  -- 'local' | 's3' (future)
    is_global      TINYINT(1)      NOT NULL DEFAULT 0, -- 1 = visible to every user (pre-built backgrounds)
    created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at     DATETIME        NULL,
    PRIMARY KEY (media_id),
    UNIQUE KEY uq_media_file_path (file_path),
    KEY idx_media_user (user_id, deleted_at, file_type),
    KEY idx_media_folder (folder_id),
    KEY idx_media_global (is_global),
    CONSTRAINT fk_media_user   FOREIGN KEY (user_id)   REFERENCES users (user_id)           ON DELETE CASCADE,
    CONSTRAINT fk_media_folder FOREIGN KEY (folder_id) REFERENCES media_folders (folder_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  6. SCREENS & WIDGETS
-- =============================================================================

CREATE TABLE screen_folders (
    folder_id   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NOT NULL,
    folder_name VARCHAR(150)    NOT NULL,
    color       VARCHAR(9)      NULL,
    sort_order  INT             NOT NULL DEFAULT 0,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (folder_id),
    UNIQUE KEY uq_sf_name (user_id, folder_name),
    CONSTRAINT fk_sf_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE screens (
    screen_id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    screen_uid             CHAR(36)        NOT NULL,   -- public UUID (safe to expose / share)
    user_id                BIGINT UNSIGNED NOT NULL,
    template_id            BIGINT UNSIGNED NULL,
    folder_id              BIGINT UNSIGNED NULL,
    screen_name            VARCHAR(150)    NOT NULL,
    description            VARCHAR(255)    NULL,
    orientation            ENUM('landscape','portrait','square','custom') NOT NULL DEFAULT 'landscape',
    width                  INT UNSIGNED    NOT NULL DEFAULT 1920,  -- design resolution (aspect ratio source)
    height                 INT UNSIGNED    NOT NULL DEFAULT 1080,
    background_type        ENUM('color','gradient','image','video','preset') NOT NULL DEFAULT 'color',
    background_value       VARCHAR(255)    NULL,       -- '#17191C' | preset key | media_id as string
    background_media_id    BIGINT UNSIGNED NULL,       -- set when type = image|video (keeps FK integrity)
    background_config_json JSON            NULL,       -- {gradient:{...}, fit, position, opacity, blur, overlay_color, overlay_opacity}
    default_duration       INT UNSIGNED    NOT NULL DEFAULT 10,   -- seconds when used in a playlist
    thumbnail_path         VARCHAR(255)    NULL,
    status                 ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    layout_version         INT UNSIGNED    NOT NULL DEFAULT 1,    -- bumps on every save (optimistic locking / autosave)
    published_version      INT UNSIGNED    NULL,       -- latest screen_publications.version
    has_unpublished_changes TINYINT(1)     NOT NULL DEFAULT 1,
    is_favorite            TINYINT(1)      NOT NULL DEFAULT 0,
    expires_at             DATETIME        NULL,       -- content expiry: player skips after this time
    created_at             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at             DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    published_at           DATETIME        NULL,
    deleted_at             DATETIME        NULL,
    PRIMARY KEY (screen_id),
    UNIQUE KEY uq_screens_uid (screen_uid),
    UNIQUE KEY uq_screens_id_user (screen_id, user_id),   -- target of composite FKs (ownership integrity)
    KEY idx_screens_user (user_id, deleted_at, status),
    KEY idx_screens_template (template_id),
    KEY idx_screens_folder (folder_id),
    KEY idx_screens_bg_media (background_media_id),
    KEY idx_screens_updated (user_id, updated_at),
    CONSTRAINT fk_screens_user     FOREIGN KEY (user_id)             REFERENCES users (user_id)                ON DELETE CASCADE,
    CONSTRAINT fk_screens_template FOREIGN KEY (template_id)         REFERENCES screen_templates (template_id) ON DELETE SET NULL,
    CONSTRAINT fk_screens_folder   FOREIGN KEY (folder_id)           REFERENCES screen_folders (folder_id)     ON DELETE SET NULL,
    CONSTRAINT fk_screens_bg_media FOREIGN KEY (background_media_id) REFERENCES media_library (media_id)       ON DELETE SET NULL,
    CONSTRAINT chk_screens_dims CHECK (width BETWEEN 16 AND 16384 AND height BETWEEN 16 AND 16384)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE screen_widgets (
    widget_id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    widget_uid         CHAR(36)        NOT NULL,   -- client-generated id (stable across undo/redo + autosave)
    screen_id          BIGINT UNSIGNED NOT NULL,
    widget_type        VARCHAR(60)     NOT NULL,   -- FK → widget_types.widget_key
    widget_name        VARCHAR(150)    NULL,
    position_x         DECIMAL(8,6)    NOT NULL DEFAULT 0,  -- normalised 0..1 (left edge / canvas width)
    position_y         DECIMAL(8,6)    NOT NULL DEFAULT 0,  -- normalised 0..1 (top edge / canvas height)
    width              DECIMAL(8,6)    NOT NULL DEFAULT 0.4,
    height             DECIMAL(8,6)    NOT NULL DEFAULT 0.3,
    rotation           DECIMAL(6,2)    NOT NULL DEFAULT 0,  -- degrees
    z_index            INT             NOT NULL DEFAULT 0,
    configuration_json JSON            NOT NULL,
    is_locked          TINYINT(1)      NOT NULL DEFAULT 0,
    is_visible         TINYINT(1)      NOT NULL DEFAULT 1,
    status             ENUM('active','disabled') NOT NULL DEFAULT 'active',
    created_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at         DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (widget_id),
    UNIQUE KEY uq_sw_uid (screen_id, widget_uid),
    KEY idx_sw_screen_z (screen_id, z_index),
    KEY idx_sw_type (widget_type),
    CONSTRAINT fk_sw_screen FOREIGN KEY (screen_id)   REFERENCES screens (screen_id)       ON DELETE CASCADE,
    CONSTRAINT fk_sw_type   FOREIGN KEY (widget_type) REFERENCES widget_types (widget_key) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT chk_sw_geometry CHECK (
        position_x BETWEEN -1 AND 2 AND position_y BETWEEN -1 AND 2 AND
        width > 0 AND width <= 2 AND height > 0 AND height <= 2)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every media item a widget references (image, carousel slide, video, logo...).
-- Gives referential integrity, ordering, and safe "unused media" cleanup.
CREATE TABLE widget_assets (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    widget_id   BIGINT UNSIGNED NOT NULL,
    media_id    BIGINT UNSIGNED NOT NULL,
    asset_order INT             NOT NULL DEFAULT 0,
    duration    INT UNSIGNED    NULL,              -- seconds (carousel slide); NULL = widget default
    settings_json JSON          NULL,              -- per-asset overrides: {fit, caption, transition}
    PRIMARY KEY (id),
    UNIQUE KEY uq_wa_order (widget_id, asset_order),
    KEY idx_wa_media (media_id),
    CONSTRAINT fk_wa_widget FOREIGN KEY (widget_id) REFERENCES screen_widgets (widget_id) ON DELETE CASCADE,
    CONSTRAINT fk_wa_media  FOREIGN KEY (media_id)  REFERENCES media_library (media_id)  ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Immutable published snapshots. Player only ever reads these, never drafts.
CREATE TABLE screen_publications (
    publication_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    screen_id      BIGINT UNSIGNED NOT NULL,
    version        INT UNSIGNED    NOT NULL,
    published_json JSON            NOT NULL,     -- full layout document + media manifest
    content_hash   CHAR(64)        NOT NULL,     -- SHA-256 of published_json
    published_by   BIGINT UNSIGNED NULL,
    published_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status         ENUM('current','superseded','revoked') NOT NULL DEFAULT 'current',
    PRIMARY KEY (publication_id),
    UNIQUE KEY uq_sp_version (screen_id, version),
    KEY idx_sp_status (screen_id, status),
    KEY idx_sp_published_by (published_by),
    CONSTRAINT fk_sp_screen FOREIGN KEY (screen_id)    REFERENCES screens (screen_id) ON DELETE CASCADE,
    CONSTRAINT fk_sp_user   FOREIGN KEY (published_by) REFERENCES users (user_id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  7. PLAYLISTS (carousel of complete screens) & SCHEDULING
-- =============================================================================

CREATE TABLE playlists (
    playlist_id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    playlist_uid        CHAR(36)        NOT NULL,
    user_id             BIGINT UNSIGNED NOT NULL,
    playlist_name       VARCHAR(150)    NOT NULL,
    description         VARCHAR(255)    NULL,
    loop_enabled        TINYINT(1)      NOT NULL DEFAULT 1,
    autoplay            TINYINT(1)      NOT NULL DEFAULT 1,
    shuffle             TINYINT(1)      NOT NULL DEFAULT 0,
    transition_type     ENUM('none','fade','slide_left','slide_right','slide_up','slide_down','zoom','flip') NOT NULL DEFAULT 'fade',
    transition_duration INT UNSIGNED    NOT NULL DEFAULT 800,   -- milliseconds
    default_duration    INT UNSIGNED    NOT NULL DEFAULT 10,    -- seconds per item if item.duration NULL
    priority            INT             NOT NULL DEFAULT 0,     -- tie-breaker when no schedule priority applies
    status              ENUM('draft','published','paused','archived') NOT NULL DEFAULT 'draft',
    published_version   INT UNSIGNED    NULL,
    has_unpublished_changes TINYINT(1)  NOT NULL DEFAULT 1,
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    published_at        DATETIME        NULL,
    deleted_at          DATETIME        NULL,
    PRIMARY KEY (playlist_id),
    UNIQUE KEY uq_playlists_uid (playlist_uid),
    UNIQUE KEY uq_playlists_id_user (playlist_id, user_id),
    KEY idx_playlists_user (user_id, deleted_at, status),
    CONSTRAINT fk_playlists_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE,
    CONSTRAINT chk_playlists_transition CHECK (transition_duration <= 10000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- user_id is denormalised so that composite FKs GUARANTEE a playlist can only
-- contain screens owned by the same user (cross-user protection at DB level).
CREATE TABLE playlist_items (
    item_id     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    playlist_id BIGINT UNSIGNED NOT NULL,
    screen_id   BIGINT UNSIGNED NOT NULL,
    user_id     BIGINT UNSIGNED NOT NULL,
    sort_order  INT             NOT NULL DEFAULT 0,
    duration    INT UNSIGNED    NULL,              -- seconds; NULL = screen.default_duration
    transition_type ENUM('inherit','none','fade','slide_left','slide_right','slide_up','slide_down','zoom','flip') NOT NULL DEFAULT 'inherit',
    is_enabled  TINYINT(1)      NOT NULL DEFAULT 1,
    valid_from  DATETIME        NULL,              -- per-item content window (priority content / expiry)
    valid_until DATETIME        NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (item_id),
    KEY idx_pi_playlist_order (playlist_id, sort_order),
    KEY idx_pi_screen (screen_id),
    KEY idx_pi_playlist_user (playlist_id, user_id),
    KEY idx_pi_screen_user (screen_id, user_id),
    CONSTRAINT fk_pi_playlist FOREIGN KEY (playlist_id, user_id) REFERENCES playlists (playlist_id, user_id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_pi_screen   FOREIGN KEY (screen_id, user_id)   REFERENCES screens (screen_id, user_id)     ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_pi_duration CHECK (duration IS NULL OR duration BETWEEN 1 AND 86400)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE playlist_schedules (
    schedule_id       BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    playlist_id       BIGINT UNSIGNED NOT NULL,
    schedule_name     VARCHAR(150)    NULL,          -- "Morning promotion"
    schedule_type     ENUM('recurring','one_time','special_event') NOT NULL DEFAULT 'recurring',
    start_date        DATE            NULL,          -- NULL = no lower bound
    end_date          DATE            NULL,          -- NULL = no upper bound
    start_time        TIME            NULL,          -- NULL = 00:00:00
    end_time          TIME            NULL,          -- NULL = 23:59:59; end < start ⇒ crosses midnight
    days_of_week_json JSON            NULL,          -- [1..7] ISO (1=Mon); NULL = every day
    timezone          VARCHAR(64)     NULL,          -- NULL = device timezone
    priority          INT             NOT NULL DEFAULT 10,  -- higher wins; special_event adds +1000 at runtime
    status            ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (schedule_id),
    KEY idx_ps_playlist (playlist_id, status),
    KEY idx_ps_dates (start_date, end_date),
    CONSTRAINT fk_ps_playlist FOREIGN KEY (playlist_id) REFERENCES playlists (playlist_id) ON DELETE CASCADE,
    CONSTRAINT chk_ps_dates CHECK (start_date IS NULL OR end_date IS NULL OR end_date >= start_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE playlist_publications (
    publication_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    playlist_id    BIGINT UNSIGNED NOT NULL,
    version        INT UNSIGNED    NOT NULL,
    published_json JSON            NOT NULL,   -- playlist + items + embedded screen publications + media manifest
    content_hash   CHAR(64)        NOT NULL,
    published_by   BIGINT UNSIGNED NULL,
    published_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status         ENUM('current','superseded','revoked') NOT NULL DEFAULT 'current',
    PRIMARY KEY (publication_id),
    UNIQUE KEY uq_pp_version (playlist_id, version),
    KEY idx_pp_status (playlist_id, status),
    KEY idx_pp_published_by (published_by),
    CONSTRAINT fk_pp_playlist FOREIGN KEY (playlist_id)  REFERENCES playlists (playlist_id) ON DELETE CASCADE,
    CONSTRAINT fk_pp_user     FOREIGN KEY (published_by) REFERENCES users (user_id)         ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  8. LOCATIONS & DEVICES
-- =============================================================================

CREATE TABLE locations (
    location_id   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id       BIGINT UNSIGNED NOT NULL,
    location_name VARCHAR(150)    NOT NULL,
    address       VARCHAR(255)    NULL,
    city          VARCHAR(100)    NULL,
    country       VARCHAR(100)    NULL,
    latitude      DECIMAL(10,7)   NULL,
    longitude     DECIMAL(10,7)   NULL,
    timezone      VARCHAR(64)     NULL,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (location_id),
    UNIQUE KEY uq_loc_name (user_id, location_name),
    CONSTRAINT fk_loc_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- A device row is created when the player first registers (user_id NULL = unpaired).
-- Unpairing sets user_id = NULL (after deleting its device_playlists rows in the same transaction).
-- Hard-purging a user cascades to their devices; players simply re-register.
CREATE TABLE devices (
    device_id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    device_uid          CHAR(36)        NOT NULL,   -- generated by player on first launch, persisted locally
    user_id             BIGINT UNSIGNED NULL,       -- NULL until paired
    location_id         BIGINT UNSIGNED NULL,
    device_name         VARCHAR(150)    NOT NULL DEFAULT 'New display',
    device_code         VARCHAR(20)     NOT NULL,   -- short human-readable id shown on TV (e.g. "DSP-7K4Q")
    device_token_hash   CHAR(64)        NULL,       -- SHA-256 of the player's bearer token
    device_type         ENUM('android_tv','android_box','tablet','phone','other') NOT NULL DEFAULT 'android_tv',
    platform            VARCHAR(50)     NULL,       -- 'android'
    os_version          VARCHAR(50)     NULL,
    manufacturer        VARCHAR(100)    NULL,
    model               VARCHAR(100)    NULL,
    resolution          VARCHAR(20)     NULL,       -- '1920x1080'
    orientation         ENUM('landscape','portrait','auto') NOT NULL DEFAULT 'auto',
    app_version         VARCHAR(30)     NULL,
    timezone            VARCHAR(64)     NULL,
    ip_address          VARCHAR(45)     NULL,
    storage_total_mb    INT UNSIGNED    NULL,
    storage_free_mb     INT UNSIGNED    NULL,
    capabilities_json   JSON            NULL,       -- {"commands":["restart","pause","resume","sync","screenshot"],"video":true}
    current_playlist_id BIGINT UNSIGNED NULL,
    current_screen_id   BIGINT UNSIGNED NULL,
    current_content_version VARCHAR(60) NULL,       -- "pl:12@v4"
    playback_state      ENUM('playing','paused','idle','error','downloading') NOT NULL DEFAULT 'idle',
    last_heartbeat      DATETIME        NULL,
    last_sync_at        DATETIME        NULL,
    paired_at           DATETIME        NULL,
    status              ENUM('unpaired','active','disabled') NOT NULL DEFAULT 'unpaired',
    created_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at          DATETIME        NULL,
    PRIMARY KEY (device_id),
    UNIQUE KEY uq_devices_uid (device_uid),
    UNIQUE KEY uq_devices_code (device_code),
    UNIQUE KEY uq_devices_token (device_token_hash),
    UNIQUE KEY uq_devices_id_user (device_id, user_id),
    KEY idx_devices_user (user_id, deleted_at, status),
    KEY idx_devices_heartbeat (last_heartbeat),
    KEY idx_devices_location (location_id),
    KEY idx_devices_cur_playlist (current_playlist_id),
    KEY idx_devices_cur_screen (current_screen_id),
    CONSTRAINT fk_devices_user     FOREIGN KEY (user_id)             REFERENCES users (user_id)         ON DELETE CASCADE,
    CONSTRAINT fk_devices_location FOREIGN KEY (location_id)         REFERENCES locations (location_id) ON DELETE SET NULL,
    CONSTRAINT fk_devices_playlist FOREIGN KEY (current_playlist_id) REFERENCES playlists (playlist_id) ON DELETE SET NULL,
    CONSTRAINT fk_devices_screen   FOREIGN KEY (current_screen_id)   REFERENCES screens (screen_id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Playlists assigned to a device. The player evaluates schedules across ALL
-- assigned playlists; is_default = 1 plays when no schedule matches.
-- Composite FKs guarantee device and playlist belong to the same user.
CREATE TABLE device_playlists (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    device_id   BIGINT UNSIGNED NOT NULL,
    playlist_id BIGINT UNSIGNED NOT NULL,
    user_id     BIGINT UNSIGNED NOT NULL,
    is_default  TINYINT(1)      NOT NULL DEFAULT 0,
    assigned_by BIGINT UNSIGNED NULL,
    assigned_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dp_device_playlist (device_id, playlist_id),
    KEY idx_dp_playlist (playlist_id),
    KEY idx_dp_device_user (device_id, user_id),
    KEY idx_dp_playlist_user (playlist_id, user_id),
    KEY idx_dp_assigned_by (assigned_by),
    CONSTRAINT fk_dp_device   FOREIGN KEY (device_id, user_id)   REFERENCES devices (device_id, user_id)     ON DELETE CASCADE,
    CONSTRAINT fk_dp_playlist FOREIGN KEY (playlist_id, user_id) REFERENCES playlists (playlist_id, user_id) ON DELETE CASCADE,
    CONSTRAINT fk_dp_assigned FOREIGN KEY (assigned_by)          REFERENCES users (user_id)                  ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE device_logs (
    log_id     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    device_id  BIGINT UNSIGNED NOT NULL,
    log_type   ENUM('info','warning','error','playback_error','sync','command','heartbeat','crash') NOT NULL DEFAULT 'info',
    message    VARCHAR(1000)   NOT NULL,
    context_json JSON          NULL,             -- {"screen_id":5,"widget_uid":"...","media_id":9}
    logged_at  DATETIME        NULL,             -- device-side timestamp (offline-queued logs)
    created_at DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (log_id),
    KEY idx_dl_device_time (device_id, created_at),
    KEY idx_dl_type (log_type),
    CONSTRAINT fk_dl_device FOREIGN KEY (device_id) REFERENCES devices (device_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE device_pairing_codes (
    id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pairing_code_hash CHAR(64)        NOT NULL,   -- SHA-256(pepper + normalised 6-char code)
    device_id         BIGINT UNSIGNED NOT NULL,
    attempts          INT UNSIGNED    NOT NULL DEFAULT 0,
    expires_at        DATETIME        NOT NULL,   -- typically now + 15 min
    used_at           DATETIME        NULL,
    used_by           BIGINT UNSIGNED NULL,
    created_at        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dpc_hash (pairing_code_hash),
    KEY idx_dpc_device (device_id),
    KEY idx_dpc_expires (expires_at),
    KEY idx_dpc_used_by (used_by),
    CONSTRAINT fk_dpc_device FOREIGN KEY (device_id) REFERENCES devices (device_id) ON DELETE CASCADE,
    CONSTRAINT fk_dpc_user   FOREIGN KEY (used_by)   REFERENCES users (user_id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Remote command queue: the management app inserts, the player picks up on
-- heartbeat/sync and acknowledges. Only commands listed in devices.capabilities_json are accepted.
CREATE TABLE device_commands (
    command_id   BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    device_id    BIGINT UNSIGNED NOT NULL,
    command      ENUM('restart','pause','resume','sync','clear_cache','screenshot','identify') NOT NULL,
    payload_json JSON            NULL,
    status       ENUM('pending','delivered','completed','failed','expired') NOT NULL DEFAULT 'pending',
    result_message VARCHAR(500)  NULL,
    issued_by    BIGINT UNSIGNED NULL,
    created_at   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    delivered_at DATETIME        NULL,
    completed_at DATETIME        NULL,
    expires_at   DATETIME        NOT NULL,
    PRIMARY KEY (command_id),
    KEY idx_dc_device_status (device_id, status),
    KEY idx_dc_issued_by (issued_by),
    CONSTRAINT fk_dc_device FOREIGN KEY (device_id) REFERENCES devices (device_id) ON DELETE CASCADE,
    CONSTRAINT fk_dc_user   FOREIGN KEY (issued_by) REFERENCES users (user_id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Proof-of-play / analytics. Batched uploads from the player.
CREATE TABLE playback_logs (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    device_id     BIGINT UNSIGNED NOT NULL,
    playlist_id   BIGINT UNSIGNED NULL,
    screen_id     BIGINT UNSIGNED NULL,
    playlist_version INT UNSIGNED NULL,
    screen_version   INT UNSIGNED NULL,
    started_at    DATETIME        NOT NULL,
    duration_ms   INT UNSIGNED    NOT NULL DEFAULT 0,
    completed     TINYINT(1)      NOT NULL DEFAULT 1,
    created_at    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pb_device_time (device_id, started_at),
    KEY idx_pb_playlist (playlist_id, started_at),
    KEY idx_pb_screen (screen_id, started_at),
    CONSTRAINT fk_pb_device   FOREIGN KEY (device_id)   REFERENCES devices (device_id)     ON DELETE CASCADE,
    CONSTRAINT fk_pb_playlist FOREIGN KEY (playlist_id) REFERENCES playlists (playlist_id) ON DELETE SET NULL,
    CONSTRAINT fk_pb_screen   FOREIGN KEY (screen_id)   REFERENCES screens (screen_id)     ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Emergency full-screen override. Pre-empts every schedule on targeted devices.
CREATE TABLE emergency_messages (
    message_id     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id        BIGINT UNSIGNED NOT NULL,
    title          VARCHAR(150)    NOT NULL,
    body           TEXT            NULL,
    style_json     JSON            NULL,           -- {"background":"#B00020","color":"#FFFFFF","flash":true}
    target_all_devices TINYINT(1)  NOT NULL DEFAULT 1,
    target_device_ids_json JSON    NULL,           -- [3,4] when target_all_devices = 0
    starts_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ends_at        DATETIME        NULL,           -- NULL = until cancelled
    status         ENUM('active','cancelled','expired') NOT NULL DEFAULT 'active',
    created_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (message_id),
    KEY idx_em_user_status (user_id, status),
    CONSTRAINT fk_em_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  9. NOTIFICATIONS, AUDIT, SETTINGS
-- =============================================================================

CREATE TABLE notifications (
    notification_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id         BIGINT UNSIGNED NOT NULL,
    title           VARCHAR(150)    NOT NULL,
    message         VARCHAR(1000)   NOT NULL,
    type            ENUM('info','success','warning','error','device_offline','limit_reached','system') NOT NULL DEFAULT 'info',
    link            VARCHAR(255)    NULL,           -- in-app route, e.g. '/devices/3'
    is_read         TINYINT(1)      NOT NULL DEFAULT 0,
    created_at      DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (notification_id),
    KEY idx_notif_user (user_id, is_read, created_at),
    CONSTRAINT fk_notif_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activity_logs (
    activity_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id     BIGINT UNSIGNED NULL,               -- NULL = system / device
    action      VARCHAR(80)     NOT NULL,           -- 'screen.create', 'auth.login', 'admin.user.suspend'
    entity_type VARCHAR(40)     NULL,               -- 'screen', 'playlist', 'device', 'user'
    entity_id   BIGINT UNSIGNED NULL,
    description VARCHAR(500)    NULL,
    meta_json   JSON            NULL,
    ip_address  VARCHAR(45)     NULL,
    created_at  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (activity_id),
    KEY idx_al_user_time (user_id, created_at),
    KEY idx_al_entity (entity_type, entity_id),
    KEY idx_al_action (action),
    CONSTRAINT fk_al_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE app_settings (
    setting_id    INT UNSIGNED NOT NULL AUTO_INCREMENT,
    setting_key   VARCHAR(100) NOT NULL,
    setting_value TEXT         NULL,
    value_type    ENUM('string','int','bool','json') NOT NULL DEFAULT 'string',
    is_public     TINYINT(1)   NOT NULL DEFAULT 0,  -- 1 = returned to unauthenticated clients (app name, branding)
    description   VARCHAR(255) NULL,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (setting_id),
    UNIQUE KEY uq_app_settings_key (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  10. HELPER VIEWS
-- =============================================================================

-- Effective limits per user: user override → active plan → default plan.
CREATE OR REPLACE VIEW v_user_effective_limits AS
SELECT
    u.user_id,
    p.plan_id,
    p.plan_name,
    COALESCE(ul.max_screens,    p.max_screens)    AS max_screens,
    COALESCE(ul.max_playlists,  p.max_playlists)  AS max_playlists,
    COALESCE(ul.max_devices,    p.max_devices)    AS max_devices,
    COALESCE(ul.max_storage_mb, p.max_storage_mb) AS max_storage_mb,
    COALESCE(ul.max_upload_mb,  p.max_upload_mb)  AS max_upload_mb,
    COALESCE(ul.allowed_widgets_json, JSON_EXTRACT(p.features_json, '$.allowed_widgets')) AS allowed_widgets_json,
    COALESCE(ul.can_upload_video,
             CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(p.features_json, '$.video_upload')) = 'true' THEN 1 ELSE 0 END) AS can_upload_video,
    COALESCE(ul.can_use_premium_templates,
             CASE WHEN JSON_UNQUOTE(JSON_EXTRACT(p.features_json, '$.premium_templates')) = 'true' THEN 1 ELSE 0 END) AS can_use_premium_templates
FROM users u
LEFT JOIN user_subscriptions s
       ON s.subscription_id = (
            SELECT s2.subscription_id FROM user_subscriptions s2
             WHERE s2.user_id = u.user_id AND s2.status = 'active'
               AND s2.start_date <= CURRENT_DATE
               AND (s2.end_date IS NULL OR s2.end_date >= CURRENT_DATE)
             ORDER BY s2.start_date DESC, s2.subscription_id DESC LIMIT 1)
LEFT JOIN plans p
       ON p.plan_id = COALESCE(s.plan_id, (SELECT plan_id FROM plans WHERE is_default = 1 ORDER BY plan_id LIMIT 1))
LEFT JOIN user_limits ul ON ul.user_id = u.user_id
WHERE u.deleted_at IS NULL;

-- Current usage per user (counts exclude soft-deleted rows).
CREATE OR REPLACE VIEW v_user_usage AS
SELECT
    u.user_id,
    (SELECT COUNT(*) FROM screens   sc WHERE sc.user_id = u.user_id AND sc.deleted_at IS NULL) AS screens_used,
    (SELECT COUNT(*) FROM playlists pl WHERE pl.user_id = u.user_id AND pl.deleted_at IS NULL) AS playlists_used,
    (SELECT COUNT(*) FROM devices   d  WHERE d.user_id  = u.user_id AND d.deleted_at  IS NULL) AS devices_used,
    (SELECT COALESCE(SUM(m.file_size),0) FROM media_library m WHERE m.user_id = u.user_id AND m.deleted_at IS NULL) AS storage_used_bytes
FROM users u
WHERE u.deleted_at IS NULL;
