<?php
/**
 * The Hashir — site configuration.
 * Edit the values in this file for each environment (local, staging, live).
 * This file must never be publicly downloadable (.htaccess blocks /config).
 */

// ---------------------------------------------------------------------------
// Database (your EXISTING database — this website only READS from it)
// ---------------------------------------------------------------------------
define('DB_HOST', getenv('TH_DB_HOST') ?: 'localhost');
define('DB_USER', getenv('TH_DB_USER') ?: 'your_db_user');
define('DB_PASS', getenv('TH_DB_PASS') ?: 'your_db_password');
define('DB_NAME', getenv('TH_DB_NAME') ?: 'u925768674_thehashir');
define('DB_PORT', (int)(getenv('TH_DB_PORT') ?: 3306));

// ---------------------------------------------------------------------------
// URLs
// ---------------------------------------------------------------------------
// The LIVE address of the site, no trailing slash. Canonical URLs, Open Graph and the sitemap always
// point here, so the beta copy never competes with the live site in Google.
define('SITE_URL', getenv('TH_SITE_URL') ?: 'https://www.thehashir.com');

// Sub-folder this copy is installed in. Detected automatically:
// www.thehashir.com/beta  ->  '/beta'      web root  ->  ''
// Only set TH_BASE_PATH if detection is wrong on your server.
$thBase = getenv('TH_BASE_PATH');
if ($thBase === false) {
    $thBase = '';
    $thScript = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '';
    if (substr($thScript, -10) === '/index.php') {
        // Normal Apache/cPanel case: /beta/index.php -> /beta
        $thBase = rtrim(dirname($thScript), '/');
    } elseif (!empty($_SERVER['DOCUMENT_ROOT'])) {
        // Fallback: folder of this site relative to the web root
        $thRoot = realpath($_SERVER['DOCUMENT_ROOT']);
        $thHere = realpath(__DIR__ . '/..');
        if ($thRoot && $thHere && strpos($thHere, $thRoot) === 0) {
            $thBase = rtrim(str_replace('\\', '/', substr($thHere, strlen($thRoot))), '/');
        }
    }
    unset($thScript, $thRoot, $thHere);
}
define('BASE_PATH', $thBase);
unset($thBase);

// BETA MODE: true while the site runs at /beta.
//  - every page is marked noindex (meta tag + X-Robots-Tag header) so Google never indexes /beta
//  - a slim bar invites testers to give feedback or switch back to the current site
// Set to false when this build replaces the live site.
define('BETA_MODE', getenv('TH_BETA') !== false ? getenv('TH_BETA') === '1' : true);

// Pages that still belong to your current system at the domain root. Links to them are never
// prefixed with /beta (e.g. /beta/editor would not exist).
$LEGACY_PATHS = array('/editor', '/sign-in', '/admin');

// Back office of this build (kept apart from your existing /admin so both can run side by side).
define('ADMIN_PATH', '/manage');   // Super Admin panel
define('PORTAL_PATH', '/portal');  // Mosque & organization portal

// Setup check page: set a long random key, then open /beta/setup-check?key=YOUR_KEY on the server.
// Leave empty to switch the page off (do this after deployment).
define('SETUP_KEY', getenv('TH_SETUP_KEY') ?: '');

// Where your existing uploaded images live (the folder that contains images/venue-images, images/venue-cover ...).
// '' = same server as this site. Use 'https://www.thehashir.com' when testing this build on another domain.
define('MEDIA_URL', getenv('TH_MEDIA_URL') !== false ? getenv('TH_MEDIA_URL') : '');

// Folder on the server that CONTAINS your existing images/ folder (images/venue-images, images/venue-cover ...).
// New uploads are saved there so the current site and the apps keep seeing them.
// '' = the web root (DOCUMENT_ROOT). Example for a custom path: '/home/u925768674/public_html'
define('MEDIA_DIR', getenv('TH_MEDIA_DIR') !== false ? getenv('TH_MEDIA_DIR') : '');

// Private, writable folder for site settings, blog layouts, static pages, drafts and login throttling.
// Must NOT be web-accessible (.htaccess blocks it). Make it writable (chmod 755 or 775).
define('STORAGE_DIR', getenv('TH_STORAGE_DIR') ?: __DIR__ . '/../storage');

