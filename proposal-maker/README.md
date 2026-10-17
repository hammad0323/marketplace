# Proposal Maker

A proposal builder for digital agencies, written in **Core PHP + MySQL (MySQLi)**, **Bootstrap 5**, **jQuery/AJAX**, **SweetAlert2**, **SortableJS** and **Summernote**. It needs no framework, Node or build step, and runs on ordinary cPanel / Hostinger shared hosting.

---

## Features

| Area | What you get |
|---|---|
| **Dashboard** | Total / draft / sent / viewed / accepted / rejected / expired counts, total and won value, win rate, 6‑month chart, status chart, recent proposals with View · Edit · Duplicate · PDF · Share · Delete, and an activity feed |
| **Company settings** | Logo (normal + light version for dark templates), name, tagline, phone, WhatsApp, email, website, address, city, country, postal code, Facebook, Instagram, LinkedIn, YouTube, X |
| **Brand settings** | Primary / secondary / accent / heading / text / background / button colours, fonts, default template, currency, number prefix, validity. Unlimited **colour presets** (Webanza Default, Blue Corporate, Dark Premium, Green Modern and Red Creative are included) |
| **Create wizard** | Client info → auto proposal number (`PROP-2026-001`, editable) → choose one of 10 templates (live thumbnails) → choose a pre-built service proposal or start blank |
| **10 templates** | Modern Corporate, Dark Premium, Creative Agency, Minimal White, Tech/Digital, Elegant Business, Bold Creative, Startup, Professional Blue, Luxury Minimal. Each has its own **cover layout** and matching **internal page design**, and every colour comes from CSS variables |
| **Visual editor** | Canva‑style three-panel layout: widgets on the left, A4 pages in the centre, settings on the right. Drag & drop, move up/down, duplicate, hide/show, delete, keyboard shortcuts (Ctrl+S, Del), zoom, and autosave |
| **31 widgets** | Text, Heading, Image, Image + Text, Logo, Button, Divider, Spacer, Icon, Bullet List, Numbered List, Quote, Feature Cards, Service Cards, Pricing Table, Comparison Table, Table, Statistics, Testimonial, Team Member, Timeline, Process Steps, FAQ, Signature, Terms & Conditions, Contact Info, Social Links, Call To Action, Page Break, Custom HTML, Cover |
| **Rich text** | Bold, italic, underline, font size, headings, alignment, text colour, highlight, links, bullet/numbered lists, line height, tables |
| **Pages** | Add, rename, duplicate, delete, reorder (drag), hide, header/footer on/off per page, background colour per page |
| **Header & footer** | Logo, custom text, company / website / email / phone, page numbers (`Page {n} of {total}`, `{nn}`), and 4 styles |
| **Pricing** | Line items with qty × price, subtotal, % or fixed discount, tax, total; USD, CAD, AED, GBP, EUR, PKR (+AUD, INR, SAR) or a custom currency |
| **Variables** | `{{client_name}}`, `{{client_company}}`, `{{client_email}}`, `{{proposal_number}}`, `{{proposal_date}}`, `{{expiry_date}}`, `{{company_name}}`, `{{company_email}}`, `{{company_phone}}`, `{{company_website}}`, `{{total_amount}}`, `{{project_name}}`, `{{proposal_link}}` … |
| **Pre-built proposals** | Web Development (20 sections), Web Design, SEO, Graphic Design, Video Editing, Mobile App, Social Media Marketing. You can save any proposal as a new pre-built one |
| **Content library** | Save any block or a whole page as a reusable section and insert it into any proposal |
| **Preview** | Full presentation mode plus desktop, tablet and mobile previews |
| **PDF** | A4 output from Dompdf with fonts, colours, backgrounds, images, tables, headers/footers and page numbers. File names look like `Website_Development_Proposal_ABC_Company.pdf` |
| **Public link** | `/proposal/view/8f73a9c21…`, works on mobile, with Accept / Reject / Download PDF / Print |
| **Tracking** | Draft → Sent → Viewed, first/last viewed, view count, automatic **Expired**, full activity log |
| **Accept / Reject** | Name, email, comment, reason. Signature can be **typed, drawn or uploaded**, and is stored with the date and IP |
| **Sharing** | Copy link, open email client, send by **SMTP** (PHPMailer) with optional PDF attachment, email templates with variables, and a notification email when the client responds |
| **History** | Automatic versions (every 10 minutes while editing), named versions, one-click restore |
| **Users** | Multiple users (admin / editor) and a profile page with password change |

---

## Requirements

* PHP **7.4+** (8.x recommended) with the `mysqli`, `mbstring`, `gd`, `dom` and `fileinfo` extensions
* MySQL 5.7+ or MariaDB 10.3+
* Apache with `mod_rewrite` for the pretty public URL (optional; see below)

All PHP libraries (`vendor/`) and front-end libraries (`assets/vendor/`) are **included**, so you do **not** need Composer, npm or any CDN.

---

## Installation (cPanel / Hostinger)

1. **Upload** the contents of the zip to your hosting, e.g. `public_html/proposals/`, and extract it.
2. **Create a MySQL database and user** in cPanel → *MySQL Databases* (or hPanel → *Databases*), and give the user all privileges.
3. Open **`https://yourdomain.com/proposals/install.php`** in your browser.
4. Enter the database details, your company name and an admin email and password, then click **Install**.
   The installer creates `config.php` and the tables, and loads the 7 pre-built proposals, the content library and the colour presets.
