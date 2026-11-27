<?php
/**
 * Coordinates for venues.
 *
 * th_venue has no latitude/longitude columns. The location is kept in the existing
 * `address_iframe` column (a Google Maps embed). We read coordinates from it, and when an admin
 * sets or confirms a location we save a standard Google Maps embed with "q=LAT,LNG", so the
 * existing site and the apps keep showing a correct map. Coordinates are never guessed.
 */

/** array('lat' => float, 'lng' => float) or null. */
function coords_from_iframe($html)
{
    $s = html_entity_decode((string)$html, ENT_QUOTES, 'UTF-8');
    if ($s === '') {
        return null;
    }
    $num = '(-?\d{1,3}\.\d{3,})';
    $lat = $lng = null;
    // Google "pb" embeds: !2d<lng>!3d<lat>
    if (preg_match('/!2d' . $num . '!3d' . $num . '/', $s, $m)) {
        $lng = (float)$m[1];
        $lat = (float)$m[2];
    } elseif (preg_match('/!3d' . $num . '!4d' . $num . '/', $s, $m)) {
        $lat = (float)$m[1];
        $lng = (float)$m[2];
    } elseif (preg_match('/[?&;](?:q|ll|center)=' . $num . '\s*,\s*' . $num . '/', $s, $m)) {
        $lat = (float)$m[1];
        $lng = (float)$m[2];
    } elseif (preg_match('/@' . $num . ',' . $num . '/', $s, $m)) {
        $lat = (float)$m[1];
        $lng = (float)$m[2];
    }
    if ($lat === null || abs($lat) > 90 || abs($lng) > 180 || ($lat == 0 && $lng == 0)) {
        return null;
    }
    return array('lat' => $lat, 'lng' => $lng);
}

function venue_coords($v)
{
    return coords_from_iframe(isset($v['address_iframe']) ? $v['address_iframe'] : '');
}

/** The iframe saved into th_venue.address_iframe when a location is confirmed. */
function coords_iframe($lat, $lng)
{
    $lat = round((float)$lat, 6);
    $lng = round((float)$lng, 6);
    $src = 'https://maps.google.com/maps?q=' . $lat . ',' . $lng . '&z=16&output=embed';
    return '<iframe src="' . $src . '" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>';
}

function valid_lat_lng($lat, $lng)
{
    return is_numeric($lat) && is_numeric($lng) && abs((float)$lat) <= 90 && abs((float)$lng) <= 180 && !((float)$lat == 0 && (float)$lng == 0);
}

/** Great-circle distance in km. */
function distance_km($lat1, $lng1, $lat2, $lng2)
{
    $r = 6371.0;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) * sin($dLng / 2);
    return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

/**
 * Published venues that have coordinates. Cached for 10 minutes in storage (the iframe column must be parsed in PHP).
 * Returns rows: id, title, role_id, url, city, state, logo, lat, lng
 */
function venues_with_coords($role = 1)
{
    $cacheKey = 'cache/coords-' . ($role === 'all' ? 'all' : (int)$role) . '.json';
    $cache = storage_read($cacheKey, array());
    if (!empty($cache['t']) && $cache['t'] > time() - 600 && isset($cache['rows'])) {
        return $cache['rows'];
    }
    $sql = 'SELECT v.venue_id, v.role_id, v.url, v.venue_title, v.street_address, v.profile_image, v.back_image, v.address_iframe, c.city_title, s.state_title
        FROM th_venue v LEFT JOIN th_city c ON c.city_id = v.city_id LEFT JOIN th_state s ON s.state_id = c.state_id
        WHERE v.verify_status = 1 AND v.address_iframe IS NOT NULL AND v.address_iframe <> \'\'';
    $rows = $role === 'all' ? db_all($sql) : db_all($sql . ' AND v.role_id = ?', 'i', array((int)$role));
    $out = array();
    foreach ($rows as $r) {
        $c = venue_coords($r);
        if (!$c) {
            continue;
        }
        $out[] = array(
            'id' => (int)$r['venue_id'],
            'title' => trim((string)$r['venue_title']),
            'role_id' => (int)$r['role_id'],
            'href' => url(venue_url($r)),
            'address' => trim((string)$r['street_address']),
            'city' => trim((string)$r['city_title']),
            'state' => trim((string)$r['state_title']),
            'logo' => venue_logo($r),
            'lat' => $c['lat'],
            'lng' => $c['lng'],
        );
    }
    try {
        storage_write($cacheKey, array('t' => time(), 'rows' => $out));
    } catch (Exception $e) {
        error_log($e->getMessage());
    }
    return $out;
}

function coords_cache_clear()
{
    foreach (array('1', '2', '3', '4', '5', '6', '7', '8', '9', 'all') as $k) {
        storage_delete('cache/coords-' . $k . '.json');
    }
}
