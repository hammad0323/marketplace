# Proposal Maker

A website for making professional proposals for your clients.

It is built with **simple Core PHP** (plain functions, no classes, no framework), plus **MySQL**, **Bootstrap 5**, **jQuery/AJAX** and **SweetAlert2**.
It runs on normal cPanel / Hostinger shared hosting, and everything it needs is already inside the folder, so you don't need Composer, npm or a CDN.

---

## 1. Installation (5 minutes)

1. **Upload** the zip to your hosting (for example `public_html/proposals/`) and **extract** it.
2. In cPanel → **MySQL Databases**, create a **database** and a **user**, and give the user **all privileges**.
3. Open `https://your-domain.com/proposals/install.php` in your browser.
4. Fill in:
   * the database name, user and password
   * your company name, and your admin email and password
   * ✅ **Install demo data** (ticked by default). This adds 11 sample proposals so you can try everything.
5. Click **Install**, then **delete `install.php`** from your server.

### Other way: phpMyAdmin
1. Create an empty database and import **`database.sql`** (tables + starter content + demo data).
2. Copy `config.sample.php` to `config.php` and enter your database details.
3. Log in with **admin@demo.com / admin123**.

### Demo logins (only when demo data is installed)

| Email | Password | Role |
|---|---|---|
| admin@demo.com | admin123 | Admin |
| editor@demo.com | editor123 | Editor |

> Change these passwords (or delete these users in **Users**) before you go live.

### What the demo data contains
* The company profile **Webanza Tech** with a logo, contact details and social links.
* **11 proposals** for different clients, covering all 7 services, the 10 templates and 5 currencies (USD, CAD, GBP, AED, PKR):

| # | Proposal | Client | Template | Status |
|---|---|---|---|---|
| 1 | Website Development | ABC Company | Modern Corporate | Accepted (with signature) |
| 2 | SEO Growth | Bright Dental Clinic | Professional Blue | Viewed |
| 3 | Mobile App Development | FitLife Studio | Startup | Sent |
| 4 | Social Media Marketing | Bella Boutique | Creative Agency | Draft |
| 5 | Brand Identity | Nova Labs | Dark Premium | Rejected (with reason) |
| 6 | Website Redesign | Green Leaf Organics | Elegant Business | Viewed |
| 7 | Video Production | Peak Fitness | Bold Creative | Expired |
| 8 | E-commerce Website | Al Noor Trading LLC | Tech / Digital | Draft |
| 9 | Local SEO | Maple Realty | Luxury Minimal | Sent |
| 10 | Corporate Website Design | Luxe Interiors | Minimal White | Accepted |
| 11 | Food Delivery App | QuickBite PK | Startup | Viewed |

* Views, downloads, emails, accept/reject answers and the activity history.
* The dates move forward automatically, so the demo always looks recent.

### Folder permissions
`uploads/` and `storage/` must be writable (usually `755`).

### Public links
Client links look like `/proposal/view/abc123…`. They need Apache `mod_rewrite` (on almost every host it is on).
If those links show "404", either set `define('PRETTY_URLS', false);` in `config.php` (links become `view.php?token=…`), or set `RewriteBase` in `.htaccess`.

### Email
Go to **Settings → Email / SMTP**.
For Hostinger use host `smtp.hostinger.com`, port `465` with SSL, and your full email address and its password.
Use **Save & send test** to check it works.

---

## 2. What it can do

* **Dashboard:** counts of total, draft, sent, viewed, accepted, rejected and expired proposals; the total value (per currency); a 6-month chart; a status chart; recent proposals; and recent activity.
* **Company settings:** logo (plus a light version for dark designs), name, tagline, phone, WhatsApp, email, website, address, city, country, postal code and social media links.
* **Brand settings:** your colours and fonts, the default template, currency and number prefix, and saved **colour presets**.
* **Create proposal:** client details, then an automatic number (`PROP-2026-001`) that you can change, then one of **10 templates**, then a **pre-built proposal** (Web Development, Web Design, SEO, Graphics, Video, Mobile App, Social Media) or a blank one.
* **Editor (like Canva):** widgets on the left, pages in the middle, settings on the right.
  * 31 widgets: text, heading, image, image+text, logo, button, divider, spacer, icon, lists, quote, feature cards, service cards, pricing table, comparison table, table, statistics, testimonials, team, timeline, process, FAQ, signature, terms, contact, social links, call to action, page break and custom HTML.
  * Drag & drop, move up/down, duplicate, hide/show and delete.
  * Pages: add, rename, copy, delete, reorder and hide.
  * Change colours, fonts and the template at any time (only for that proposal).
  * Header/footer with page numbers.
  * `{{variables}}` such as `{{client_name}}`.
  * Content library, autosave, version history, and desktop/tablet/mobile preview.
