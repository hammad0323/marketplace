<?php
/**
 * Quran Foundation API settings. This is the ONLY file you need to edit.
 *
 * 1. Create a "Backend/server app" at https://dev-console.quran.foundation/projects
 * 2. Paste its Client ID and Client Secret below.
 * 3. Keep QF_ENV = 'prelive' until Quran Foundation approves production access.
 *    (Pre-live data only contains Surah 1 and Surah 2.)
 *
 * Credentials can also come from environment variables QF_CLIENT_ID /
 * QF_CLIENT_SECRET / QF_ENV, which override the values here.
 */

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    exit('Direct access not permitted.');
}

define('QF_CLIENT_ID', getenv('QF_CLIENT_ID') ?: 'PASTE_YOUR_CLIENT_ID');
define('QF_CLIENT_SECRET', getenv('QF_CLIENT_SECRET') ?: 'PASTE_YOUR_CLIENT_SECRET');

// 'prelive' or 'production' — must match the credentials above.
define('QF_ENV', getenv('QF_ENV') ?: 'prelive');

// How the secret is sent to the token endpoint: 'basic' (HTTP Basic, the
// documented default) or 'post' (client_id/client_secret in the body).
// The client automatically tries the other one once on "invalid_client".
define('QF_AUTH_METHOD', 'basic');

// Folder for the cached token and cached API responses. Must be writable.
// Best: a folder OUTSIDE public_html, e.g. dirname(__DIR__, 2) . '/qf-cache'.
define('QF_CACHE_DIR', __DIR__ . '/cache');

// Response cache lifetime in seconds. QF Terms allow at most 7 days.
define('QF_CACHE_TTL', 86400);

// Defaults used by the demo pages (users can change them from dropdowns).
define('QF_DEFAULT_TRANSLATION', 131); // Dr. Mustafa Khattab, The Clear Quran (English)
define('QF_DEFAULT_LANGUAGE', 'en');

// Sent with every request; QF blocks requests without a User-Agent.
define('QF_USER_AGENT', 'MyQuranSite/1.0 (PHP)');

// Set to true only while debugging: shows full API error details on pages.
define('QF_DEBUG', false);
