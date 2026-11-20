<?php
/**
 * PrintHub configuration. Copy to config/config.php and fill in.
 * Keep config.php private (it is blocked by .htaccess and git-ignored).
 */

// --- Database (MySQLi) -------------------------------------------------
const DB_HOST = 'localhost';
const DB_PORT = 3306;
const DB_NAME = 'printhub';
const DB_USER = 'printhub';
const DB_PASS = 'change-me';

// --- Site --------------------------------------------------------------
// Full public URL without trailing slash. QR codes point to APP_URL/shop/{SHOP-ID}.
const APP_URL = 'https://yourdomain.com';
// Long random secret (64+ hex chars). Signs file links and encrypts Wi-Fi passwords.
// Generate:  php -r "echo bin2hex(random_bytes(32));"
const APP_KEY = 'replace-with-64-hex-characters';
const APP_DEBUG = false;
const APP_TIMEZONE = 'Asia/Karachi';

// --- Private file storage -----------------------------------------------
// Customer documents are stored here. Put it OUTSIDE public_html on your host,
// e.g. /home/cpaneluser/printhub_storage . The folder must be writable by PHP.
const STORAGE_PATH = __DIR__ . '/../storage';

// --- Optional server tools ----------------------------------------------
// LibreOffice converts DOCX to PDF (page count + print-ready file). Leave '' if not installed.
const SOFFICE_PATH = '';            // e.g. '/usr/bin/soffice'
// Poppler pdftocairo rewrites PDFs that the built-in PDF reader cannot open. Optional.
const PDFTOCAIRO_PATH = '';         // e.g. '/usr/bin/pdftocairo'

// --- API -----------------------------------------------------------------
const TOKEN_TTL_DAYS = 30;           // shop-owner / admin app tokens
const CUSTOMER_SESSION_DAYS = 180;   // anonymous customer sessions
// Extra origins allowed to call the API from a browser (e.g. a Flutter web build). Same-origin is always allowed.
const CORS_ORIGINS = [];

// --- Mail (optional, for password reset and reminders) --------------------
const MAIL_FROM = 'no-reply@yourdomain.com';
const MAIL_FROM_NAME = 'PrintHub';
