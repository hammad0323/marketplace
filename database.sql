-- =====================================================================
-- Wedding Hall Management & Online Booking SaaS
-- Full database schema + demo data
-- Import via phpMyAdmin (Import tab) or: mysql -u USER -p DBNAME < database.sql
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- ---------------------------------------------------------------------
-- businesses (SaaS tenants — the platform can host many hall businesses)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS businesses (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_name VARCHAR(150) NOT NULL,
  slug VARCHAR(170) NOT NULL,
  owner_name VARCHAR(150) DEFAULT NULL,
  email VARCHAR(150) DEFAULT NULL,
  phone VARCHAR(30) DEFAULT NULL,
  plan ENUM('basic','professional','enterprise') NOT NULL DEFAULT 'basic',
  status ENUM('active','suspended','expired') NOT NULL DEFAULT 'active',
  expiry_date DATE DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_business_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- admin_users (super_admin has business_id = NULL)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id INT UNSIGNED DEFAULT NULL,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('super_admin','admin','manager','staff') NOT NULL DEFAULT 'staff',
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  last_login DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_admin_email (email),
  KEY idx_admin_business (business_id),
  CONSTRAINT fk_admin_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS password_resets (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  admin_user_id INT UNSIGNED NOT NULL,
  token VARCHAR(100) NOT NULL,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_reset_token (token),
  CONSTRAINT fk_reset_admin FOREIGN KEY (admin_user_id) REFERENCES admin_users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  email VARCHAR(150) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_attempt_email_time (email, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- halls
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS halls (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(170) NOT NULL,
  description TEXT,
  capacity_min INT UNSIGNED NOT NULL DEFAULT 0,
  capacity_max INT UNSIGNED NOT NULL DEFAULT 0,
  price_type ENUM('fixed','per_person') NOT NULL DEFAULT 'fixed',
  base_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  per_person_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  address VARCHAR(255) DEFAULT NULL,
  city VARCHAR(100) DEFAULT NULL,
  area VARCHAR(100) DEFAULT NULL,
  map_url VARCHAR(500) DEFAULT NULL,
  map_embed TEXT,
  latitude DECIMAL(10,7) DEFAULT NULL,
  longitude DECIMAL(10,7) DEFAULT NULL,
  featured_image VARCHAR(255) DEFAULT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  is_public TINYINT(1) NOT NULL DEFAULT 1,
  is_booking_enabled TINYINT(1) NOT NULL DEFAULT 1,
  seo_title VARCHAR(255) DEFAULT NULL,
  meta_description VARCHAR(500) DEFAULT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_hall_slug (business_id, slug),
  KEY idx_hall_business (business_id),
  CONSTRAINT fk_hall_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hall_facilities (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  hall_id INT UNSIGNED NOT NULL,
  facility_name VARCHAR(100) NOT NULL,
  icon VARCHAR(50) DEFAULT 'fa-circle-check',
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_facility_hall (hall_id),
  CONSTRAINT fk_facility_hall FOREIGN KEY (hall_id) REFERENCES halls(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS hall_images (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  hall_id INT UNSIGNED NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  title VARCHAR(150) DEFAULT NULL,
  alt_text VARCHAR(200) DEFAULT NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_hallimg_hall (hall_id),
  CONSTRAINT fk_hallimg_hall FOREIGN KEY (hall_id) REFERENCES halls(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- time_slots (admin-editable, never hard-coded)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS time_slots (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id INT UNSIGNED NOT NULL,
  name VARCHAR(50) NOT NULL,
  start_time TIME NOT NULL,
  end_time TIME NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_slot_business (business_id),
  CONSTRAINT fk_slot_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- event_types (admin-editable)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS event_types (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id INT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_eventtype_slug (business_id, slug),
  CONSTRAINT fk_eventtype_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- customers
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS customers (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  father_husband_name VARCHAR(150) DEFAULT NULL,
  phone VARCHAR(30) NOT NULL,
  whatsapp VARCHAR(30) DEFAULT NULL,
  email VARCHAR(150) DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
  cnic VARCHAR(20) DEFAULT NULL,
  notes TEXT,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_customer_business (business_id),
  KEY idx_customer_phone (phone),
  CONSTRAINT fk_customer_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- bookings
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS bookings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id INT UNSIGNED NOT NULL,
  booking_code VARCHAR(30) NOT NULL,
  hall_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  event_type_id INT UNSIGNED DEFAULT NULL,
  time_slot_id INT UNSIGNED NOT NULL,
  booking_date DATE NOT NULL,
  guests INT UNSIGNED NOT NULL DEFAULT 0,
  package_name VARCHAR(150) DEFAULT NULL,
  per_person_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  total_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  additional_charges DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  discount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  final_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  advance_required DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  booking_status ENUM('pending','confirmed','cancelled','completed','hold') NOT NULL DEFAULT 'pending',
  payment_status ENUM('unpaid','partial','paid','refunded') NOT NULL DEFAULT 'unpaid',
  source ENUM('admin','online') NOT NULL DEFAULT 'admin',
  next_payment_date DATE DEFAULT NULL,
  notes TEXT,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_booking_code (booking_code),
  KEY idx_booking_business (business_id),
  KEY idx_booking_date (booking_date),
  KEY idx_booking_hall_date (hall_id, booking_date),
  KEY idx_booking_customer (customer_id),
  KEY idx_booking_status (booking_status),
  CONSTRAINT fk_booking_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
  CONSTRAINT fk_booking_hall FOREIGN KEY (hall_id) REFERENCES halls(id) ON DELETE RESTRICT,
  CONSTRAINT fk_booking_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
  CONSTRAINT fk_booking_eventtype FOREIGN KEY (event_type_id) REFERENCES event_types(id) ON DELETE SET NULL,
  CONSTRAINT fk_booking_timeslot FOREIGN KEY (time_slot_id) REFERENCES time_slots(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- slot_locks: the single source of truth for "is this Hall+Date+Slot
-- taken". A row exists here ONLY for a booking that currently occupies
-- the slot (confirmed/hold/completed, or pending when the admin setting
-- hold_pending_slots is ON). The UNIQUE KEY is what makes double-booking
-- impossible even under a simultaneous-submit race condition.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS slot_locks (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id INT UNSIGNED NOT NULL,
  hall_id INT UNSIGNED NOT NULL,
  booking_date DATE NOT NULL,
  time_slot_id INT UNSIGNED NOT NULL,
  booking_id INT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_slot_lock (business_id, hall_id, booking_date, time_slot_id),
  KEY idx_lock_booking (booking_id),
  CONSTRAINT fk_lock_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- booking_payments
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS booking_payments (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id INT UNSIGNED NOT NULL,
  booking_id INT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  payment_date DATE NOT NULL,
  payment_method ENUM('cash','bank_transfer','jazzcash','easypaisa','card','other') NOT NULL DEFAULT 'cash',
  notes VARCHAR(255) DEFAULT NULL,
  created_by INT UNSIGNED DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_payment_booking (booking_id),
  CONSTRAINT fk_payment_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- gallery
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS gallery_categories (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id INT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_gallerycat_slug (business_id, slug),
  CONSTRAINT fk_gallerycat_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gallery (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id INT UNSIGNED NOT NULL,
  hall_id INT UNSIGNED DEFAULT NULL,
  category_id INT UNSIGNED DEFAULT NULL,
  image_path VARCHAR(255) NOT NULL,
  title VARCHAR(150) DEFAULT NULL,
  alt_text VARCHAR(200) DEFAULT NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_gallery_business (business_id),
  CONSTRAINT fk_gallery_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE,
  CONSTRAINT fk_gallery_hall FOREIGN KEY (hall_id) REFERENCES halls(id) ON DELETE SET NULL,
  CONSTRAINT fk_gallery_category FOREIGN KEY (category_id) REFERENCES gallery_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- banners (homepage hero carousel, admin-managed)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS banners (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id INT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  subheading VARCHAR(300) DEFAULT NULL,
  background_image VARCHAR(255) DEFAULT NULL,
  cta_text VARCHAR(80) DEFAULT NULL,
  cta_link VARCHAR(255) DEFAULT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_banner_business (business_id),
  CONSTRAINT fk_banner_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- pages (About / Contact / FAQ / Privacy / Terms editable content)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS pages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id INT UNSIGNED NOT NULL,
  page_key VARCHAR(50) NOT NULL,
  title VARCHAR(200) DEFAULT NULL,
  content LONGTEXT,
  seo_title VARCHAR(255) DEFAULT NULL,
  meta_description VARCHAR(500) DEFAULT NULL,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_page_key (business_id, page_key),
  CONSTRAINT fk_page_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS faqs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id INT UNSIGNED NOT NULL,
  question VARCHAR(255) NOT NULL,
  answer TEXT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  PRIMARY KEY (id),
  CONSTRAINT fk_faq_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- settings (key/value store, per business)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS settings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id INT UNSIGNED NOT NULL,
  setting_key VARCHAR(100) NOT NULL,
  setting_value LONGTEXT,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_setting_key (business_id, setting_key),
  CONSTRAINT fk_setting_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seo_settings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id INT UNSIGNED NOT NULL,
  page_key VARCHAR(100) NOT NULL,
  seo_title VARCHAR(255) DEFAULT NULL,
  meta_description VARCHAR(500) DEFAULT NULL,
  keywords VARCHAR(500) DEFAULT NULL,
  canonical_url VARCHAR(255) DEFAULT NULL,
  og_image VARCHAR(255) DEFAULT NULL,
  robots VARCHAR(50) NOT NULL DEFAULT 'index,follow',
  schema_json TEXT,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_seo_page (business_id, page_key),
  CONSTRAINT fk_seo_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- blog
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS blog_posts (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id INT UNSIGNED NOT NULL,
  title VARCHAR(200) NOT NULL,
  slug VARCHAR(220) NOT NULL,
  excerpt VARCHAR(500) DEFAULT NULL,
  content LONGTEXT,
  featured_image VARCHAR(255) DEFAULT NULL,
  category VARCHAR(100) DEFAULT NULL,
  status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  seo_title VARCHAR(255) DEFAULT NULL,
  meta_description VARCHAR(500) DEFAULT NULL,
  published_at DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_blog_slug (business_id, slug),
  CONSTRAINT fk_blog_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- chatbot_logs
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS chatbot_logs (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id INT UNSIGNED NOT NULL,
  admin_user_id INT UNSIGNED DEFAULT NULL,
  question VARCHAR(500) NOT NULL,
  answer TEXT,
  matched_intent VARCHAR(100) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_chatbot_business (business_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- notifications
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS notifications (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id INT UNSIGNED NOT NULL,
  type VARCHAR(50) NOT NULL,
  title VARCHAR(200) NOT NULL,
  message VARCHAR(500) DEFAULT NULL,
  link VARCHAR(255) DEFAULT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_notif_business (business_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contact_messages (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  business_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  email VARCHAR(150) DEFAULT NULL,
  phone VARCHAR(30) DEFAULT NULL,
  subject VARCHAR(200) DEFAULT NULL,
  message TEXT NOT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_contactmsg_business (business_id),
  CONSTRAINT fk_contactmsg_business FOREIGN KEY (business_id) REFERENCES businesses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- DEMO / SEED DATA
-- =====================================================================

INSERT INTO businesses (id, business_name, slug, owner_name, email, phone, plan, status, expiry_date) VALUES
(1, 'Royal Banquet Group', 'royal-banquet-group', 'Ahmed Raza', 'owner@royalbanquet.test', '+92 300 1234567', 'professional', 'active', '2027-12-31');

-- Passwords below are demo only — README documents forcing a change on first login.
-- Admin@12345 (role: admin, owner of the business)
-- Manager@12345 (role: manager)
INSERT INTO admin_users (id, business_id, name, email, password_hash, role, status, must_change_password) VALUES
(1, NULL, 'Platform Super Admin', 'superadmin@weddinghallsaas.test', '$2y$12$7kObAU8oZZRyC1KXImtsterayOnz7C2LQZLawIZrXPm6VGXZFR7Di', 'super_admin', 'active', 1),
(2, 1, 'Ahmed Raza', 'admin@royalbanquet.test', '$2y$12$7kObAU8oZZRyC1KXImtsterayOnz7C2LQZLawIZrXPm6VGXZFR7Di', 'admin', 'active', 1),
(3, 1, 'Hall Manager', 'manager@royalbanquet.test', '$2y$12$pj7D3A9j4ogvCnrOeJmqyetn3vlVkCqilkEuSmO/SzJTKgr2CFqVO', 'manager', 'active', 1);

INSERT INTO time_slots (id, business_id, name, start_time, end_time, sort_order, status) VALUES
(1, 1, 'Morning', '08:00:00', '13:00:00', 1, 'active'),
(2, 1, 'Evening', '14:00:00', '19:00:00', 2, 'active'),
(3, 1, 'Night', '20:00:00', '01:00:00', 3, 'active');

INSERT INTO event_types (id, business_id, name, slug, status, sort_order) VALUES
(1, 1, 'Wedding', 'wedding', 'active', 1),
(2, 1, 'Walima', 'walima', 'active', 2),
(3, 1, 'Mehndi', 'mehndi', 'active', 3),
(4, 1, 'Baraat', 'baraat', 'active', 4),
(5, 1, 'Engagement', 'engagement', 'active', 5),
(6, 1, 'Nikkah', 'nikkah', 'active', 6),
(7, 1, 'Birthday', 'birthday', 'active', 7),
(8, 1, 'Corporate Event', 'corporate-event', 'active', 8),
(9, 1, 'Seminar', 'seminar', 'active', 9),
(10, 1, 'Conference', 'conference', 'active', 10),
(11, 1, 'Other', 'other', 'active', 11);

INSERT INTO halls (id, business_id, name, slug, description, capacity_min, capacity_max, price_type, base_price, per_person_price, address, city, area, map_url, latitude, longitude, featured_image, status, is_public, is_booking_enabled, seo_title, meta_description, sort_order) VALUES
(1, 1, 'Royal Banquet Hall', 'royal-banquet-hall-karachi', 'Our flagship air-conditioned banquet hall with a grand entrance, crystal chandeliers and a dedicated bridal room — built for weddings of every scale.', 200, 800, 'per_person', 0.00, 3500.00, 'Shahrah-e-Faisal', 'Karachi', 'Shahrah-e-Faisal', 'https://maps.google.com/?q=Royal+Banquet+Hall+Karachi', 24.8600000, 67.0100000, 'uploads/halls/royal-banquet-hall.jpg', 'active', 1, 1, 'Royal Banquet Hall Karachi | Wedding & Walima Venue', 'Premium banquet hall in Karachi for weddings, walima and corporate events. Capacity up to 800 guests. Book online.', 1),
(2, 1, 'Pearl Marriage Hall', 'pearl-marriage-hall-lahore', 'An elegant marriage hall in the heart of Lahore, known for its spacious lawn and modern catering kitchen.', 150, 500, 'per_person', 0.00, 3000.00, 'Gulberg', 'Lahore', 'Gulberg', 'https://maps.google.com/?q=Pearl+Marriage+Hall+Lahore', 31.5200000, 74.3587000, 'uploads/halls/pearl-marriage-hall.jpg', 'active', 1, 1, 'Pearl Marriage Hall Lahore | Banquet & Event Venue', 'Book Pearl Marriage Hall in Gulberg, Lahore for wedding, mehndi and corporate functions.', 2),
(3, 1, 'Grand Event Hall', 'grand-event-hall-islamabad', 'A premium rooftop and indoor event hall in Islamabad with panoramic city views, ideal for intimate to mid-size events.', 80, 300, 'fixed', 450000.00, 0.00, 'Blue Area', 'Islamabad', 'Blue Area', 'https://maps.google.com/?q=Grand+Event+Hall+Islamabad', 33.7180000, 73.0620000, 'uploads/halls/grand-event-hall.jpg', 'active', 1, 1, 'Grand Event Hall Islamabad | Rooftop Wedding Venue', 'Grand Event Hall in Blue Area Islamabad — rooftop and indoor venue for weddings and corporate events.', 3);

INSERT INTO banners (business_id, title, subheading, background_image, cta_text, cta_link, sort_order, status) VALUES
(1, 'Unforgettable Weddings Start Here', 'Pakistan''s trusted wedding and event venues in Karachi, Lahore and Islamabad — book your date online in minutes.', '', 'Book a Hall', '/booking', 1, 'active'),
(1, 'Check Live Availability Instantly', 'See which hall, date and time slot is free before you commit — no phone calls needed.', '', 'Check Availability', '/availability', 2, 'active'),
(1, 'Premium Halls for Every Celebration', 'From intimate nikkah ceremonies to grand walima receptions with 800+ guests.', '', 'Explore Our Halls', '/halls', 3, 'active');

INSERT INTO hall_facilities (hall_id, facility_name, icon, sort_order) VALUES
(1, 'Air Conditioned Hall', 'fa-snowflake', 1),
(1, 'Bridal Room', 'fa-door-closed', 2),
(1, 'Car Parking', 'fa-square-parking', 3),
(1, 'Catering Kitchen', 'fa-utensils', 4),
(1, 'Sound System', 'fa-volume-high', 5),
(1, 'Stage & Lighting', 'fa-lightbulb', 6),
(2, 'Lawn & Indoor Hall', 'fa-tree', 1),
(2, 'Car Parking', 'fa-square-parking', 2),
(2, 'Catering Kitchen', 'fa-utensils', 3),
(2, 'Generator Backup', 'fa-bolt', 4),
(3, 'Rooftop View', 'fa-building', 1),
(3, 'Valet Parking', 'fa-square-parking', 2),
(3, 'Sound System', 'fa-volume-high', 3),
(3, 'Elevator Access', 'fa-elevator', 4);

INSERT INTO customers (id, business_id, name, father_husband_name, phone, whatsapp, email, address, cnic, notes) VALUES
(1, 1, 'Muhammad Ali', 'Abdul Karim', '+92 321 1111111', '+92 321 1111111', 'ali@example.test', 'DHA Phase 5, Karachi', '42101-1234567-1', 'Prefers night slot.'),
(2, 1, 'Ayesha Khan', 'Imran Khan', '+92 333 2222222', '+92 333 2222222', 'ayesha@example.test', 'Gulberg, Lahore', '35202-7654321-2', NULL),
(3, 1, 'Bilal Ahmed', 'Nasir Ahmed', '+92 345 3333333', '+92 345 3333333', 'bilal@example.test', 'F-10, Islamabad', NULL, NULL);

-- Sample bookings — demonstrates hall+date+slot uniqueness and mixed statuses.
INSERT INTO bookings (id, business_id, booking_code, hall_id, customer_id, event_type_id, time_slot_id, booking_date, guests, package_name, per_person_price, total_amount, additional_charges, discount, final_total, advance_required, paid_amount, balance, booking_status, payment_status, source, next_payment_date, notes) VALUES
(1, 1, 'WH-2026-000001', 1, 1, 2, 3, '2026-12-25', 350, 'Silver Walima Package', 3500.00, 1225000.00, 25000.00, 50000.00, 1200000.00, 300000.00, 250000.00, 950000.00, 'confirmed', 'partial', 'admin', '2026-11-25', 'Advance received in two installments.'),
(2, 1, 'WH-2026-000002', 2, 2, 1, 2, '2026-12-30', 500, 'Gold Wedding Package', 3000.00, 1500000.00, 0.00, 0.00, 1500000.00, 400000.00, 100000.00, 1400000.00, 'confirmed', 'partial', 'online', '2026-11-30', NULL),
(3, 1, 'WH-2026-000003', 1, 3, 3, 1, '2026-10-05', 200, 'Mehndi Package', 0.00, 350000.00, 0.00, 0.00, 350000.00, 100000.00, 350000.00, 0.00, 'completed', 'paid', 'admin', NULL, 'Paid in full.');

INSERT INTO slot_locks (business_id, hall_id, booking_date, time_slot_id, booking_id) VALUES
(1, 1, '2026-12-25', 3, 1),
(1, 2, '2026-12-30', 2, 2),
(1, 1, '2026-10-05', 1, 3);

INSERT INTO booking_payments (business_id, booking_id, amount, payment_date, payment_method, notes) VALUES
(1, 1, 150000.00, '2026-06-01', 'bank_transfer', 'First advance'),
(1, 1, 100000.00, '2026-08-01', 'easypaisa', 'Second installment'),
(1, 2, 100000.00, '2026-07-15', 'jazzcash', 'Booking advance'),
(1, 3, 350000.00, '2026-09-01', 'cash', 'Full payment received');

INSERT INTO gallery_categories (id, business_id, name, slug) VALUES
(1, 1, 'Hall Interior', 'hall-interior'),
(2, 1, 'Hall Exterior', 'hall-exterior'),
(3, 1, 'Wedding Setup', 'wedding-setup'),
(4, 1, 'Mehndi', 'mehndi'),
(5, 1, 'Walima', 'walima'),
(6, 1, 'Stage', 'stage'),
(7, 1, 'Dining', 'dining'),
(8, 1, 'Decoration', 'decoration'),
(9, 1, 'Other', 'other');

INSERT INTO pages (business_id, page_key, title, content, seo_title, meta_description) VALUES
(1, 'about', 'About Royal Banquet Group', '<p>Royal Banquet Group has been hosting unforgettable weddings and events across Pakistan for over a decade. From intimate nikkah ceremonies to grand walima receptions, our halls in Karachi, Lahore and Islamabad are designed to make every event memorable.</p>', 'About Us | Royal Banquet Group', 'Learn about Royal Banquet Group, a trusted name in wedding hall and banquet management across Pakistan.'),
(1, 'contact', 'Contact Us', '<p>Reach out to our booking team for a site visit or a custom package quote.</p>', 'Contact Us | Royal Banquet Group', 'Contact Royal Banquet Group for wedding hall bookings, packages and enquiries.'),
(1, 'privacy', 'Privacy Policy', '<p>We respect your privacy. Personal details you submit through our booking form are used only to process your booking request and are never shared publicly.</p>', 'Privacy Policy | Royal Banquet Group', 'Privacy policy for Royal Banquet Group online booking website.'),
(1, 'terms', 'Terms & Conditions', '<p>A booking is confirmed only after admin confirmation and receipt of the minimum advance payment. Cancellation policies apply as communicated at the time of booking.</p>', 'Terms & Conditions | Royal Banquet Group', 'Terms and conditions for booking a hall with Royal Banquet Group.');

INSERT INTO faqs (business_id, question, answer, sort_order, status) VALUES
(1, 'How do I book a hall online?', 'Select a hall, pick an available date and time slot on our Availability page, fill in your details and submit. Our team will confirm your booking shortly after.', 1, 'active'),
(1, 'What is the advance payment required?', 'The minimum advance varies per hall and package, and is shown at the time of booking. It is required to confirm your reserved date and slot.', 2, 'active'),
(1, 'Can I change my event date after booking?', 'Please contact our booking office as soon as possible. A date change is subject to availability of the new date/slot.', 3, 'active'),
(1, 'What payment methods do you accept?', 'We accept Cash, Bank Transfer, JazzCash, Easypaisa and Card payments.', 4, 'active');

INSERT INTO blog_posts (business_id, title, slug, excerpt, content, category, status, seo_title, meta_description, published_at) VALUES
(1, 'A Complete Guide to Booking a Wedding Hall in Pakistan', 'wedding-hall-booking-guide', 'Everything you need to know before booking a marriage hall for your big day.', '<p>Booking the right wedding hall involves checking guest capacity, catering options, parking and — most importantly — date and time slot availability well in advance...</p>', 'Guides', 'published', 'Wedding Hall Booking Guide Pakistan', 'A complete guide to booking wedding halls in Pakistan — capacity, packages, and what to check before you pay an advance.', NOW());

INSERT INTO settings (business_id, setting_key, setting_value) VALUES
(1, 'site_name', 'Royal Banquet Group'),
(1, 'tagline', 'Pakistan''s Premier Wedding & Event Venues'),
(1, 'logo', ''),
(1, 'favicon', ''),
(1, 'email', 'info@royalbanquet.test'),
(1, 'phone', '+92 21 1234567'),
(1, 'whatsapp', '+92 300 1234567'),
(1, 'address', 'Shahrah-e-Faisal, Karachi, Pakistan'),
(1, 'city', 'Karachi'),
(1, 'google_maps_url', 'https://maps.google.com/?q=Royal+Banquet+Hall+Karachi'),
(1, 'google_maps_embed', ''),
(1, 'facebook', 'https://facebook.com/royalbanquetgroup'),
(1, 'instagram', 'https://instagram.com/royalbanquetgroup'),
(1, 'youtube', ''),
(1, 'tiktok', ''),
(1, 'opening_hours', 'Daily: 9:00 AM - 10:00 PM'),
(1, 'primary_color', '#7a1f3d'),
(1, 'secondary_color', '#c79a4b'),
(1, 'footer_text', 'Making your celebrations unforgettable since day one.'),
(1, 'copyright', '© 2026 Royal Banquet Group. All rights reserved.'),
(1, 'allow_online_booking', '1'),
(1, 'require_admin_confirmation', '1'),
(1, 'show_public_availability', '1'),
(1, 'allow_hall_select', '1'),
(1, 'allow_timeslot_select', '1'),
(1, 'enable_advance_payment', '1'),
(1, 'minimum_advance_percent', '20'),
(1, 'enable_payment_tracking', '1'),
(1, 'pending_booking_expiry_hours', '48'),
(1, 'cancellation_policy', 'Advance payment is non-refundable within 30 days of the event date.'),
(1, 'hold_pending_slots', '1'),
(1, 'ga_id', ''),
(1, 'gtm_id', ''),
(1, 'gsc_verification', ''),
(1, 'bing_verification', '');

INSERT INTO seo_settings (business_id, page_key, seo_title, meta_description, keywords, robots) VALUES
(1, 'home', 'Royal Banquet Group | Wedding Halls in Karachi, Lahore & Islamabad', 'Book premium wedding halls, marriage halls and banquet venues across Pakistan. Check live availability and book online.', 'wedding halls Pakistan, marriage hall booking, banquet hall Karachi, wedding venue Lahore', 'index,follow'),
(1, 'halls', 'Our Halls | Royal Banquet Group', 'Explore our banquet halls in Karachi, Lahore and Islamabad with capacity, packages and facilities.', 'banquet halls, marriage halls Pakistan', 'index,follow'),
(1, 'gallery', 'Gallery | Royal Banquet Group', 'Browse photos of our wedding halls, stages and event setups.', 'wedding hall gallery Pakistan', 'index,follow'),
(1, 'availability', 'Check Availability | Royal Banquet Group', 'Check real-time hall availability by date and time slot before you book.', 'wedding hall availability Pakistan', 'index,follow'),
(1, 'booking', 'Book Online | Royal Banquet Group', 'Book your wedding hall online in minutes. Select hall, date and time slot.', 'online wedding hall booking Pakistan', 'index,follow'),
(1, 'contact', 'Contact Us | Royal Banquet Group', 'Get in touch with Royal Banquet Group for bookings and enquiries.', 'contact wedding hall Pakistan', 'index,follow'),
(1, 'blog', 'Blog | Royal Banquet Group', 'Wedding planning tips, guides and news from Royal Banquet Group.', 'wedding blog Pakistan', 'index,follow');
