<?php
/**
 * General helpers: escaping, URLs, images, dates, times, sanitising.
 * Written for PHP 7.2+ (the live server runs 7.2).
 */

// ---------------------------------------------------------------------------
// Output escaping
// ---------------------------------------------------------------------------
function e($s)
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function clean_text($s)
{
    $s = preg_replace('#<(br|/p|/div|/li|/h[1-6])\s*/?>#i', ' ', (string)$s);
    $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $s = str_replace("\xC2\xA0", ' ', $s);
    return trim(preg_replace('/\s+/u', ' ', $s));
}

function excerpt($html, $len = 160)
{
    $t = preg_replace('/^(details?|description)\s*:\s*/i', '', clean_text($html));
    if (mb_strlen($t, 'UTF-8') <= $len) {
        return $t;
    }
    $cut = mb_substr($t, 0, $len, 'UTF-8');
    $sp = mb_strrpos($cut, ' ', 0, 'UTF-8');
    if ($sp !== false && $sp > $len * 0.6) {
        $cut = mb_substr($cut, 0, $sp, 'UTF-8');
    }
    return rtrim($cut, " ,.;:-") . '…';
}

// ---------------------------------------------------------------------------
// URLs (existing URL patterns are preserved)
// ---------------------------------------------------------------------------
function url($path = '/')
{
    global $LEGACY_PATHS;
    if (preg_match('#^(https?:)?//#i', $path)) {
        return $path;
    }
    if ($path === '' || $path[0] !== '/') {
        $path = '/' . $path;
    }
    // Pages of the current system stay at the domain root
    foreach ((array)$LEGACY_PATHS as $legacy) {
        if ($path === $legacy || strpos($path, $legacy . '/') === 0 || strpos($path, $legacy . '?') === 0 || strpos($path, $legacy . '#') === 0) {
            return $path;
        }
    }
    return BASE_PATH . $path;
}

function abs_url($path = '/')
{
    return SITE_URL . $path;
}

/** Legacy slug: spaces become hyphens, other characters are URL-encoded (matches existing links). */
function legacy_slug($text)
{
    $text = trim(preg_replace('/\s+/u', ' ', (string)$text));
    $text = str_replace(' ', '-', $text);
    // Keep characters that are valid in a URL path as they are (matches links already indexed by Google)
    $keep = array('%2D' => '-', '%21' => '!', '%24' => '$', '%26' => '&', '%27' => "'", '%28' => '(', '%29' => ')', '%2A' => '*',
        '%2B' => '+', '%2C' => ',', '%3B' => ';', '%3D' => '=', '%3A' => ':', '%40' => '@');
    return strtr(rawurlencode($text), $keep);
}

/** Loose comparison key so slightly different slugs don't cause redirect loops. */
function slug_key($text)
{
    $t = mb_strtolower(rawurldecode((string)$text), 'UTF-8');
    return preg_replace('/[^\p{L}\p{N}]+/u', '', $t);
}

function venue_url($v)
{
    $role = isset($v['role_id']) ? (int)$v['role_id'] : 1;
    $slug = legacy_slug(!empty($v['url']) ? $v['url'] : $v['venue_title']);
    if ($role === 1) {
        return '/mosques-details/' . $slug . '/' . (int)$v['venue_id'];
    }
    return '/organization-details/' . $slug . '/' . (int)$v['venue_id'];
}

function detail_url($d)
{
    $slug = legacy_slug(!empty($d['details_url']) ? $d['details_url'] : $d['details_title']);
    return '/details/' . $slug . '/' . (int)$d['detail_id'];
}

function grand_info($gc)
{
    $map = array(
        1 => array('key' => 'events',   'single' => 'Event',   'plural' => 'Events',   'catalog' => '/events-catalog',   'icon' => 'bi-calendar-event'),
        2 => array('key' => 'programs', 'single' => 'Program', 'plural' => 'Programs', 'catalog' => '/program-catalog',  'icon' => 'bi-journal-bookmark'),
        3 => array('key' => 'services', 'single' => 'Service', 'plural' => 'Services', 'catalog' => '/services-catalog', 'icon' => 'bi-hand-thumbs-up'),
    );
    return isset($map[(int)$gc]) ? $map[(int)$gc] : $map[2];
}