// Login pages. The portal replaces the old sign-in page for mosques & organizations.
define('LOGIN_URL', '/portal/login');
define('REGISTER_URL', '/solution-mosque#get-started');

// ---------------------------------------------------------------------------
// Passwords
// ---------------------------------------------------------------------------
// Existing passwords in th_venue / th_admin_table are stored as plain text. New and changed passwords are
// always hashed with password_hash(). When this is true, a legacy password is also replaced by a hash right
// after its owner logs in successfully. READ README section "Password migration" first: any other system that
// still compares plain-text passwords (old admin, mobile app, display screens) must use password_verify() too.
define('PASSWORD_MIGRATE_ON_LOGIN', getenv('TH_PW_MIGRATE') !== false ? getenv('TH_PW_MIGRATE') === '1' : true);
define('LOGIN_MAX_ATTEMPTS', 5);        // failed attempts allowed ...
define('LOGIN_LOCK_MINUTES', 15);       // ... per this many minutes, per account and per IP
define('SESSION_IDLE_MINUTES', 120);    // back-office sessions end after this much inactivity
define('MAX_UPLOAD_MB', 5);             // image uploads

// ---------------------------------------------------------------------------
// Brand & contact
// ---------------------------------------------------------------------------
define('SITE_NAME', 'The Hashir');
define('SITE_TAGLINE', 'Mosques, prayer times & Muslim community services');
define('CONTACT_EMAIL', 'info@thehashir.com');      // contact form messages are emailed here
define('MAIL_FROM', 'no-reply@thehashir.com');      // must be an address on your domain for deliverability
define('CONTACT_PHONE_DISPLAY', '416-833-8922');
define('CONTACT_PHONE_E164', '+14168338922');
define('COPYRIGHT_HOLDER', 'Creatifty Inc.');
define('COPYRIGHT_URL', 'https://creatifty.com');

$SOCIAL_LINKS = array(
    'facebook'  => 'https://www.facebook.com/hashirconnect/',
    'instagram' => 'https://www.instagram.com/hashirconnect',
    'youtube'   => 'https://www.youtube.com/@hashirconnect',
    'tiktok'    => 'https://www.tiktok.com/@hashirconnect',
);

// Mobile app store links. Leave empty to hide the download buttons.
define('APP_STORE_URL', '');
define('PLAY_STORE_URL', '');

// ---------------------------------------------------------------------------
// Content rules
// ---------------------------------------------------------------------------
// Announcements older than this many days are hidden from profiles (unless their schedule_date is in the future).
define('ANNOUNCEMENT_MAX_AGE_DAYS', 90);
define('DEFAULT_TIMEZONE', 'America/Toronto');
define('PER_PAGE_VENUES', 24);
define('PER_PAGE_DETAILS', 12);

// Top bar ticker. Every item links somewhere real.
$TICKER_ITEMS = array(
    array('Discover local mosques', '/mosques'),
    array('Find prayer times in your city', '/prayer-times'),
    array('Browse Islamic programs & classes', '/program-catalog'),
    array('Nikah, counselling & funeral services', '/services-catalog'),
    array('Charities & food banks near you', '/organizations-catalog'),
    array('Support for new Muslims', '/services/Outreach-Services/18'),
    array('Run a masjid? See Solutions for Mosques', '/solution-mosque'),
);

// Homepage slider. Slides past their 'until' date (YYYY-MM-DD) are hidden automatically.
$HOME_SLIDES = array(
    array(
        'image' => 'images/thumbnails/Hashir editor banner.webp',
        'title' => 'Design your perfect greeting card in minutes',
        'button' => 'Open the editor',
        'link' => '/editor',
        'until' => '',
    ),
    array(
        'image' => 'images/thumbnails/eid-ul-adha-event-banner.webp',
        'title' => 'Eid ul Adha events across North America',
        'button' => 'Explore events',
        'link' => '/events/Eid-ul-Adha/22',
        'until' => '2026-06-15',
    ),
);

// Show detailed PHP errors (set to false on the live site).
define('DEBUG', getenv('TH_DEBUG') === '1');
