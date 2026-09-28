# Webanza Tech — Agency Website + Admin Panel

A modern agency website built with plain PHP 8 and MySQL (no framework, no build step). It has parallax and scroll animations, and an admin panel where you can edit everything.

## What's included

**Website pages:** Home, About, Services (+ one page per service), Packages & Pricing, Portfolio (+ case studies), Blog (+ articles), Contact / Order form, Privacy & Terms pages, 404.

**Design and animation:**
- Parallax hero with a mouse-reactive 3D browser and phone mockup, floating stat cards, a particle network, glowing gradient blobs and rotating headline words.
- Word-by-word heading reveals and scroll-triggered fade, zoom and blur animations.
- Scroll-linked parallax layers, animated counters, angled scrolling ribbons, 3D tilt and spotlight service cards, magnetic buttons and a custom cursor.
- Smooth scrolling (Lenis), a preloader, a scroll-progress bar, a back-to-top progress ring and a WhatsApp button.
- The layout is responsive for phones, tablets and desktops. Animations are switched off for visitors whose device is set to reduce motion.

**30 ready-made packages in 10 categories:** E-commerce, E-commerce + Mobile App, Portfolio & Business Website (free domain + hosting), SaaS, Mobile Apps (iOS & Android), Graphic Design, Video Editing, SEO, Google Services, and Growth Bundles. Each "Get Started" button opens an order form with that package already selected.

**Admin panel (`/admin`):**
- **Dashboard:** stats, latest inquiries and the most-requested packages.
- **Inquiries & Orders:** status pipeline (New, Contacted, In progress, Won, Lost), private notes, email/WhatsApp reply buttons and CSV export.
- **Add, edit, duplicate, hide and delete** with drag-to-reorder for: Services, Package Categories, Packages, Portfolio, Blog Posts, Pages, Team, Testimonials, FAQs, Technologies, Counters and Process Steps.
- **Site Settings:**
  - Logos and favicon
  - Contact info and WhatsApp
  - Currency
  - Announcement bar
  - Social links
  - Hero text
  - About, CEO message
  - Show/hide each homepage section
  - All section headings
  - CTA and footer
  - Brand colors
  - SEO meta, Google Analytics 4, and custom head/footer code
- Rich-text editor, icon picker, image uploads with preview, newsletter subscribers (CSV export), multiple admin users and password change.
- **Security:**
  - Hashed passwords, and logins are locked for 15 minutes after 5 failed attempts.
  - Every admin form is protected against forged submissions (CSRF).
  - Uploads are checked to be real images, and no scripts can run from `uploads/`.
  - The contact form has a spam trap and a rate limit.

## Install on shared hosting (cPanel)

1. Upload the contents of this `webanza/` folder to `public_html/` (or any subfolder).
2. Create a MySQL database and user in cPanel, then edit the four `DB_*` lines at the top of **`config.php`**.
3. Open **`https://yourdomain.com/install.php`** and create your admin login. It imports `database.sql` for you. Alternatively, import `database.sql` in phpMyAdmin; the default login is then `admin@webanzatech.com` / `admin123`.
4. **Delete `install.php`**, then log in at **`/admin`**.
5. Make sure `uploads/` is writable (755). Once everything works, set `APP_DEBUG` to `false` in `config.php`.

Requirements: PHP 8.1+ with PDO MySQL, and MySQL 5.7+ or MariaDB 10.3+.

## Replace the sample content

The portfolio projects, testimonials, counters, phone/address and blog posts are **placeholder examples** so the site looks complete. Replace them with your real projects, real client reviews and real numbers from the admin panel before launch. Also upload a CEO photo under **Site Settings → CEO Message**.

## Tips

- In any heading field, wrap words in `*asterisks*` to highlight them in the green gradient, e.g. `Grow *faster* online`.
- To attach packages to a service page, set **Linked service** on the package category.
- Font Awesome icons and the Lenis smooth-scroll library are bundled in `assets/vendor/`, so the site does not depend on those CDNs. Only Google Fonts loads from the web.
