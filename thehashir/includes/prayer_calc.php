<?php
/**
 * Astronomical prayer-time calculation (angle based), ported from the well-known PrayTimes algorithm.
 * Used only to PREVIEW times in the back office. Nothing is saved until an admin confirms, and dates that
 * already hold manually entered times are skipped unless the admin explicitly chooses to replace them.
 */

function pt_methods()
{
    return array(
        'ISNA' => array('name' => 'ISNA — Islamic Society of North America (15° / 15°)', 'fajr' => 15, 'isha' => 15),
        'MWL' => array('name' => 'Muslim World League (18° / 17°)', 'fajr' => 18, 'isha' => 17),
        'Egypt' => array('name' => 'Egyptian General Authority (19.5° / 17.5°)', 'fajr' => 19.5, 'isha' => 17.5),
        'Makkah' => array('name' => 'Umm al-Qura, Makkah (18.5° / 90 min)', 'fajr' => 18.5, 'isha_min' => 90),
        'Karachi' => array('name' => 'University of Islamic Sciences, Karachi (18° / 18°)', 'fajr' => 18, 'isha' => 18),
        'Tehran' => array('name' => 'Institute of Geophysics, Tehran (17.7° / 14°)', 'fajr' => 17.7, 'isha' => 14, 'maghrib' => 4.5),
        'Custom' => array('name' => 'Custom angles', 'fajr' => 18, 'isha' => 17),
    );
}

function pt_dsin($d) { return sin(deg2rad($d)); }
function pt_dcos($d) { return cos(deg2rad($d)); }
function pt_dtan($d) { return tan(deg2rad($d)); }
function pt_darcsin($x) { return rad2deg(asin($x)); }
function pt_darccos($x) { return rad2deg(acos($x)); }
function pt_darctan2($y, $x) { return rad2deg(atan2($y, $x)); }
function pt_darccot($x) { return rad2deg(atan(1 / $x)); }
function pt_fix($a, $b) { $a = $a - $b * floor($a / $b); return $a < 0 ? $a + $b : $a; }

function pt_julian($y, $m, $d)
{
    if ($m <= 2) {
        $y -= 1;
        $m += 12;
    }
    $a = floor($y / 100);
    $b = 2 - $a + floor($a / 4);
    return floor(365.25 * ($y + 4716)) + floor(30.6001 * ($m + 1)) + $d + $b - 1524.5;
}

function pt_sun($jd)
{
    $D = $jd - 2451545.0;
    $g = pt_fix(357.529 + 0.98560028 * $D, 360);
    $q = pt_fix(280.459 + 0.98564736 * $D, 360);
    $L = pt_fix($q + 1.915 * pt_dsin($g) + 0.020 * pt_dsin(2 * $g), 360);
    $e = 23.439 - 0.00000036 * $D;
    $RA = pt_darctan2(pt_dcos($e) * pt_dsin($L), pt_dcos($L)) / 15;
    $eqt = $q / 15 - pt_fix($RA, 24);
    $decl = pt_darcsin(pt_dsin($e) * pt_dsin($L));
    return array('decl' => $decl, 'eqt' => $eqt);
}

/**
 * Calculate one day.
 * $o: lat, lng, tz (IANA), method, fajr (deg), isha (deg) or isha_min, maghrib (deg, optional), asr (1 standard | 2 hanafi),
 *     high_lat (AngleBased|NightMiddle|OneSeventh|None), adjust: array(fajr=>min, sunrise, dhuhr, asr, maghrib, isha), dhuhr_min
 * Returns array of 'H:i' strings (24 h) keyed fajr, sunrise, dhuhr, asr, sunset, maghrib, isha. Invalid -> ''.
 */
