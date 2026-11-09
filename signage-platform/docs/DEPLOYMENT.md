# Installation and Deployment

## 1. Requirements

| Component | Version |
|---|---|
| PHP | 8.1 or later, with `mysqli`, `gd`, `fileinfo`, `mbstring`, `json`, `curl` (curl is for the tests) |
| Database | MySQL 8.0.16+ (or MariaDB 10.6+), InnoDB, utf8mb4 |
| Web server | Apache 2.4 with `mod_rewrite` and `mod_headers`, or Nginx with PHP-FPM |
| Flutter | 3.41 or later (built and tested with 3.47.9 stable) |
| HTTPS | Required in production. Let's Encrypt is fine. |

## 2. Local development (about 5 minutes)

1. Create the database and user:

   ```bash
   mysql -u root -p -e "CREATE DATABASE signage CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER 'signage'@'localhost' IDENTIFIED BY 'devpass'; GRANT ALL ON signage.* TO 'signage'@'localhost';"
   ```

2. Load the schema:

   ```bash
   mysql -u signage -pdevpass signage < signage-platform/backend/database/schema.sql
   ```

3. Load the seed data:

   ```bash
   mysql -u signage -pdevpass signage < signage-platform/backend/database/seed.sql
   ```

4. Start the API with PHP's built-in server. The router mirrors the `.htaccess` rules:

   ```bash
   DB_NAME=signage DB_USER=signage DB_PASS=devpass php -S 0.0.0.0:8080 -t signage-platform/backend signage-platform/backend/router.php
   ```

5. Optional: run the full API test suite. It changes data, so reload the seed afterwards.

   ```bash
   php signage-platform/backend/tests/api_test.php http://127.0.0.1:8080
   ```

6. Run the management app. The Android emulator reaches the host machine at 10.0.2.2:

   ```bash
   cd signage-platform/flutter_app && flutter pub get && flutter run --flavor manager -t lib/main.dart --dart-define=API_BASE_URL=http://10.0.2.2:8080
   ```

7. Run the TV player (Android TV emulator or a real box):

   ```bash
   flutter run --flavor player -t lib/main_player.dart --dart-define=API_BASE_URL=http://10.0.2.2:8080
   ```

8. Optional: run the management app in Chrome against the local API:

   ```bash
   flutter run -d chrome -t lib/main.dart --dart-define=API_BASE_URL=http://127.0.0.1:8080
   ```

Development logins: `admin@signage.local` / `Admin@12345`, and `bilal@demo.local` / `Demo@12345` (more in `DATABASE.md §6`).

> **Testing a physical TV or phone against a local backend.** Android blocks plain HTTP by default. Add your computer's LAN IP to `android/app/src/main/res/xml/network_security_config.xml`. In production, always use HTTPS.

## 3. Production backend

### 3.1 Upload and configure

1. Copy `signage-platform/backend/` to the server, for example `/var/www/signage/backend`. Point the web root at a folder that contains it, so the API lives at `https://signage.example.com/api/…`.

2. Configure secrets. Use environment variables in the vhost or PHP-FPM pool (preferred), or create `backend/config/config.local.php`:

   ```php
   <?php return [
     'APP_ENV' => 'production',
     'APP_URL' => 'https://signage.example.com',
     'APP_SECRET' => '<64 random characters>',
     'DB_HOST' => '127.0.0.1', 'DB_NAME' => 'signage', 'DB_USER' => 'signage', 'DB_PASS' => '<strong password>',
     'CORS_ORIGINS' => 'https://signage.example.com',   // web build origin; native apps don't need CORS
     'MAIL_ENABLED' => '1', 'MAIL_FROM' => 'no-reply@signage.example.com',
   ];
   ```

   Generate the secret with:

   ```bash
   php -r "echo bin2hex(random_bytes(32));"
   ```

   The API refuses to run in production while `APP_SECRET` is still the default.

3. Set permissions:

   ```bash
   chown -R www-data:www-data backend/uploads backend/logs && chmod 750 backend/uploads backend/logs
   ```

4. Load `schema.sql`. Then **do not load the demo `seed.sql`**. Create the production data instead:

   ```bash
   php signage-platform/backend/database/tools/generate_seed.php
   ```

   Load roles, permissions, plans, categories, templates, widget types and app settings from the regenerated `seed.sql` (the first sections, up to `-- users`). Then create your first admin:

   ```bash
   php -r "echo password_hash('<password>', PASSWORD_DEFAULT);"
   ```

   ```sql
   INSERT INTO users (role_id, full_name, email, password_hash, must_change_password) VALUES (1, 'Your Name', 'you@company.com', '<hash>', 1);
   ```

   The simpler alternative: load `seed.sql`, sign in as `admin@signage.local`, create your real admin, then delete the demo users (Admin → Users → Delete) and change the default password.

5. PHP settings: `upload_max_filesize = 512M`, `post_max_size = 520M`, `max_execution_time = 300`, `display_errors = Off`. Apache picks these up from the `.htaccess`.

### 3.2 Apache

`backend/.htaccess` already handles clean URLs, forwarding the `Authorization` header, blocking `config/ includes/ database/ cron/ logs/` and `_*.php`, and blocking direct access to `uploads/`. Media is served only through signed URLs. Enable the modules and turn on HTTPS:

```bash
a2enmod rewrite headers && systemctl reload apache2
```

```bash
certbot --apache -d signage.example.com
```

### 3.3 Nginx (alternative)

