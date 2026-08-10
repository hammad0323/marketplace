-- =====================================================================
-- Wanderly — Trip Planning & Multi-Service Travel Marketplace
-- Phase 1 database schema (core architecture)
-- Engine: InnoDB, Charset: utf8mb4
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- ACCESS CONTROL
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL,
  slug VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS permissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS role_permissions (
  role_id INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  role_id INT UNSIGNED NOT NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  phone VARCHAR(30) DEFAULT NULL,
  password_hash VARCHAR(255) NOT NULL,
  avatar VARCHAR(255) DEFAULT NULL,
  status ENUM('active','blocked','deleted') NOT NULL DEFAULT 'active',
  email_verified_at DATETIME DEFAULT NULL,
  last_login_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id),
  INDEX idx_users_role (role_id),
  INDEX idx_users_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS password_resets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token VARCHAR(128) NOT NULL,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_pwreset_token (token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS login_attempts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(150) NOT NULL,
  ip_address VARCHAR(45) NOT NULL,
  success TINYINT(1) NOT NULL DEFAULT 0,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_attempts_lookup (email, ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- GEOGRAPHY
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS countries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  code VARCHAR(5) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS states (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  country_id INT UNSIGNED NOT NULL,
  name VARCHAR(100) NOT NULL,
  FOREIGN KEY (country_id) REFERENCES countries(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS cities (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  country_id INT UNSIGNED NOT NULL,
  state_id INT UNSIGNED DEFAULT NULL,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE,
  image VARCHAR(255) DEFAULT NULL,
  description TEXT,
  latitude DECIMAL(10,7) DEFAULT NULL,
  longitude DECIMAL(10,7) DEFAULT NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  seo_title VARCHAR(180) DEFAULT NULL,
  seo_description VARCHAR(300) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (country_id) REFERENCES countries(id),
  FOREIGN KEY (state_id) REFERENCES states(id),
  INDEX idx_cities_active_featured (is_active, is_featured),
  INDEX idx_cities_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS currencies (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(5) NOT NULL UNIQUE,
  symbol VARCHAR(5) NOT NULL,
  name VARCHAR(50) NOT NULL,
  exchange_rate DECIMAL(12,6) NOT NULL DEFAULT 1,
  is_default TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- CATEGORIES (dynamic, admin-managed, self-referencing for subcategories)
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  parent_id INT UNSIGNED DEFAULT NULL,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE,
  icon VARCHAR(100) DEFAULT NULL,
  image VARCHAR(255) DEFAULT NULL,
  description TEXT,
  seo_title VARCHAR(180) DEFAULT NULL,
  seo_description VARCHAR(300) DEFAULT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL,
  INDEX idx_categories_parent (parent_id),
  INDEX idx_categories_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS category_fields (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NOT NULL,
  field_label VARCHAR(100) NOT NULL,
  field_key VARCHAR(100) NOT NULL,
  field_type ENUM('text','number','textarea','select','checkbox','date','time') NOT NULL DEFAULT 'text',
  field_options TEXT DEFAULT NULL,
  is_required TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
  INDEX idx_catfields_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS category_filters (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NOT NULL,
  filter_label VARCHAR(100) NOT NULL,
  filter_key VARCHAR(100) NOT NULL,
  filter_type ENUM('range','select','checkbox','radio') NOT NULL DEFAULT 'select',
  filter_options TEXT DEFAULT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
  INDEX idx_catfilters_category (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS amenities (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  icon VARCHAR(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- MEMBERSHIP / BADGES
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS membership_plans (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  billing_cycle ENUM('monthly','yearly','lifetime') NOT NULL DEFAULT 'monthly',
  max_services INT UNSIGNED DEFAULT NULL,
  max_gallery_images INT UNSIGNED DEFAULT NULL,
  priority_ranking INT UNSIGNED NOT NULL DEFAULT 0,
  features TEXT DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS provider_badges (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  icon VARCHAR(100) DEFAULT NULL,
  color VARCHAR(20) DEFAULT '#8B5CF6',
  priority INT UNSIGNED NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- PROVIDERS
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS providers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  business_name VARCHAR(150) NOT NULL,
  slug VARCHAR(180) NOT NULL UNIQUE,
  category_id INT UNSIGNED DEFAULT NULL,
  city_id INT UNSIGNED DEFAULT NULL,
  address VARCHAR(255) DEFAULT NULL,
  latitude DECIMAL(10,7) DEFAULT NULL,
  longitude DECIMAL(10,7) DEFAULT NULL,
  description TEXT,
  website VARCHAR(255) DEFAULT NULL,
  social_links TEXT DEFAULT NULL,
  logo VARCHAR(255) DEFAULT NULL,
  cover_image VARCHAR(255) DEFAULT NULL,
  status ENUM('pending','approved','rejected','suspended','blocked') NOT NULL DEFAULT 'pending',
  is_verified TINYINT(1) NOT NULL DEFAULT 0,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  membership_plan_id INT UNSIGNED DEFAULT NULL,
  commission_rate DECIMAL(5,2) DEFAULT NULL,
  show_phone TINYINT(1) NOT NULL DEFAULT 1,
  show_email TINYINT(1) NOT NULL DEFAULT 1,
  show_address TINYINT(1) NOT NULL DEFAULT 1,
  show_calendar TINYINT(1) NOT NULL DEFAULT 1,
  show_pricing TINYINT(1) NOT NULL DEFAULT 1,
  show_reviews TINYINT(1) NOT NULL DEFAULT 1,
  show_gallery TINYINT(1) NOT NULL DEFAULT 1,
  show_map TINYINT(1) NOT NULL DEFAULT 1,
  avg_rating DECIMAL(3,2) NOT NULL DEFAULT 0,
  review_count INT UNSIGNED NOT NULL DEFAULT 0,
  profile_views INT UNSIGNED NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id),
  FOREIGN KEY (city_id) REFERENCES cities(id),
  FOREIGN KEY (membership_plan_id) REFERENCES membership_plans(id),
  INDEX idx_providers_status (status),
  INDEX idx_providers_city (city_id),
  INDEX idx_providers_category (category_id),
  INDEX idx_providers_featured (is_featured)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS provider_documents (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider_id INT UNSIGNED NOT NULL,
  doc_type VARCHAR(100) NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS provider_badge_map (
  provider_id INT UNSIGNED NOT NULL,
  badge_id INT UNSIGNED NOT NULL,
  assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (provider_id, badge_id),
  FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
  FOREIGN KEY (badge_id) REFERENCES provider_badges(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS provider_memberships (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider_id INT UNSIGNED NOT NULL,
  plan_id INT UNSIGNED NOT NULL,
  starts_at DATETIME NOT NULL,
  ends_at DATETIME DEFAULT NULL,
  status ENUM('active','expired','cancelled') NOT NULL DEFAULT 'active',
  payment_id INT UNSIGNED DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
  FOREIGN KEY (plan_id) REFERENCES membership_plans(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- SERVICES
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS services (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  provider_id INT UNSIGNED NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  city_id INT UNSIGNED DEFAULT NULL,
  title VARCHAR(180) NOT NULL,
  slug VARCHAR(200) NOT NULL UNIQUE,
  short_description VARCHAR(300) DEFAULT NULL,
  description TEXT,
  address VARCHAR(255) DEFAULT NULL,
  latitude DECIMAL(10,7) DEFAULT NULL,
  longitude DECIMAL(10,7) DEFAULT NULL,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  price_unit ENUM('hour','day','night','person','fixed') NOT NULL DEFAULT 'fixed',
  max_guests INT UNSIGNED DEFAULT NULL,
  status ENUM('pending','approved','rejected','hidden') NOT NULL DEFAULT 'pending',
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  avg_rating DECIMAL(3,2) NOT NULL DEFAULT 0,
  review_count INT UNSIGNED NOT NULL DEFAULT 0,
  view_count INT UNSIGNED NOT NULL DEFAULT 0,
  cancellation_policy TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id),
  FOREIGN KEY (city_id) REFERENCES cities(id),
  INDEX idx_services_status (status),
  INDEX idx_services_city (city_id),
  INDEX idx_services_category (category_id),
  INDEX idx_services_price (price),
  INDEX idx_services_featured (is_featured)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS service_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_id INT UNSIGNED NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  is_cover TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
  INDEX idx_service_images_service (service_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS service_videos (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_id INT UNSIGNED NOT NULL,
  video_url VARCHAR(255) NOT NULL,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS service_amenity_map (
  service_id INT UNSIGNED NOT NULL,
  amenity_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (service_id, amenity_id),
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
  FOREIGN KEY (amenity_id) REFERENCES amenities(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS service_field_values (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_id INT UNSIGNED NOT NULL,
  category_field_id INT UNSIGNED NOT NULL,
  field_value TEXT,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
  FOREIGN KEY (category_field_id) REFERENCES category_fields(id) ON DELETE CASCADE,
  INDEX idx_fieldvalues_service (service_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS service_availability (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_id INT UNSIGNED NOT NULL,
  date DATE NOT NULL,
  start_time TIME DEFAULT NULL,
  end_time TIME DEFAULT NULL,
  status ENUM('available','blocked','reserved') NOT NULL DEFAULT 'available',
  price_override DECIMAL(10,2) DEFAULT NULL,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
  INDEX idx_availability_service_date (service_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- BOOKINGS / RESERVATIONS
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS bookings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_ref VARCHAR(30) NOT NULL UNIQUE,
  service_id INT UNSIGNED NOT NULL,
  customer_id INT UNSIGNED NOT NULL,
  provider_id INT UNSIGNED NOT NULL,
  date_from DATE NOT NULL,
  date_to DATE DEFAULT NULL,
  start_time TIME DEFAULT NULL,
  end_time TIME DEFAULT NULL,
  guests INT UNSIGNED NOT NULL DEFAULT 1,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  base_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  service_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  commission_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
  status ENUM('pending','accepted','rejected','paid','confirmed','completed','cancelled','refunded','expired') NOT NULL DEFAULT 'pending',
  customer_notes TEXT DEFAULT NULL,
  provider_notes TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (service_id) REFERENCES services(id),
  FOREIGN KEY (customer_id) REFERENCES users(id),
  FOREIGN KEY (provider_id) REFERENCES providers(id),
  INDEX idx_bookings_status (status),
  INDEX idx_bookings_customer (customer_id),
  INDEX idx_bookings_provider (provider_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS booking_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  booking_id INT UNSIGNED NOT NULL,
  label VARCHAR(150) NOT NULL,
  quantity INT UNSIGNED NOT NULL DEFAULT 1,
  unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  total_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- PAYMENTS / FINANCE
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS payment_gateways (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  api_key VARCHAR(255) DEFAULT NULL,
  secret_key VARCHAR(255) DEFAULT NULL,
  currency VARCHAR(5) DEFAULT 'USD',
  mode ENUM('sandbox','live') NOT NULL DEFAULT 'sandbox',
  webhook_secret VARCHAR(255) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  booking_id INT UNSIGNED DEFAULT NULL,
  membership_id INT UNSIGNED DEFAULT NULL,
  plan_id INT UNSIGNED DEFAULT NULL,
  gateway_id INT UNSIGNED DEFAULT NULL,
  amount DECIMAL(10,2) NOT NULL,
  currency VARCHAR(5) NOT NULL DEFAULT 'USD',
  status ENUM('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  transaction_ref VARCHAR(150) DEFAULT NULL,
  paid_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (booking_id) REFERENCES bookings(id),
  FOREIGN KEY (membership_id) REFERENCES provider_memberships(id),
  FOREIGN KEY (plan_id) REFERENCES membership_plans(id),
  FOREIGN KEY (gateway_id) REFERENCES payment_gateways(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS transactions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  payment_id INT UNSIGNED NOT NULL,
  type ENUM('charge','refund','payout') NOT NULL DEFAULT 'charge',
  amount DECIMAL(10,2) NOT NULL,
  meta TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS commissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED DEFAULT NULL,
  provider_id INT UNSIGNED DEFAULT NULL,
  service_id INT UNSIGNED DEFAULT NULL,
  rate_percent DECIMAL(5,2) DEFAULT NULL,
  flat_fee DECIMAL(10,2) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE CASCADE,
  FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS taxes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  percent DECIMAL(5,2) NOT NULL DEFAULT 0,
  applies_to ENUM('all','booking','membership') NOT NULL DEFAULT 'all',
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS coupons (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(50) NOT NULL UNIQUE,
  discount_type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  discount_value DECIMAL(10,2) NOT NULL,
  max_uses INT UNSIGNED DEFAULT NULL,
  used_count INT UNSIGNED NOT NULL DEFAULT 0,
  min_amount DECIMAL(10,2) DEFAULT NULL,
  valid_from DATE DEFAULT NULL,
  valid_to DATE DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS coupon_usage (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  coupon_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  booking_id INT UNSIGNED DEFAULT NULL,
  used_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- REVIEWS / FAVORITES
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS reviews (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_id INT UNSIGNED NOT NULL,
  booking_id INT UNSIGNED DEFAULT NULL,
  customer_id INT UNSIGNED NOT NULL,
  rating TINYINT UNSIGNED NOT NULL,
  title VARCHAR(150) DEFAULT NULL,
  review_text TEXT,
  provider_response TEXT DEFAULT NULL,
  status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
  FOREIGN KEY (customer_id) REFERENCES users(id),
  INDEX idx_reviews_service (service_id),
  INDEX idx_reviews_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS review_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  review_id INT UNSIGNED NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  FOREIGN KEY (review_id) REFERENCES reviews(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS favorites (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  favoritable_type ENUM('service','provider','city','trip') NOT NULL,
  favoritable_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  UNIQUE KEY uniq_favorite (user_id, favoritable_type, favoritable_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- MESSAGING / NOTIFICATIONS
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS conversations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  provider_id INT UNSIGNED NOT NULL,
  service_id INT UNSIGNED DEFAULT NULL,
  booking_id INT UNSIGNED DEFAULT NULL,
  last_message_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (provider_id) REFERENCES providers(id) ON DELETE CASCADE,
  INDEX idx_conversations_customer (customer_id),
  INDEX idx_conversations_provider (provider_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS messages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT UNSIGNED NOT NULL,
  sender_id INT UNSIGNED NOT NULL,
  message_text TEXT,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (conversation_id) REFERENCES conversations(id) ON DELETE CASCADE,
  INDEX idx_messages_conversation (conversation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS message_attachments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  message_id INT UNSIGNED NOT NULL,
  file_path VARCHAR(255) NOT NULL,
  FOREIGN KEY (message_id) REFERENCES messages(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  type VARCHAR(50) NOT NULL,
  title VARCHAR(180) NOT NULL,
  message TEXT,
  link VARCHAR(255) DEFAULT NULL,
  is_read TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_notifications_user (user_id, is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- TRIP PLANNER
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS trips (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  trip_name VARCHAR(150) NOT NULL,
  destination_city_id INT UNSIGNED DEFAULT NULL,
  date_from DATE DEFAULT NULL,
  date_to DATE DEFAULT NULL,
  adults INT UNSIGNED NOT NULL DEFAULT 1,
  children INT UNSIGNED NOT NULL DEFAULT 0,
  budget_mode ENUM('economy','standard','luxury','custom') NOT NULL DEFAULT 'standard',
  max_budget DECIMAL(10,2) DEFAULT NULL,
  status ENUM('draft','planned','completed','cancelled') NOT NULL DEFAULT 'draft',
  visibility ENUM('private','public') NOT NULL DEFAULT 'private',
  share_token VARCHAR(64) DEFAULT NULL,
  notes TEXT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (destination_city_id) REFERENCES cities(id),
  INDEX idx_trips_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS trip_days (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  trip_id INT UNSIGNED NOT NULL,
  day_number INT UNSIGNED NOT NULL,
  day_date DATE DEFAULT NULL,
  FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
  INDEX idx_tripdays_trip (trip_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS trip_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  trip_day_id INT UNSIGNED NOT NULL,
  service_id INT UNSIGNED DEFAULT NULL,
  custom_title VARCHAR(180) DEFAULT NULL,
  item_type VARCHAR(50) DEFAULT NULL,
  start_time TIME DEFAULT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  notes TEXT,
  FOREIGN KEY (trip_day_id) REFERENCES trip_days(id) ON DELETE CASCADE,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS trip_budget (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  trip_id INT UNSIGNED NOT NULL,
  hotel_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  transport_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  food_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  activities_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  fees_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  tax_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  estimated_total DECIMAL(10,2) NOT NULL DEFAULT 0,
  calculated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS trip_services (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  trip_id INT UNSIGNED NOT NULL,
  service_id INT UNSIGNED NOT NULL,
  added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
  FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- CONTENT / CMS / SEO
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS blog_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS blog_tags (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS blogs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  author_id INT UNSIGNED DEFAULT NULL,
  category_id INT UNSIGNED DEFAULT NULL,
  title VARCHAR(200) NOT NULL,
  slug VARCHAR(220) NOT NULL UNIQUE,
  featured_image VARCHAR(255) DEFAULT NULL,
  excerpt VARCHAR(300) DEFAULT NULL,
  content LONGTEXT,
  status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  seo_title VARCHAR(180) DEFAULT NULL,
  seo_description VARCHAR(300) DEFAULT NULL,
  published_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS blog_tag_map (
  blog_id INT UNSIGNED NOT NULL,
  tag_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (blog_id, tag_id),
  FOREIGN KEY (blog_id) REFERENCES blogs(id) ON DELETE CASCADE,
  FOREIGN KEY (tag_id) REFERENCES blog_tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(150) NOT NULL,
  slug VARCHAR(180) NOT NULL UNIQUE,
  content LONGTEXT,
  seo_title VARCHAR(180) DEFAULT NULL,
  seo_description VARCHAR(300) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS menus (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  location ENUM('header','footer') NOT NULL DEFAULT 'header'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS menu_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  menu_id INT UNSIGNED NOT NULL,
  parent_id INT UNSIGNED DEFAULT NULL,
  label VARCHAR(100) NOT NULL,
  url VARCHAR(255) NOT NULL,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  FOREIGN KEY (menu_id) REFERENCES menus(id) ON DELETE CASCADE,
  FOREIGN KEY (parent_id) REFERENCES menu_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS homepage_sections (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  section_key VARCHAR(100) NOT NULL UNIQUE,
  title VARCHAR(150) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT UNSIGNED NOT NULL DEFAULT 0,
  settings TEXT DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(150) NOT NULL UNIQUE,
  setting_value LONGTEXT,
  setting_group VARCHAR(100) DEFAULT 'general'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS email_templates (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_key VARCHAR(100) NOT NULL UNIQUE,
  subject VARCHAR(200) NOT NULL,
  body_html LONGTEXT,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS email_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  template_key VARCHAR(100) DEFAULT NULL,
  to_email VARCHAR(150) NOT NULL,
  subject VARCHAR(255) NOT NULL,
  body_html LONGTEXT,
  status ENUM('sent','failed','logged_only') NOT NULL DEFAULT 'logged_only',
  error TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_email_log_to (to_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS seo_settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  page_type VARCHAR(50) NOT NULL,
  page_reference_id INT UNSIGNED DEFAULT NULL,
  meta_title VARCHAR(180) DEFAULT NULL,
  meta_description VARCHAR(300) DEFAULT NULL,
  meta_keywords VARCHAR(255) DEFAULT NULL,
  og_image VARCHAR(255) DEFAULT NULL,
  canonical_url VARCHAR(255) DEFAULT NULL,
  INDEX idx_seo_page (page_type, page_reference_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- LOGS / SUPPORT
-- ---------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS activity_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED DEFAULT NULL,
  action VARCHAR(100) NOT NULL,
  description VARCHAR(255) DEFAULT NULL,
  ip_address VARCHAR(45) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_activity_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS audit_logs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  admin_id INT UNSIGNED DEFAULT NULL,
  entity_type VARCHAR(100) NOT NULL,
  entity_id INT UNSIGNED DEFAULT NULL,
  action VARCHAR(100) NOT NULL,
  old_value TEXT DEFAULT NULL,
  new_value TEXT DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS support_tickets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  subject VARCHAR(180) NOT NULL,
  message TEXT,
  status ENUM('open','pending','closed') NOT NULL DEFAULT 'open',
  priority ENUM('low','normal','high') NOT NULL DEFAULT 'normal',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS newsletter_subscribers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(150) NOT NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS support_ticket_replies (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ticket_id INT UNSIGNED NOT NULL,
  sender_id INT UNSIGNED NOT NULL,
  message TEXT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
-- SEED DATA
-- =====================================================================

INSERT INTO roles (id, name, slug) VALUES
  (1, 'Admin', 'admin'),
  (2, 'Provider', 'provider'),
  (3, 'Customer', 'customer');

-- Admin user — email: admin@wanderly.test / password: Admin@12345
INSERT INTO users (id, role_id, name, email, password_hash, status, email_verified_at) VALUES
  (1, 1, 'Platform Admin', 'admin@wanderly.test', '$2y$12$xgdcHMNl4PdGfVAp5Z69Te1iOYj0C1DmpMbIGUSptxegDR8MvtZvu', 'active', NOW());

INSERT INTO currencies (code, symbol, name, exchange_rate, is_default) VALUES
  ('USD', '$', 'US Dollar', 1.000000, 1);

INSERT INTO countries (id, name, code) VALUES
  (1, 'Pakistan', 'PK'), (2, 'United Arab Emirates', 'AE'), (3, 'United Kingdom', 'GB'), (4, 'France', 'FR'), (5, 'Turkey', 'TR'), (6, 'United States', 'US');

INSERT INTO cities (country_id, name, slug, description, latitude, longitude, is_featured, is_active, sort_order, image) VALUES
  (1, 'Karachi', 'karachi', 'Pakistan''s largest city and economic hub, with a long coastline and vibrant food scene.', 24.8607, 67.0011, 1, 1, 1, NULL),
  (1, 'Lahore', 'lahore', 'A city of history, culture, and legendary hospitality.', 31.5497, 74.3436, 1, 1, 2, NULL),
  (1, 'Islamabad', 'islamabad', 'Pakistan''s green, planned capital at the foot of the Margalla Hills.', 33.6844, 73.0479, 1, 1, 3, NULL),
  (2, 'Dubai', 'dubai', 'Ultra-modern skyline, desert adventures, and luxury experiences.', 25.2048, 55.2708, 1, 1, 4, NULL),
  (3, 'London', 'london', 'Iconic landmarks, world-class museums, and endless things to do.', 51.5072, -0.1276, 1, 1, 5, NULL),
  (4, 'Paris', 'paris', 'The City of Light — art, cuisine, and romance around every corner.', 48.8566, 2.3522, 1, 1, 6, NULL),
  (5, 'Istanbul', 'istanbul', 'Where East meets West across the Bosphorus.', 41.0082, 28.9784, 1, 1, 7, NULL),
  (6, 'New York', 'new-york', 'The city that never sleeps.', 40.7128, -74.0060, 1, 1, 8, NULL);

INSERT INTO categories (id, name, slug, icon, description, sort_order, is_active) VALUES
  (1, 'Hotels', 'hotels', 'bi-building', 'Hotels, resorts, guest houses, apartments and villas.', 1, 1),
  (2, 'Rent a Car', 'rent-a-car', 'bi-car-front', 'Cars, SUVs, luxury vehicles and vans with or without a driver.', 2, 1),
  (3, 'Restaurants', 'restaurants', 'bi-cup-hot', 'Restaurants and cafes for every cuisine and budget.', 3, 1),
  (4, 'Tours & Activities', 'tours-activities', 'bi-map', 'Guided tours, adventure activities and local experiences.', 4, 1),
  (5, 'Airport Transfers', 'airport-transfers', 'bi-airplane', 'Reliable pickup and drop-off transport.', 5, 1),
  (6, 'Buses & Coaches', 'buses-coaches', 'bi-bus-front', 'Group transport for tours and events.', 6, 1);

INSERT INTO category_fields (category_id, field_label, field_key, field_type, is_required, sort_order) VALUES
  (1, 'Room Type', 'room_type', 'select', 1, 1),
  (1, 'Beds', 'beds', 'number', 1, 2),
  (1, 'Max Guests', 'max_guests', 'number', 1, 3),
  (1, 'Check-in Time', 'check_in', 'time', 0, 4),
  (1, 'Check-out Time', 'check_out', 'time', 0, 5),
  (2, 'Car Brand', 'car_brand', 'text', 1, 1),
  (2, 'Model Year', 'model_year', 'number', 0, 2),
  (2, 'Seats', 'seats', 'number', 1, 3),
  (2, 'Transmission', 'transmission', 'select', 0, 4),
  (2, 'Driver Included', 'driver_included', 'checkbox', 0, 5),
  (3, 'Cuisine', 'cuisine', 'text', 1, 1),
  (3, 'Seating Capacity', 'seating_capacity', 'number', 0, 2),
  (3, 'Opening Hours', 'opening_hours', 'text', 0, 3);

INSERT INTO amenities (name, icon) VALUES
  ('Free WiFi', 'bi-wifi'), ('Swimming Pool', 'bi-water'), ('Parking', 'bi-p-square'),
  ('Breakfast Included', 'bi-egg-fried'), ('Air Conditioning', 'bi-snow'), ('Pet Friendly', 'bi-heart');

INSERT INTO membership_plans (name, price, billing_cycle, max_services, max_gallery_images, priority_ranking, is_active, sort_order) VALUES
  ('Free', 0.00, 'monthly', 3, 5, 0, 1, 1),
  ('Professional', 29.00, 'monthly', 20, 20, 10, 1, 2),
  ('Premium', 79.00, 'monthly', NULL, 50, 20, 1, 3);

INSERT INTO provider_badges (name, slug, icon, color, priority, is_active) VALUES
  ('Verified', 'verified', 'bi-patch-check-fill', '#8B5CF6', 10, 1),
  ('Featured', 'featured', 'bi-star-fill', '#F59E0B', 8, 1),
  ('Top Rated', 'top-rated', 'bi-trophy-fill', '#10B981', 6, 1);

INSERT INTO taxes (name, percent, applies_to, is_active) VALUES ('Service Tax', 5.00, 'all', 1);

INSERT INTO payment_gateways (name, slug, currency, mode, is_active) VALUES
  ('Stripe', 'stripe', 'USD', 'sandbox', 0),
  ('PayPal', 'paypal', 'USD', 'sandbox', 0),
  ('Bank Transfer', 'bank-transfer', 'USD', 'live', 1);

INSERT INTO homepage_sections (section_key, title, is_active, sort_order) VALUES
  ('hero', 'Hero Search', 1, 1),
  ('cities', 'Explore Cities', 1, 2),
  ('categories', 'Browse Categories', 1, 3),
  ('featured_providers', 'Featured Providers', 1, 4),
  ('how_it_works', 'How It Works', 1, 5),
  ('trip_planner_cta', 'Plan Your Trip', 1, 6),
  ('testimonials', 'Testimonials', 1, 7),
  ('newsletter', 'Newsletter', 1, 8);

INSERT INTO settings (setting_key, setting_value, setting_group) VALUES
  ('site_name', 'Wanderly', 'general'),
  ('site_tagline', 'Plan, book, and explore — all in one place.', 'general'),
  ('site_logo', '', 'general'),
  ('site_favicon', '', 'general'),
  ('contact_email', 'hello@wanderly.test', 'general'),
  ('contact_phone', '+1 234 567 890', 'general'),
  ('default_currency', 'USD', 'general'),
  ('default_commission_percent', '10', 'finance'),
  ('service_fee_percent', '3', 'finance'),
  ('facebook_url', '', 'social'),
  ('instagram_url', '', 'social'),
  ('twitter_url', '', 'social'),
  ('maps_provider', 'openstreetmap', 'maps'),
  ('maps_api_key', '', 'maps'),
  ('smtp_host', '', 'email'),
  ('smtp_port', '587', 'email'),
  ('smtp_username', '', 'email'),
  ('smtp_password', '', 'email'),
  ('smtp_encryption', 'tls', 'email'),
  ('smtp_from_name', 'Wanderly', 'email'),
  ('smtp_from_email', 'no-reply@wanderly.test', 'email');

INSERT INTO email_templates (template_key, subject, body_html, is_active) VALUES
  ('welcome_customer', 'Welcome to {site_name}, {name}!', '<p>Hi {name},</p><p>Welcome to {site_name} — start exploring destinations, save favorites, and plan your next trip.</p>', 1),
  ('welcome_provider', 'Your {site_name} provider application was received', '<p>Hi {name},</p><p>Thanks for registering {business_name} on {site_name}. Our team will review your application shortly.</p>', 1),
  ('provider_approved', 'You''re approved on {site_name}!', '<p>Hi {name},</p><p>Great news — {business_name} is now approved and live on {site_name}. You can start adding services right away.</p>', 1),
  ('booking_created_customer', 'Booking request sent — {booking_ref}', '<p>Hi {name},</p><p>Your booking request for {service_title} ({booking_ref}) has been sent to the provider. We will notify you once it is confirmed.</p>', 1),
  ('booking_created_provider', 'New booking request — {booking_ref}', '<p>Hi {name},</p><p>You have a new booking request for {service_title} ({booking_ref}). Log in to accept or decline it.</p>', 1),
  ('booking_status_changed', 'Your booking is now {status} — {booking_ref}', '<p>Hi {name},</p><p>Your booking {booking_ref} for {service_title} is now <strong>{status}</strong>.</p>', 1),
  ('new_message', 'New message on {site_name}', '<p>Hi {name},</p><p>You have a new message from {sender_name}. Log in to reply.</p>', 1),
  ('password_reset', 'Reset your {site_name} password', '<p>We received a request to reset your password.</p><p><a href="{reset_link}">Click here to choose a new password</a>. This link expires in 1 hour. If you did not request this, you can ignore this email.</p>', 1);

INSERT INTO pages (title, slug, content, is_active) VALUES
  ('About Us', 'about', '<p>Wanderly connects travelers with trusted hotels, transport, restaurants and experiences worldwide, and helps you plan the whole trip in one place.</p>', 1),
  ('Terms of Service', 'terms', '<p>Terms of service content goes here.</p>', 1),
  ('Privacy Policy', 'privacy', '<p>Privacy policy content goes here.</p>', 1),
  ('FAQ', 'faq', '<p>Frequently asked questions go here.</p>', 1);
