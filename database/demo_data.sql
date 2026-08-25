-- =====================================================================
-- Wanderly — Demo / dummy data
--
-- Populates the whole site (providers, services, bookings, reviews,
-- messages, trips, blog posts, etc.) so it doesn't look empty on a
-- fresh install.
--
-- HOW TO USE
--   1. Import database/schema.sql first (creates tables + the base
--      seed: roles, admin user, categories, cities, settings, ...).
--   2. Import THIS file right after, on that same empty database.
--      It relies on the auto-increment ids schema.sql produces
--      (cities 1-8, amenities 1-6, category_fields 1-13, membership
--      plans 1-3, provider badges 1-3) so don't run it twice or after
--      you've already added your own records — you'll get duplicate
--      key / mismatched-id errors.
--
-- LOGIN CREDENTIALS (all demo accounts share one password)
--   Password for every demo provider & customer account: Demo@12345
--   Admin login (from schema.sql):     admin@wanderly.test / Admin@12345
--   Provider logins:                   karachigrandhotel@wanderly.test,
--                                       lahoreelitecars@wanderly.test,
--                                       karachispice@wanderly.test,
--                                       islamabadtours@wanderly.test,
--                                       dubaitransfers@wanderly.test,
--                                       istanbulbosphorus@wanderly.test,
--                                       pariscitytours@wanderly.test,
--                                       londoncomfortcars@wanderly.test
--   Customer logins:                   ayesha@example.com, bilal@example.com,
--                                       sara@example.com, hassan@example.com,
--                                       emma@example.com, omar@example.com
--
-- Images are placehold.co URLs (a free placeholder-image service) so
-- everything renders with a picture out of the box — swap them for
-- real uploads whenever you're ready.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- City cover images (rows already exist from schema.sql)
-- ---------------------------------------------------------------------
UPDATE cities SET image = 'https://placehold.co/800x500/8B5CF6/FFFFFF?text=Karachi'     WHERE slug = 'karachi';
UPDATE cities SET image = 'https://placehold.co/800x500/7C3AED/FFFFFF?text=Lahore'      WHERE slug = 'lahore';
UPDATE cities SET image = 'https://placehold.co/800x500/6D28D9/FFFFFF?text=Islamabad'   WHERE slug = 'islamabad';
UPDATE cities SET image = 'https://placehold.co/800x500/8B5CF6/FFFFFF?text=Dubai'       WHERE slug = 'dubai';
UPDATE cities SET image = 'https://placehold.co/800x500/7C3AED/FFFFFF?text=London'      WHERE slug = 'london';
UPDATE cities SET image = 'https://placehold.co/800x500/6D28D9/FFFFFF?text=Paris'       WHERE slug = 'paris';
UPDATE cities SET image = 'https://placehold.co/800x500/8B5CF6/FFFFFF?text=Istanbul'    WHERE slug = 'istanbul';
UPDATE cities SET image = 'https://placehold.co/800x500/7C3AED/FFFFFF?text=New+York'    WHERE slug = 'new-york';

-- Give the two select-type dynamic fields real dropdown options
UPDATE category_fields SET field_options = 'Standard,Deluxe,Suite,Family Suite,Executive Suite' WHERE field_key = 'room_type';
UPDATE category_fields SET field_options = 'Automatic,Manual' WHERE field_key = 'transmission';
UPDATE category_fields SET field_options = 'New,Used,Refurbished' WHERE field_key = 'condition';

-- ---------------------------------------------------------------------
-- PROVIDER USER ACCOUNTS  (role_id 2 = Provider)
-- Password hash below is bcrypt for: Demo@12345
-- ---------------------------------------------------------------------
INSERT INTO users (id, role_id, name, email, phone, password_hash, status, email_verified_at) VALUES
  (101, 2, 'Ahmed Sheikh',   'karachigrandhotel@wanderly.test', '+92 300 1112233', '$2y$12$S6TVvHpOEWZfUTed6YsSqeNVkVcsO82m9wPobNiCF.iWAVEwMD5sG', 'active', NOW()),
  (102, 2, 'Bilal Chaudhry', 'lahoreelitecars@wanderly.test',   '+92 300 2223344', '$2y$12$S6TVvHpOEWZfUTed6YsSqeNVkVcsO82m9wPobNiCF.iWAVEwMD5sG', 'active', NOW()),
  (103, 2, 'Sana Qureshi',   'karachispice@wanderly.test',      '+92 300 3334455', '$2y$12$S6TVvHpOEWZfUTed6YsSqeNVkVcsO82m9wPobNiCF.iWAVEwMD5sG', 'active', NOW()),
  (104, 2, 'Farhan Malik',   'islamabadtours@wanderly.test',    '+92 300 4445566', '$2y$12$S6TVvHpOEWZfUTed6YsSqeNVkVcsO82m9wPobNiCF.iWAVEwMD5sG', 'active', NOW()),
  (105, 2, 'Khalid Al Marri','dubaitransfers@wanderly.test',    '+971 50 1234567', '$2y$12$S6TVvHpOEWZfUTed6YsSqeNVkVcsO82m9wPobNiCF.iWAVEwMD5sG', 'active', NOW()),
  (106, 2, 'Elif Yildiz',    'istanbulbosphorus@wanderly.test', '+90 532 1234567', '$2y$12$S6TVvHpOEWZfUTed6YsSqeNVkVcsO82m9wPobNiCF.iWAVEwMD5sG', 'active', NOW()),
  (107, 2, 'Claire Dubois',  'pariscitytours@wanderly.test',    '+33 6 12345678',  '$2y$12$S6TVvHpOEWZfUTed6YsSqeNVkVcsO82m9wPobNiCF.iWAVEwMD5sG', 'active', NOW()),
  (108, 2, 'James Carter',   'londoncomfortcars@wanderly.test', '+44 7700 900123', '$2y$12$S6TVvHpOEWZfUTed6YsSqeNVkVcsO82m9wPobNiCF.iWAVEwMD5sG', 'active', NOW());

