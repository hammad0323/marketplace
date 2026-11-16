-- TV-safe video versions (run once on an existing database; new installs get this from schema.sql).
--   mysql -u root -p signage < backend/database/migrations/2026_11_16_tv_video_versions.sql
-- Then convert videos uploaded earlier:
--   php backend/cron/optimize_videos.php --all
ALTER TABLE media_library
    ADD COLUMN tv_path         VARCHAR(255)    NULL AFTER checksum_sha256,
    ADD COLUMN tv_file_size    BIGINT UNSIGNED NULL AFTER tv_path,
    ADD COLUMN tv_checksum     CHAR(64)        NULL AFTER tv_file_size,
    ADD COLUMN optimize_status ENUM('none','pending','processing','done','failed') NOT NULL DEFAULT 'none' AFTER tv_checksum,
    ADD COLUMN optimize_note   VARCHAR(255)    NULL AFTER optimize_status,
    ADD COLUMN optimized_at    DATETIME        NULL AFTER optimize_note,
    ADD KEY idx_media_optimize (optimize_status);
