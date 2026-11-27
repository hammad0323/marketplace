<?php
/**
 * File storage for data that has no place in the existing database schema
 * (the database structure is never changed): site settings, blog page-builder layouts,
 * static pages, blog drafts, pending profile changes, prayer-time sources, login throttling,
 * newsletter subscribers.
 *
 * Everything lives in STORAGE_DIR as JSON. Writes are atomic (temp file + rename) and locked.
 */

function storage_path($rel)
{
    $rel = str_replace('\\', '/', (string)$rel);
    // Only simple relative paths: letters, digits, dash, underscore, dot, slash. No "..".
    if ($rel === '' || strpos($rel, '..') !== false || !preg_match('#^[A-Za-z0-9_\-./]+$#', $rel)) {
        throw new RuntimeException('Invalid storage path');
    }
    return rtrim(STORAGE_DIR, '/') . '/' . ltrim($rel, '/');
}

function storage_read($rel, $default = array())
{
    $file = storage_path($rel);
    if (!is_file($file)) {
        return $default;
    }
    $fh = @fopen($file, 'rb');
    if (!$fh) {
        return $default;
    }
    flock($fh, LOCK_SH);
    $raw = stream_get_contents($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    $data = json_decode((string)$raw, true);
    return is_array($data) ? $data : $default;
}

function storage_write($rel, $data)
{
    $file = storage_path($rel);
    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException('Storage folder is not writable: ' . $dir);
    }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if ($json === false) {
        throw new RuntimeException('Could not encode data for ' . $rel);
    }
    $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) {
        throw new RuntimeException('Storage folder is not writable: ' . $dir);
    }
    if (!@rename($tmp, $file)) {
        @unlink($tmp);
        throw new RuntimeException('Could not save ' . $rel);
    }
    return true;
}

function storage_delete($rel)
{
    $file = storage_path($rel);
    return is_file($file) ? @unlink($file) : true;
}

/** File names (without .json) in a storage sub-folder. */
function storage_list($dir)
{
    $path = storage_path($dir);
    if (!is_dir($path)) {
        return array();
    }
    $out = array();
    foreach (scandir($path) as $f) {
        if (substr($f, -5) === '.json') {
            $out[] = substr($f, 0, -5);
        }
    }
    sort($out);
    return $out;
}

function storage_writable()
{
    $dir = rtrim(STORAGE_DIR, '/');
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return is_dir($dir) && is_writable($dir);
}

// ---------------------------------------------------------------------------
// Site settings (managed in the Super Admin → Settings). config.php holds the defaults.
// ---------------------------------------------------------------------------
function settings_defaults()
{
    global $SOCIAL_LINKS, $TICKER_ITEMS, $HOME_SLIDES;
    return array(
        'site_name' => SITE_NAME,
        'logo_url' => '',
        'favicon_url' => '',
        'contact_email' => CONTACT_EMAIL,
        'contact_phone' => CONTACT_PHONE_DISPLAY,
        'footer_about' => 'The Hashir is a community-first discovery platform designed to strengthen the connection between Muslims and the institutions that serve them. From mosques and institutes to organizations and programs, Hashir makes it easier to find, follow, and engage with authentic Muslim spaces locally and beyond.',
        'social' => $SOCIAL_LINKS,
        'ticker' => $TICKER_ITEMS,
        'slides' => $HOME_SLIDES,
        'announcement' => array('enabled' => false, 'text' => '', 'link' => '', 'style' => 'info'),
        'hero_title' => 'Discover the Muslim Community Around You',
        'hero_subtitle' => 'Explore mosques, prayer times, programs, services, events and more.',
        'home_sections' => array(
            'slider' => true, 'cities' => true, 'featured_mosques' => true, 'nearby' => true, 'platform' => true,
            'programs' => true, 'services' => true, 'events' => true, 'organizations' => true, 'featured_orgs' => true,
            'blogs' => true, 'newsletter' => true,
        ),
        'featured_mosques' => array(),
        'featured_orgs' => array(),
        'seo_title' => SITE_NAME . ' – Find Mosques, Prayer Times & Muslim Community Services',
        'seo_description' => 'Find mosques, prayer and Jumu\'ah times, Islamic programs, services and community organizations across Canada and the United States.',
        'seo_image' => '',
        'ga4_id' => '',
        'head_code_verification' => '',
        'map_tiles' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        'map_attribution' => '&copy; OpenStreetMap contributors',
        'map_center' => array('lat' => 43.6532, 'lng' => -79.3832, 'zoom' => 9),
        'approve_details' => true,     // portal programs / services / events need approval
        'approve_profile' => true,     // portal profile changes need approval
        'approve_announcements' => false,
    );
}

function settings_all()
{
    static $cache = null;
    if ($cache === null || !empty($GLOBALS['__settings_dirty'])) {
        $saved = storage_read('settings.json', array());
        $cache = array_merge(settings_defaults(), $saved);
        // Merge nested section toggles so new sections default to visible
        $defs = settings_defaults();
        $cache['home_sections'] = array_merge($defs['home_sections'], isset($saved['home_sections']) && is_array($saved['home_sections']) ? $saved['home_sections'] : array());
        $GLOBALS['__settings_dirty'] = false;
    }
    return $cache;
}

function setting($key, $default = null)
{
    $all = settings_all();
    return array_key_exists($key, $all) ? $all[$key] : $default;
}

function settings_save($changes)
{
    $saved = storage_read('settings.json', array());
    foreach ($changes as $k => $v) {
        $saved[$k] = $v;
    }
    storage_write('settings.json', $saved);
    $GLOBALS['__settings_dirty'] = true;
}

function home_section_on($key)
{
    $s = setting('home_sections', array());
    return !isset($s[$key]) || !empty($s[$key]);
}