-- ---------------------------------------------------------------------
-- CUSTOMER USER ACCOUNTS  (role_id 3 = Customer)
-- ---------------------------------------------------------------------
INSERT INTO users (id, role_id, name, email, phone, password_hash, status, email_verified_at) VALUES
  (201, 3, 'Ayesha Khan',  'ayesha@example.com', '+92 301 1112233', '$2y$12$S6TVvHpOEWZfUTed6YsSqeNVkVcsO82m9wPobNiCF.iWAVEwMD5sG', 'active', NOW()),
  (202, 3, 'Bilal Ahmed',  'bilal@example.com',  '+92 301 2223344', '$2y$12$S6TVvHpOEWZfUTed6YsSqeNVkVcsO82m9wPobNiCF.iWAVEwMD5sG', 'active', NOW()),
  (203, 3, 'Sara Malik',   'sara@example.com',   '+92 301 3334455', '$2y$12$S6TVvHpOEWZfUTed6YsSqeNVkVcsO82m9wPobNiCF.iWAVEwMD5sG', 'active', NOW()),
  (204, 3, 'Hassan Raza',  'hassan@example.com', '+92 301 4445566', '$2y$12$S6TVvHpOEWZfUTed6YsSqeNVkVcsO82m9wPobNiCF.iWAVEwMD5sG', 'active', NOW()),
  (205, 3, 'Emma Wilson',  'emma@example.com',   '+44 7700 900456', '$2y$12$S6TVvHpOEWZfUTed6YsSqeNVkVcsO82m9wPobNiCF.iWAVEwMD5sG', 'active', NOW()),
  (206, 3, 'Omar Farouk',  'omar@example.com',   '+971 50 7654321', '$2y$12$S6TVvHpOEWZfUTed6YsSqeNVkVcsO82m9wPobNiCF.iWAVEwMD5sG', 'active', NOW());