```nginx
server {
  listen 443 ssl http2;
  server_name signage.example.com;
  root /var/www/signage/backend;
  client_max_body_size 520m;
  add_header Strict-Transport-Security "max-age=31536000" always;

  location ~ ^/(config|includes|database|cron|logs)/ { deny all; }
  location ~ /_[^/]*\.php$ { deny all; }
  location ^~ /uploads/images/avatars/ { try_files $uri =404; }
  location ^~ /uploads/ { deny all; }

  location / { try_files $uri $uri.php $uri/ =404; }
  location ~ \.php$ {
    try_files $uri =404;
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_param HTTP_AUTHORIZATION $http_authorization;
    fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    fastcgi_read_timeout 300;
  }
}
```

### 3.4 Cron jobs

```bash
*/5 * * * * www-data php /var/www/signage/backend/cron/mark_offline_devices.php >> /var/www/signage/backend/logs/cron.log 2>&1
```

```bash
15 3 * * *  www-data php /var/www/signage/backend/cron/expire_subscriptions.php >> /var/www/signage/backend/logs/cron.log 2>&1
```

```bash
30 3 * * *  www-data php /var/www/signage/backend/cron/cleanup.php >> /var/www/signage/backend/logs/cron.log 2>&1
```

### 3.5 Hardening checklist

- [ ] HTTPS only. HSTS is sent automatically when `APP_ENV=production`.
- [ ] A unique `APP_SECRET`. Changing it invalidates every signed media URL; players refresh them on their next sync.
- [ ] A database user with privileges on the `signage` database only.
- [ ] Demo accounts deleted; the admin password changed.
- [ ] `CORS_ORIGINS` restricted to your web origin.
- [ ] `logs/` is not web-accessible. Rotate `error.log`.
- [ ] Nightly backups of the database and `uploads/`.
- [ ] Optional: put `uploads/` on block storage. The `storage_driver` column is ready for an S3 adapter.

### 3.6 Web app

The core-PHP web app ships inside `backend/web/` and needs no build step. With the backend at `https://signage.example.com`:

| URL | Who |
|---|---|
| `https://signage.example.com/web/` | Users and the super admin (sign in with the same accounts as the app) |
| `https://signage.example.com/web/admin/templates.php` | Super admin: template gallery and designer |
| `https://signage.example.com/web/display.php` | Browser TV player: open it on a smart-TV browser or kiosk PC and enter a display code (or append `?code=LOBBY1`) |

* Pages use PHP sessions (`signage_web` cookie, HTTP-only, `Secure` on HTTPS, SameSite=Lax) and call the same REST API with a per-browser token, revoked on sign-out.
* Files starting with `_` (`_bootstrap.php`, `_layout.php`) are blocked by `.htaccess` / the router; keep that rule in Nginx too (`location ~ /_[^/]*\.php$ { deny all; }`).
* The pages load Google Fonts and Material Symbols from Google's CDN. The QR-code library is bundled (`web/assets/vendor/qrcode.js`, MIT).
* If `APP_URL` is set, it is used for links; otherwise the folder is detected automatically, so the backend can live in a sub-folder.

## 4. Building the apps

Flavors are defined in `android/app/build.gradle.kts`:

| Flavor | Entry point | Application ID | Purpose |
|---|---|---|---|
| `manager` | `lib/main.dart` | `com.signalsignage.manager` | Phones and tablets (users and admins) |
| `player` | `lib/main_player.dart` | `com.signalsignage.player` | Android TV, TV boxes, signage tablets. Includes the TV launcher entry, boot auto-start and wakelock. |

**Release signing.** Create a keystore once:

```bash
keytool -genkey -v -keystore ~/signage-release.jks -keyalg RSA -keysize 2048 -validity 10000 -alias signage
```

Then create `android/key.properties`:

```
storePassword=…
keyPassword=…
keyAlias=signage
storeFile=/home/you/signage-release.jks
```

**Build the manager app:**

```bash
flutter build appbundle --flavor manager -t lib/main.dart --dart-define=API_BASE_URL=https://signage.example.com
```

**Build the player APK** (sideloaded onto TV boxes):

```bash
flutter build apk --release --flavor player -t lib/main_player.dart --dart-define=API_BASE_URL=https://signage.example.com
```

**Build the iOS manager app** (on macOS):

```bash
flutter build ipa -t lib/main.dart --dart-define=API_BASE_URL=https://signage.example.com
```

**Build the web admin** (optional):

```bash
flutter build web -t lib/main.dart --dart-define=API_BASE_URL=https://signage.example.com
```

Serve `build/web` from any static host.

**Showing content on a TV (simplest).** Install the normal app (the `manager` build) on the TV or box. Open it, tap **Show Display**, and enter the display code of a screen or playlist. Users find the code in the app under *Show on a TV*. The device remembers the code, reopens after power loss, and updates within a minute of each publish.

**Managed display (remote control, schedules, monitoring).** This works in the same app: **Show Display → Managed display** shows a pairing code, which you enter under **Devices → Add device**. The separate `player` build below is optional, for dedicated kiosk boxes.

**Player installation on a TV box (optional dedicated build).**

1. Install the APK, for example:

   ```bash
   adb install app-player-release.apk
   ```

   You can also use a USB drive and a file manager.

2. Open Signal Player. It shows a 6-character pairing code.
3. In the manager app, go to **Devices → Add device**, enter the code and choose a playlist.
4. Optional: set Signal Player as the default launcher, or enable "auto start" in the box settings. The boot receiver already relaunches it after power loss.
5. On the TV remote, **OK/Enter** or **Menu** opens the info panel (sync now, re-pair).

## 5. Upgrading

1. Back up the database and `uploads/`.
2. Deploy the new backend files. Keep `config/config.local.php`, `uploads/` and `logs/`.
3. Apply any `database/migrations/*.sql` in order. Future releases will ship these; the 1.0 schema has none.
4. Ship the new player APK. Players keep playing their cached content during the update.
