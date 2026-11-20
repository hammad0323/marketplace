# PrintHub: hosting and deployment guide

## Hosting checklist

| Item | Recommendation |
|------|----------------|
| Hosting | Any cPanel / VPS with PHP 8.1+ and MySQL. For LibreOffice (DOCX conversion), use a VPS or a host that allows `soffice`. |
| HTTPS | Required. Use AutoSSL or Let's Encrypt, and redirect HTTP to HTTPS. |
| PHP limits | `upload_max_filesize` and `post_max_size` ≥ 30M, `memory_limit` ≥ 256M (PDF building), `max_execution_time` ≥ 120 |
| Private storage | Set `STORAGE_PATH` outside `public_html`, for example `/home/USER/printhub_storage` (permissions 750) |
| Cron | Hourly: `php /home/USER/public_html/cron/run.php` |
| Backups | Daily database dump, and back up `STORAGE_PATH` if you need order files (they expire with retention anyway) |
| Mail | PHP `mail()` must work for password resets and reminders. Use your host's SMTP relay or a local MTA. |
| Debug | `APP_DEBUG = false` in production |

## Apache / cPanel

The included `.htaccess`:

- sends every request to `index.php` (front controller)
- returns **403** for `config/`, `core/`, `database/`, `storage/`, `vendor/`, `cron/`, `tools/`, `mobile/`, `docs/`, `pages/`, `api/modules/`, dot-files, and `*.sql`, `*.md`, `*.json`, `*.log`, `*.sh` files
- passes the `Authorization` header through to PHP (needed for app tokens)

If your host disables `.htaccess` overrides, add the same rules to the virtual host.

## Nginx

```nginx
server {
    server_name print.example.pk;
    root /var/www/printhub;
    index index.php;
    client_max_body_size 30m;

    location ~ ^/(config|core|database|storage|vendor|cron|tools|mobile|docs|pages|api/modules)(/|$) { return 403; }
    location ~ /\.(?!well-known/) { return 403; }
    location ~ \.(sql|md|lock|json|log|sh)$ { return 403; }

    location / { try_files $uri $uri/ /index.php?$query_string; }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param HTTP_AUTHORIZATION $http_authorization;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
    }
    location = /manifest.webmanifest { try_files /index.php?$query_string =404; }
    location ^~ /.well-known/ { try_files $uri /index.php?$query_string; }
}
```

## Optional tools

```bash
# Debian / Ubuntu VPS
sudo apt install libreoffice-writer-nogui poppler-utils
# then in config.php
const SOFFICE_PATH = '/usr/bin/soffice';
const PDFTOCAIRO_PATH = '/usr/bin/pdftocairo';
```

DOCX conversion runs LibreOffice headless with a private profile and a timeout. Complex Word layouts (unusual fonts, embedded objects) may not match Word exactly, so the shop always receives the customer's original file as well.

## Security notes

- Customer documents live only in `STORAGE_PATH` and are served through signed, expiring links (`APP_KEY` HMAC). Admins see shop and order metadata, not document contents.
- Shop isolation: every owner query is scoped to the shop of the authenticated token. Values such as `shop_id` sent by clients are ignored.
- Passwords use `password_hash` (bcrypt). Tokens are stored as SHA-256 hashes. Login, forgot-password, upload and order endpoints are rate-limited.
- Uploads are checked by their real content type (finfo), size-limited, renamed to random names, and stored with no execute permission. Image metadata (EXIF, including GPS location) is removed from CV photos and logos.
- Wi-Fi passwords are encrypted at rest and shown only when the owner turns on public sharing.
- Files are deleted automatically after each shop's retention period (the platform admin sets the maximum). Customer documents are never used for AI training or any purpose other than printing the order.
- Payment gateways are **not** integrated. Shops record cash, bank transfer, JazzCash and Easypaisa payments manually, and customers can upload a receipt. Card details are never collected.

## Updating

1. Back up the database and `config/config.php`.
2. Upload the new files, but keep `config/config.php` and your storage folder.
3. Apply any new SQL migration files if a release includes them.

## Flutter app

Source: `mobile/` (Flutter 3.50+, Dart 3.14+, Material 3). One app with two modes: **customers** (scan QR, order, track, CV maker) and **shop owners** (sign in, dashboard, orders, QR, Wi-Fi, services, prices, reports, subscription).

### Configure

| What | Where |
|------|-------|
| API server | `--dart-define=API_BASE=https://yourdomain.com` (must match `APP_URL`) |
| App name | `--dart-define=APP_NAME=PrintHub`, plus `android:label` and `CFBundleDisplayName` |
| Android package | `applicationId` in `android/app/build.gradle.kts` (default `pk.printhub.printhub_app`) |
| App Links domain | `appLinkHost=yourdomain.com` in `android/gradle.properties` |
| iOS Universal Links | `ios/Runner/Runner.entitlements` → `applinks:yourdomain.com`. In Xcode, add the entitlements file to the Runner target and enable **Associated Domains**. |
| Icons | Replace `android/app/src/main/res/mipmap-*` and `ios/Runner/Assets.xcassets/AppIcon.appiconset`, or use `flutter_launcher_icons` |

### Build

```bash
cd mobile
flutter pub get
flutter analyze && flutter test

# Android (needs the Android SDK)
flutter build apk --release --dart-define=API_BASE=https://yourdomain.com -PappLinkHost=yourdomain.com
flutter build appbundle --release --dart-define=API_BASE=https://yourdomain.com   # Play Store

# iOS (needs macOS + Xcode)
flutter build ipa --release --dart-define=API_BASE=https://yourdomain.com

# Web (optional; the PHP customer web app already covers browsers)
flutter build web --release --no-web-resources-cdn --dart-define=API_BASE=https://yourdomain.com
```

**Release signing (Android):** create an upload key with `keytool -genkey -v -keystore upload.jks -keyalg RSA -keysize 2048 -validity 10000 -alias upload`, add a `signingConfigs.release` block to `android/app/build.gradle.kts` (load passwords from `key.properties`, which must be git-ignored), and point `buildTypes.release.signingConfig` at it. The template currently signs release builds with the debug key so `flutter run --release` works out of the box.

### App Links / Universal Links

1. **Android:** get the SHA-256 fingerprint of your signing key (from the Play Console → App integrity, or `keytool -list -v -keystore upload.jks`). Enter the package name and fingerprint(s) in **Admin → Settings → App links**. The server then publishes `/.well-known/assetlinks.json`.
2. **iOS:** enter `TEAMID.bundle.id` in the same settings. The server publishes `/.well-known/apple-app-site-association`.
3. Add the Play Store and App Store links in settings. The customer web page then shows an "Open in app / Install" banner, and customers can always carry on in the browser without installing anything.

Links that open the app: `/shop/{SHOP-ID}`, `/track/{order}?t=…`, `/cv/{token}`.

### Notifications

The app checks the notification API every 30 seconds while it is open, shows a local notification for new orders or order updates, and lists everything in the in-app notification centre. Background push while the app is closed needs Firebase Cloud Messaging (Android) or APNs (iOS) credentials, which are not included. To add it, add `firebase_messaging`, store device tokens with a new `device_tokens` table, and send from `notify()` in `core/notifications.php`.

### Hosting a Flutter web build on another domain

Add its origin to `CORS_ORIGINS`. API and signed file links then send the right CORS headers. Shop logos under `/media/` are static files, so allow that origin in your web server as well if you need them in the Flutter web build.
