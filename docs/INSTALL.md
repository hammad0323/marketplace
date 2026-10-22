# Installation & Deployment

## 1. Requirements

- PHP **8.1 or newer** (8.2/8.3 recommended) with extensions: `mysqli`, `gd` (with WebP), `fileinfo`,
  `openssl`, `curl`, `mbstring`, `json`. All are enabled by default on most cPanel hosts
  (cPanel » *Select PHP Version* to check).
- MySQL 5.7+ / MariaDB 10.3+.
- Apache with `mod_rewrite` (standard on cPanel). LiteSpeed also reads `.htaccess`.
- An SSL certificate (cPanel » *SSL/TLS Status* → AutoSSL) — required for online payments.

## 2. Deploy on cPanel (step by step)

1. **Upload the files**
   - Download the repository as a ZIP.
   - cPanel » *File Manager* » `public_html` (or the folder for your domain) » *Upload* the ZIP » *Extract*.
   - Make sure `index.php`, `.htaccess`, `admin/`, `assets/` etc. sit directly in `public_html`
     (enable *Show Hidden Files* in File Manager settings to see `.htaccess`).
2. **Create the database**
   - cPanel » *MySQL® Databases*: create a database (e.g. `cpuser_urban`), a user with a strong
     password, and *Add User To Database* with **ALL PRIVILEGES**.
3. **Import the data**
   - cPanel » *phpMyAdmin* » select the database » *Import* » choose `database/urban_collections.sql` » *Go*.
   - (`schema.sql` + `seed.sql` are the same content split in two, if you prefer an empty catalogue
     import only `schema.sql` — then create an admin with `tools/create-admin.php`, see §4.)
4. **Configure**
   - In File Manager copy `config/config.sample.php` to `config/config.php` and edit:
     - `DB_NAME`, `DB_USER`, `DB_PASS` (and `DB_HOST`, usually `localhost`).
     - `SITE_URL` — e.g. `https://www.yourdomain.com` (no trailing slash).
     - `APP_KEY` — 64 random hex characters. Generate one at cPanel » *Terminal* with
       `php -r "echo bin2hex(random_bytes(32)), PHP_EOL;"`, or use any secure password generator
       (characters 0-9 and a-f only). **Keep it secret and do not change it later** — it encrypts
       stored payment credentials and the SMTP password.
     - Leave `APP_ENV` as `production`.
5. **Permissions** — `uploads/` and `logs/` must be writable by PHP (755 is normal on cPanel).
   Everything else can stay at the defaults (644 files / 755 folders).
6. **Force HTTPS** — in `.htaccess` uncomment the two *Force HTTPS* lines once SSL works.
7. Visit your domain — the store should load with the sample content.

### Installing in a sub-folder

If the store lives at `https://example.com/store`:
- set `SITE_URL` to `https://example.com/store`,
- in `.htaccess` change `RewriteBase /` to `RewriteBase /store/`,
- change `ErrorDocument 404 /index.php` to `/store/index.php`.

### Protected folders

`config/`, `includes/`, `pages/`, `database/`, `docs/`, `tools/` and `logs/` are blocked from the
web by `.htaccess` files. `uploads/` cannot execute scripts. After importing, you may also delete the
`database/` folder from the server.

## 3. Local development

```bash
cp config/config.sample.php config/config.php   # set SITE_URL=http://localhost:8080, APP_ENV=development
mysql -u root -p -e "CREATE DATABASE urban CHARACTER SET utf8mb4"
mysql -u root -p urban < database/urban_collections.sql
php -S localhost:8080 tools/dev-router.php
```

`tools/dev-router.php` mimics the `.htaccess` rewrite rules for PHP's built-in server.
`php tools/generate-seed.php` regenerates `database/seed.sql` and `database/urban_collections.sql`
from the sample-data definitions.

## 4. Admin account setup

- Sample data includes **`admin@urbancollections.pk` / `ChangeMe@2026`** (Super Admin). On first
  sign-in at `/admin/` you must choose a new password (10+ characters, letters and numbers).
- Then open *Admin Users*, change that account's email to your own, and add staff with the right
  role: **Super Admin**, **Store Manager**, **Order Manager** or **Content Manager**
  (permissions are editable under *Roles & permissions*).
- Locked out / no admin yet? Use cPanel » *Terminal*:
  ```bash
  cd ~/public_html && php tools/create-admin.php you@yourdomain.com "Your Name"
  ```
  This creates (or resets) a Super Admin; you will be prompted for the password.
- Admin sign-in is rate-limited (5 attempts per account / 10 per IP per 15 minutes) and all
  important admin changes are recorded in the *Audit Log*.

## 5. Email

Admin » *Store Settings* » *Email*:
1. Create a mailbox in cPanel » *Email Accounts* (e.g. `orders@yourdomain.com`).
2. Enter host `mail.yourdomain.com`, port `465`, security `SSL`, the mailbox username and password.
3. Tick *Send emails*, save, then click *Send test email to me*.
4. Optional: add your address under *Orders & reviews » New-order notification emails*.

Failures are logged in `logs/mail.log`.

## 6. Cron job (recommended)

cPanel » *Cron Jobs* » every 30 minutes:

```
php /home/CPANELUSER/public_html/tools/cron.php >/dev/null 2>&1
```

It re-checks pending online payments with the gateways, cancels unpaid online orders after the
configured timeout (releasing their reserved stock) and cleans old guest carts. Without cron, the
unpaid-order cleanup still runs whenever an admin opens the dashboard.

## 7. Make it yours

1. *Store Settings* — name, logo, favicon, contact details, WhatsApp, social links, announcement bar.
2. *Theme & Design* — colours, fonts, buttons, card style, header layout, animations.
3. *Products* — replace sample products and photos with your own (or deactivate them).
4. *Homepage Builder* and *Hero Slides* — headlines, images, sections, order.
5. *Content Pages* — review every policy page.
6. *Delivery & Shipping* — your real courier rates and delivery times.
7. *Payment Methods* — see [PAYMENTS.md](PAYMENTS.md).
8. *SEO* — Search Console verification, analytics, then submit `/sitemap.xml`.

Finally work through [CHECKLISTS.md](CHECKLISTS.md).
