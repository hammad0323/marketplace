<?php
/**
 * Beglet configuration.
 *
 * Copy this file to `app/config.php` — or, better, to a file named
 * `beglet-config.php` placed ONE LEVEL ABOVE your public_html folder
 * (the bootstrap looks there first, so secrets never sit inside the
 * web root). Never commit the real file.
 */

// ---- Database (cPanel → MySQL Databases) ---------------------------
define('DB_HOST', 'localhost');
define('DB_PORT', 3306);
define('DB_NAME', 'beglet');
define('DB_USER', 'beglet_user');
define('DB_PASS', 'change-me');

// ---- Application ---------------------------------------------------
// Public URL of the store, no trailing slash. Sub-folder installs work:
// e.g. 'https://example.com/shop'
define('APP_URL', 'https://www.example.com');

// 'production' hides PHP errors from visitors and logs them instead.
define('APP_ENV', 'production');

// 64 hex characters. Encrypts payment-gateway secrets at rest and signs
// tokens. Generate with:  php -r "echo bin2hex(random_bytes(32));"
// Changing it later makes stored gateway secrets unreadable (re-enter them).
define('APP_KEY', 'REPLACE_WITH_64_HEX_CHARACTERS_GENERATED_AS_DESCRIBED_ABOVE_____');

// ---- Outgoing email --------------------------------------------------
// 'mail' uses PHP mail() (works on most cPanel hosts).
// 'smtp' uses the SMTP account below (recommended for deliverability).
define('MAIL_DRIVER', 'mail');
define('MAIL_FROM', 'orders@example.com');
define('MAIL_FROM_NAME', 'Beglet');
define('SMTP_HOST', 'mail.example.com');
define('SMTP_PORT', 587);              // 587 = STARTTLS, 465 = SSL
define('SMTP_SECURE', 'tls');          // 'tls', 'ssl' or ''
define('SMTP_USER', 'orders@example.com');
define('SMTP_PASS', '');

// Secret token used by the cron URL (tools/cron.php?key=...). Optional.
define('CRON_KEY', '');