function category_url($c)
{
    $g = grand_info($c['grand_category_id']);
    return '/' . $g['key'] . '/' . legacy_slug(trim($c['name'])) . '/' . (int)$c['category_id'];
}

function org_role_url($r)
{
    return '/organizations/' . legacy_slug($r['role_title']) . '/' . (int)$r['role_id'];
}

function city_prayer_url($city)
{
    return '/prayer-times/' . legacy_slug($city['country_title']) . '/' . legacy_slug($city['city_title']) . '/' . (int)$city['city_id'];
}

function blog_url($b)
{
    return '/blog/' . rawurlencode($b['url']) . '/' . (int)$b['id'];
}

function redirect($path, $code = 301)
{
    header('Location: ' . url($path), true, $code);
    exit;
}

function current_path()
{
    $p = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    if (BASE_PATH !== '' && strpos($p, BASE_PATH) === 0) {
        $p = substr($p, strlen(BASE_PATH));
    }
    return $p === '' ? '/' : $p;
}

/** Keep current query string but replace some keys. */
function query_with($changes)
{
    $q = $_GET;
    foreach ($changes as $k => $v) {
        if ($v === null || $v === '') {
            unset($q[$k]);
        } else {
            $q[$k] = $v;
        }
    }
    $s = http_build_query($q);
    return $s === '' ? '' : '?' . $s;
}

// ---------------------------------------------------------------------------
// Images from the existing uploads folders
// ---------------------------------------------------------------------------
function media($path)
{
    $path = trim((string)$path);
    if ($path === '') {
        return '';
    }
    if (preg_match('#^https?://#i', $path)) {
        return $path;
    }
    $parts = explode('/', ltrim($path, '/'));
    $parts = array_map('rawurlencode', $parts);
    return MEDIA_URL . '/' . implode('/', $parts);
}

function venue_logo($v)
{
    return empty($v['profile_image']) ? '' : media('images/venue-images/' . $v['profile_image']);
}

function venue_cover($v)
{
    return empty($v['back_image']) ? '' : media('images/venue-cover/' . $v['back_image']);
}

function detail_image($d)
{
    if (!empty($d['profile_image_1'])) {
        return media('images/details_picture/' . $d['profile_image_1']);
    }
    return '';
}

function asset($path)
{
    $file = __DIR__ . '/../assets/' . $path;
    $v = file_exists($file) ? filemtime($file) : '1';
    return url('/assets/' . $path) . '?v=' . $v;
}

/** Initials used when an image is missing (e.g. "JM" for Jame Masjid). */
function initials($name)
{
    $words = preg_split('/[\s\-]+/u', clean_text($name));
    $skip = array('of', 'the', 'and', '&', 'al', 'el', 'de', 'la');
    $out = '';
    foreach ($words as $w) {
        if ($w === '' || in_array(mb_strtolower($w, 'UTF-8'), $skip, true)) {
            continue;
        }
        $out .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
        if (mb_strlen($out, 'UTF-8') >= 2) {
            break;
        }
    }
    return $out !== '' ? $out : 'H';
}

// ---------------------------------------------------------------------------
// Dates, times and time zones
// ---------------------------------------------------------------------------
function valid_date($d)
{
    return !empty($d) && strpos($d, '0000-00-00') !== 0;
}

function fmt_date($d, $format = 'j M Y')
{
    if (!valid_date($d)) {
        return '';
    }
    $ts = strtotime($d);
    return $ts ? date($format, $ts) : '';
}

/** "13:30:00" or "1:30 PM" -> "1:30 PM". Returns '' for empty / 00:00 placeholders. */
function fmt_time($t, $allowMidnight = false)
{
    $t = trim((string)$t);
    if ($t === '' || (!$allowMidnight && preg_match('/^0?0:00(:00)?$/', $t))) {
        return '';
    }
    $ts = strtotime('2000-01-01 ' . $t);
    if (!$ts) {
        return $t;
    }
    return date('g:i A', $ts);
}

