-- =========================================================
-- Database for "my-website"
-- Import this file in phpMyAdmin (Import tab) or run:
--   mysql -u root -p < database.sql
-- =========================================================

CREATE DATABASE IF NOT EXISTS my_website
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE my_website;

-- Read this file as UTF-8 so emojis (🎨 ⚛️ 📱) are saved correctly
SET NAMES utf8mb4;

-- ---------- Services (shown on the Home page) ----------
CREATE TABLE IF NOT EXISTS services (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  icon       VARCHAR(10)  NOT NULL,
  title      VARCHAR(100) NOT NULL,
  text       VARCHAR(255) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0
);

-- ---------- Skills (progress bars on the About page) ----------
CREATE TABLE IF NOT EXISTS skills (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(100) NOT NULL,
  level      TINYINT UNSIGNED NOT NULL,   -- 0 to 100
  sort_order INT NOT NULL DEFAULT 0
);

-- ---------- Timeline (Experience on the About page) ----------
CREATE TABLE IF NOT EXISTS timeline (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  year       VARCHAR(10)  NOT NULL,
  title      VARCHAR(150) NOT NULL,
  text       VARCHAR(255) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0
);

-- ---------- Messages (saved from the Contact form) ----------
CREATE TABLE IF NOT EXISTS messages (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(100)  NOT NULL,
  email      VARCHAR(150)  NOT NULL,
  subject    VARCHAR(150)  NOT NULL DEFAULT '',
  message    TEXT          NOT NULL,
  created_at TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ---------- Starting data (only added if the tables are empty) ----------
INSERT INTO services (icon, title, text, sort_order)
SELECT * FROM (
  SELECT '🎨' AS icon, 'Web Design' AS title, 'Clean and modern layouts that look great on every screen size.' AS text, 1 AS sort_order
  UNION ALL SELECT '⚛️', 'React Development', 'Fast single-page apps built with reusable React components.', 2
  UNION ALL SELECT '📱', 'Responsive Websites', 'Websites that work perfectly on phones, tablets and laptops.', 3
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM services);

INSERT INTO skills (name, level, sort_order)
SELECT * FROM (
  SELECT 'HTML & CSS' AS name, 90 AS level, 1 AS sort_order
  UNION ALL SELECT 'JavaScript', 80, 2
  UNION ALL SELECT 'React', 75, 3
  UNION ALL SELECT 'PHP & MySQL', 65, 4
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM skills);

INSERT INTO timeline (year, title, text, sort_order)
SELECT * FROM (
  SELECT '2026' AS year, 'Frontend Developer (Freelance)' AS title, 'Building websites for small businesses with React.' AS text, 1 AS sort_order
  UNION ALL SELECT '2025', 'Learned React', 'Built my first portfolio, a todo app and a weather app.', 2
  UNION ALL SELECT '2024', 'Started Web Development', 'Learned HTML, CSS and JavaScript from free online courses.', 3
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM timeline);