-- ---------------------------------------------------------------------
-- PROVIDERS  (category ids / city ids come from schema.sql's base seed)
-- ---------------------------------------------------------------------
INSERT INTO providers (id, user_id, business_name, slug, category_id, city_id, address, latitude, longitude, description, logo, cover_image, status, is_verified, is_featured, membership_plan_id, avg_rating, review_count, profile_views, created_at) VALUES
  (1, 101, 'Karachi Grand Hotel',     'karachi-grand-hotel',     1, 1, 'Clifton Beach Road, Karachi',        24.8138, 67.0300, 'A landmark 5-star hotel on Karachi''s seafront with sweeping Arabian Sea views, three restaurants, and a full-service spa.', 'https://placehold.co/300x300/8B5CF6/FFFFFF?text=KGH', 'https://placehold.co/1200x400/6D28D9/FFFFFF?text=Karachi+Grand+Hotel', 'approved', 1, 1, 3, 0, 0, 842, '2026-05-01 09:00:00'),
  (2, 102, 'Lahore Elite Rent a Car', 'lahore-elite-rent-a-car', 2, 2, 'Gulberg III, Lahore',                 31.5204, 74.3587, 'Late-model self-drive and chauffeured cars for business trips, weddings, and family holidays across Punjab.', 'https://placehold.co/300x300/7C3AED/FFFFFF?text=LEC', 'https://placehold.co/1200x400/6D28D9/FFFFFF?text=Lahore+Elite+Cars', 'approved', 0, 0, 1, 0, 0, 356, '2026-05-03 10:15:00'),
  (3, 103, 'Karachi Spice Restaurant','karachi-spice-restaurant',3, 1, 'Zamzama Boulevard, Karachi',          24.8320, 67.0300, 'Award-winning Pakistani and continental cuisine with private dining rooms for family gatherings and events.', 'https://placehold.co/300x300/8B5CF6/FFFFFF?text=KSR', 'https://placehold.co/1200x400/6D28D9/FFFFFF?text=Karachi+Spice', 'approved', 0, 0, 1, 0, 0, 511, '2026-05-05 11:30:00'),
  (4, 104, 'Islamabad Adventure Tours','islamabad-adventure-tours',4, 3, 'F-7 Markaz, Islamabad',              33.7180, 73.0551, 'Licensed local guides running hiking, cultural, and day-trip tours around the capital and Margalla Hills.', 'https://placehold.co/300x300/7C3AED/FFFFFF?text=IAT', 'https://placehold.co/1200x400/6D28D9/FFFFFF?text=Islamabad+Tours', 'approved', 1, 0, 2, 0, 0, 298, '2026-05-08 08:45:00'),
  (5, 105, 'Dubai Luxury Transfers',  'dubai-luxury-transfers',  5, 4, 'Sheikh Zayed Road, Dubai',            25.2110, 55.2790, 'Meet-and-greet airport transfers in sedans and luxury SUVs, available 24/7 across the UAE.', 'https://placehold.co/300x300/8B5CF6/FFFFFF?text=DLT', 'https://placehold.co/1200x400/6D28D9/FFFFFF?text=Dubai+Transfers', 'approved', 0, 0, 1, 0, 0, 620, '2026-05-10 14:00:00'),
  (6, 106, 'Istanbul Bosphorus Hotel','istanbul-bosphorus-hotel',1, 7, 'Beyoglu, Istanbul',                   41.0370, 28.9850, 'A boutique hotel steps from Taksim Square with rooftop Bosphorus views and a traditional Turkish breakfast.', 'https://placehold.co/300x300/7C3AED/FFFFFF?text=IBH', 'https://placehold.co/1200x400/6D28D9/FFFFFF?text=Bosphorus+Hotel', 'approved', 1, 1, 1, 0, 0, 733, '2026-05-12 09:20:00'),
  (7, 107, 'Paris City Tours',        'paris-city-tours',        4, 6, 'Rue de Rivoli, Paris',                48.8606, 2.3376,  'Skip-the-line guided tours of Paris'' most iconic landmarks with small, English-speaking groups.', 'https://placehold.co/300x300/8B5CF6/FFFFFF?text=PCT', 'https://placehold.co/1200x400/6D28D9/FFFFFF?text=Paris+City+Tours', 'approved', 0, 1, 1, 0, 0, 455, '2026-05-15 13:10:00'),
  (8, 108, 'London Comfort Cars',     'london-comfort-cars',     2, 5, 'Kensington High Street, London',      51.5009, -0.1925, 'Chauffeured and self-drive car hire in central London, from compact runabouts to executive saloons.', 'https://placehold.co/300x300/7C3AED/FFFFFF?text=LCC', 'https://placehold.co/1200x400/6D28D9/FFFFFF?text=London+Comfort+Cars', 'approved', 0, 0, 1, 0, 0, 210, '2026-05-18 16:40:00');

-- ---------------------------------------------------------------------
-- SERVICES
-- ---------------------------------------------------------------------
INSERT INTO services (id, provider_id, category_id, city_id, title, slug, short_description, description, address, latitude, longitude, price, price_unit, max_guests, status, is_featured, cancellation_policy, created_at) VALUES
  (1,  1, 1, 1, 'Deluxe Sea View Room',            'deluxe-sea-view-room',            'Spacious room with a private balcony overlooking the Arabian Sea.', 'Wake up to ocean views in this 32 sqm room featuring a king bed, rain shower, and complimentary breakfast for two.', 'Clifton Beach Road, Karachi', 24.8138, 67.0300, 120.00, 'night', 2, 'approved', 1, 'Free cancellation up to 48 hours before check-in.', '2026-05-01 09:30:00'),
  (2,  1, 1, 1, 'Executive Suite',                 'executive-suite',                 'Separate living area, sea-facing balcony, and butler service.', 'Our largest category — a 60 sqm suite with a lounge, dining table, and dedicated check-in.', 'Clifton Beach Road, Karachi', 24.8138, 67.0300, 220.00, 'night', 4, 'approved', 0, 'Free cancellation up to 72 hours before check-in.', '2026-05-01 09:45:00'),
  (3,  2, 2, 2, 'Toyota Corolla — Self Drive',     'toyota-corolla-self-drive',       'Reliable, fuel-efficient sedan, perfect for city driving.', 'A well-maintained 2023 Corolla with GPS, unlimited mileage within Lahore, and full insurance.', 'Gulberg III, Lahore', 31.5204, 74.3587, 45.00, 'day', 4, 'approved', 0, 'Free cancellation up to 24 hours before pickup.', '2026-05-03 10:30:00'),
  (4,  2, 2, 2, 'Luxury SUV with Driver',          'luxury-suv-with-driver',          'Chauffeured Land Cruiser for weddings, airport runs, and events.', 'A premium SUV with an experienced driver, bottled water, and flexible hourly extensions.', 'Gulberg III, Lahore', 31.5204, 74.3587, 120.00, 'day', 6, 'approved', 1, 'Free cancellation up to 24 hours before pickup.', '2026-05-03 10:45:00'),
  (5,  3, 3, 1, 'Family Dinner Table (4 pax)',     'family-dinner-table-4-pax',       'Reserved table for four with a set Pakistani tasting menu.', 'Enjoy chef''s-choice appetizers, mains, and dessert in our main dining hall.', 'Zamzama Boulevard, Karachi', 24.8320, 67.0300, 60.00, 'fixed', 4, 'approved', 0, 'Free cancellation up to 6 hours before reservation.', '2026-05-05 12:00:00'),
  (6,  3, 3, 1, 'Private Dining Experience',       'private-dining-experience',       'A private room with a personal server for up to 8 guests.', 'Ideal for celebrations — includes a custom menu tasting and dedicated server for the evening.', 'Zamzama Boulevard, Karachi', 24.8320, 67.0300, 150.00, 'fixed', 8, 'approved', 0, 'Free cancellation up to 24 hours before reservation.', '2026-05-05 12:15:00'),
  (7,  4, 4, 3, 'Margalla Hills Hiking Tour',      'margalla-hills-hiking-tour',      'Half-day guided hike with a licensed local guide.', 'A moderate 3-hour trail up Trail 3 with viewpoint stops, water, and trail snacks included.', 'F-7 Markaz, Islamabad', 33.7180, 73.0551, 35.00, 'person', 12, 'approved', 0, 'Free cancellation up to 24 hours before start.', '2026-05-08 09:00:00'),
  (8,  4, 4, 3, 'Faisal Mosque & City Tour',       'faisal-mosque-city-tour',         'Half-day cultural tour of Islamabad''s top landmarks.', 'Visit Faisal Mosque, Daman-e-Koh, and Lok Virsa Museum with an English-speaking guide and private transport.', 'F-7 Markaz, Islamabad', 33.7180, 73.0551, 25.00, 'person', 15, 'approved', 0, 'Free cancellation up to 24 hours before start.', '2026-05-08 09:15:00'),
  (9,  5, 5, 4, 'DXB Airport Pickup — Sedan',      'dxb-airport-pickup-sedan',        'Meet-and-greet arrival transfer in a comfortable sedan.', 'Your driver tracks your flight and waits in arrivals with a name board — up to 60 minutes free wait time.', 'Sheikh Zayed Road, Dubai', 25.2110, 55.2790, 40.00, 'fixed', 3, 'approved', 0, 'Free cancellation up to 6 hours before pickup.', '2026-05-10 14:30:00'),
  (10, 5, 5, 4, 'DXB Airport Pickup — Luxury SUV', 'dxb-airport-pickup-luxury-suv',   'Premium SUV transfer with bottled water and WiFi.', 'A spacious SUV for families and groups with extra luggage, meet-and-greet included.', 'Sheikh Zayed Road, Dubai', 25.2110, 55.2790, 90.00, 'fixed', 6, 'approved', 0, 'Free cancellation up to 6 hours before pickup.', '2026-05-10 14:45:00'),
  (11, 6, 1, 7, 'Bosphorus View Room',             'bosphorus-view-room',             'Cosy double room with a partial Bosphorus view.', 'Traditional Turkish decor, a queen bed, and a made-to-order breakfast spread each morning.', 'Beyoglu, Istanbul', 41.0370, 28.9850, 95.00, 'night', 2, 'approved', 1, 'Free cancellation up to 48 hours before check-in.', '2026-05-12 09:30:00'),
  (12, 6, 1, 7, 'Family Suite',                    'family-suite-istanbul',          'Two-room suite that comfortably sleeps up to five.', 'A living room with a sofa bed plus a separate bedroom — great for families exploring the old city.', 'Beyoglu, Istanbul', 41.0370, 28.9850, 160.00, 'night', 5, 'approved', 0, 'Free cancellation up to 48 hours before check-in.', '2026-05-12 09:45:00'),
  (13, 7, 4, 6, 'Eiffel Tower Skip-the-Line Tour', 'eiffel-tower-skip-the-line-tour', 'Guided visit with priority access to the second floor.', 'Beat the queues with a small-group tour covering the tower''s history and best photo spots.', 'Rue de Rivoli, Paris', 48.8606, 2.3376, 55.00, 'person', 10, 'approved', 1, 'Free cancellation up to 24 hours before start.', '2026-05-15 13:30:00'),
  (14, 7, 4, 6, 'Louvre Museum Guided Tour',       'louvre-museum-guided-tour',       'Two-hour highlights tour with an art-history guide.', 'See the Mona Lisa, Venus de Milo, and other masterpieces without the wait in ticket lines.', 'Rue de Rivoli, Paris', 48.8606, 2.3376, 65.00, 'person', 10, 'approved', 0, 'Free cancellation up to 24 hours before start.', '2026-05-15 13:45:00'),
  (15, 8, 2, 5, 'Mercedes E-Class — With Driver',  'mercedes-e-class-with-driver',    'Executive saloon with a professional chauffeur.', 'Ideal for business travel and airport transfers across Greater London, billed per day with mileage included.', 'Kensington High Street, London', 51.5009, -0.1925, 80.00, 'day', 3, 'approved', 0, 'Free cancellation up to 24 hours before pickup.', '2026-05-18 17:00:00'),
  (16, 8, 2, 5, 'Compact Car — Self Drive',        'compact-car-self-drive',         'Economical hatchback, easy to park in central London.', 'A fuel-efficient Volkswagen Golf with satnav and unlimited mileage within the M25.', 'Kensington High Street, London', 51.5009, -0.1925, 38.00, 'day', 4, 'approved', 0, 'Free cancellation up to 24 hours before pickup.', '2026-05-18 17:15:00');

-- Cover + gallery image per service
INSERT INTO service_images (service_id, image_path, is_cover, sort_order) VALUES
  (1,  'https://placehold.co/800x600/A78BFA/1E1B4B?text=Sea+View+Room+1',  1, 0), (1,  'https://placehold.co/800x600/A78BFA/1E1B4B?text=Sea+View+Room+2',  0, 1),
  (2,  'https://placehold.co/800x600/A78BFA/1E1B4B?text=Executive+Suite+1',1, 0), (2,  'https://placehold.co/800x600/A78BFA/1E1B4B?text=Executive+Suite+2',0, 1),
  (3,  'https://placehold.co/800x600/A78BFA/1E1B4B?text=Corolla+1',        1, 0), (3,  'https://placehold.co/800x600/A78BFA/1E1B4B?text=Corolla+2',        0, 1),
  (4,  'https://placehold.co/800x600/A78BFA/1E1B4B?text=Luxury+SUV+1',     1, 0), (4,  'https://placehold.co/800x600/A78BFA/1E1B4B?text=Luxury+SUV+2',     0, 1),
  (5,  'https://placehold.co/800x600/A78BFA/1E1B4B?text=Dinner+Table+1',   1, 0), (5,  'https://placehold.co/800x600/A78BFA/1E1B4B?text=Dinner+Table+2',   0, 1),
  (6,  'https://placehold.co/800x600/A78BFA/1E1B4B?text=Private+Dining+1', 1, 0), (6,  'https://placehold.co/800x600/A78BFA/1E1B4B?text=Private+Dining+2', 0, 1),
  (7,  'https://placehold.co/800x600/A78BFA/1E1B4B?text=Margalla+Hike+1',  1, 0), (7,  'https://placehold.co/800x600/A78BFA/1E1B4B?text=Margalla+Hike+2',  0, 1),
  (8,  'https://placehold.co/800x600/A78BFA/1E1B4B?text=Faisal+Mosque+1',  1, 0), (8,  'https://placehold.co/800x600/A78BFA/1E1B4B?text=Faisal+Mosque+2',  0, 1),
  (9,  'https://placehold.co/800x600/A78BFA/1E1B4B?text=Sedan+Transfer+1', 1, 0), (9,  'https://placehold.co/800x600/A78BFA/1E1B4B?text=Sedan+Transfer+2', 0, 1),
  (10, 'https://placehold.co/800x600/A78BFA/1E1B4B?text=SUV+Transfer+1',   1, 0), (10, 'https://placehold.co/800x600/A78BFA/1E1B4B?text=SUV+Transfer+2',   0, 1),
  (11, 'https://placehold.co/800x600/A78BFA/1E1B4B?text=Bosphorus+Room+1', 1, 0), (11, 'https://placehold.co/800x600/A78BFA/1E1B4B?text=Bosphorus+Room+2', 0, 1),
  (12, 'https://placehold.co/800x600/A78BFA/1E1B4B?text=Family+Suite+1',   1, 0), (12, 'https://placehold.co/800x600/A78BFA/1E1B4B?text=Family+Suite+2',   0, 1),
  (13, 'https://placehold.co/800x600/A78BFA/1E1B4B?text=Eiffel+Tower+1',   1, 0), (13, 'https://placehold.co/800x600/A78BFA/1E1B4B?text=Eiffel+Tower+2',   0, 1),
  (14, 'https://placehold.co/800x600/A78BFA/1E1B4B?text=Louvre+Tour+1',    1, 0), (14, 'https://placehold.co/800x600/A78BFA/1E1B4B?text=Louvre+Tour+2',    0, 1),
  (15, 'https://placehold.co/800x600/A78BFA/1E1B4B?text=Mercedes+E-Class+1',1,0), (15, 'https://placehold.co/800x600/A78BFA/1E1B4B?text=Mercedes+E-Class+2',0,1),
  (16, 'https://placehold.co/800x600/A78BFA/1E1B4B?text=Compact+Car+1',    1, 0), (16, 'https://placehold.co/800x600/A78BFA/1E1B4B?text=Compact+Car+2',    0, 1);

-- Amenities (ids from schema.sql base seed: 1 WiFi, 2 Pool, 3 Parking, 4 Breakfast, 5 AC, 6 Pet Friendly)
INSERT INTO service_amenity_map (service_id, amenity_id) VALUES
  (1,1),(1,2),(1,4),(1,5), (2,1),(2,2),(2,4),(2,5),
  (11,1),(11,4),(11,5), (12,1),(12,4),(12,5),
  (5,1),(5,3),(5,5), (6,1),(6,3),(6,5),
  (3,5), (4,5),(4,3), (15,5),(15,3), (16,5);

-- Dynamic category fields — Hotels (field ids 1-5: room_type, beds, max_guests, check_in, check_out)
INSERT INTO service_field_values (service_id, category_field_id, field_value) VALUES
  (1, 1, 'Deluxe'), (1, 2, '2'), (1, 3, '2'), (1, 4, '14:00'), (1, 5, '12:00'),
  (2, 1, 'Executive Suite'), (2, 2, '3'), (2, 3, '4'), (2, 4, '14:00'), (2, 5, '12:00'),
  (11, 1, 'Deluxe'), (11, 2, '2'), (11, 3, '2'), (11, 4, '15:00'), (11, 5, '11:00'),
  (12, 1, 'Family Suite'), (12, 2, '4'), (12, 3, '5'), (12, 4, '15:00'), (12, 5, '11:00');

-- Dynamic category fields — Rent a Car (field ids 6-10: car_brand, model_year, seats, transmission, driver_included)
INSERT INTO service_field_values (service_id, category_field_id, field_value) VALUES
  (3, 6, 'Toyota Corolla'), (3, 7, '2023'), (3, 8, '5'), (3, 9, 'Automatic'), (3, 10, '0'),
  (4, 6, 'Toyota Land Cruiser'), (4, 7, '2024'), (4, 8, '7'), (4, 9, 'Automatic'), (4, 10, '1'),
  (15, 6, 'Mercedes-Benz E-Class'), (15, 7, '2024'), (15, 8, '4'), (15, 9, 'Automatic'), (15, 10, '1'),
  (16, 6, 'Volkswagen Golf'), (16, 7, '2022'), (16, 8, '5'), (16, 9, 'Manual'), (16, 10, '0');

-- Dynamic category fields — Restaurants (field ids 11-13: cuisine, seating_capacity, opening_hours)
INSERT INTO service_field_values (service_id, category_field_id, field_value) VALUES
  (5, 11, 'Pakistani'), (5, 12, '4'), (5, 13, '12:00 PM - 11:00 PM'),
  (6, 11, 'Continental'), (6, 12, '8'), (6, 13, '6:00 PM - 12:00 AM');

-- A couple of blocked dates so the availability calendar has something to show
INSERT INTO service_availability (service_id, date, status) VALUES
  (1, '2026-09-05', 'blocked'), (1, '2026-09-06', 'blocked'),
  (11, '2026-10-02', 'blocked');

-- ---------------------------------------------------------------------
-- MEMBERSHIPS (2 of the 8 providers on paid plans)
-- ---------------------------------------------------------------------
INSERT INTO provider_memberships (id, provider_id, plan_id, starts_at, status) VALUES
  (1, 1, 3, '2026-07-01 00:00:00', 'active'),
  (2, 4, 2, '2026-07-15 00:00:00', 'active');

-- Badges (ids from schema.sql base seed: 1 Verified, 2 Featured, 3 Top Rated)
INSERT INTO provider_badge_map (provider_id, badge_id) VALUES
  (1,1),(1,2), (4,1), (6,1),(6,3), (7,2);

-- ---------------------------------------------------------------------
-- BOOKINGS
-- ---------------------------------------------------------------------
INSERT INTO bookings (id, booking_ref, service_id, customer_id, provider_id, date_from, date_to, guests, base_price, tax_amount, service_fee, commission_amount, total_amount, status, created_at) VALUES
  (1,  'WD-DEMO0001', 1,  201, 1, '2026-06-10', '2026-06-13', 2, 360.00, 18.00, 10.80, 36.00,  388.80, 'completed', '2026-06-01 10:00:00'),
  (2,  'WD-DEMO0002', 2,  202, 1, '2026-09-20', '2026-09-22', 3, 440.00, 22.00, 13.20, 44.00,  475.20, 'confirmed', '2026-08-05 11:00:00'),
  (3,  'WD-DEMO0003', 3,  203, 2, '2026-05-01', '2026-05-04', 1, 135.00, 6.75,  4.05,  13.50,  145.80, 'completed', '2026-04-20 09:00:00'),
  (4,  'WD-DEMO0004', 4,  204, 2, '2026-09-15', '2026-09-17', 4, 240.00, 12.00, 7.20,  24.00,  259.20, 'pending',   '2026-08-10 08:30:00'),
  (5,  'WD-DEMO0005', 5,  201, 3, '2026-07-01', NULL,         4, 60.00,  3.00,  1.80,  6.00,   64.80,  'completed', '2026-06-25 18:00:00'),
  (6,  'WD-DEMO0006', 6,  205, 3, '2026-08-01', NULL,         8, 150.00, 7.50,  4.50,  15.00,  162.00, 'cancelled', '2026-07-20 19:00:00'),
  (7,  'WD-DEMO0007', 7,  206, 4, '2026-06-20', NULL,         2, 70.00,  3.50,  2.10,  7.00,   75.60,  'completed', '2026-06-10 07:00:00'),
  (8,  'WD-DEMO0008', 8,  202, 4, '2026-09-10', NULL,         4, 100.00, 5.00,  3.00,  10.00,  108.00, 'confirmed', '2026-08-11 12:00:00'),
  (9,  'WD-DEMO0009', 9,  203, 5, '2026-07-15', NULL,         2, 40.00,  2.00,  1.20,  4.00,   43.20,  'completed', '2026-07-10 06:00:00'),
  (10, 'WD-DEMO0010', 11, 204, 6, '2026-10-01', '2026-10-05', 2, 380.00, 19.00, 11.40, 38.00,  410.40, 'confirmed', '2026-08-12 15:00:00'),
  (11, 'WD-DEMO0011', 13, 205, 7, '2026-09-25', NULL,         2, 110.00, 5.50,  3.30,  11.00,  118.80, 'pending',   '2026-08-13 09:00:00'),
  (12, 'WD-DEMO0012', 15, 206, 8, '2026-09-18', '2026-09-21', 2, 240.00, 12.00, 7.20,  24.00,  259.20, 'accepted',  '2026-08-12 17:30:00');

-- ---------------------------------------------------------------------
-- REVIEWS (for the completed bookings)
-- ---------------------------------------------------------------------
INSERT INTO reviews (service_id, booking_id, customer_id, rating, title, review_text, status, created_at) VALUES
  (1, 1, 201, 5, 'Amazing stay!',           'The sea view room was stunning and the staff were incredibly attentive. Would book again in a heartbeat.', 'approved', '2026-06-14 09:00:00'),
  (3, 3, 203, 4, 'Great car, smooth ride',  'The Corolla was clean and well maintained. Pickup was quick, only minor delay at drop-off.', 'approved', '2026-05-05 10:00:00'),
  (5, 5, 201, 5, 'Delicious food',          'Best Pakistani food we''ve had in Karachi — generous portions and great service.', 'approved', '2026-07-02 20:00:00'),
  (7, 7, 206, 5, 'Unforgettable hike',      'Our guide was knowledgeable and the views from Trail 3 were breathtaking. Highly recommend.', 'approved', '2026-06-21 12:00:00'),
  (9, 9, 203, 4, 'On time and professional','Driver was waiting exactly when we landed. Car was spotless and comfortable.', 'approved', '2026-07-16 08:00:00');

-- Keep services/providers rating fields in sync with the reviews above
UPDATE services s
SET review_count = (SELECT COUNT(*) FROM reviews r WHERE r.service_id = s.id AND r.status = 'approved'),
    avg_rating    = (SELECT ROUND(AVG(r.rating), 2) FROM reviews r WHERE r.service_id = s.id AND r.status = 'approved')
WHERE EXISTS (SELECT 1 FROM reviews r WHERE r.service_id = s.id);

UPDATE providers p
SET review_count = (SELECT COUNT(*) FROM reviews r JOIN services s ON s.id = r.service_id WHERE s.provider_id = p.id AND r.status = 'approved'),
    avg_rating    = (SELECT ROUND(AVG(r.rating), 2) FROM reviews r JOIN services s ON s.id = r.service_id WHERE s.provider_id = p.id AND r.status = 'approved')
WHERE EXISTS (SELECT 1 FROM reviews r JOIN services s ON s.id = r.service_id WHERE s.provider_id = p.id);

-- ---------------------------------------------------------------------
-- FAVORITES
-- ---------------------------------------------------------------------
INSERT INTO favorites (user_id, favoritable_type, favoritable_id) VALUES
  (201, 'service', 2), (201, 'service', 11),
  (202, 'service', 7),
  (205, 'provider', 7),
  (206, 'city', 7);

-- ---------------------------------------------------------------------
-- MESSAGING
-- ---------------------------------------------------------------------
INSERT INTO conversations (id, customer_id, provider_id, service_id, booking_id, last_message_at, created_at) VALUES
  (1, 201, 1, 1, 1,  '2026-06-02 09:15:00', '2026-06-01 10:05:00'),
  (2, 204, 6, 11, 10, '2026-08-12 15:30:00', '2026-08-12 15:05:00'),
  (3, 205, 7, 13, NULL, '2026-08-13 09:20:00', '2026-08-13 09:00:00');

INSERT INTO messages (conversation_id, sender_id, message_text, is_read, created_at) VALUES
  (1, 201, 'Hi! Is early check-in possible around 11am on the 10th?', 1, '2026-06-01 10:05:00'),
  (1, 101, 'Hello Ayesha, yes we can arrange 11am check-in for you at no extra cost.', 1, '2026-06-01 14:20:00'),
  (1, 201, 'Perfect, thank you so much!', 1, '2026-06-02 09:15:00'),
  (2, 204, 'Hi, could we get a room on a higher floor if available?', 1, '2026-08-12 15:05:00'),
  (2, 106, 'Hi Hassan, noted — I''ve requested a high-floor room for your stay.', 0, '2026-08-12 15:30:00'),
  (3, 205, 'Hi, do you offer a private tour option for just my family (4 people)?', 0, '2026-08-13 09:00:00'),
  (3, 107, 'Bonjour Emma, yes we can arrange a private group tour — I''ll send you a quote shortly.', 0, '2026-08-13 09:20:00');

-- ---------------------------------------------------------------------
-- NOTIFICATIONS
-- ---------------------------------------------------------------------
INSERT INTO notifications (user_id, type, title, message, link, is_read, created_at) VALUES
  (201, 'booking_status', 'Booking confirmed', 'Your booking WD-DEMO0002 for Executive Suite has been confirmed.', '/customer/bookings.php', 0, '2026-08-05 11:05:00'),
  (101, 'new_booking',    'New booking request', 'You have a new booking request for Executive Suite (WD-DEMO0002).', '/provider/bookings.php', 1, '2026-08-05 11:00:00'),
  (202, 'new_message',    'New message', 'You have a new message from Karachi Grand Hotel.', '/customer/messages.php', 0, '2026-06-01 14:20:00'),
  (103, 'new_review',     'New review received', 'Your listing Family Dinner Table (4 pax) received a new 5-star review.', '/provider/reviews.php', 0, '2026-07-02 20:05:00');

-- ---------------------------------------------------------------------
-- TRIP PLANNER
-- ---------------------------------------------------------------------
INSERT INTO trips (id, user_id, trip_name, destination_city_id, date_from, date_to, adults, children, budget_mode, max_budget, status, visibility, share_token, notes, created_at) VALUES
  (1, 201, 'Istanbul Getaway', 7, '2026-10-01', '2026-10-05', 2, 0, 'standard', 700.00, 'planned', 'public',  'a1b2c3d4e5f6a7b8c9d0e1f2a3b4c5d6', 'Anniversary trip — want a nice hotel and at least one guided activity.', '2026-08-01 10:00:00'),
  (2, 205, 'Paris Weekend',    6, '2026-09-25', '2026-09-27', 1, 0, 'economy',  250.00, 'draft',   'private', NULL, 'Quick solo weekend, keep it budget-friendly.', '2026-08-10 16:00:00');

INSERT INTO trip_days (id, trip_id, day_number, day_date) VALUES
  (1, 1, 1, '2026-10-01'), (2, 1, 2, '2026-10-02'), (3, 1, 3, '2026-10-03'), (4, 1, 4, '2026-10-04'),
  (5, 2, 1, '2026-09-25'), (6, 2, 2, '2026-09-26');

INSERT INTO trip_items (trip_day_id, service_id, custom_title, item_type, start_time, sort_order, notes) VALUES
  (1, 11, NULL, 'accommodation', '15:00:00', 0, 'Check in to Bosphorus View Room.'),
  (2, NULL, 'Grand Bazaar & Spice Market', 'activity', '10:00:00', 0, 'Self-guided shopping and lunch nearby.'),
  (3, NULL, 'Bosphorus Cruise', 'activity', '14:00:00', 0, 'Book a sunset cruise ticket on arrival.'),
  (4, NULL, 'Departure', 'transport', '11:00:00', 0, 'Airport transfer, checkout by 11am.'),
  (5, 13, NULL, 'activity', '09:00:00', 0, 'Eiffel Tower skip-the-line tour.'),
  (6, 14, NULL, 'activity', '10:00:00', 0, 'Louvre guided tour, then free afternoon.');

INSERT INTO trip_services (trip_id, service_id) VALUES (1, 11), (2, 13), (2, 14);

INSERT INTO trip_budget (trip_id, hotel_total, transport_total, food_total, activities_total, fees_total, tax_total, estimated_total) VALUES
  (1, 380.00, 0.00,  120.00, 60.00,  11.40, 19.00, 590.40),
  (2, 0.00,   20.00, 60.00,  120.00, 3.30,  5.50,  208.80);

-- ---------------------------------------------------------------------
-- BLOG
-- ---------------------------------------------------------------------
INSERT INTO blog_categories (id, name, slug) VALUES
  (1, 'Destinations', 'destinations'),
  (2, 'Travel Tips', 'travel-tips'),
  (3, 'Food & Culture', 'food-culture');

INSERT INTO blog_tags (id, name, slug) VALUES
  (1, 'Adventure', 'adventure'),
  (2, 'Budget Travel', 'budget-travel'),
  (3, 'Family', 'family'),
  (4, 'Luxury', 'luxury'),
  (5, 'Solo Travel', 'solo-travel');

INSERT INTO blogs (id, author_id, category_id, title, slug, featured_image, excerpt, content, status, published_at, created_at) VALUES
  (1, 1, 1, '10 Must-Visit Spots in Istanbul', '10-must-visit-spots-in-istanbul',
    'https://placehold.co/1200x630/8B5CF6/FFFFFF?text=Istanbul+Travel+Guide',
    'From the Blue Mosque to the Bosphorus shoreline, here''s where to spend your time in Istanbul.',
    '<p>Istanbul sits where two continents meet, and its skyline of domes and minarets makes it one of the most photogenic cities in the world.</p><p>Start at the Hagia Sophia and Blue Mosque, both an easy walk apart in Sultanahmet, then wander the Grand Bazaar for spices, ceramics, and textiles. In the evening, take a Bosphorus cruise for skyline views from the water.</p><p>Don''t miss Karakoy for third-wave coffee, or a rooftop dinner in Beyoglu overlooking the strait.</p>',
    'published', '2026-07-01 09:00:00', '2026-07-01 08:00:00'),
  (2, 1, 2, 'A Budget Traveler''s Guide to Karachi', 'budget-travelers-guide-to-karachi',
    'https://placehold.co/1200x630/7C3AED/FFFFFF?text=Karachi+on+a+Budget',
    'Great food, a lively beach, and plenty to do without breaking the bank.',
    '<p>Karachi is one of the most affordable big cities to explore. Public transport and rideshare apps make getting around cheap, and street food along Burns Road rivals any restaurant.</p><p>Spend a free afternoon at Clifton Beach, visit the Mohatta Palace museum, and save your splurge budget for a seafood dinner at Do Darya.</p>',
    'published', '2026-07-08 09:00:00', '2026-07-08 08:00:00'),
  (3, 1, 1, 'Family-Friendly Things to Do in Dubai', 'family-friendly-things-to-do-in-dubai',
    'https://placehold.co/1200x630/6D28D9/FFFFFF?text=Dubai+with+Kids',
    'Theme parks, beaches, and air-conditioned malls — Dubai has plenty for younger travelers.',
    '<p>Dubai is built for family trips. Spend a morning at an indoor water park to beat the heat, then head to the beach at JBR in the late afternoon.</p><p>The Dubai Mall''s aquarium and ice rink are easy wins for kids of any age, and the Miracle Garden is a great photo stop if you''re visiting in cooler months.</p>',
    'published', '2026-07-15 09:00:00', '2026-07-15 08:00:00'),
  (4, 1, 2, '5 Tips for Booking the Perfect Rental Car', '5-tips-for-booking-the-perfect-rental-car',
    'https://placehold.co/1200x630/8B5CF6/FFFFFF?text=Rental+Car+Tips',
    'What to check before you confirm a booking, from insurance to mileage limits.',
    '<p>Always confirm whether unlimited mileage is included before you book, especially for multi-day road trips.</p><p>Photograph the car with the provider present before you drive off, check the cancellation policy in case your plans change, and ask whether a driver option is available if you''d rather not navigate an unfamiliar city yourself.</p>',
    'published', '2026-07-22 09:00:00', '2026-07-22 08:00:00'),
  (5, 1, 3, 'Where to Eat in Paris: A Foodie''s Guide', 'where-to-eat-in-paris-a-foodies-guide',
    'https://placehold.co/1200x630/7C3AED/FFFFFF?text=Paris+Food+Guide',
    'From neighborhood bistros to Michelin-starred tables, here''s how to eat well in Paris.',
    '<p>You don''t need a big budget to eat well in Paris. A fresh baguette and cheese from a local market makes for a perfect picnic lunch by the Seine.</p><p>For dinner, look for a neighborhood bistro with a chalkboard menu — they''re usually better value and just as good as the famous spots. Save one night for a proper tasting menu if you can.</p>',
    'published', '2026-07-29 09:00:00', '2026-07-29 08:00:00'),
  (6, 1, 2, 'Solo Travel 101: Planning Your First Trip', 'solo-travel-101-planning-your-first-trip',
    'https://placehold.co/1200x630/6D28D9/FFFFFF?text=Solo+Travel+101',
    'Practical advice for first-time solo travelers, from safety to meeting people on the road.',
    '<p>Solo travel is easier than it looks. Start with a destination that has a well-established tourist infrastructure, and book your first night''s accommodation in advance so you have a landing pad.</p><p>Use our trip planner to build a day-by-day itinerary before you go — it takes the guesswork out of your first few days and helps you budget realistically.</p>',
    'published', '2026-08-05 09:00:00', '2026-08-05 08:00:00');

INSERT INTO blog_tag_map (blog_id, tag_id) VALUES
  (1,1),(1,4), (2,2),(2,5), (3,3),(3,4), (4,2), (5,4), (6,5),(6,1);

-- ---------------------------------------------------------------------
-- COUPONS
-- ---------------------------------------------------------------------
INSERT INTO coupons (code, discount_type, discount_value, max_uses, used_count, min_amount, valid_from, valid_to, is_active) VALUES
  ('WELCOME10', 'percent', 10.00, 500, 0, NULL,    '2026-01-01', '2026-12-31', 1),
  ('SUMMER25',  'fixed',   25.00, 200, 0, 150.00,  '2026-06-01', '2026-09-30', 1);

-- ---------------------------------------------------------------------
-- PAYMENTS
-- ---------------------------------------------------------------------
INSERT INTO payments (id, user_id, booking_id, membership_id, plan_id, gateway_id, amount, currency, status, transaction_ref, paid_at, created_at) VALUES
  (1, 201, 1, NULL, NULL, 3, 388.80, 'USD', 'completed', 'TXN-DEMO-0001', '2026-06-01 10:10:00', '2026-06-01 10:05:00'),
  (2, 203, 3, NULL, NULL, 3, 145.80, 'USD', 'completed', 'TXN-DEMO-0002', '2026-04-20 09:05:00', '2026-04-20 09:00:00'),
  (3, 203, 9, NULL, NULL, 3, 43.20,  'USD', 'completed', 'TXN-DEMO-0003', '2026-07-10 06:05:00', '2026-07-10 06:00:00'),
  (4, 101, NULL, 1, 3, 3, 79.00, 'USD', 'completed', 'TXN-DEMO-0004', '2026-07-01 00:05:00', '2026-07-01 00:00:00');

UPDATE provider_memberships SET payment_id = 4 WHERE id = 1;

-- ---------------------------------------------------------------------
-- SUPPORT
-- ---------------------------------------------------------------------
INSERT INTO support_tickets (id, user_id, subject, message, status, priority, created_at) VALUES
  (1, 202, 'Cancellation policy question', 'Hi, I have a pending booking (WD-DEMO0004) and just wanted to confirm the cancellation policy in case my plans change. Thanks!', 'open', 'normal', '2026-08-11 10:00:00');

INSERT INTO support_ticket_replies (ticket_id, sender_id, message, created_at) VALUES
  (1, 1, 'Hi Bilal, thanks for reaching out — that booking has free cancellation up to 24 hours before pickup, so you''re all set. Let us know if you need anything else!', '2026-08-11 13:00:00');

-- ---------------------------------------------------------------------
-- NEWSLETTER
-- ---------------------------------------------------------------------
INSERT INTO newsletter_subscribers (email) VALUES
  ('traveler1@example.com'), ('traveler2@example.com'), ('wanderlust@example.com'), ('tripfan@example.com');

-- ---------------------------------------------------------------------
-- STORE / E-COMMERCE — a demo Store provider selling physical products,
-- plus a couple of demo orders through the cart + checkout flow.
-- (Category id 7 "Store", category_fields ids 14-16 "Brand/SKU/Condition"
-- come from schema.sql's base seed.)
-- ---------------------------------------------------------------------

INSERT INTO users (id, role_id, name, email, phone, password_hash, status, email_verified_at) VALUES
  (109, 2, 'Priya Nair', 'karachitravelgear@wanderly.test', '+92 300 5556677', '$2y$12$S6TVvHpOEWZfUTed6YsSqeNVkVcsO82m9wPobNiCF.iWAVEwMD5sG', 'active', NOW());

INSERT INTO providers (id, user_id, business_name, slug, category_id, city_id, address, latitude, longitude, description, logo, cover_image, status, is_verified, is_featured, membership_plan_id, avg_rating, review_count, profile_views, created_at) VALUES
  (9, 109, 'Karachi Travel Gear', 'karachi-travel-gear', 7, 1, 'Tariq Road, Karachi', 24.8710, 67.0530, 'A local shop stocking backpacks, packing accessories and travel electronics, shipped anywhere in the city.', 'https://placehold.co/300x300/8B5CF6/FFFFFF?text=KTG', 'https://placehold.co/1200x400/6D28D9/FFFFFF?text=Karachi+Travel+Gear', 'approved', 1, 0, 1, 0, 0, 187, '2026-06-01 10:00:00');

INSERT INTO services (id, provider_id, category_id, city_id, title, slug, short_description, description, address, price, price_unit, stock_quantity, status, is_featured, cancellation_policy, created_at) VALUES
  (17, 9, 7, 1, 'Travel Backpack 40L', 'travel-backpack-40l', 'Carry-on friendly backpack with a padded laptop sleeve and rain cover.', 'A durable 40L backpack built for weekend trips and carry-on travel, with a padded 15" laptop sleeve, multiple organizer pockets, and a hidden rain cover.', 'Tariq Road, Karachi', 45.00, 'fixed', 25, 'approved', 1, 'Unopened items can be returned within 7 days of delivery for a full refund.', '2026-06-01 11:00:00'),
  (18, 9, 7, 1, 'Universal Travel Adapter', 'universal-travel-adapter', 'All-in-one plug adapter with dual USB-A and one USB-C port.', 'Works in over 150 countries — UK, EU, US, and AU plug types built in, plus two USB-A ports and a USB-C port for charging multiple devices at once.', 'Tariq Road, Karachi', 12.00, 'fixed', 60, 'approved', 0, 'Unopened items can be returned within 7 days of delivery for a full refund.', '2026-06-01 11:15:00'),
  (19, 9, 7, 1, 'Packing Cubes Set (4-pack)', 'packing-cubes-set-4-pack', 'Compression packing cubes in four sizes to organize a full suitcase.', 'Keep your suitcase organized with four mesh-top compression cubes in graduated sizes — great for separating clothes, shoes, and toiletries.', 'Tariq Road, Karachi', 18.00, 'fixed', 40, 'approved', 0, 'Unopened items can be returned within 7 days of delivery for a full refund.', '2026-06-01 11:30:00');

INSERT INTO service_images (service_id, image_path, is_cover, sort_order) VALUES
  (17, 'https://placehold.co/800x600/A78BFA/1E1B4B?text=Travel+Backpack+1', 1, 0), (17, 'https://placehold.co/800x600/A78BFA/1E1B4B?text=Travel+Backpack+2', 0, 1),
  (18, 'https://placehold.co/800x600/A78BFA/1E1B4B?text=Travel+Adapter+1', 1, 0), (18, 'https://placehold.co/800x600/A78BFA/1E1B4B?text=Travel+Adapter+2', 0, 1),
  (19, 'https://placehold.co/800x600/A78BFA/1E1B4B?text=Packing+Cubes+1', 1, 0), (19, 'https://placehold.co/800x600/A78BFA/1E1B4B?text=Packing+Cubes+2', 0, 1);

INSERT INTO service_field_values (service_id, category_field_id, field_value) VALUES
  (17, 14, 'Wanderlite'), (17, 15, 'WL-BP40-BLK'), (17, 16, 'New'),
  (18, 14, 'Wanderlite'), (18, 15, 'WL-ADPT-UNI'), (18, 16, 'New'),
  (19, 14, 'Wanderlite'), (19, 15, 'WL-CUBE-4PK'), (19, 16, 'New');

-- A delivered order (customer 201 bought a backpack + adapter) and a
-- pending one (customer 202 just placed an order for packing cubes).
INSERT INTO orders (id, order_ref, customer_id, subtotal, tax_amount, service_fee, total_amount, status, shipping_name, shipping_phone, shipping_address, shipping_city_id, created_at) VALUES
  (1, 'OD-DEMO0001', 201, 57.00, 2.85, 1.71, 61.56, 'delivered', 'Ayesha Khan', '+92 301 1112233', 'House 12, Street 4, DHA Phase 5, Karachi', 1, '2026-07-05 12:00:00'),
  (2, 'OD-DEMO0002', 202, 18.00, 0.90, 0.54, 19.44, 'pending', 'Bilal Ahmed', '+92 301 2223344', 'Flat 3B, Clifton Block 2, Karachi', 1, '2026-08-12 16:00:00');

INSERT INTO order_items (order_id, service_id, provider_id, title, unit_price, quantity, total_price, commission_amount, status, created_at) VALUES
  (1, 17, 9, 'Travel Backpack 40L', 45.00, 1, 48.60, 4.50, 'delivered', '2026-07-05 12:00:00'),
  (1, 18, 9, 'Universal Travel Adapter', 12.00, 1, 12.96, 1.20, 'delivered', '2026-07-05 12:00:00'),
  (2, 19, 9, 'Packing Cubes Set (4-pack)', 18.00, 1, 19.44, 1.80, 'pending', '2026-08-12 16:00:00');

UPDATE services SET stock_quantity = stock_quantity - 1 WHERE id IN (17, 18);

SET FOREIGN_KEY_CHECKS = 1;