5. **Delete `install.php`** from the server.
6. Log in, then go to **Settings → Company Profile** to upload your logo and fill in your details.

**Manual installation (no installer):** import `database.sql` in phpMyAdmin, copy `config.sample.php` to `config.php`, fill in your credentials, and create the admin user by inserting a row into `users` with a `password_hash()` value. The pre-built proposals are loaded automatically the first time you log in.

### Folder permissions
`uploads/` and `storage/` (including `storage/fonts` and `storage/tmp`) must be writable by PHP, usually `755` (or `775`).

### Pretty public URLs
`.htaccess` rewrites `/proposal/view/{token}` to `view.php?token={token}`.
If your host does not support `mod_rewrite`, set `define('PRETTY_URLS', false);` in `config.php` and links will use `view.php?token=…`.
If the app is in a sub-folder and the pretty links return 404, uncomment `RewriteBase` in `.htaccess` and set it to your folder.

### Email (SMTP)
Go to **Settings → Email / SMTP**. For Hostinger use `smtp.hostinger.com`, port `465` with SSL (or `587` with TLS), and your full mailbox address and password. Use **Save & send test** to check it works.

---

## How the PDF matches the preview

The same PHP renderer (`includes/renderer.php`) produces the HTML for the editor, the preview, the public link and the PDF.

* Templates and blocks use **tables and absolute positioning** (no flexbox/grid), which Dompdf renders faithfully.
* Each proposal's colours are injected as **CSS variables**, so changing one proposal never affects another.
* The fonts (Inter, Poppins, Montserrat, DM Sans, Space Grotesk, Playfair Display, Lora, Cormorant Garamond) are **bundled TTF files** used by both the browser and Dompdf. Their line metrics are normalised at PDF time so text wraps and spaces the same way as in Chrome.
* The editor **measures every block** and stores where each A4 page must break (`settings.breaks`). The preview and the PDF use the same break points, and long text, lists, tables, FAQs and timelines split cleanly across pages. Blocks that are taller than a whole page are flagged in red in the editor.
* Cover / hero images are **cropped server-side** (GD), so they look identical everywhere. Uploads are resized to 1800 px and compressed.

Gradients (Tech and Startup templates) appear in the browser; in the PDF they fall back to the solid base colour.

---

## Project structure

```
index.php               Dashboard
proposals.php           Proposal list (search, filters, sort, pagination)
proposal-create.php     3-step creation wizard
editor.php              Visual proposal builder
proposal.php            Details: send email, tracking, responses, versions, activity
preview.php             Admin presentation preview (?embed=1, ?print=1)
view.php                Public client page  (/proposal/view/{token})
pdf.php                 PDF download (?id= for admin, ?token= for client)
settings.php            Company profile · Brand & presets · Email/SMTP
templates.php           Design template gallery
service-templates.php   Pre-built proposals
library.php             Content library
users.php / profile.php User management / own profile
install.php             Web installer (delete after use)
ajax/                   AJAX endpoints (editor, proposals, public responses, management)
includes/
  bootstrap.php         Config, session, autoload of the modules below
  db.php                MySQLi prepared-statement helpers
  helpers.php           Escaping, CSRF, uploads, image compression/cropping, fonts …
  auth.php              Login / roles
  templates.php         10 template definitions + cover/page designs
  blocks.php            Widget registry (defaults + form schema) and renderers
  renderer.php          ProposalRenderer (variables, pagination, sheets, PDF HTML)
  proposal_model.php    Save/load, duplicate, versions, totals, PDF generation
  prebuilt.php          Pre-built service proposals + default library sections
  mailer.php            PHPMailer/SMTP sending
  icons.php             Icon library (SVG → coloured data URIs)
layouts/                Admin layout, viewer, shared partials
assets/css              app.css (admin), editor.css, proposal.css (blocks), templates.css (10 designs)
assets/js               app.js, editor.js, viewer.js
assets/fonts            Bundled TTF fonts (SIL Open Font License)
assets/picons           Proposal icons (Bootstrap Icons, MIT)
assets/vendor           Bootstrap, Bootstrap Icons, jQuery, SweetAlert2, SortableJS, Summernote, Chart.js
vendor/                 Dompdf, PHPMailer (Composer)
database.sql            Database schema
```

### Database tables
`users`, `company_settings`, `brand_presets`, `proposals`, `proposal_pages`, `proposal_blocks`, `proposal_responses`, `proposal_activity`, `proposal_versions`, `content_library`, `service_templates`.

---

## Security notes

* Every query uses prepared statements, and every form or AJAX call is CSRF-protected.
* Passwords are stored with `password_hash()`, and there is basic login throttling.
* Rich text and custom HTML are sanitised (scripts and event handlers are removed).
* Uploads are checked by MIME type, re-encoded with GD, and blocked from executing (`uploads/.htaccess`).
* `includes/`, `layouts/`, `storage/`, `vendor/`, `config.php` and `database.sql` are blocked from web access.
* Public links use random 16-character tokens. You can regenerate a link from the proposal details page.

## Customising
* **Add a template:** add an entry in `pm_templates()` (`includes/templates.php`), a `tpl_cover_N()` function and a `tpl_deco()` case, then style `.tpl-N` in `assets/css/templates.css`.
* **Add a widget:** add it to `pm_blocks()` with `defaults` and `fields`, and write a `pm_render_<type>()` function. The editor builds its settings form from the `fields` schema automatically.
