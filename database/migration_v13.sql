-- Migration v13: lightweight, self-hosted analytics (admin/analytics.php).
-- No IP addresses are stored; visitor_hash is a same-day salted hash used
-- only to de-duplicate a visitor's pageviews within one day.
CREATE TABLE IF NOT EXISTS page_visits (
    id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    url            VARCHAR(255) NOT NULL,
    source_type    ENUM('direct','search','social','referral','internal','campaign') NOT NULL DEFAULT 'direct',
    source_label   VARCHAR(100) DEFAULT NULL,
    search_keyword VARCHAR(255) DEFAULT NULL COMMENT 'only populated when the referrer/UTM discloses it — Google organic search does not',
    device_type    ENUM('desktop','mobile','tablet') NOT NULL DEFAULT 'desktop',
    browser        VARCHAR(50) DEFAULT NULL,
    visitor_hash   CHAR(64) NOT NULL,
    visit_date     DATE NOT NULL,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_visits_date (visit_date),
    KEY idx_visits_source (source_type),
    KEY idx_visits_device (device_type),
    KEY idx_visits_visitor (visitor_hash)
) ENGINE=InnoDB;
