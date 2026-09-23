-- Migration v11: per-doctor social media links, shown as icons on the
-- public profile only when set (mirrors the existing site-wide social
-- links pattern in site_settings / includes/footer.php).
ALTER TABLE doctors
    ADD COLUMN facebook_url  VARCHAR(255) DEFAULT NULL AFTER longitude,
    ADD COLUMN twitter_url   VARCHAR(255) DEFAULT NULL AFTER facebook_url,
    ADD COLUMN instagram_url VARCHAR(255) DEFAULT NULL AFTER twitter_url,
    ADD COLUMN linkedin_url  VARCHAR(255) DEFAULT NULL AFTER instagram_url;