/** Minutes after midnight for sorting / comparisons in JS. */
function time_minutes($t)
{
    $ts = strtotime('2000-01-01 ' . trim((string)$t));
    if (!$ts) {
        return null;
    }
    return (int)date('G', $ts) * 60 + (int)date('i', $ts);
}

function state_timezone($state)
{
    static $map = array(
        'ontario' => 'America/Toronto', 'quebec' => 'America/Toronto', 'alberta' => 'America/Edmonton',
        'new brunswick' => 'America/Moncton', 'prince edward island' => 'America/Halifax', 'nova scotia' => 'America/Halifax',
        'newfoundland and labrador' => 'America/St_Johns', 'british columbia' => 'America/Vancouver',
        'saskatchewan' => 'America/Regina', 'manitoba' => 'America/Winnipeg',
        'texas' => 'America/Chicago', 'arizona' => 'America/Phoenix', 'washington' => 'America/Los_Angeles',
        'california' => 'America/Los_Angeles', 'oregon' => 'America/Los_Angeles', 'nevada' => 'America/Los_Angeles',
        'minnesota' => 'America/Chicago', 'illinois' => 'America/Chicago', 'alabama' => 'America/Chicago',
        'mississippi' => 'America/Chicago', 'missouri' => 'America/Chicago', 'nebraska' => 'America/Chicago',
        'arkansas' => 'America/Chicago', 'north dakota' => 'America/Chicago', 'oklahoma' => 'America/Chicago',
        'iowa' => 'America/Chicago', 'kansas' => 'America/Chicago', 'louisiana' => 'America/Chicago',
        'tennessee' => 'America/Chicago', 'wisconsin' => 'America/Chicago', 'south dakota' => 'America/Chicago',
        'montana' => 'America/Denver', 'new mexico' => 'America/Denver', 'wyoming' => 'America/Denver',
        'utah' => 'America/Denver', 'colorado' => 'America/Denver', 'idaho' => 'America/Boise',
        'alaska' => 'America/Anchorage', 'hawaii' => 'Pacific/Honolulu', 'puerto rico' => 'America/Puerto_Rico',
        'michigan' => 'America/Detroit', 'indiana' => 'America/Indiana/Indianapolis',
    );
    $k = mb_strtolower(trim((string)$state), 'UTF-8');
    return isset($map[$k]) ? $map[$k] : DEFAULT_TIMEZONE;
}

function today_in($tz)
{
    try {
        $d = new DateTime('now', new DateTimeZone($tz));
    } catch (Exception $ex) {
        $d = new DateTime('now');
    }
    return $d->format('Y-m-d');
}

function ordinal($n)
{
    $n = (int)$n;
    $s = array('th', 'st', 'nd', 'rd');
    $v = $n % 100;
    $idx = ($v - 20) % 10;
    if (isset($s[$idx]) && $v > 20) {
        return $n . $s[$idx];
    }
    return $n . (isset($s[$v]) ? $s[$v] : 'th');
}

// ---------------------------------------------------------------------------
// Contact links
// ---------------------------------------------------------------------------
function tel_href($phone)
{
    $digits = preg_replace('/[^0-9+]/', '', (string)$phone);
    if ($digits === '') {
        return '';
    }
    if ($digits[0] !== '+') {
        $digits = (strlen($digits) === 10 ? '+1' : '+') . $digits;
    }
    return 'tel:' . $digits;
}

function safe_url($u)
{
    $u = trim((string)$u);
    if ($u === '' || $u === '#') {
        return '';
    }
    if (!preg_match('#^https?://#i', $u)) {
        if (preg_match('#^(mailto:|tel:|javascript:|data:)#i', $u)) {
            return '';
        }
        $u = 'https://' . ltrim($u, '/');
    }
    return filter_var($u, FILTER_VALIDATE_URL) ? $u : '';
}

