# Deployment on cPanel / shared hosting

**Requirements:** PHP 8.1+ with `mysqli`, `gd` (FreeType), `mbstring`, `intl` (recommended), `sodium`, `openssl`, `curl`, `fileinfo` and `dom`. You also need MySQL 5.7+ or MariaDB 10.3+, and Apache with `mod_rewrite` (LiteSpeed works too, since it reads `.htaccess`).

## 1. Create the database
1. In cPanel, open **MySQL® Databases** and create a database (for example `cpuser_beglet`) and a user with a strong password.
2. Add the user to the database with **ALL PRIVILEGES**.
3. Open **phpMyAdmin**, select the database, go to **Import** and choose `install/database.sql`.
   - For a clean store with no demo products, import `install/schema.sql` and then `install/seed.sql` instead.

## 2. Upload the files
Upload everything into `public_html/` (or the addon domain's folder), including the hidden `.htaccess` files in the root, `admin/`, `app/`, `uploads/`, `install/`, `docs/`, `tools/` and `storage/`. Those folders contain `Require all denied` rules, so their contents are never served.

Make `storage/logs`, `storage/cache` and `uploads/` writable by PHP (usually `755`; use `775` if your host requires it).

## 3. Configure, keeping secrets outside the web root
Copy `app/config.sample.php` to **one level above `public_html`** and name it `beglet-config.php` (for example `/home/cpuser/beglet-config.php`). The bootstrap looks there first. If your host doesn't allow that, use `app/config.php`, which is also protected by `.htaccess`.

Set these values:
- `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`
- `APP_URL`: the public URL with no trailing slash, e.g. `https://www.beglet.pk`
- `APP_ENV`: `production`
- `APP_KEY`: generate it with `php -r "echo bin2hex(random_bytes(32));"`. This key encrypts payment credentials, so keep it safe and never change it after saving credentials.
- Mail: `MAIL_DRIVER` is `mail` (PHP `mail()`) or `smtp`. Use SMTP with a cPanel email account for better deliverability, and add SPF and DKIM records in cPanel → **Email Deliverability**.

**Sub-folder installs** (e.g. `https://example.com/shop`): set `APP_URL` to include the folder and change `RewriteBase /` to `RewriteBase /shop/` in `.htaccess`.

## 4. First login
1. Visit `/admin` and sign in as `admin@example.com` with the password `ChangeMe@2026`. You will be required to set a new password.
2. Go to **System → Administrators** and change the email address to your own. Create accounts for staff with the appropriate roles.
3. Work through the **Pre-launch checklist** in [TESTING.md](TESTING.md).

## 5. Cron job
In cPanel → **Cron Jobs**, add a job that runs every 15 minutes:
```
*/15 * * * * php /home/cpuser/public_html/tools/cron.php >/dev/null 2>&1
```
It re-checks online payments that are still pending with the provider, releases stock from unpaid online orders after the configured timeout (*Settings → Store features*), and purges expired tokens, rate-limit rows and stale guest carts.

## 6. HTTPS
Enable AutoSSL or Let's Encrypt in cPanel → **SSL/TLS Status**. Then uncomment the *Force HTTPS* block in `.htaccess` and make sure `APP_URL` starts with `https://`. Session cookies are marked `Secure` automatically over HTTPS.

## 7. Search engines
- Submit `https://your-domain/sitemap.xml` in Google Search Console and Bing Webmaster Tools. Add the verification codes under **Settings → SEO**.
- The sitemap updates automatically as content changes. Both robots.txt and ads.txt are served dynamically from the SEO screen.

## Updating
Back up the database (phpMyAdmin → Export) and `uploads/` before uploading new code. Never overwrite your config file or `uploads/`.

## Troubleshooting
| Symptom | Fix |
|---|---|
| Every page except the home page returns 404 | `mod_rewrite` is disabled or `.htaccess` wasn't uploaded. Check `AllowOverride All`. |
| "Beglet is not configured yet" | The config file is missing; see step 3. |
| "store is temporarily unavailable" | The database credentials are wrong. Check the details in `storage/logs/php-error.log`. |
| Images fail to upload | Make `uploads/` writable and confirm that PHP `upload_max_filesize` is at least 5M. |
| Emails don't arrive | Switch to SMTP and check **email_log** entries (phpMyAdmin) for errors. |
