-- ============================================================
--  Webanza Tech — database schema + starter content
--  Import this file once (phpMyAdmin → Import), or run install.php
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(190) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  last_login DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  skey VARCHAR(100) PRIMARY KEY,
  svalue MEDIUMTEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS services (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL UNIQUE,
  icon VARCHAR(80) NOT NULL DEFAULT 'fa-solid fa-code',
  short_desc VARCHAR(500) NULL,
  description MEDIUMTEXT NULL,
  features TEXT NULL,
  image VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS package_categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  service_id INT UNSIGNED NULL,
  name VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL UNIQUE,
  icon VARCHAR(80) NOT NULL DEFAULT 'fa-solid fa-box',
  description VARCHAR(500) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS packages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NULL,
  name VARCHAR(190) NOT NULL,
  tagline VARCHAR(255) NULL,
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  old_price DECIMAL(12,2) NULL,
  price_suffix VARCHAR(60) NULL,
  delivery_time VARCHAR(80) NULL,
  features TEXT NULL,
  badge VARCHAR(60) NULL,
  cta_text VARCHAR(60) NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  KEY (category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS portfolio (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL UNIQUE,
  category VARCHAR(120) NULL,
  client VARCHAR(190) NULL,
  image VARCHAR(255) NULL,
  summary VARCHAR(500) NULL,
  description MEDIUMTEXT NULL,
  tech VARCHAR(255) NULL,
  project_url VARCHAR(255) NULL,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS team (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  role VARCHAR(190) NULL,
  photo VARCHAR(255) NULL,
  bio TEXT NULL,
  linkedin VARCHAR(255) NULL,
  facebook VARCHAR(255) NULL,
  email VARCHAR(190) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS testimonials (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  role VARCHAR(190) NULL,
  photo VARCHAR(255) NULL,
  content TEXT NOT NULL,
  rating TINYINT NOT NULL DEFAULT 5,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS faqs (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  question VARCHAR(500) NOT NULL,
  answer TEXT NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS posts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL,
  slug VARCHAR(255) NOT NULL UNIQUE,
  category VARCHAR(120) NULL,
  image VARCHAR(255) NULL,
  excerpt VARCHAR(500) NULL,
  content MEDIUMTEXT NULL,
  author VARCHAR(120) NULL,
  published_at DATE NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS clients (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  icon VARCHAR(80) NULL,
  logo VARCHAR(255) NULL,
  link VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stats (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  label VARCHAR(120) NOT NULL,
  value INT NOT NULL DEFAULT 0,
  suffix VARCHAR(10) NULL,
  icon VARCHAR(80) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS process_steps (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  description VARCHAR(500) NULL,
  icon VARCHAR(80) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pages (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(190) NOT NULL,
  slug VARCHAR(190) NOT NULL UNIQUE,
  subtitle VARCHAR(255) NULL,
  content MEDIUMTEXT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS inquiries (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(190) NOT NULL,
  email VARCHAR(190) NOT NULL,
  phone VARCHAR(60) NULL,
  company VARCHAR(190) NULL,
  service VARCHAR(190) NULL,
  package_id INT UNSIGNED NULL,
  package_name VARCHAR(255) NULL,
  budget VARCHAR(80) NULL,
  message TEXT NULL,
  status ENUM('new','contacted','in_progress','won','lost') NOT NULL DEFAULT 'new',
  notes TEXT NULL,
  ip VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS subscribers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------
-- Admin login: admin@webanzatech.com / admin123  (change it!)
-- ------------------------------------------------------------
INSERT IGNORE INTO admins (id, name, email, password_hash) VALUES
(1, 'Hammad Jamil', 'admin@webanzatech.com', '$2y$12$2zIfQIpHy/wcvpQA5vPyYOjdZIUn80s0skvnEVDokw9MJp.d5rzUu');

-- ------------------------------------------------------------
-- Settings
-- ------------------------------------------------------------
INSERT IGNORE INTO settings (skey, svalue) VALUES
('site_name', 'Webanza Tech'),
('site_tagline', 'Websites, Apps & Growth — Engineered to Win'),
('logo', 'assets/img/logo.png'),
('logo_light', 'assets/img/logo-light.png'),
('favicon', 'assets/img/favicon.png'),
('email', 'info@webanzatech.com'),
('phone', '+92 300 0000000'),
('whatsapp', '+92 300 0000000'),
('address', 'Lahore, Pakistan'),
('working_hours', 'Mon – Sat, 10:00 AM – 7:00 PM (PKT)'),
('currency_symbol', '$'),
('currency_position', 'before'),
('notify_email', ''),
('map_embed', ''),
('announcement', '🚀 Launch offer: free domain + 1 year hosting with every website package!'),
('announcement_link', 'packages.php'),

('social_facebook', 'https://www.facebook.com/webanzatech'),
('social_linkedin', 'https://www.linkedin.com/in/webanza-tech-745b92248'),
('social_instagram', ''),
('social_twitter', ''),
('social_youtube', ''),
('social_behance', ''),
('social_github', ''),
('social_tiktok', ''),

('hero_badge', 'Full-Service Digital Agency'),
('hero_title_1', 'We Build'),
('hero_rotating', 'E-commerce Stores\nSaaS Platforms\niOS & Android Apps\nBrands that Stand Out\nSEO that Ranks'),
('hero_title_2', 'That Grow Your Business'),
('hero_subtitle', 'From pixel-perfect design to scalable code and data-driven marketing — Webanza Tech is the one team you need to launch, grow and lead online.'),
('hero_btn1_text', 'Explore Packages'),
('hero_btn1_link', 'packages.php'),
('hero_btn2_text', 'Get a Free Quote'),
('hero_btn2_link', 'contact.php'),
('hero_rating_text', 'Rated 4.9/5 by clients worldwide'),

('about_eyebrow', 'Who We Are'),
('about_title', 'A digital partner obsessed with your growth'),
('about_text', 'Webanza Tech is a full-service digital agency helping startups, brands and established businesses turn ideas into high-performing digital products. We combine strategy, design, engineering and marketing under one roof — so your website, app and growth campaigns all work together.\n\nWhether you need a conversion-focused online store, a SaaS platform built to scale, a mobile app on iOS and Android, or a brand identity that people remember, our team delivers on time, on budget and beyond expectations.'),
('about_points', 'Dedicated project manager for every client\nTransparent pricing — no hidden costs\nPost-launch support & maintenance\nModern tech stack, fast & secure by default'),
('about_image', ''),
('mission', 'To empower every business with world-class digital products that are beautiful, fast and built to grow revenue.'),
('vision', 'To be the most trusted technology partner for ambitious brands across Pakistan and around the world.'),

('ceo_name', 'Hammad Jamil'),
('ceo_role', 'Founder & CEO'),
('ceo_message', 'When we started Webanza Tech, we had one promise: treat every client''s business like our own. Today our team of designers, developers and marketers keeps that promise on every project — from a first logo to full-scale e-commerce and SaaS platforms. Your growth is the only metric we care about.'),
('ceo_photo', ''),
('ceo_linkedin', 'https://www.linkedin.com/in/hammad-jamil-499b3b193/'),

('services_heading', 'Everything you need to win online'),
('services_subheading', 'End-to-end digital services — strategy, design, development and marketing — delivered by one expert team.'),
('packages_heading', 'Transparent packages, real results'),
('packages_subheading', 'Pick a ready-made package or ask for a custom plan. Every website package includes a free domain & hosting for the first year.'),
('portfolio_heading', 'Selected work we are proud of'),
('team_heading', 'The people behind your success'),
('testimonials_heading', 'What our clients say'),
('faq_heading', 'Frequently asked questions'),
('blog_heading', 'Insights & news'),
('process_heading', 'How we deliver, step by step'),
('clients_heading', 'Tools & platforms we master'),

('home_show_services', '1'),
('home_show_stats', '1'),
('home_show_about', '1'),
('home_show_packages', '1'),
('home_show_process', '1'),
('home_show_portfolio', '1'),
('home_show_ceo', '1'),
('home_show_team', '1'),
('home_show_clients', '1'),
('home_show_testimonials', '1'),
('home_show_faq', '1'),
('home_show_blog', '1'),

('marquee_words', 'E-commerce\nSaaS Development\nWeb Development\niOS Apps\nAndroid Apps\nGraphic Design\nVideo Editing\nSEO\nGoogle Analytics\nGoogle Ads'),
('cta_title', 'Ready to build something amazing?'),
('cta_text', 'Tell us about your project and get a free consultation and quote within 24 hours.'),
('cta_btn_text', 'Let''s Talk'),
('footer_about', 'Webanza Tech builds e-commerce stores, SaaS platforms, mobile apps and brands — and grows them with SEO and Google marketing.'),
('copyright', '© {year} Webanza Tech. All rights reserved.'),

('meta_title', 'Webanza Tech — E-commerce, SaaS, Web & Mobile App Development Agency'),
('meta_description', 'Webanza Tech is a full-service digital agency offering e-commerce development, SaaS, web development, iOS & Android apps, graphic design, video editing, SEO and Google Analytics services.'),
('meta_keywords', 'web development, ecommerce website, saas development, mobile app development, seo, graphic design, video editing, google analytics, Pakistan'),
('og_image', ''),
('ga_id', ''),
('head_code', ''),
('footer_code', ''),

('color_primary', '#1f9d55'),
('color_accent', '#3ee089'),
('color_dark', '#0a1424');

-- ------------------------------------------------------------
-- Services
-- ------------------------------------------------------------
INSERT IGNORE INTO services (id, title, slug, icon, short_desc, description, features, sort_order) VALUES
(1, 'E-commerce Development', 'ecommerce-development', 'fa-solid fa-cart-shopping',
 'High-converting online stores on Shopify, WooCommerce or fully custom — with payments, shipping and inventory built in.',
 '<p>Your online store is your 24/7 salesperson. We design and develop e-commerce experiences that load fast, look stunning on every device and turn visitors into repeat customers.</p><p>From product catalogues and secure checkout to local payment gateways (JazzCash, Easypaisa, Stripe, PayPal), courier integrations and powerful admin dashboards — we handle everything.</p>',
 'Shopify, WooCommerce & custom PHP/Laravel stores\nPayment gateways: Stripe, PayPal, JazzCash, Easypaisa\nInventory, orders & customer management\nCourier & shipping integrations\nConversion-focused UX and speed optimisation\nOptional iOS & Android shopping app', 1),
(2, 'SaaS Product Development', 'saas-development', 'fa-solid fa-cloud',
 'Scalable multi-tenant SaaS platforms — from MVP to enterprise — with subscriptions, dashboards and APIs.',
 '<p>Have a software idea? We turn it into a secure, scalable SaaS product. Our team handles product discovery, UX, architecture, development, cloud deployment and ongoing iteration.</p>',
 'Product discovery & MVP roadmap\nMulti-tenant architecture\nSubscription billing (Stripe, Paddle)\nRole-based dashboards & analytics\nREST / GraphQL APIs & integrations\nCloud deployment on AWS / DigitalOcean', 2),
(3, 'Web Development', 'web-development', 'fa-solid fa-code',
 'Corporate websites, portfolios, landing pages and custom web apps — fast, secure and easy to manage.',
 '<p>We build websites that do more than look good — they rank, load in a blink and convert. Every site comes with a content management panel so you can update it yourself.</p>',
 'Corporate & business websites\nPersonal portfolios & landing pages\nCustom web applications\nCMS / admin panel included\nDomain, hosting & business email setup\nMobile-first, SEO-ready code', 3),
(4, 'Mobile App Development', 'mobile-app-development', 'fa-solid fa-mobile-screen-button',
 'Native-quality iOS & Android apps with Flutter, React Native, Swift and Kotlin — published on App Store & Google Play.',
 '<p>We design and build mobile apps that users love to open. From UI/UX to backend APIs, push notifications and store publishing, we take your app from idea to the App Store and Google Play.</p>',
 'iOS (App Store) & Android (Google Play)\nFlutter & React Native cross-platform apps\nNative Swift & Kotlin development\nPush notifications, payments & maps\nAdmin panel & backend APIs\nApp Store / Play Store publishing', 4),
(5, 'Graphic Designing', 'graphic-designing', 'fa-solid fa-pen-nib',
 'Logos, brand identities, social media creatives, UI/UX and print design that make your brand unforgettable.',
 '<p>Great design builds trust in seconds. Our creative studio crafts brand identities, marketing creatives and product interfaces that are consistent, modern and memorable.</p>',
 'Logo & complete brand identity\nSocial media post & ad creatives\nUI/UX design in Figma\nBrochures, flyers & business cards\nPackaging & label design\nPitch decks & presentations', 5),
(6, 'Video Editing & Motion', 'video-editing', 'fa-solid fa-film',
 'Scroll-stopping reels, YouTube edits, product videos and motion graphics that grow engagement.',
 '<p>Video is the most engaging content on the internet. We edit, colour-grade and animate videos that hook viewers in the first second and keep them watching.</p>',
 'Instagram Reels, TikTok & YouTube Shorts\nYouTube long-form editing\nProduct & promotional videos\nMotion graphics & logo animation\nColour grading & sound design\nSubtitles & captions', 6),
(7, 'Search Engine Optimization', 'seo', 'fa-solid fa-magnifying-glass-chart',
 'Rank higher on Google with technical SEO, content, local SEO and white-hat link building.',
 '<p>We help customers find you. Our data-driven SEO covers technical audits, on-page optimisation, content strategy, local SEO and authority link building — with transparent monthly reporting.</p>',
 'Technical SEO audit & fixes\nKeyword research & on-page SEO\nLocal SEO & Google Business Profile\nContent strategy & blog writing\nWhite-hat link building\nMonthly ranking & traffic reports', 7),
(8, 'Google Analytics & Google Services', 'google-services', 'fa-brands fa-google',
 'GA4, Tag Manager, Search Console, Google Ads and Merchant Center — set up right and tracked properly.',
 '<p>Make decisions based on data, not guesses. We set up and manage the full Google ecosystem so every visit, lead and sale is tracked accurately.</p>',
 'Google Analytics 4 setup & conversion tracking\nGoogle Tag Manager implementation\nSearch Console & sitemap submission\nGoogle Business Profile optimisation\nGoogle Ads (Search, Shopping, PMax)\nMerchant Center & Looker Studio dashboards', 8);

-- ------------------------------------------------------------
-- Package categories & packages
-- ------------------------------------------------------------
INSERT IGNORE INTO package_categories (id, service_id, name, slug, icon, description, sort_order) VALUES
(1, 1, 'E-commerce', 'ecommerce', 'fa-solid fa-cart-shopping', 'Online stores with domain, hosting and payments ready to sell.', 1),
(2, 1, 'E-commerce + Mobile App', 'ecommerce-app', 'fa-solid fa-mobile-screen', 'Your store on the web, iOS and Android — all synced.', 2),
(3, 3, 'Portfolio & Business Website', 'websites', 'fa-solid fa-globe', 'Websites with free domain & hosting for the first year.', 3),
(4, 2, 'SaaS Development', 'saas', 'fa-solid fa-cloud', 'Launch your software product, from MVP to scale.', 4),
(5, 4, 'Mobile Apps', 'mobile-apps', 'fa-brands fa-app-store-ios', 'iOS & Android apps published on both stores.', 5),
(6, 5, 'Graphic Design', 'graphic-design', 'fa-solid fa-pen-nib', 'Brand identity and creatives that stand out.', 6),
(7, 6, 'Video Editing', 'video-editing', 'fa-solid fa-film', 'Reels, YouTube and promo videos.', 7),
(8, 7, 'SEO', 'seo', 'fa-solid fa-magnifying-glass-chart', 'Monthly SEO plans to rank and grow.', 8),
(9, 8, 'Google Services', 'google-services', 'fa-brands fa-google', 'Analytics, Tag Manager, Ads & Business Profile.', 9),
(10, NULL, 'Growth Bundles', 'bundles', 'fa-solid fa-rocket', 'Everything combined — the best value for new businesses.', 10);

INSERT IGNORE INTO packages (id, category_id, name, tagline, price, old_price, price_suffix, delivery_time, features, badge, cta_text, is_featured, sort_order) VALUES
-- E-commerce
(1, 1, 'Starter Store', 'Perfect to start selling online', 399, 549, 'one-time', '10–14 days',
 'Up to 50 products\nFree .com domain (1 year)\nFree hosting + SSL (1 year)\nMobile-responsive premium theme\nCash on Delivery + 1 payment gateway\nOrder & inventory admin panel\nBasic on-page SEO\nWhatsApp chat button\n1 month free support', NULL, 'Get Started', 0, 1),
(2, 1, 'Business Store', 'For growing brands', 899, 1199, 'one-time', '3–4 weeks',
 'Up to 500 products\nFree domain + business hosting (1 year)\n3 business email accounts\nCustom UI design\nMultiple payment gateways (Stripe, PayPal, JazzCash, Easypaisa)\nCourier & shipping integration\nCoupons, reviews & wishlist\nGoogle Analytics 4 + Facebook Pixel\nAdvanced SEO setup\n3 months free support', 'Most Popular', 'Choose Plan', 1, 2),
(3, 1, 'Enterprise Store', 'Custom e-commerce at scale', 1999, 2499, 'one-time', '6–8 weeks',
 'Unlimited products\nFully custom design & development\nMulti-vendor or multi-store ready\nPremium cloud hosting (1 year) + CDN\nERP / POS / CRM integrations\nAdvanced reports & dashboards\nSpeed optimisation (90+ PageSpeed)\nSecurity hardening & daily backups\n6 months priority support', 'Best Value', 'Let''s Talk', 0, 3),
-- E-commerce + App
(4, 2, 'Store + App Starter', 'Web store with Android app', 1299, 1699, 'one-time', '4–5 weeks',
 'Everything in Starter Store\nAndroid app synced with your store\nPush notifications\nGoogle Play publishing\nFree domain & hosting (1 year)\n2 months free support', NULL, 'Get Started', 0, 1),
(5, 2, 'Store + iOS & Android', 'Complete omnichannel commerce', 2499, 3199, 'one-time', '6–8 weeks',
 'Everything in Business Store\niOS & Android apps (Flutter)\nIn-app payments & order tracking\nPush notifications & promo banners\nApp Store + Google Play publishing\nSingle admin panel for web & apps\n4 months free support', 'Most Popular', 'Choose Plan', 1, 2),
(6, 2, 'Enterprise Commerce Suite', 'Web + apps + marketplace', 4999, 6499, 'one-time', '10–12 weeks',
 'Custom web store & native-quality apps\nMulti-vendor marketplace option\nLoyalty points & wallet\nAdvanced analytics dashboard\nAPI integrations (ERP, POS, couriers)\nPremium cloud infrastructure\n6 months priority support', NULL, 'Let''s Talk', 0, 3),
-- Websites
(7, 3, 'Personal Portfolio', 'Showcase your work beautifully', 149, 199, 'one-time', '5–7 days',
 'Up to 5 pages\nFree .com domain (1 year)\nFree hosting + SSL (1 year)\nModern animated design\nContact form + WhatsApp button\nMobile responsive\nBasic SEO setup\n1 month support', NULL, 'Get Started', 0, 1),
(8, 3, 'Business Website', 'Professional online presence', 349, 449, 'one-time', '1–2 weeks',
 'Up to 10 pages\nFree domain + hosting (1 year)\n3 business email accounts\nCustom design with animations\nEasy admin panel (CMS)\nBlog & portfolio sections\nGoogle Analytics & Search Console\nOn-page SEO\n2 months support', 'Most Popular', 'Choose Plan', 1, 2),
(9, 3, 'Corporate Website', 'Premium, fully custom', 799, 999, 'one-time', '3–4 weeks',
 'Unlimited pages\nFree domain + premium hosting (1 year)\n10 business email accounts\nUnique UI/UX design (Figma)\nParallax & advanced animations\nMultilingual support\nAdvanced SEO & schema markup\nSpeed & security optimisation\n3 months support', NULL, 'Let''s Talk', 0, 3),
-- SaaS
(10, 4, 'SaaS MVP', 'Validate your idea fast', 2999, NULL, 'starting at', '6–8 weeks',
 'Product discovery workshop\nUI/UX design (up to 15 screens)\nUser auth & roles\nCore feature set\nSubscription billing (Stripe)\nAdmin dashboard\nCloud deployment\n2 months support', NULL, 'Get Started', 0, 1),
(11, 4, 'SaaS Growth', 'Scale with confidence', 6499, NULL, 'starting at', '10–14 weeks',
 'Everything in MVP\nMulti-tenant architecture\nTeam workspaces & permissions\nAnalytics & reporting\nThird-party integrations & API\nEmail automation\nCI/CD & automated backups\n4 months support', 'Most Popular', 'Choose Plan', 1, 2),
(12, 4, 'SaaS Enterprise', 'Custom-built for scale', 0, NULL, 'custom quote', 'Custom',
 'Dedicated product team\nMicroservices / high-availability\nSSO, audit logs & compliance\nMobile apps for your SaaS\nSLA-backed support\nOngoing feature development', NULL, 'Request Quote', 0, 3),
-- Mobile apps
(13, 5, 'Basic App', 'Simple app, one platform', 999, 1299, 'one-time', '3–4 weeks',
 'Android OR iOS\nUp to 8 screens\nClean modern UI\nFirebase backend\nPush notifications\nStore publishing\n1 month support', NULL, 'Get Started', 0, 1),
(14, 5, 'Standard App', 'iOS & Android together', 2499, 2999, 'one-time', '6–8 weeks',
 'iOS & Android (Flutter)\nUp to 20 screens\nCustom UI/UX design\nUser accounts & profiles\nPayments & maps integration\nAdmin panel + APIs\nApp Store & Play Store publishing\n3 months support', 'Most Popular', 'Choose Plan', 1, 2),
(15, 5, 'Premium App', 'Feature-rich & scalable', 5499, 6499, 'one-time', '10–12 weeks',
 'Native-quality iOS & Android\nUnlimited screens\nReal-time chat / tracking\nAdvanced backend & dashboard\nAnalytics & crash reporting\nScalable cloud infrastructure\n6 months priority support', NULL, 'Let''s Talk', 0, 3),
-- Graphic design
(16, 6, 'Logo Design', 'A mark that means business', 49, 79, 'one-time', '2–3 days',
 '3 unique logo concepts\nUnlimited revisions\nAll source files (AI, PSD, SVG, PNG)\nSocial media profile kit\nCommercial rights', NULL, 'Get Started', 0, 1),
(17, 6, 'Brand Identity', 'Complete visual identity', 249, 349, 'one-time', '7–10 days',
 'Logo + brand guidelines\nColour palette & typography\nBusiness card & letterhead\nEmail signature\n10 social media templates\nAll source files', 'Most Popular', 'Choose Plan', 1, 2),
(18, 6, 'Social Media Creatives', 'Monthly content design', 199, NULL, '/month', 'Ongoing',
 '30 post designs per month\n8 story designs\n4 ad creatives\nContent calendar\nBrand-consistent templates\nFast 48h turnaround', NULL, 'Subscribe', 0, 3),
-- Video
(19, 7, 'Reels Pack', 'Short-form that hooks', 99, NULL, '/10 videos', '5 days',
 '10 Reels / Shorts / TikToks (up to 60s)\nTrending transitions & captions\nMusic & sound effects\nColour correction\n2 revisions each', NULL, 'Get Started', 0, 1),
(20, 7, 'YouTube Growth', 'Long-form editing', 299, NULL, '/month', 'Ongoing',
 '4 long-form videos (up to 15 min)\n8 Shorts cut from long-form\nCustom thumbnails\nMotion titles & lower-thirds\nSubtitles\nUnlimited revisions', 'Most Popular', 'Choose Plan', 1, 2),
(21, 7, 'Commercial Video', 'Promo & product ads', 499, NULL, 'per project', '7–10 days',
 'Script & storyboard support\nUp to 90s promotional video\n2D motion graphics & logo animation\nVoice-over & licensed music\nMultiple formats (16:9, 9:16, 1:1)\n3 revisions', NULL, 'Let''s Talk', 0, 3),
-- SEO
(22, 8, 'Local SEO', 'Dominate your city', 199, NULL, '/month', 'Monthly',
 '10 target keywords\nGoogle Business Profile optimisation\nLocal citations (20/month)\nOn-page optimisation\nMonthly ranking report', NULL, 'Get Started', 0, 1),
(23, 8, 'Business SEO', 'Grow organic traffic', 399, NULL, '/month', 'Monthly',
 '30 target keywords\nTechnical SEO audit & fixes\n4 SEO blog articles / month\nWhite-hat link building\nCompetitor analysis\nGA4 & Search Console reporting', 'Most Popular', 'Choose Plan', 1, 2),
(24, 8, 'E-commerce SEO', 'Rank products & categories', 799, NULL, '/month', 'Monthly',
 '75+ target keywords\nProduct & category optimisation\nSchema / rich snippets\nCore Web Vitals optimisation\n8 articles + premium backlinks\nDedicated SEO manager', NULL, 'Let''s Talk', 0, 3),
-- Google services
(25, 9, 'Analytics Setup', 'Track what matters', 99, NULL, 'one-time', '2–3 days',
 'Google Analytics 4 setup\nGoogle Tag Manager\nConversion & event tracking\nSearch Console + sitemap\nLooker Studio dashboard', NULL, 'Get Started', 0, 1),
(26, 9, 'Google Business Pro', 'Get found on Maps', 149, NULL, 'one-time', '3–5 days',
 'Google Business Profile setup & verification help\nCategory, services & photos optimisation\nReview strategy\nGoogle Maps ranking tips\nPosts template pack', NULL, 'Choose Plan', 0, 2),
(27, 9, 'Google Ads Management', 'Leads & sales on demand', 249, NULL, '/month', 'Monthly',
 'Search, Shopping & Performance Max\nKeyword & audience research\nAd copy & creatives\nConversion tracking\nMerchant Center setup\nWeekly optimisation & report', 'Most Popular', 'Choose Plan', 1, 3),
-- Bundles
(28, 10, 'Startup Launch Kit', 'Brand + website + Google', 499, 699, 'one-time', '2–3 weeks',
 'Logo & brand identity\nBusiness website (up to 10 pages)\nFree domain + hosting (1 year)\n3 business emails\nGoogle Analytics & Business Profile\n10 social media designs\n2 months support', NULL, 'Get Started', 0, 1),
(29, 10, 'E-commerce Growth Kit', 'Store + app + marketing', 2999, 3999, 'one-time', '8 weeks',
 'Business Store + iOS & Android app\nBrand identity kit\n3 months Business SEO\nGoogle Ads & Analytics setup\n10 product/promo reels\nFree domain + hosting (1 year)\n6 months support', 'Best Value', 'Choose Plan', 1, 2),
(30, 10, 'Digital Growth Retainer', 'Your full marketing team', 899, NULL, '/month', 'Monthly',
 'Business SEO plan\nGoogle Ads management\n30 social creatives / month\n8 reels / month\nWebsite maintenance & updates\nMonthly strategy call', NULL, 'Let''s Talk', 0, 3);

-- ------------------------------------------------------------
-- Portfolio (sample projects — replace with your own)
-- ------------------------------------------------------------
INSERT IGNORE INTO portfolio (id, title, slug, category, client, summary, description, tech, is_featured, sort_order) VALUES
(1, 'Urban Threads Fashion Store', 'urban-threads-fashion-store', 'E-commerce', 'Urban Threads',
 'A high-converting fashion e-commerce store with 1,200+ products, local payments and courier tracking.',
 '<p>We redesigned and rebuilt the store on WooCommerce with a custom theme, integrated JazzCash and Easypaisa, automated courier bookings and cut page load time by 60%.</p><h3>Results</h3><ul><li>+140% online revenue in 6 months</li><li>2.1s average page load</li><li>35% of orders from returning customers</li></ul>',
 'WooCommerce, PHP, MySQL, JazzCash', 1, 1),
(2, 'MediCare+ Patient App', 'medicare-patient-app', 'Mobile App', 'MediCare+',
 'iOS & Android app for booking doctor appointments, video consultations and e-prescriptions.',
 '<p>A Flutter app with real-time availability, secure video calls and a web dashboard for clinics.</p>',
 'Flutter, Firebase, Laravel API', 1, 2),
(3, 'LedgerFlow SaaS Dashboard', 'ledgerflow-saas-dashboard', 'SaaS', 'LedgerFlow',
 'Multi-tenant invoicing & accounting SaaS with subscription billing and team roles.',
 '<p>From MVP to 800+ paying businesses — built with a scalable multi-tenant architecture and Stripe billing.</p>',
 'Laravel, Vue.js, Stripe, AWS', 1, 3),
(4, 'Bloom Organics Brand Identity', 'bloom-organics-brand-identity', 'Graphic Design', 'Bloom Organics',
 'Complete brand identity, packaging and social media kit for an organic skincare brand.',
 '<p>Logo, colour system, packaging labels and 60+ social templates that gave the brand a premium, consistent look.</p>',
 'Illustrator, Photoshop, Figma', 1, 4),
(5, 'Aroma Café Promo Campaign', 'aroma-cafe-promo-campaign', 'Video Editing', 'Aroma Café',
 'Reels and a 60-second promotional commercial that reached 500K+ views.',
 '<p>Short-form content strategy, filming guidance, editing and motion graphics.</p>',
 'Premiere Pro, After Effects', 1, 5),
(6, 'SmileCare Dental — Local SEO', 'smilecare-dental-local-seo', 'SEO', 'SmileCare Dental',
 'Took a local clinic from page 4 to the Google Maps top 3 for 20+ keywords.',
 '<p>Technical SEO, Google Business Profile optimisation, local citations and review strategy.</p>',
 'GA4, Search Console, GBP', 1, 6);

-- ------------------------------------------------------------
-- Team
-- ------------------------------------------------------------
INSERT IGNORE INTO team (id, name, role, bio, linkedin, sort_order) VALUES
(1, 'Hammad Jamil', 'Founder & CEO', 'Leads strategy and client success at Webanza Tech.', 'https://www.linkedin.com/in/hammad-jamil-499b3b193/', 1),
(2, 'Development Team', 'Web, SaaS & Mobile Engineers', 'Full-stack developers building fast, secure web and mobile products.', '', 2),
(3, 'Creative Studio', 'Designers & Video Editors', 'Brand designers, UI/UX experts and motion artists.', '', 3),
(4, 'Growth Team', 'SEO & Google Marketing', 'SEO specialists and Google-certified marketers.', '', 4);

-- ------------------------------------------------------------
-- Testimonials (samples — replace with real client reviews)
-- ------------------------------------------------------------
INSERT IGNORE INTO testimonials (id, name, role, content, rating, sort_order) VALUES
(1, 'Ayesha K.', 'Founder, Fashion Brand', 'Webanza Tech built our online store from scratch and our sales doubled within months. Communication was excellent and they delivered before the deadline.', 5, 1),
(2, 'Daniel R.', 'CEO, SaaS Startup', 'They took our SaaS idea to a working MVP in eight weeks. Clean code, great UX and they genuinely care about the product.', 5, 2),
(3, 'Usman A.', 'Owner, Restaurant Chain', 'The mobile app and the Google Business optimisation brought us a steady flow of new customers. Highly recommended!', 5, 3),
(4, 'Sara M.', 'Marketing Manager', 'Their reels and design team transformed our social media. Engagement is up 4x and our brand finally looks premium.', 5, 4);

-- ------------------------------------------------------------
-- FAQs
-- ------------------------------------------------------------
INSERT IGNORE INTO faqs (id, question, answer, sort_order) VALUES
(1, 'Do website packages really include a free domain and hosting?', 'Yes. Every website and e-commerce package includes a free .com domain, hosting and SSL certificate for the first year. After that, renewal is charged at standard rates — we will remind you well in advance.', 1),
(2, 'How long does it take to build a website or app?', 'A portfolio site takes about a week, business websites 1–2 weeks, e-commerce stores 2–6 weeks and mobile apps 4–12 weeks depending on features. You get an exact timeline with your quote.', 2),
(3, 'Can I update the website content myself?', 'Absolutely. Every website comes with an easy admin panel so you can edit text, images, products and blog posts without any coding.', 3),
(4, 'Do you publish apps on the App Store and Google Play?', 'Yes, we handle the complete publishing process for both the Apple App Store and Google Play Store, including store listings and screenshots.', 4),
(5, 'What payment methods do you accept?', 'Bank transfer, JazzCash, Easypaisa, Payoneer, Wise and PayPal. Projects are usually split into milestones — typically 50% to start and 50% on delivery.', 5),
(6, 'Do you offer support after launch?', 'Every package includes free support for the period listed. After that, you can choose an affordable monthly maintenance plan for updates, backups and security.', 6),
(7, 'Can I get a custom package?', 'Of course. The packages are a starting point — contact us with your requirements and we will prepare a tailored proposal and quote within 24 hours.', 7);

-- ------------------------------------------------------------
-- Blog
-- ------------------------------------------------------------
INSERT IGNORE INTO posts (id, title, slug, category, excerpt, content, author, published_at, sort_order) VALUES
(1, 'Shopify vs WooCommerce vs Custom: Which Is Right for Your Store?', 'shopify-vs-woocommerce-vs-custom', 'E-commerce',
 'Choosing the right platform shapes your costs, speed and growth. Here is a practical comparison for new and growing brands.',
 '<p>Choosing an e-commerce platform is one of the most important decisions for your online business.</p><h3>Shopify</h3><p>Fast to launch, secure and hosted — ideal for brands that want to focus on selling. Monthly fees and app costs add up as you grow.</p><h3>WooCommerce</h3><p>Flexible and cost-effective, built on WordPress. Great for content-driven stores and local payment integrations.</p><h3>Custom</h3><p>Maximum control and performance for unique business models such as marketplaces, subscriptions or complex B2B pricing.</p><p><strong>Not sure?</strong> Talk to our team for a free recommendation.</p>',
 'Webanza Tech', '2026-08-12', 1),
(2, '10 SEO Quick Wins to Boost Your Rankings This Month', 'seo-quick-wins', 'SEO',
 'Simple, high-impact SEO fixes you can apply today — from title tags to Core Web Vitals.',
 '<p>SEO does not always need months to show results. These quick wins can move the needle fast:</p><ol><li>Fix title tags and meta descriptions</li><li>Compress images and enable caching</li><li>Add internal links to key pages</li><li>Claim and optimise your Google Business Profile</li><li>Submit your sitemap in Search Console</li></ol>',
 'Webanza Tech', '2026-07-28', 2),
(3, 'Flutter vs Native: Building Your App for iOS and Android', 'flutter-vs-native', 'Mobile Apps',
 'Should you build one cross-platform app or two native apps? We break down cost, performance and time-to-market.',
 '<p>Cross-platform frameworks like Flutter let you ship to iOS and Android from one codebase — often cutting cost and time by 30–40%. Native development still shines for heavy graphics, AR and deep platform integrations.</p>',
 'Webanza Tech', '2026-07-05', 3);

-- ------------------------------------------------------------
-- Tools & platforms
-- ------------------------------------------------------------
INSERT IGNORE INTO clients (id, name, icon, sort_order) VALUES
(1, 'Shopify', 'fa-brands fa-shopify', 1),
(2, 'WordPress', 'fa-brands fa-wordpress', 2),
(3, 'Laravel', 'fa-brands fa-laravel', 3),
(4, 'React', 'fa-brands fa-react', 4),
(5, 'Node.js', 'fa-brands fa-node-js', 5),
(6, 'PHP', 'fa-brands fa-php', 6),
(7, 'Flutter', 'fa-solid fa-mobile-screen', 7),
(8, 'Apple iOS', 'fa-brands fa-apple', 8),
(9, 'Android', 'fa-brands fa-android', 9),
(10, 'Figma', 'fa-brands fa-figma', 10),
(11, 'Google', 'fa-brands fa-google', 11),
(12, 'AWS', 'fa-brands fa-aws', 12),
(13, 'Stripe', 'fa-brands fa-stripe', 13),
(14, 'Meta', 'fa-brands fa-meta', 14),
(15, 'Python', 'fa-brands fa-python', 15),
(16, 'Adobe', 'fa-solid fa-pen-ruler', 16);

-- ------------------------------------------------------------
-- Stats (animated counters)
-- ------------------------------------------------------------
INSERT IGNORE INTO stats (id, label, value, suffix, icon, sort_order) VALUES
(1, 'Projects Delivered', 250, '+', 'fa-solid fa-rocket', 1),
(2, 'Happy Clients', 180, '+', 'fa-solid fa-face-smile', 2),
(3, 'Countries Served', 15, '+', 'fa-solid fa-earth-asia', 3),
(4, 'Client Satisfaction', 98, '%', 'fa-solid fa-star', 4);

-- ------------------------------------------------------------
-- Process
-- ------------------------------------------------------------
INSERT IGNORE INTO process_steps (id, title, description, icon, sort_order) VALUES
(1, 'Discover', 'We learn your goals, audience and competitors in a free consultation.', 'fa-solid fa-lightbulb', 1),
(2, 'Plan & Design', 'Sitemaps, wireframes and pixel-perfect UI designs for your approval.', 'fa-solid fa-pen-ruler', 2),
(3, 'Develop', 'Clean, scalable code with weekly progress updates and demo links.', 'fa-solid fa-laptop-code', 3),
(4, 'Test & Launch', 'QA on every device, speed & security checks, then a smooth go-live.', 'fa-solid fa-rocket', 4),
(5, 'Grow & Support', 'SEO, analytics and ongoing support to keep you growing.', 'fa-solid fa-chart-line', 5);

-- ------------------------------------------------------------
-- Extra pages (shown in the footer)
-- ------------------------------------------------------------
INSERT IGNORE INTO pages (id, title, slug, subtitle, content, sort_order) VALUES
(1, 'Privacy Policy', 'privacy-policy', 'How we collect, use and protect your information.',
 '<p>We respect your privacy. Information you submit through our forms (name, email, phone and project details) is used only to respond to your inquiry and deliver our services. We never sell your data.</p><h3>Cookies & analytics</h3><p>We may use Google Analytics to understand how visitors use our website. You can disable cookies in your browser settings.</p><h3>Contact</h3><p>For any privacy questions, please contact us through the contact page.</p>', 1),
(2, 'Terms & Conditions', 'terms', 'The terms that apply to our services.',
 '<p>By ordering a package you agree to the scope, timeline and payment milestones described in your proposal. Free domain and hosting are included for the first year where stated; renewals are billed at standard rates.</p><h3>Revisions</h3><p>Revisions are included as listed in each package. Additional changes outside the agreed scope are quoted separately.</p><h3>Refunds</h3><p>Refunds are handled case by case for work that has not yet started.</p>', 2);