function display_host($u)
{
    $h = parse_url($u, PHP_URL_HOST);
    $p = rtrim((string)parse_url($u, PHP_URL_PATH), '/');
    $h = preg_replace('/^www\./', '', (string)$h);
    return $h . ($p !== '' && strlen($p) < 25 ? $p : '');
}

function maps_link($parts)
{
    $q = implode(', ', array_filter(array_map('trim', $parts)));
    return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($q);
}

/** Only allow Google Maps embeds from stored iframe HTML; rebuild the iframe ourselves. */
function map_embed_src($iframeHtml)
{
    if (!preg_match('/src\s*=\s*["\']([^"\']+)["\']/i', (string)$iframeHtml, $m)) {
        return '';
    }
    $src = html_entity_decode($m[1], ENT_QUOTES, 'UTF-8');
    if (preg_match('#^https://(www\.)?google\.[a-z.]+/maps/embed#i', $src) || preg_match('#^https://maps\.google\.[a-z.]+/#i', $src)) {
        return $src;
    }
    return '';
}

// ---------------------------------------------------------------------------
// Rich text from the database: keep simple formatting, drop anything risky
// ---------------------------------------------------------------------------
function safe_html($html)
{
    $html = trim((string)$html);
    if ($html === '') {
        return '';
    }
    $allowed = '<p><br><strong><b><em><i><u><ul><ol><li><h2><h3><h4><h5><a><blockquote><span><div><table><thead><tbody><tr><th><td>';
    $html = strip_tags($html, $allowed);
    if (!class_exists('DOMDocument')) {
        return preg_replace('/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    }
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8"?><div id="th-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $xpath = new DOMXPath($doc);
    foreach ($xpath->query('//*') as $node) {
        if (!$node->hasAttributes()) {
            continue;
        }
        $keep = array();
        if ($node->nodeName === 'a' && $node->hasAttribute('href')) {
            $href = $node->getAttribute('href');
            if (preg_match('#^(https?:|mailto:|tel:|/)#i', $href)) {
                $keep['href'] = $href;
            }
        }
        if ($node->getAttribute('id') === 'th-root') {
            continue;
        }
        while ($node->attributes->length) {
            $node->removeAttribute($node->attributes->item(0)->nodeName);
        }
        foreach ($keep as $k => $v) {
            $node->setAttribute($k, $v);
        }
        if ($node->nodeName === 'a' && isset($keep['href']) && preg_match('#^https?:#i', $keep['href'])) {
            $node->setAttribute('target', '_blank');
            $node->setAttribute('rel', 'noopener nofollow');
        }
    }
    $root = $doc->getElementById('th-root');
    $out = '';
    if ($root) {
        foreach ($root->childNodes as $child) {
            $out .= $doc->saveHTML($child);
        }
    }
    // Remove empty paragraphs left by editors
    $out = preg_replace('#<p>(\s|&nbsp;|\xC2\xA0|<br>)*</p>#u', '', $out);
    return $out;
}

// ---------------------------------------------------------------------------
// Security helpers
// ---------------------------------------------------------------------------
function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_check($token)
{
    return !empty($_SESSION['csrf']) && is_string($token) && hash_equals($_SESSION['csrf'], $token);
}

function int_param($key, $default = 0)
{
    return isset($_GET[$key]) && is_numeric($_GET[$key]) ? (int)$_GET[$key] : $default;
}

function str_param($key, $max = 100)
{
    if (!isset($_GET[$key]) || !is_string($_GET[$key])) {
        return '';
    }
    return mb_substr(trim($_GET[$key]), 0, $max, 'UTF-8');
}

/** City chosen by the visitor (stored by the location picker). */
function visitor_city_id()
{
    return isset($_COOKIE['th_city']) && ctype_digit((string)$_COOKIE['th_city']) ? (int)$_COOKIE['th_city'] : 0;
}

function plural($n, $one, $many = null)
{
    $n = (int)$n;
    return number_format($n) . ' ' . ($n === 1 ? $one : ($many !== null ? $many : $one . 's'));
}
