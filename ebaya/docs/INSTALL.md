# Installation & Deployment

## Requirements

- PHP 8.1 or newer with the `mysqli`, `mbstring`, `fileinfo`, `gd` (with JPEG and WebP), `openssl`, `curl` and `dom` extensions. All of these are standard on cPanel.
- MySQL 5.7+ or MariaDB 10.3+.
- Apache with `mod_rewrite` and `AllowOverride All`. This is the default on cPanel.
- An SSL certificate (cPanel AutoSSL is fine). It is required for live payments.

## cPanel deployment

1. **Create the database.** Go to cPanel → *MySQL® Databases* and create a database and a user. Add the user to the database with **ALL PRIVILEGES**, and note the full names, for example `cpuser_ebaya` and `cpuser_ebayauser`.
2. **Import the data.** Go to cPanel → *phpMyAdmin*, select the database, open the *Import* tab, choose `database/ebaya.sql` and click *Go*. This one file creates every table and loads the sample store.
3. **Upload the files.** Upload the **contents** of this `ebaya/` folder to `public_html/` (or to an addon-domain or sub-folder). You can zip it locally and use *File Manager → Upload → Extract*. Make sure hidden files (`.htaccess`) are included.
4. **Configure the store.** In File Manager, copy `config/config.sample.php` to `config/config.php` and edit it:
   - the `DB_HOST`, `DB_NAME`, `DB_USER` and `DB_PASS` values from step 1;
   - `SITE_URL`: your full URL with no trailing slash, e.g. `https://ebaya.pk`, or `https://example.com/shop` for a sub-folder;
   - `APP_KEY`: a random 32-byte base64 string. Generate one at cPanel → *Terminal*: `php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"`. **Keep it safe.** It encrypts the payment gateway secrets.
   - Keep `APP_ENV` as `production` and `FORCE_HTTPS` as `true`.
5. **Set permissions.** `uploads/` and `storage/logs/` must be writable by PHP (usually 755 directories, owned by your cPanel user). Files should be 644. Keep `config/config.php` at 640 or 600.
6. **Turn on HTTPS.** Once SSL is active, uncomment the *Force HTTPS* block in `.htaccess`.
7. **Check the site.** Visit your domain, then `/admin/` to sign in.

### `.htaccess` notes

- All storefront URLs (`/shop`, `/category/…`, `/product/…`, `/account/login`, `/sitemap.xml`, …) are routed to `index.php`.
- `/admin/products` maps to `/admin/products.php`.
- `config/`, `includes/`, `pages/`, `templates/`, `database/`, `storage/` and `docs/` each carry their own `Require all denied` and are also blocked by the root rules.
- `uploads/.htaccess` disables PHP and script execution.
- If the site runs in a sub-folder and URLs 404, uncomment `RewriteBase` and set it to the folder, e.g. `RewriteBase /shop/`.

### Optional: move the database file

After importing, you can delete `database/` from the server. It is already blocked from the web.

## Local development

```bash
# MySQL
mysql -uroot -e "CREATE DATABASE ebaya CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -uroot ebaya < database/ebaya.sql
cp config/config.sample.php config/config.php   # set DB creds, SITE_URL=http://localhost:8080, APP_ENV=development, FORCE_HTTPS=false
```

Serve the folder with Apache (AllowOverride All) on port 8080. `.htaccess` does the routing, so PHP's built-in server is not a drop-in replacement.

## Admin account setup

- Sign in at `/admin/` with **admin@ebaya.test / Ebaya@Admin2026**.
- You are **forced to choose a new password** (at least 10 characters) before anything else.
- Go to *Admin users & roles* and change the email to your own. Better still, create your own Super Admin and then disable the seeded one.
- Create staff accounts with the right role:
  - **Store Manager**: products, categories, inventory, orders, customers and coupons.
  - **Order Manager**: order processing and fulfilment.
  - **Content Manager**: homepage, banners, collections, pages and theme.
  - Fine-tune any role in the permissions matrix. Cost prices are visible only to roles with *View & edit cost prices* (Super Admin by default).
- **Forgotten admin password:** another Super Admin can set a temporary password under *Admin users & roles*. If you are locked out entirely, generate a hash with `php -r "echo password_hash('NewPass123!', PASSWORD_DEFAULT);"` and paste it into `admins.password_hash` in phpMyAdmin.

## Replacing the sample content

1. *Products*: upload real photos (front, back, side, detail) and set alt text. Confirm the fabric, lining and transparency details.
2. *Hero slides*: upload desktop (about 2400×1250) and mobile (about 1000×1400) images.
3. *Categories* and *Collections*: upload card images and banners.
4. *Homepage builder → Handcrafted with love* and *The Ebaya story*: upload close-up craft photography.
5. *Reviews & testimonials*: delete the sample testimonials and feature genuine reviews only.
6. *Content pages*: review every policy, especially Shipping, Returns, Privacy and Terms.
7. *Store settings*: add your logo, favicon, contact details and social links.
