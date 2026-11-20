# PrintHub: installation guide

PrintHub has four parts that share one database:

| Part | Folder | Who uses it |
|------|--------|-------------|
| Super Admin panel | `admin/` | Platform owner |
| Shop owner web dashboard | `owner/` | Print shop owners (desktop or tablet) |
| Customer web app (a PWA) | `index.php`, `pages/`, `assets/` | Customers, after scanning a shop QR code |
| REST API | `api/` | The Flutter app (and the web pages) |
| Flutter app | `mobile/` | Shop owners and customers (Android, iOS) |

## 1. Requirements

- PHP 8.1 or newer with these extensions: `mysqli`, `gd`, `mbstring`, `fileinfo`, `openssl`, `json`, `zlib`, `dom` (needed by dompdf)
- MySQL 5.7+ or MariaDB 10.4+
- Apache with `mod_rewrite` (cPanel default). Nginx works too; see DEPLOYMENT.md.
- HTTPS (needed for app links, the PWA and secure cookies)
- Optional: **LibreOffice** (`soffice`) converts DOCX to PDF so Word files get page counts and previews. Without it, customers enter the page count and the shop prints the original Word file.
- Optional: **Poppler** `pdftocairo` repairs PDFs that the built-in reader cannot open (for example some compressed PDFs).

PHP libraries (dompdf, php-qrcode, FPDI/FPDF) are already included in `vendor/`, so Composer is not needed on the server.

## 2. Upload the files

1. Upload the contents of the `printhub/` folder to your web root (for example `public_html/`) or to a subfolder (for example `public_html/print/`). Subfolders work, and links adapt automatically to `APP_URL`.
2. The `mobile/` folder is the Flutter source. It does not need to be on the server (it is blocked by `.htaccess` anyway).

## 3. Create the database

In cPanel → **MySQL Databases**: create a database and a user, then give the user ALL PRIVILEGES on that database. Then in phpMyAdmin, select the database and import, in this order:

1. `database/schema.sql` (tables)
2. `database/seed.sql` (default settings, printing options, global categories, 10 CV templates and the first admin account)

From the command line:

```bash
mysql -u USER -p DBNAME < database/schema.sql
mysql -u USER -p DBNAME < database/seed.sql
```

## 4. Configure

```bash
cp config/config.sample.php config/config.php
```

Edit `config/config.php`:

| Setting | Value |
|---------|-------|
| `DB_*` | database host, name, user and password |
| `APP_URL` | public URL without a trailing slash, for example `https://print.example.pk`. QR codes point to `APP_URL/shop/{SHOP-ID}`. |
| `APP_KEY` | 64 random hex characters: `php -r "echo bin2hex(random_bytes(32));"`. It signs file links and encrypts Wi-Fi passwords. Never change it after go-live, or old links and stored Wi-Fi passwords stop working. |
| `STORAGE_PATH` | private folder for customer files, **outside `public_html`**, for example `/home/USER/printhub_storage`. PHP must be able to write to it (permissions 750). |
| `SOFFICE_PATH`, `PDFTOCAIRO_PATH` | full paths if these tools are installed, otherwise `''` |
| `CORS_ORIGINS` | leave `[]` unless you host a Flutter web build on another domain |
| `MAIL_FROM` | sender for password resets and subscription reminders (uses PHP `mail()`) |

`config/config.php` is git-ignored and blocked by `.htaccess`. Never commit it.

## 5. Cron job

Add this in cPanel → **Cron Jobs** to run once an hour:

```
php /home/USER/public_html/cron/run.php
```

It sends subscription reminders and expiry notices, deletes order files after each shop's retention period, and removes expired temporary uploads and previews. Subscription expiry itself is checked in real time, so a missed cron run never leaves an expired shop open.

## 6. First login

1. Open `https://yourdomain.com/admin/` and sign in with **admin@printhub.pk** / **ChangeMe@2026**.
2. You must choose a new password immediately.
3. In **Settings**, set the platform name, support phone and email, default monthly fee (PKR 1,500), grace days, reminder days, maximum upload size and file retention limits.
4. In **Shops → Add shop**, create a shop and its owner login. Give the owner the username and temporary password. They must change the password on first sign-in.
5. Record the first subscription payment under **Payments** (cash, bank transfer, JazzCash or Easypaisa, with an optional proof image) to activate the shop.

There is no public shop registration. Only the admin can create shops.

## 7. Shop owner setup

The owner signs in at `/owner/` or in the app, then:

1. **Services:** turn on the categories, paper sizes, paper types, GSM and finishing the shop offers.
2. **Prices:** enter the shop's own rates (for example A4 B&W single-sided Rs. 10 per page). Anything without a price becomes a quote request.
3. **Shop settings:** choose automatic price or quote, the payment mode and instructions, ready time and file retention.
4. **QR code:** print the poster and put it on the counter. Optionally switch on **Wi-Fi sharing**.

## 8. Check the installation

- `https://yourdomain.com/api/v1/platform` returns `{"status":true,...}`.
- `https://yourdomain.com/config/config.php`, `/storage/` and `/database/schema.sql` all return 403 or 404.
- Scan a shop poster with a phone: the shop page opens without any app installed.

## Local development

```bash
mysql -u root -e "CREATE DATABASE printhub CHARACTER SET utf8mb4; CREATE USER 'printhub'@'localhost' IDENTIFIED BY 'phpass'; GRANT ALL ON printhub.* TO 'printhub'@'localhost';"
mysql -u printhub -pphpass printhub < database/schema.sql
mysql -u printhub -pphpass printhub < database/seed.sql
cp config/config.sample.php config/config.php   # set APP_URL=http://localhost:8090
php -S localhost:8090 tools/dev-router.php
```

`tools/dev-router.php` applies the same "deny private folders" rules as `.htaccess`.

## Flutter app

See [DEPLOYMENT.md](DEPLOYMENT.md#flutter-app) for build instructions. Quick start:

```bash
cd mobile
flutter pub get
flutter run --dart-define=API_BASE=https://yourdomain.com
```
