<?php
/**
 * Read-only data access for the existing database.
 *
 * Table map (existing schema, unchanged):
 *   th_venue            mosques (role_id = 1) and organizations (role_id 2-9, see organization_roles)
 *   th_details          programs (grand_category_id 2), services (3), events (1); category_id may hold "18,21"
 *   th_categories       categories, each belongs to a grand category
 *   prayer_timings      daily azan / iqamah per venue per date (times stored as "6:01 AM")
 *   th_jummah_prayers   Jumu'ah sessions per venue, valid from_date..to_date
 *   th_announcement     venue announcements
 *   th_people / venue_people   people at a venue
 *   th_key_bodies       venue_id belongs to organization_id (both th_venue ids)
 *   th_city / th_state / th_country   locations
 *   blogs               city guides
 */

// Never select login columns (username, password, reset_token).
define('VENUE_COLS', 'v.venue_id, v.role_id, v.verify_status, v.url, v.venue_title, v.venue_aka, v.street_address, v.city_id,
    v.about_details, v.meta_description, v.phone_number, v.email_address, v.website_link, v.postal_code, v.profile_image,
    v.back_image, v.address_iframe, v.facebook_link, v.instagram_link, v.twitter_link, v.youtube_link, v.linkedin_link,
    v.tiktok_link, v.linktree_link, v.whatsapp_link, v.spotify_link, v.snapchat_link, v.other_link, v.update_date,
    c.city_title, s.state_id, s.state_title, co.country_id, co.country_title, r.role_title');

define('VENUE_JOINS', 'LEFT JOIN th_city c ON c.city_id = v.city_id
    LEFT JOIN th_state s ON s.state_id = c.state_id
    LEFT JOIN th_country co ON co.country_id = c.country_id
    LEFT JOIN organization_roles r ON r.role_id = v.role_id');

// Published detail rows; expired events are left out.
define('DETAIL_ACTIVE', "d.status_verify = 1 AND (d.grand_category_id <> 1 OR (
    (d.expire_date < '1000-01-01' OR d.expire_date >= CURDATE()) AND (d.end_date < '1000-01-01' OR d.end_date >= CURDATE())))");

function get_venue($id)
{
    return db_one('SELECT ' . VENUE_COLS . ' FROM th_venue v ' . VENUE_JOINS . ' WHERE v.venue_id = ? AND v.verify_status = 1', 'i', array($id));
}

function venue_timezone($v)
{
    return state_timezone(isset($v['state_title']) ? $v['state_title'] : '');
}

function venue_place_line($v)
{
    $parts = array();
    if (!empty($v['city_title'])) {
        $parts[] = $v['city_title'];
    }
    if (!empty($v['state_title'])) {
        $parts[] = $v['state_title'];
    }
    return implode(', ', $parts);
}

function venue_social($v)
{
    $map = array(
        'facebook_link' => array('Facebook', 'bi-facebook'),
        'instagram_link' => array('Instagram', 'bi-instagram'),
        'youtube_link' => array('YouTube', 'bi-youtube'),
        'twitter_link' => array('X (Twitter)', 'bi-twitter-x'),
        'tiktok_link' => array('TikTok', 'bi-tiktok'),
        'linkedin_link' => array('LinkedIn', 'bi-linkedin'),
        'whatsapp_link' => array('WhatsApp', 'bi-whatsapp'),
        'linktree_link' => array('Linktree', 'bi-tree'),
        'spotify_link' => array('Spotify', 'bi-spotify'),
        'snapchat_link' => array('Snapchat', 'bi-snapchat'),
        'other_link' => array('Other link', 'bi-link-45deg'),
    );
    $out = array();
    foreach ($map as $col => $info) {
        $u = isset($v[$col]) ? safe_url($v[$col]) : '';
        if ($u !== '') {
            $out[] = array('url' => $u, 'label' => $info[0], 'icon' => $info[1], 'key' => $col);
        }
    }
    return $out;
}

// ---------------------------------------------------------------------------
// Categories and organization types
// ---------------------------------------------------------------------------
function get_categories($gc, $withCounts = true)
{
    $rows = db_all('SELECT category_id, grand_category_id, TRIM(name) AS name, page_name, description, long_description, raw_image, profile_image, arrangement
        FROM th_categories WHERE grand_category_id = ? ORDER BY arrangement, category_id', 'i', array($gc));
    if ($withCounts && $rows) {
        $counts = array();
        foreach (db_all('SELECT d.category_id FROM th_details d WHERE d.grand_category_id = ? AND ' . DETAIL_ACTIVE, 'i', array($gc)) as $d) {
            foreach (explode(',', $d['category_id']) as $cid) {
                $cid = (int)trim($cid);
                $counts[$cid] = isset($counts[$cid]) ? $counts[$cid] + 1 : 1;
            }
        }
        foreach ($rows as $i => $r) {
            $rows[$i]['count'] = isset($counts[(int)$r['category_id']]) ? $counts[(int)$r['category_id']] : 0;
        }
    }
    return $rows;
}

function get_category($id)
{
    return db_one('SELECT category_id, grand_category_id, TRIM(name) AS name, page_name, description, long_description, raw_image, profile_image
        FROM th_categories WHERE category_id = ?', 'i', array($id));
}

function all_categories_by_id()
{
    static $cache = null;
    if ($cache === null) {
        $cache = array();
        foreach (db_all('SELECT category_id, grand_category_id, TRIM(name) AS name FROM th_categories') as $c) {
            $cache[(int)$c['category_id']] = $c;
        }
    }
    return $cache;
}

function detail_categories($d)
{
    $all = all_categories_by_id();
    $out = array();
    foreach (explode(',', (string)$d['category_id']) as $cid) {
        $cid = (int)trim($cid);
        if (isset($all[$cid])) {
            $out[] = $all[$cid];
        }
    }
    return $out;
}

function get_org_roles($withCounts = true)
{
    $rows = db_all('SELECT role_id, role_title, slug, description, profile_image FROM organization_roles WHERE role_id <> 1 ORDER BY role_id');
    if ($withCounts) {
        $counts = array();
        foreach (db_all('SELECT role_id, COUNT(*) n FROM th_venue WHERE verify_status = 1 GROUP BY role_id') as $c) {
            $counts[(int)$c['role_id']] = (int)$c['n'];
        }
        foreach ($rows as $i => $r) {
            $rows[$i]['count'] = isset($counts[(int)$r['role_id']]) ? $counts[(int)$r['role_id']] : 0;
        }
    }
    return $rows;
}

function get_role($id)
{
    return db_one('SELECT role_id, role_title, slug, description, profile_image FROM organization_roles WHERE role_id = ?', 'i', array($id));
}

function genders_by_id()
{
    static $g = null;
    if ($g === null) {
        $g = array();
        foreach (db_all('SELECT gender_id, gender_title FROM th_gender') as $r) {
            $g[(int)$r['gender_id']] = $r['gender_title'];
        }
    }
    return $g;
}

function age_groups_by_id()
{
    static $a = null;
    if ($a === null) {
        $a = array();
        foreach (db_all('SELECT group_id, group_title, group_description FROM th_age_group WHERE status = 1 ORDER BY group_id') as $r) {
            $a[(int)$r['group_id']] = array('title' => $r['group_title'], 'desc' => trim($r['group_description']));
        }
    }
    return $a;
}

/** "Brothers & sisters", "Sisters only" ... from a "1,2,3" list. */
function audience_gender($ids)
{
    $ids = array_filter(array_map('intval', explode(',', (string)$ids)));
    if (!$ids) {
        return '';
    }
    if (in_array(3, $ids, true) || (in_array(1, $ids, true) && in_array(2, $ids, true))) {
        return 'Brothers & sisters';
    }
    if ($ids === array(1) || in_array(1, $ids, true)) {
        return 'Brothers';
    }
    return 'Sisters';
}

function audience_ages($ids)
{
    $ids = array_filter(array_map('intval', explode(',', (string)$ids)));
    if (!$ids) {
        return '';
    }
    $all = age_groups_by_id();
    if (count($ids) >= count($all)) {
        return 'All ages';
    }
    $names = array();
    foreach ($ids as $i) {
        if (isset($all[$i])) {
            $names[] = $all[$i]['title'];
        }
    }
    return implode(', ', $names);
}

function detail_when($d)
{
    $parts = array();
    if (valid_date($d['start_date'])) {
        $s = fmt_date($d['start_date'], 'D j M Y');
        if (valid_date($d['end_date']) && $d['end_date'] !== $d['start_date']) {
            $s .= ' – ' . fmt_date($d['end_date'], 'D j M Y');
        }
        $parts[] = $s;
    } elseif (!empty($d['detail_date'])) {
        $parts[] = trim($d['detail_date']);
    }
    if (!empty($d['detail_day'])) {
        $parts[] = trim($d['detail_day']);
    }
    if (!empty($d['detail_time'])) {
        $parts[] = trim($d['detail_time']);
    }
    return implode(' · ', array_filter($parts));
}

// ---------------------------------------------------------------------------
// Details (programs / services / events)
// ---------------------------------------------------------------------------
define('DETAIL_COLS', 'd.detail_id, d.details_title, d.details_url, d.grand_category_id, d.category_id, d.gender_id, d.age_group_id,
    d.venue_id, d.detail_date, d.start_date, d.end_date, d.detail_day, d.detail_time, d.description, d.event_location,
    d.profile_image_1, d.update_date, d.expire_date');

function details_for_venue($venueId, $gc = 0, $excludeId = 0, $limit = 200)
{
    $sql = 'SELECT ' . DETAIL_COLS . ' FROM th_details d WHERE d.venue_id = ? AND d.detail_id <> ? AND ' . DETAIL_ACTIVE;
    $types = 'ii';
    $params = array($venueId, $excludeId);
    if ($gc) {
        $sql .= ' AND d.grand_category_id = ?';
        $types .= 'i';
        $params[] = $gc;
    }
    $sql .= ' ORDER BY d.grand_category_id, d.details_title LIMIT ' . (int)$limit;
    return db_all($sql, $types, $params);
}

function get_detail($id)
{
    return db_one('SELECT ' . DETAIL_COLS . ' FROM th_details d WHERE d.detail_id = ? AND ' . DETAIL_ACTIVE, 'i', array($id));
}

/**
 * Listing with filters. $f keys: gc, category, q, city, gender, age, day, page, per
 */
function list_details($f)
{
    $where = array('d.grand_category_id = ?', DETAIL_ACTIVE, 'v.verify_status = 1');
    $types = 'i';
    $params = array((int)$f['gc']);
    if (!empty($f['category'])) {
        $where[] = 'FIND_IN_SET(?, REPLACE(d.category_id, " ", "")) > 0';
        $types .= 's';
        $params[] = (string)(int)$f['category'];
    }
    if (!empty($f['q'])) {
        $where[] = '(d.details_title LIKE ? OR v.venue_title LIKE ? OR d.description LIKE ?)';
        $like = '%' . $f['q'] . '%';
        $types .= 'sss';
        array_push($params, $like, $like, $like);
    }
    if (!empty($f['city'])) {
        $where[] = 'v.city_id = ?';
        $types .= 'i';
        $params[] = (int)$f['city'];
    }
    if (!empty($f['venue'])) {
        $where[] = 'd.venue_id = ?';
        $types .= 'i';
        $params[] = (int)$f['venue'];
    }
    if (!empty($f['org_type'])) {
        $where[] = $f['org_type'] === 'mosque' ? 'v.role_id = 1' : 'v.role_id <> 1';
    }
    if (!empty($f['gender'])) {
        $g = (int)$f['gender'];
        $where[] = '(FIND_IN_SET(?, d.gender_id) > 0 OR FIND_IN_SET("3", d.gender_id) > 0)';
        $types .= 's';
        $params[] = (string)$g;
    }
    if (!empty($f['age'])) {
        $where[] = 'FIND_IN_SET(?, d.age_group_id) > 0';
        $types .= 's';
        $params[] = (string)(int)$f['age'];
    }
    if (!empty($f['day'])) {
        $where[] = 'd.detail_day LIKE ?';
        $types .= 's';
        $params[] = '%' . $f['day'] . '%';
    }
    $w = implode(' AND ', $where);
    $from = 'FROM th_details d JOIN th_venue v ON v.venue_id = d.venue_id LEFT JOIN th_city c ON c.city_id = v.city_id LEFT JOIN th_state s ON s.state_id = c.state_id';
    $total = (int)db_val("SELECT COUNT(*) $from WHERE $w", $types, $params);
    $per = isset($f['per']) ? (int)$f['per'] : PER_PAGE_DETAILS;
    $page = max(1, (int)$f['page']);
    $off = ($page - 1) * $per;
    $sort = 'd.details_title ASC';
    if (isset($f['sort']) && $f['sort'] === 'recent') {
        $sort = 'd.update_date DESC, d.detail_id DESC';
    } elseif (isset($f['sort']) && $f['sort'] === 'date') {
        $sort = "(d.start_date < '1000-01-01'), d.start_date ASC, d.details_title";
    }
    $rows = db_all('SELECT ' . DETAIL_COLS . ", v.venue_title, v.url, v.role_id, v.profile_image, c.city_title, s.state_title
        $from WHERE $w ORDER BY $sort LIMIT $off, $per", $types, $params);
    return array('rows' => $rows, 'total' => $total, 'page' => $page, 'per' => $per);
}

function related_details($d, $limit = 6)
{
    $cats = explode(',', (string)$d['category_id']);
    $cat = (string)(int)trim($cats[0]);
    $cityId = (int)db_val('SELECT city_id FROM th_venue WHERE venue_id = ?', 'i', array((int)$d['venue_id']));
    return db_all('SELECT ' . DETAIL_COLS . ', v.venue_title, v.url, v.role_id, v.profile_image, c.city_title
        FROM th_details d JOIN th_venue v ON v.venue_id = d.venue_id LEFT JOIN th_city c ON c.city_id = v.city_id
        WHERE d.detail_id <> ? AND d.venue_id <> ? AND v.verify_status = 1 AND FIND_IN_SET(?, REPLACE(d.category_id, " ", "")) > 0 AND ' . DETAIL_ACTIVE . '
        ORDER BY (v.city_id = ?) DESC, d.update_date DESC LIMIT ' . (int)$limit,
        'iisi', array((int)$d['detail_id'], (int)$d['venue_id'], $cat, $cityId));
}

// ---------------------------------------------------------------------------
// Venues (mosques & organizations)
// ---------------------------------------------------------------------------
/** $f keys: role (1 = mosques, 'org' = all orgs, or role id), q, city, state, sort, page, per */
function list_venues($f)
{
    $where = array('v.verify_status = 1');
    $types = '';
    $params = array();
    if ($f['role'] === 'org') {
        $where[] = 'v.role_id <> 1';
    } else {
        $where[] = 'v.role_id = ?';
        $types .= 'i';
        $params[] = (int)$f['role'];
    }
    if (!empty($f['q'])) {
        $where[] = '(v.venue_title LIKE ? OR v.venue_aka LIKE ? OR v.street_address LIKE ? OR v.postal_code LIKE ? OR c.city_title LIKE ?)';
        $like = '%' . $f['q'] . '%';
        $types .= 'sssss';
        array_push($params, $like, $like, $like, $like, $like);
    }
    if (!empty($f['city'])) {
        $where[] = 'v.city_id = ?';
        $types .= 'i';
        $params[] = (int)$f['city'];
    }
    if (!empty($f['state'])) {
        $where[] = 'c.state_id = ?';
        $types .= 'i';
        $params[] = (int)$f['state'];
    }
    $w = implode(' AND ', $where);
    $total = (int)db_val('SELECT COUNT(*) FROM th_venue v ' . VENUE_JOINS . " WHERE $w", $types, $params);
    $per = isset($f['per']) ? (int)$f['per'] : PER_PAGE_VENUES;
    $page = max(1, (int)$f['page']);
    $off = ($page - 1) * $per;
    $sort = 'v.venue_title ASC';
    if (isset($f['sort']) && $f['sort'] === 'updated') {
        $sort = 'v.update_date DESC, v.venue_id DESC';
    } elseif (isset($f['sort']) && $f['sort'] === 'featured') {
        $sort = "(v.back_image IS NOT NULL AND v.back_image <> '') DESC, v.venue_id ASC";
    }
    $rows = db_all('SELECT ' . VENUE_COLS . ' FROM th_venue v ' . VENUE_JOINS . " WHERE $w ORDER BY $sort LIMIT $off, $per", $types, $params);
    return array('rows' => $rows, 'total' => $total, 'page' => $page, 'per' => $per);
}

/** For a set of venues: which have prayer times today and which have current Jumu'ah. */
function venue_badges($venues)
{
    $ids = array();
    foreach ($venues as $v) {
        $ids[] = (int)$v['venue_id'];
    }
    $out = array('prayer' => array(), 'jummah' => array());
    if (!$ids) {
        return $out;
    }
    list($ph, $t, $vals) = db_in_ints($ids);
    // Times are compared in the Toronto time zone for the badge; the profile itself uses each mosque's own zone.
    $today = today_in(DEFAULT_TIMEZONE);
    foreach (db_all("SELECT DISTINCT venue_id FROM prayer_timings WHERE venue_id IN ($ph) AND georgian_date = ?", $t . 's', array_merge($vals, array($today))) as $r) {
        $out['prayer'][(int)$r['venue_id']] = true;
    }
    foreach (db_all("SELECT venue_id, MIN(iqama_time) first_iqama FROM th_jummah_prayers WHERE venue_id IN ($ph) AND from_date <= ? AND to_date >= ? GROUP BY venue_id", $t . 'ss', array_merge($vals, array($today, $today))) as $r) {
        $out['jummah'][(int)$r['venue_id']] = $r['first_iqama'];
    }
    return $out;
}

function city_venues($cityId, $excludeId = 0, $limit = 6, $role = 1)
{
    return db_all('SELECT ' . VENUE_COLS . ' FROM th_venue v ' . VENUE_JOINS . "
        WHERE v.verify_status = 1 AND v.role_id = ? AND v.city_id = ? AND v.venue_id <> ?
        ORDER BY (v.back_image IS NOT NULL AND v.back_image <> '') DESC, v.venue_title LIMIT " . (int)$limit,
        'iii', array($role, $cityId, $excludeId));
}

/** Organizations a mosque belongs to, and mosques that belong to an organization (th_key_bodies). */
function venue_affiliations($venueId)
{
    return db_all('SELECT ' . VENUE_COLS . ' FROM th_key_bodies k JOIN th_venue v ON v.venue_id = k.organization_id ' . VENUE_JOINS . '
        WHERE k.venue_id = ? AND k.status = 1 AND k.organization_id <> k.venue_id AND v.verify_status = 1 ORDER BY v.venue_title', 'i', array($venueId));
}

function organization_members($orgId)
{
    return db_all('SELECT ' . VENUE_COLS . ' FROM th_key_bodies k JOIN th_venue v ON v.venue_id = k.venue_id ' . VENUE_JOINS . '
        WHERE k.organization_id = ? AND k.status = 1 AND k.organization_id <> k.venue_id AND v.verify_status = 1 ORDER BY v.venue_title', 'i', array($orgId));
}

// ---------------------------------------------------------------------------
// Prayer times
// ---------------------------------------------------------------------------
function prayer_day($venueId, $date)
{
    return db_one('SELECT * FROM prayer_timings WHERE venue_id = ? AND georgian_date = ? ORDER BY id DESC LIMIT 1', 'is', array($venueId, $date));
}

function prayer_rows($p)
{
    if (!$p) {
        return array();
    }
    $defs = array(
        array('Fajr', 'fajr_azan', 'fajr_iqama'),
        array('Dhuhr', 'dhuhr_azan', 'dhuhr_iqama'),
        array('Asr', 'asr_azan', 'asr_iqama'),
        array('Maghrib', 'maghrib_azan', 'maghrib_iqama'),
        array('Isha', 'isha_azan', 'isha_iqama'),
    );
    $rows = array();
    foreach ($defs as $d) {
        $az = fmt_time($p[$d[1]]);
        $iq = fmt_time($p[$d[2]]);
        if ($az === '' && $iq === '') {
            continue;
        }
        $rows[] = array(
            'name' => $d[0],
            'azan' => $az,
            'iqama' => $iq,
            'azan_min' => $az !== '' ? time_minutes($az) : null,
            'iqama_min' => $iq !== '' ? time_minutes($iq) : null,
        );
    }
    return $rows;
}

function prayer_date_range($venueId)
{
    return db_one('SELECT MIN(georgian_date) first_date, MAX(georgian_date) last_date FROM prayer_timings WHERE venue_id = ?', 'i', array($venueId));
}

function jummah_sessions($venueId, $date)
{
    $rows = db_all('SELECT id, azan_time, iqama_time, place, imam_name, from_date, to_date FROM th_jummah_prayers
        WHERE venue_id = ? AND from_date <= ? AND to_date >= ? ORDER BY iqama_time, azan_time', 'iss', array($venueId, $date, $date));
    $out = array();
    $seen = array();
    foreach ($rows as $r) {
        $key = $r['azan_time'] . '|' . $r['iqama_time'] . '|' . $r['place'];
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        if (fmt_time($r['azan_time']) === '' && fmt_time($r['iqama_time']) === '') {
            continue;
        }
        $out[] = array(
            'khutbah' => fmt_time($r['azan_time']),
            'iqama' => fmt_time($r['iqama_time']),
            'place' => trim((string)$r['place']),
            'imam' => trim((string)$r['imam_name']),
            'from' => $r['from_date'],
            'to' => $r['to_date'],
        );
    }
    return $out;
}

/**
 * Jumu'ah for a profile. th_jummah_prayers.type: 0 = daylight-saving season, 1 = standard-time season.
 * If nothing is valid today, fall back to the latest schedule published for the current season
 * and flag it as possibly out of date (never shown as current).
 */
function jummah_for_profile($venueId, $date, $tz)
{
    $current = jummah_sessions($venueId, $date);
    if ($current) {
        return array('sessions' => $current, 'stale' => false, 'to' => $current[0]['to']);
    }
    try {
        $isDst = (new DateTime($date . ' 12:00', new DateTimeZone($tz)))->format('I') === '1';
    } catch (Exception $ex) {
        $isDst = false;
    }
    $season = $isDst ? 0 : 1;
    $last = db_val('SELECT MAX(to_date) FROM th_jummah_prayers WHERE venue_id = ? AND type = ? AND to_date < ?', 'iis', array($venueId, $season, $date));
    if (!$last) {
        return array('sessions' => array(), 'stale' => false, 'to' => '');
    }
    $from = db_val('SELECT MAX(from_date) FROM th_jummah_prayers WHERE venue_id = ? AND type = ? AND to_date = ?', 'iis', array($venueId, $season, $last));
    $sessions = jummah_sessions($venueId, $from);
    return array('sessions' => $sessions, 'stale' => (bool)$sessions, 'to' => $last, 'season' => $isDst ? 'summer' : 'winter');
}

// ---------------------------------------------------------------------------
// Announcements & people
// ---------------------------------------------------------------------------
function venue_announcements($venueId, $tz)
{
    $today = today_in($tz);
    return db_all('SELECT news_id, news_title, news_description, news_image, created_date, schedule_date FROM th_announcement
        WHERE venue_id = ? AND (created_date >= DATE_SUB(?, INTERVAL ? DAY) OR schedule_date >= ?)
        ORDER BY created_date DESC, news_id DESC LIMIT 12', 'isis', array($venueId, $today, ANNOUNCEMENT_MAX_AGE_DAYS, $today));
}

function venue_people($venueId)
{
    $a = db_all('SELECT name, designation, description, image FROM th_people WHERE venue_id = ? ORDER BY people_id', 'i', array($venueId));
    $b = db_all('SELECT full_name AS name, designation, about_detail AS description, profile_image AS image FROM venue_people WHERE venue_id = ? ORDER BY vp_id', 'i', array($venueId));
    return array_merge($a, $b);
}

// ---------------------------------------------------------------------------
// Locations
// ---------------------------------------------------------------------------
function get_city($id)
{
    return db_one('SELECT c.city_id, c.city_title, s.state_id, s.state_title, co.country_id, co.country_title
        FROM th_city c LEFT JOIN th_state s ON s.state_id = c.state_id LEFT JOIN th_country co ON co.country_id = c.country_id
        WHERE c.city_id = ?', 'i', array($id));
}

function get_state($id)
{
    return db_one('SELECT state_id, state_title, country_id FROM th_state WHERE state_id = ?', 'i', array($id));
}

function top_cities($limit = 12, $role = 1)
{
    return db_all('SELECT c.city_id, c.city_title, s.state_title, co.country_title, COUNT(*) n
        FROM th_venue v JOIN th_city c ON c.city_id = v.city_id LEFT JOIN th_state s ON s.state_id = c.state_id LEFT JOIN th_country co ON co.country_id = c.country_id
        WHERE v.verify_status = 1 AND v.role_id = ? GROUP BY c.city_id ORDER BY n DESC LIMIT ' . (int)$limit, 'i', array($role));
}

function search_cities($q, $limit = 15)
{
    $like = $q . '%';
    $like2 = '%' . $q . '%';
    return db_all('SELECT c.city_id, c.city_title, s.state_title, co.country_title, COUNT(v.venue_id) n
        FROM th_city c LEFT JOIN th_state s ON s.state_id = c.state_id LEFT JOIN th_country co ON co.country_id = c.country_id
        JOIN th_venue v ON v.city_id = c.city_id AND v.verify_status = 1
        WHERE c.city_title LIKE ? OR s.state_title LIKE ?
        GROUP BY c.city_id ORDER BY (c.city_title LIKE ?) DESC, n DESC LIMIT ' . (int)$limit, 'sss', array($like2, $like, $like));
}

/** Cities that have daily prayer times or current Jumu'ah times. */
function prayer_cities()
{
    $today = today_in(DEFAULT_TIMEZONE);
    return db_all('SELECT c.city_id, c.city_title, s.state_title, co.country_title,
            COUNT(DISTINCT pt.venue_id) daily, COUNT(DISTINCT j.venue_id) jummah
        FROM th_venue v JOIN th_city c ON c.city_id = v.city_id LEFT JOIN th_state s ON s.state_id = c.state_id
        LEFT JOIN th_country co ON co.country_id = c.country_id
        LEFT JOIN prayer_timings pt ON pt.venue_id = v.venue_id AND pt.georgian_date = ?
        LEFT JOIN th_jummah_prayers j ON j.venue_id = v.venue_id AND j.from_date <= ? AND j.to_date >= ?
        WHERE v.verify_status = 1 AND v.role_id = 1
        GROUP BY c.city_id HAVING daily > 0 OR jummah > 0
        ORDER BY co.country_id, daily DESC, jummah DESC, c.city_title', 'sss', array($today, $today, $today));
}

// ---------------------------------------------------------------------------
// Blogs
// ---------------------------------------------------------------------------
function get_blogs($limit = 0, $includeHidden = false)
{
    if (!$includeHidden) {
        blogs_publish_due();
    }
    $sql = 'SELECT b.id, b.heading, b.url, b.updated_date, b.created_at, b.author, b.city_id, b.quick_answer_text, b.profile_image,
            c.city_title, s.state_title FROM blogs b LEFT JOIN th_city c ON c.city_id = b.city_id LEFT JOIN th_state s ON s.state_id = c.state_id
            ORDER BY b.updated_date DESC, b.id DESC';
    $rows = db_all($sql);
    if (!$includeHidden) {
        $hidden = blog_hidden_ids();
        $rows = array_values(array_filter($rows, function ($r) use ($hidden) { return !isset($hidden[(int)$r['id']]); }));
    }
    return $limit ? array_slice($rows, 0, (int)$limit) : $rows;
}

function get_blog($id)
{
    return db_one('SELECT b.*, c.city_title, s.state_title, co.country_title FROM blogs b LEFT JOIN th_city c ON c.city_id = b.city_id
        LEFT JOIN th_state s ON s.state_id = c.state_id LEFT JOIN th_country co ON co.country_id = c.country_id WHERE b.id = ?', 'i', array($id));
}