* **Pricing table:** qty × price, subtotal, discount (% or fixed), tax and total, in any currency.
* **PDF:** A4, looks the same as the preview. The file name looks like `Website_Development_Proposal_ABC_Company.pdf`.
* **Client page:** view, download, print, **Accept** (with a typed, drawn or uploaded signature) or **Reject** (with a reason).
* **Tracking:** Draft → Sent → Viewed → Accepted/Rejected, Expired, number of views, first/last view, and the full history.
* **Send:** copy the link, open your email app, or send by SMTP with the PDF attached.

---

## 3. How the code works (for beginners)

Every page is a normal `.php` file. It starts like this:

```php
require __DIR__ . '/includes/bootstrap.php';   // load config, database, functions
require_login();                                 // only logged-in users
$p = pm_get($id);                                // get data from the database
include __DIR__ . '/layouts/header.php';         // print the page...
```

### Files you will touch most

| File | What it does |
|---|---|
| `index.php` | Dashboard |
| `proposals.php` | List of proposals |
| `proposal-create.php` | Create a proposal (3 steps) |
| `editor.php` + `assets/js/editor.js` | The visual editor |
| `proposal.php` | One proposal: send, link, status, history |
| `view.php` | The page your client opens |
| `pdf.php` | Download the PDF |
| `settings.php` | Company, brand colours, email |
| `ajax/*.php` | Small files that answer the JavaScript (AJAX) calls |

### The `includes/` folder

| File | What it does |
|---|---|
| `bootstrap.php` | Loads everything (always included first) |
| `db.php` | Database helpers: `db_all()`, `db_row()`, `db_insert()`, `db_update()` |
| `helpers.php` | Small helpers: `e()` (safe HTML), `url()`, `money()`, image upload/resize |
| `auth.php` | Login: `require_login()`, `require_admin()` |
| `templates.php` | The 10 designs: names, colours, and the cover page of each |
| `blocks.php` | The 31 widgets: their settings and the HTML of each one |
| `renderer.php` | Turns a proposal into HTML pages (for the editor, preview and PDF) |
| `proposal_model.php` | Save, load, copy, versions and make the PDF |
| `prebuilt.php` | The text of the 7 ready-made proposals |
| `mailer.php` | Sending emails |

### How a proposal is stored
* `proposals`: one row per proposal (client, template, colours, totals, status…).
* `proposal_pages`: the pages of a proposal.
* `proposal_blocks`: the widgets on each page. The settings of a widget are saved as JSON in the `data` column.

### How the HTML is made (renderer.php)
1. `doc_create($proposal, $pages, $company, 'screen')` puts everything into one array, `$doc`.
2. `render_sheets($doc)` returns the A4 pages as HTML.
3. Each widget has its own function, e.g. `pm_render_text($doc, $data, $block)`.

The **same code** makes the editor, the preview, the client page and the PDF, which is why they all look the same.

### Why the PDF looks like the preview
* The designs use `<table>` and fixed positions instead of flexbox/grid, because the PDF engine (Dompdf) doesn't support flexbox/grid.
* The fonts are inside the app (`assets/fonts`), so the browser and the PDF use the exact same fonts.
* The editor measures every widget and saves where each A4 page must end. The PDF uses the same page ends.

### Adding things
* **A new widget:** add it to the list in `pm_blocks()` (`includes/blocks.php`) and write a function `pm_render_yourname($doc, $d, $b)`. The editor builds the settings form for you from the `fields` list.
* **A new template:** add it to `pm_templates()`, write `tpl_cover_11()` in `includes/templates.php`, and add CSS for `.tpl-11` in `assets/css/templates.css`.

---

## 4. Database files

| File | Contents |
|---|---|
| `sql/schema.sql` | Tables only |
| `sql/starter.sql` | Colour presets, the 7 pre-built proposals and the content library |
| `sql/demo.sql` | Demo company, demo users and the 11 demo proposals |
| `database.sql` | All three together (for a phpMyAdmin import) |

Tables: `users`, `company_settings`, `brand_presets`, `proposals`, `proposal_pages`, `proposal_blocks`, `proposal_responses`, `proposal_activity`, `proposal_versions`, `content_library`, `service_templates`.

---

## 5. Security
* All SQL uses prepared statements (`?` placeholders).
* All forms have a CSRF token.
* Passwords use `password_hash()`.
* Uploaded images are checked, re-saved and resized. PHP files cannot run inside `uploads/`.
* Text from the editor is cleaned (no `<script>`).
* `includes/`, `config.php`, `vendor/` and the `.sql` files cannot be opened from the browser.

## 6. Libraries included
Dompdf (PDF), PHPMailer (email), Bootstrap 5, Bootstrap Icons, jQuery, SweetAlert2, SortableJS (drag & drop), Summernote (text editor) and Chart.js.
Fonts: Inter, Poppins, Montserrat, DM Sans, Space Grotesk, Playfair Display, Lora and Cormorant Garamond (all free, SIL Open Font License).
