-- ============================================================
--  Rabia Khan's Beauty Bar & Academy — database schema + data
--  Import this file once (phpMyAdmin → Import, or
--  mysql -u USER -p DBNAME < database.sql)
--
--  Default admin login:  admin / admin123   (change it in Admin → Settings)
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS admins, settings, service_categories, services, courses,
    bookings, enrollments, testimonials, gallery, messages;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE admins (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(60)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE settings (
    skey   VARCHAR(60) PRIMARY KEY,
    svalue TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE service_categories (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(120) NOT NULL,
    slug        VARCHAR(140) NOT NULL UNIQUE,
    icon        VARCHAR(60)  NOT NULL DEFAULT 'fa-spa',
    description VARCHAR(255) DEFAULT '',
    image       VARCHAR(255) DEFAULT NULL,
    sort_order  INT NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE services (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    name        VARCHAR(150) NOT NULL,
    description VARCHAR(255) DEFAULT '',
    price       INT DEFAULT NULL,             -- NULL = "On consultation"
    price_from  TINYINT(1) NOT NULL DEFAULT 0, -- show "From Rs. …"
    duration    VARCHAR(40) DEFAULT '',
    is_featured TINYINT(1) NOT NULL DEFAULT 0,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    sort_order  INT NOT NULL DEFAULT 0,
    FOREIGN KEY (category_id) REFERENCES service_categories(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE courses (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(160) NOT NULL,
    slug        VARCHAR(180) NOT NULL UNIQUE,
    tagline     VARCHAR(200) DEFAULT '',
    description TEXT,
    includes    TEXT,                          -- one item per line
    duration    VARCHAR(60)  DEFAULT '',
    schedule    VARCHAR(100) DEFAULT '',
    timing      VARCHAR(60)  DEFAULT '',
    start_date  VARCHAR(60)  DEFAULT '',
    fee         INT DEFAULT NULL,
    seats       INT DEFAULT NULL,
    extras      VARCHAR(200) DEFAULT '',       -- e.g. "Certificate included"
    image       VARCHAR(255) DEFAULT NULL,
    is_featured TINYINT(1) NOT NULL DEFAULT 1,
    is_active   TINYINT(1) NOT NULL DEFAULT 1,
    sort_order  INT NOT NULL DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE bookings (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    ref          VARCHAR(20)  NOT NULL UNIQUE,
    service_id   INT DEFAULT NULL,
    service_name VARCHAR(150) NOT NULL,
    name         VARCHAR(120) NOT NULL,
    phone        VARCHAR(40)  NOT NULL,
    email        VARCHAR(150) DEFAULT '',
    booking_date DATE NOT NULL,
    booking_time TIME NOT NULL,
    notes        TEXT,
    status       ENUM('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
    admin_note   VARCHAR(255) DEFAULT '',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX (booking_date, booking_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE enrollments (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    course_id    INT DEFAULT NULL,
    course_title VARCHAR(160) NOT NULL,
    name         VARCHAR(120) NOT NULL,
    phone        VARCHAR(40)  NOT NULL,
    email        VARCHAR(150) DEFAULT '',
    city         VARCHAR(80)  DEFAULT '',
    message      TEXT,
    status       ENUM('new','contacted','enrolled','cancelled') NOT NULL DEFAULT 'new',
    created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE testimonials (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(120) NOT NULL,
    text       TEXT NOT NULL,
    rating     TINYINT NOT NULL DEFAULT 5,
    is_active  TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE gallery (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    image      VARCHAR(255) NOT NULL,
    caption    VARCHAR(150) DEFAULT '',
    sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE messages (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(120) NOT NULL,
    phone      VARCHAR(40)  DEFAULT '',
    email      VARCHAR(150) DEFAULT '',
    subject    VARCHAR(150) DEFAULT '',
    message    TEXT NOT NULL,
    is_read    TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ------------------------------------------------------------
--  Data
-- ------------------------------------------------------------

INSERT INTO admins (username, password_hash) VALUES
('admin', '$2y$12$TpEFbDvewCH34SW19VtS4OCNTfE5MZhqd0UHTt2IX.HK4520GV1SS');

INSERT INTO settings (skey, svalue) VALUES
('site_name',     'Rabia Khan''s Beauty Bar & Academy'),
('tagline',       'Where elegance meets artistry'),
('hero_title',    'Your Beauty, Our Passion'),
('hero_text',     'Bridal makeup, signature hair cuts, nails, facials and professional beauty courses — crafted with love in DHA Phase 2 Extension, Karachi.'),
('about_text',    'Rabia Khan''s Beauty Bar & Academy is a boutique salon and training academy in DHA Karachi. Led by Rabia Khan herself, our team pairs refined technique with genuine care — from a relaxing massage to a full bridal look. Through our academy we train the next generation of makeup artists, hairstylists and nail technicians with hands-on, practical classes.'),
('phone',         '0304 8398890'),
('whatsapp',      '923048398890'),
('email',         ''),
('address',       'Shop No 2, Plot No D-22, Sunset Lane 1, DHA Phase 2 Extension, Karachi 75500'),
('hours',         'Open daily · 11:00 AM – 8:00 PM'),
('open_time',     '11:00'),
('close_time',    '20:00'),
('slot_minutes',  '60'),
('slot_capacity', '2'),
('closed_day',    ''),
('instagram',     'https://www.instagram.com/rabiakhans_beauty_bar/'),
('facebook',      'https://www.facebook.com/RabiakhanBeautyBarSalon/'),
('map_query',     'Rabia khan''s Beauty Bar & Academy, Sunset Lane 1, DHA Phase 2 Extension, Karachi'),
('rating',        '4.8'),
('review_count',  '56'),
('followers',     '1281');

INSERT INTO service_categories (id, name, slug, icon, description, image, sort_order) VALUES
(1, 'Hair Cutting & Treatment', 'hair-cutting', 'fa-scissors',             'Precision cuts, layers and bangs shaped to suit you.',              'assets/img/bridal-side.jpg', 1),
(2, 'Hair Styling & Colour',    'hair-styling', 'fa-wand-magic-sparkles',  'Blow dry, balayage, highlights, rebounding and extensions.',        'assets/img/bridal-bun.jpg',  2),
(3, 'Makeup & Bridal',          'makeup-bridal','fa-gem',                  'Bridal, party and event looks that photograph beautifully.',        'assets/img/model.jpg',       3),
(4, 'Nails',                    'nails',        'fa-hand-sparkles',        'Acrylic extensions, gel polish, manicure and pedicure.',            'assets/img/nails.jpg',       4),
(5, 'Skin & Facial',            'skin-facial',  'fa-spa',                  'Hydra facials and targeted acne treatments for glowing skin.',     'assets/img/brushes.jpg',     5),
(6, 'Waxing & Threading',       'waxing',       'fa-feather-pointed',      'Gentle, hygienic waxing and precise threading.',                    NULL,                         6),
(7, 'Lashes & Brows',           'lashes',       'fa-eye',                  'Eyelash extensions and perfectly shaped brows.',                    NULL,                         7),
(8, 'Massage & Relaxation',     'massage',      'fa-hands',                'Unwind with a soothing, stress-melting massage.',                   NULL,                         8);

-- Hair cutting prices are from the official price list.
-- Other services have no published price yet (shown as "On consultation") — set them in Admin → Services.
INSERT INTO services (category_id, name, price, is_featured, sort_order) VALUES
(1, 'Baby Hair Cut (0–5 Years)', 800,  0, 1),
(1, 'Hair Trimming',             800,  1, 2),
(1, 'Bangs',                     400,  0, 3),
(1, 'Korean Bangs',              400,  1, 4),
(1, 'Front Layers Crown Area',   800,  0, 5),
(1, 'Full Layers',               1500, 1, 6),
(1, 'Long Layers',               2000, 0, 7),
(1, 'Step Cut',                  1500, 0, 8),
(1, 'Signature Hair Cut',        2500, 1, 9),
(1, 'Butterfly Cut',             2000, 1, 10),
(1, 'Wolf Cut',                  1500, 0, 11),
(1, 'Long Bob',                  1000, 0, 12),
(1, 'Slant Cut',                 2500, 0, 13),
(1, 'U / V Cut',                 1500, 0, 14);

INSERT INTO services (category_id, name, description, is_featured, sort_order) VALUES
(2, 'Blow Dry',                 'Smooth, bouncy, salon-finish blow dry.', 1, 1),
(2, 'Hairstyling',              'Party, event and bridal hairstyles.',    0, 2),
(2, 'Balayage',                 'Hand-painted, sun-kissed colour.',       1, 3),
(2, 'Highlights',               'Brighten and add dimension.',            0, 4),
(2, 'Rebounding',               'Long-lasting straight, sleek hair.',     0, 5),
(2, 'Hair Extensions',          'Instant length and volume.',             0, 6),
(2, 'Braids',                   '',                                       0, 7),
(2, 'Box Braids',               '',                                       0, 8),
(2, 'Shampoo & Conditioning',   '',                                       0, 9),
(3, 'Bridal Makeup',            'Complete bridal look with hairstyling.', 1, 1),
(3, 'Party / Event Makeup',     'Glam for every celebration.',            1, 2),
(3, 'Makeup Services',          'Soft glam, natural and editorial looks.',0, 3),
(4, 'Acrylic Nails',            'Durable, elegant acrylic extensions.',   1, 1),
(4, 'Gel Polish',               '',                                       0, 2),
(4, 'Manicure',                 '',                                       0, 3),
(4, 'Pedicure',                 '',                                       0, 4),
(5, 'Hydra Facial',             'Deep cleanse, exfoliate and hydrate.',   1, 1),
(5, 'Acne Treatment',           'Targeted care for clearer skin.',        0, 2),
(6, 'Body Waxing',              '',                                       0, 1),
(6, 'Brazilian Waxing',         '',                                       0, 2),
(6, 'Waxing',                   '',                                       0, 3),
(6, 'Eyebrow Threading',        '',                                       0, 4),
(6, 'Hair Threading',           '',                                       0, 5),
(7, 'Eyelash Extensions',       'Fuller, fluttery lashes.',               1, 1),
(8, 'Relaxing Massage',         'Full-body stress relief.',               1, 1);

INSERT INTO courses (title, slug, tagline, description, includes, duration, schedule, timing, start_date, fee, seats, extras, image, sort_order) VALUES
('Basic to Advance Makeup & Hairstyling Course', 'makeup-hairstyling-course',
 'Build your skills, create your future!',
 'A 2-month professional course that takes you from the fundamentals of skin preparation all the way to complete party, event and bridal looks. Learn directly from Rabia Khan with small batches and hands-on practice every day.',
 'Basic to Advance Makeup Techniques\nSkin Preparation & Base Techniques\nEye Makeup Techniques\nLip & Contouring Techniques\nBasic to Advance Hairstyling\nDifferent Hair Styling Techniques\nParty & Event Looks\nProfessional Training & Practical Practice',
 '2 Months', '5 days a week (Monday to Friday)', '2:00 PM – 6:00 PM', '1st September', 70000, NULL,
 'Practice material provided by the academy', 'assets/img/course-makeup-flyer.webp', 1),
('3 Weeks Nails Extension Course', 'nails-extension-course',
 'Professional training | Certificate included',
 'An intensive 3-week course covering everything you need to start offering nail extensions professionally — from nail anatomy and hygiene to acrylic, gel, French, ombre and chrome finishes.',
 'Nail anatomy & hygiene\nAcrylic & gel nail extensions\nDual forms technique\nFrench & ombre nails\nGlitter, chrome nails\nGel polish application\nInfill, removal & aftercare',
 '3 Weeks', '3 days per week', '', '', 20000, NULL,
 'Certificate included', 'assets/img/course-nails-flyer.webp', 2);

INSERT INTO testimonials (name, text, rating, sort_order) VALUES
('Google Review', 'Excellent service by owner Rabia Khan for acrylic nails. Highly recommended!', 5, 1),
('Google Review', 'Amazing services. Massage by Savera was extremely relaxing. I would definitely recommend others. Highly satisfied.', 5, 2),
('Google Review', 'Best service — Rabia did a great job! Plus the staff is very caring.', 5, 3);

INSERT INTO gallery (image, caption, sort_order) VALUES
('assets/img/model.jpg',        'Soft glam bridal look',   1),
('assets/img/bridal-bun.jpg',   'Embellished bridal bun',  2),
('assets/img/nails.jpg',        'Nude almond extensions',  3),
('assets/img/bridal-side.jpg',  'Romantic side curls',     4),
('assets/img/brushes.jpg',      'Our kit',                 5),
('assets/img/course-makeup-flyer.webp', 'Makeup & hairstyling academy', 6);