function pt_calc_day($date, $o)
{
    list($y, $m, $d) = array_map('intval', explode('-', $date));
    $lat = (float)$o['lat'];
    $lng = (float)$o['lng'];
    try {
        $dt = new DateTime($date . ' 12:00:00', new DateTimeZone($o['tz']));
        $tz = $dt->getOffset() / 3600;
    } catch (Exception $e) {
        $tz = 0;
    }
    $jd = pt_julian($y, $m, $d) - $lng / (15 * 24);
    $fajrA = (float)$o['fajr'];
    $ishaA = isset($o['isha']) && $o['isha'] !== '' ? (float)$o['isha'] : null;
    $ishaMin = isset($o['isha_min']) && $o['isha_min'] !== '' ? (int)$o['isha_min'] : 0;
    $maghribA = isset($o['maghrib']) && $o['maghrib'] !== '' ? (float)$o['maghrib'] : null;
    $asrF = (int)$o['asr'] === 2 ? 2 : 1;

    $midDay = function ($t) use ($jd) {
        $s = pt_sun($jd + $t);
        return pt_fix(12 - $s['eqt'], 24);
    };
    $sunAngleTime = function ($angle, $t, $ccw) use ($jd, $lat, $midDay) {
        $s = pt_sun($jd + $t);
        $noon = $midDay($t);
        $x = (-pt_dsin($angle) - pt_dsin($s['decl']) * pt_dsin($lat)) / (pt_dcos($s['decl']) * pt_dcos($lat));
        if ($x < -1 || $x > 1) {
            return NAN;
        }
        $T = pt_darccos($x) / 15;
        return $noon + ($ccw ? -$T : $T);
    };
    $asrTime = function ($factor, $t) use ($jd, $lat, $sunAngleTime) {
        $s = pt_sun($jd + $t);
        $angle = -pt_darccot($factor + pt_dtan(abs($lat - $s['decl'])));
        return $sunAngleTime($angle, $t, false);
    };

    $t = array('fajr' => 5, 'sunrise' => 6, 'dhuhr' => 12, 'asr' => 13, 'sunset' => 18, 'maghrib' => 18, 'isha' => 18);
    foreach ($t as $k => $v) {
        $t[$k] = $v / 24;
    }
    $times = array(
        'fajr' => $sunAngleTime($fajrA, $t['fajr'], true),
        'sunrise' => $sunAngleTime(0.833, $t['sunrise'], true),
        'dhuhr' => $midDay($t['dhuhr']),
        'asr' => $asrTime($asrF, $t['asr']),
        'sunset' => $sunAngleTime(0.833, $t['sunset'], false),
        'maghrib' => $maghribA !== null ? $sunAngleTime($maghribA, $t['maghrib'], false) : $sunAngleTime(0.833, $t['maghrib'], false),
        'isha' => $ishaA !== null ? $sunAngleTime($ishaA, $t['isha'], false) : NAN,
    );
    foreach ($times as $k => $v) {
        if (!is_nan($v)) {
            $times[$k] = $v + $tz - $lng / 15;
        }
    }
    if ($ishaA === null || $ishaMin) {
        $times['isha'] = $times['maghrib'] + $ishaMin / 60;
    }
    $times['dhuhr'] += (isset($o['dhuhr_min']) ? (int)$o['dhuhr_min'] : 0) / 60;

    // High latitudes
    $hl = isset($o['high_lat']) ? $o['high_lat'] : 'AngleBased';
    if ($hl !== 'None' && !is_nan($times['sunrise']) && !is_nan($times['sunset'])) {
        $night = pt_fix($times['sunrise'] - $times['sunset'], 24);
        $portion = function ($angle) use ($hl, $night) {
            $p = $hl === 'NightMiddle' ? 1 / 2 : ($hl === 'OneSeventh' ? 1 / 7 : $angle / 60);
            return $p * $night;
        };
        $p = $portion($fajrA);
        $diff = pt_fix($times['sunrise'] - $times['fajr'], 24);
        if (is_nan($times['fajr']) || $diff > $p) {
            $times['fajr'] = $times['sunrise'] - $p;
        }
        if ($ishaA !== null && !$ishaMin) {
            $p = $portion($ishaA);
            $diff = pt_fix($times['isha'] - $times['sunset'], 24);
            if (is_nan($times['isha']) || $diff > $p) {
                $times['isha'] = $times['sunset'] + $p;
            }
        }
    }
    $adj = isset($o['adjust']) && is_array($o['adjust']) ? $o['adjust'] : array();
    $out = array();
    foreach ($times as $k => $v) {
        if (is_nan($v)) {
            $out[$k] = '';
            continue;
        }
        $v += (isset($adj[$k]) ? (int)$adj[$k] : 0) / 60;
        $v = pt_fix($v + 0.5 / 60, 24);
        $h = (int)floor($v);
        $mi = (int)floor(($v - $h) * 60);
        $out[$k] = sprintf('%02d:%02d', $h, $mi);
    }
    return $out;
}

/** "13:05" -> "1:05 PM" (the format used in prayer_timings). */
function pt_ampm($hm)
{
    if ($hm === '' || $hm === null) {
        return '';
    }
    $ts = strtotime('2000-01-01 ' . $hm);
    return $ts ? date('g:i A', $ts) : '';
}

/** Iqamah from rules: array(prayer => array('mode' => 'offset'|'fixed'|'none', 'value' => minutes or 'H:i')) */
function pt_iqamah($azanHm, $rule)
{
    if (!$rule || empty($rule['mode']) || $rule['mode'] === 'none' || $azanHm === '') {
        return '';
    }
    if ($rule['mode'] === 'fixed') {
        return preg_match('/^\d{1,2}:\d{2}$/', (string)$rule['value']) ? $rule['value'] : '';
    }
    $ts = strtotime('2000-01-01 ' . $azanHm) + (int)$rule['value'] * 60;
    return date('H:i', $ts);
}

/** Hijri date text like "5 Jumada al-Akhirah 1448" (needs the intl extension, otherwise ''). */
function hijri_date_text($date)
{
    if (!class_exists('IntlDateFormatter')) {
        return '';
    }
    try {
        $f = new IntlDateFormatter('en_US@calendar=islamic-umalqura', IntlDateFormatter::LONG, IntlDateFormatter::NONE, 'UTC', IntlDateFormatter::TRADITIONAL, 'd MMMM y');
        $s = $f->format(new DateTime($date . ' 12:00:00', new DateTimeZone('UTC')));
        return $s ? preg_replace('/\s*AH$/', '', $s) : '';
    } catch (Exception $e) {
        return '';
    }
}
