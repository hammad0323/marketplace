<?php
/**
 * Prayer & Jumu'ah management, shared by the Super Admin and the mosque portal.
 * Every function receives the venue id from the caller; the portal always passes the signed-in venue,
 * never an id from the request, so one mosque can never edit another mosque's schedule.
 *
 * Tables (existing, unchanged):
 *   prayer_timings      one row per venue per date, times stored as text like "6:07 AM"
 *   th_jummah_prayers   sessions valid from_date..to_date; azan_time = khutbah, iqama_time = salah, place, imam_name,
 *                       type 0 = daylight-saving season, 1 = standard-time season
 * Storage: storage/prayer_meta/{venue}.json keeps where each date's times came from (manual, import, calculated)
 * and the last calculation settings.
 */
require_once __DIR__ . '/prayer_calc.php';

function prayer_fields()
{
    return array(
        'fajr_azan' => 'Fajr Adhan', 'fajr_iqama' => 'Fajr Iqamah', 'sunrise' => 'Sunrise',
        'dhuhr_azan' => 'Dhuhr Adhan', 'dhuhr_iqama' => 'Dhuhr Iqamah',
        'asr_azan' => 'Asr Adhan', 'asr_iqama' => 'Asr Iqamah', 'sunset' => 'Sunset',
        'maghrib_azan' => 'Maghrib Adhan', 'maghrib_iqama' => 'Maghrib Iqamah',
        'isha_azan' => 'Isha Adhan', 'isha_iqama' => 'Isha Iqamah',
    );
}

/** Stored text ("6:07 AM", "18:07", "06:07:00") -> "HH:MM" for <input type=time>. Placeholder 00:00 -> ''. */
function to_hm($s)
{
    $s = trim((string)$s);
    if ($s === '' || preg_match('/^0?0:00(:00)?(\s*AM)?$/i', $s)) {
        return '';
    }
    if (preg_match('/^(\d{1,2})[:.](\d{2})(?::\d{2})?\s*([AaPp])\.?\s*[Mm]?\.?$/', $s, $m)) {
        $h = (int)$m[1] % 12;
        if (strtolower($m[3]) === 'p') {
            $h += 12;
        }
        return sprintf('%02d:%02d', $h, (int)$m[2]);
    }
    if (preg_match('/^(\d{1,2})[:.](\d{2})(?::\d{2})?$/', $s, $m) && (int)$m[1] < 24 && (int)$m[2] < 60) {
        return sprintf('%02d:%02d', (int)$m[1], (int)$m[2]);
    }
    return null; // invalid
}

function prayer_meta($venueId)
{
    $m = storage_read('prayer_meta/' . (int)$venueId . '.json', array());
    if (!isset($m['dates']) || !is_array($m['dates'])) {
        $m['dates'] = array();
    }
    return $m;
}

function prayer_meta_save($venueId, $meta)
{
    storage_write('prayer_meta/' . (int)$venueId . '.json', $meta);
}

function prayer_row($venueId, $date)
{
    return db_one('SELECT * FROM prayer_timings WHERE venue_id = ? AND georgian_date = ? ORDER BY id DESC LIMIT 1', 'is', array((int)$venueId, $date));
}

/**
 * Insert or update one date. $vals: field => 'HH:MM' or ''. $source: manual | import | calculated.
 * Updates the newest existing row for that date (older duplicates are left untouched).
 */
function prayer_save_day($venueId, $date, $vals, $source, &$meta = null)
{
    $fields = prayer_fields();
    $row = prayer_row($venueId, $date);
    $data = array();
    foreach ($fields as $k => $label) {
        $data[$k] = isset($vals[$k]) && $vals[$k] !== '' ? pt_ampm($vals[$k]) : '';
    }
    $day = date('l', strtotime($date));
    if ($row) {
        $islamic = $row['islamic_date'] !== '' && $row['islamic_date'] !== null ? $row['islamic_date'] : hijri_date_text($date);
        $sql = 'UPDATE prayer_timings SET islamic_date = ?, day = ?';
        $types = 'ss';
        $params = array($islamic, $day);
        foreach ($data as $k => $v) {
            $sql .= ', ' . $k . ' = ?';
            $types .= 's';
            $params[] = $v;
        }
        $sql .= ' WHERE id = ?';
        $types .= 'i';
        $params[] = (int)$row['id'];
        db_exec($sql, $types, $params);
    } else {
        $cols = array_merge(array('venue_id', 'georgian_date', 'islamic_date', 'day'), array_keys($data));
        $params = array_merge(array((int)$venueId, $date, hijri_date_text($date), $day), array_values($data));
        db_exec('INSERT INTO prayer_timings (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_fill(0, count($cols), '?')) . ')',
            'i' . str_repeat('s', count($cols) - 1), $params);
    }
    $own = $meta === null;
    if ($own) {
        $meta = prayer_meta($venueId);
    }
    $meta['dates'][$date] = $source;
    if ($own) {
        prayer_meta_save($venueId, $meta);
    }
}

function prayer_source($meta, $date, $row)
{
    if (isset($meta['dates'][$date])) {
        return $meta['dates'][$date];
    }
    return $row ? 'existing' : '';
}

function valid_ymd($d)
{
    if (!is_string($d) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
        return false;
    }
    list($y, $m, $dd) = array_map('intval', explode('-', $d));
    return checkdate($m, $dd, $y) && $y >= 2000 && $y <= 2100;
}

/** Parse a date from CSV/Excel: YYYY-MM-DD, MM/DD/YYYY, DD-MM-YYYY (day > 12), or an Excel serial number. */
function parse_any_date($s)
{
    $s = trim((string)$s);
    if ($s === '') {
        return null;
    }
    if (preg_match('/^\d{4,5}(\.\d+)?$/', $s) && (float)$s > 30000 && (float)$s < 80000) {
        return gmdate('Y-m-d', ((int)$s - 25569) * 86400);
    }
    if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})/', $s, $m)) {
        $d = sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        return valid_ymd($d) ? $d : null;
    }
    if (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})$/', $s, $m)) {
        $a = (int)$m[1];
        $b = (int)$m[2];
        $d = $a > 12 ? sprintf('%04d-%02d-%02d', $m[3], $b, $a) : sprintf('%04d-%02d-%02d', $m[3], $a, $b);
        return valid_ymd($d) ? $d : null;
    }
    $ts = strtotime($s);
    return $ts ? date('Y-m-d', $ts) : null;
}

/** Excel fraction-of-day (0.25 = 06:00) or text time -> HH:MM */
function parse_any_time($s)
{
    $s = trim((string)$s);
    if ($s === '') {
        return '';
    }
    if (preg_match('/^0?\.\d+$/', $s) || ($s === '0')) {
        $mins = (int)round((float)$s * 1440);
        return sprintf('%02d:%02d', intdiv($mins, 60) % 24, $mins % 60);
    }
    return to_hm($s);
}

/** Read rows from an uploaded CSV or XLSX file. Returns array(rows, error). Each row is a list of strings. */
function read_table_upload($field)
{
    if (empty($_FILES[$field]) || $_FILES[$field]['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($_FILES[$field]['tmp_name'])) {
        return array(array(), 'Please choose a CSV or Excel (.xlsx) file.');
    }
    if ($_FILES[$field]['size'] > 2 * 1024 * 1024) {
        return array(array(), 'The file must be 2 MB or smaller.');
    }
    $tmp = $_FILES[$field]['tmp_name'];
    $ext = strtolower(pathinfo($_FILES[$field]['name'], PATHINFO_EXTENSION));
    $head = (string)file_get_contents($tmp, false, null, 0, 4);
    if ($ext === 'xlsx' || $head === "PK\x03\x04") {
        return read_xlsx($tmp);
    }
    if (!in_array($ext, array('csv', 'txt'), true)) {
        return array(array(), 'Only .csv and .xlsx files are supported. In Excel use File → Save As → CSV or Excel Workbook.');
    }
    $rows = array();
    $fh = fopen($tmp, 'rb');
    $first = fgets($fh);
    rewind($fh);
    $delim = substr_count((string)$first, ';') > substr_count((string)$first, ',') ? ';' : ',';
    while (($r = fgetcsv($fh, 0, $delim, '"', '\\')) !== false) {
        if (count($rows) === 0 && isset($r[0])) {
            $r[0] = preg_replace('/^\xEF\xBB\xBF/', '', $r[0]);
        }
        $rows[] = $r;
        if (count($rows) > 2000) {
            break;
        }
    }
    fclose($fh);
    return array($rows, '');
}

/** Minimal XLSX reader (first worksheet, values only). */
function read_xlsx($file)
{
    if (!class_exists('ZipArchive')) {
        return array(array(), 'Excel files need the PHP zip extension on the server. Please save the sheet as CSV instead.');
    }
    $zip = new ZipArchive();
    if ($zip->open($file) !== true) {
        return array(array(), 'The Excel file could not be opened.');
    }
    $shared = array();
    $ss = $zip->getFromName('xl/sharedStrings.xml');
    if ($ss !== false) {
        $x = @simplexml_load_string($ss, 'SimpleXMLElement', LIBXML_NONET);
        if ($x) {
            foreach ($x->si as $si) {
                $t = '';
                if (isset($si->t)) {
                    $t = (string)$si->t;
                } else {
                    foreach ($si->r as $r) {
                        $t .= (string)$r->t;
                    }
                }
                $shared[] = $t;
            }
        }
    }
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    if ($sheet === false) {
        return array(array(), 'The Excel file has no first worksheet.');
    }
    $x = @simplexml_load_string($sheet, 'SimpleXMLElement', LIBXML_NONET);
    if (!$x) {
        return array(array(), 'The Excel worksheet could not be read.');
    }
    $rows = array();
    foreach ($x->sheetData->row as $row) {
        $r = array();
        foreach ($row->c as $c) {
            $ref = (string)$c['r'];
            $col = 0;
            if (preg_match('/^([A-Z]+)/', $ref, $m)) {
                foreach (str_split($m[1]) as $ch) {
                    $col = $col * 26 + (ord($ch) - 64);
                }
                $col--;
            }
            $type = (string)$c['t'];
            $v = isset($c->v) ? (string)$c->v : (isset($c->is->t) ? (string)$c->is->t : '');
            if ($type === 's') {
                $v = isset($shared[(int)$v]) ? $shared[(int)$v] : '';
            }
            $r[$col] = $v;
        }
        if ($r) {
            $max = max(array_keys($r));
            $line = array();
            for ($i = 0; $i <= $max; $i++) {
                $line[] = isset($r[$i]) ? $r[$i] : '';
            }
            $rows[] = $line;
        }
        if (count($rows) > 2000) {
            break;
        }
    }
    return array($rows, '');
}

/** Map header names to prayer fields. */
function import_header_map($header)
{
    $aliases = array(
        'date' => array('date', 'georgian_date', 'gregorian date', 'day date'),
        'fajr_azan' => array('fajr_azan', 'fajr adhan', 'fajr azan', 'fajr', 'fajr begins'),
        'fajr_iqama' => array('fajr_iqama', 'fajr iqamah', 'fajr iqama', 'fajr jamaat', 'fajr jamat'),
        'sunrise' => array('sunrise', 'shuruq', 'shurooq'),
        'dhuhr_azan' => array('dhuhr_azan', 'dhuhr adhan', 'dhuhr azan', 'dhuhr', 'zuhr', 'zuhr azan', 'zuhr adhan'),
        'dhuhr_iqama' => array('dhuhr_iqama', 'dhuhr iqamah', 'dhuhr iqama', 'zuhr iqamah', 'zuhr iqama', 'dhuhr jamaat'),
        'asr_azan' => array('asr_azan', 'asr adhan', 'asr azan', 'asr'),
        'asr_iqama' => array('asr_iqama', 'asr iqamah', 'asr iqama', 'asr jamaat'),
        'sunset' => array('sunset'),
        'maghrib_azan' => array('maghrib_azan', 'maghrib adhan', 'maghrib azan', 'maghrib'),
        'maghrib_iqama' => array('maghrib_iqama', 'maghrib iqamah', 'maghrib iqama', 'maghrib jamaat'),
        'isha_azan' => array('isha_azan', 'isha adhan', 'isha azan', 'isha'),
        'isha_iqama' => array('isha_iqama', 'isha iqamah', 'isha iqama', 'isha jamaat'),
    );
    $map = array();
    foreach ($header as $i => $h) {
        $h = strtolower(trim(preg_replace('/\s+/', ' ', str_replace(array('_', '-'), ' ', (string)$h))));
        foreach ($aliases as $field => $names) {
            foreach ($names as $n) {
                if ($h === str_replace('_', ' ', $n)) {
                    if (!isset($map[$field])) {
                        $map[$field] = $i;
                    }
                    break 2;
                }
            }
        }
    }
    return $map;
}

function prayer_month_rows($venueId, $from, $to)
{
    $out = array();
    foreach (db_all('SELECT * FROM prayer_timings WHERE venue_id = ? AND georgian_date BETWEEN ? AND ? ORDER BY georgian_date, id', 'iss', array((int)$venueId, $from, $to)) as $r) {
        $out[$r['georgian_date']] = $r; // newest id wins
    }
    return $out;
}

function venue_calc_defaults($venueId)
{
    $v = get_venue_any($venueId);
    $c = $v ? venue_coords($v) : null;
    $meta = prayer_meta($venueId);
    $saved = isset($meta['calc']) && is_array($meta['calc']) ? $meta['calc'] : array();
    return array_merge(array(
        'lat' => $c ? $c['lat'] : '', 'lng' => $c ? $c['lng'] : '', 'tz' => $v ? venue_timezone($v) : DEFAULT_TIMEZONE,
        'method' => 'ISNA', 'fajr' => 15, 'isha' => 15, 'isha_min' => '', 'asr' => 1, 'high_lat' => 'AngleBased',
        'adjust' => array('fajr' => 0, 'sunrise' => 0, 'dhuhr' => 0, 'asr' => 0, 'maghrib' => 0, 'isha' => 0),
        'iqamah' => array(
            'fajr' => array('mode' => 'offset', 'value' => 20), 'dhuhr' => array('mode' => 'offset', 'value' => 15),
            'asr' => array('mode' => 'offset', 'value' => 10), 'maghrib' => array('mode' => 'offset', 'value' => 5),
            'isha' => array('mode' => 'offset', 'value' => 10),
        ),
    ), $saved);
}

/** Read the calculation form. */
function calc_options_from_post()
{
    $methods = pt_methods();
    $method = isset($methods[post('method')]) ? post('method') : 'ISNA';
    $o = array(
        'lat' => post('lat'), 'lng' => post('lng'), 'tz' => post('tz'), 'method' => $method,
        'fajr' => is_numeric(post('fajr')) ? (float)post('fajr') : $methods[$method]['fajr'],
        'isha' => is_numeric(post('isha')) ? (float)post('isha') : '',
        'isha_min' => is_numeric(post('isha_min')) && (int)post('isha_min') > 0 ? (int)post('isha_min') : '',
        'asr' => post_int('asr', 1) === 2 ? 2 : 1,
        'high_lat' => in_array(post('high_lat'), array('AngleBased', 'NightMiddle', 'OneSeventh', 'None'), true) ? post('high_lat') : 'AngleBased',
        'adjust' => array(), 'iqamah' => array(),
    );
    if ($method !== 'Custom') {
        $o['fajr'] = $methods[$method]['fajr'];
        $o['isha'] = isset($methods[$method]['isha']) ? $methods[$method]['isha'] : '';
        $o['isha_min'] = isset($methods[$method]['isha_min']) ? $methods[$method]['isha_min'] : '';
        if (isset($methods[$method]['maghrib'])) {
            $o['maghrib'] = $methods[$method]['maghrib'];
        }
    }
    foreach (array('fajr', 'sunrise', 'dhuhr', 'asr', 'maghrib', 'isha') as $p) {
        $o['adjust'][$p] = max(-60, min(60, (int)(isset($_POST['adj'][$p]) ? $_POST['adj'][$p] : 0)));
    }
    foreach (array('fajr', 'dhuhr', 'asr', 'maghrib', 'isha') as $p) {
        $mode = isset($_POST['iq_mode'][$p]) && in_array($_POST['iq_mode'][$p], array('none', 'offset', 'fixed'), true) ? $_POST['iq_mode'][$p] : 'none';
        $val = isset($_POST['iq_val'][$p]) ? trim((string)$_POST['iq_val'][$p]) : '';
        if ($mode === 'offset') {
            $val = max(0, min(120, (int)$val));
        } elseif ($mode === 'fixed') {
            $val = to_hm($val) ?: '';
        }
        $o['iqamah'][$p] = array('mode' => $mode, 'value' => $val);
    }
    return $o;
}

function calc_range_rows($o, $from, $to)
{
    $rows = array();
    $d = new DateTime($from);
    $end = new DateTime($to);
    while ($d <= $end) {
        $date = $d->format('Y-m-d');
        $t = pt_calc_day($date, $o);
        $rows[$date] = array(
            'fajr_azan' => $t['fajr'], 'fajr_iqama' => pt_iqamah($t['fajr'], $o['iqamah']['fajr']), 'sunrise' => $t['sunrise'],
            'dhuhr_azan' => $t['dhuhr'], 'dhuhr_iqama' => pt_iqamah($t['dhuhr'], $o['iqamah']['dhuhr']),
            'asr_azan' => $t['asr'], 'asr_iqama' => pt_iqamah($t['asr'], $o['iqamah']['asr']), 'sunset' => $t['sunset'],
            'maghrib_azan' => $t['maghrib'], 'maghrib_iqama' => pt_iqamah($t['maghrib'], $o['iqamah']['maghrib']),
            'isha_azan' => $t['isha'], 'isha_iqama' => pt_iqamah($t['isha'], $o['iqamah']['isha']),
        );
        $d->modify('+1 day');
    }
    return $rows;
}

function date_range_ok($from, $to, $maxDays = 400)
{
    if (!valid_ymd($from) || !valid_ymd($to) || $from > $to) {
        return false;
    }
    return (strtotime($to) - strtotime($from)) / 86400 <= $maxDays;
}

// ---------------------------------------------------------------------------
// POST + export handling
// ---------------------------------------------------------------------------
/**
 * Handle prayer actions for $venueId. $self = URL of the page (without query).
 * Exits with a redirect after every POST.
 */
function prayer_handle($area, $venueId, $self)
{
    $venueId = (int)$venueId;
    $fields = prayer_fields();
    // CSV export / template (GET)
    if (!is_post() && isset($_GET['export'])) {
        $from = valid_ymd(str_param('from', 10)) ? str_param('from', 10) : date('Y-m-01');
        $to = valid_ymd(str_param('to', 10)) ? str_param('to', 10) : date('Y-m-t');
        if (!date_range_ok($from, $to, 800)) {
            $to = date('Y-m-d', strtotime($from . ' +1 month -1 day'));
        }
        $template = $_GET['export'] === 'template';
        $rows = $template ? array() : prayer_month_rows($venueId, $from, $to);
        $v = get_venue_any($venueId);
        $name = blog_slugify($v ? $v['venue_title'] : 'venue');
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . ($template ? 'prayer-times-template-' : 'prayer-times-') . $name . '-' . $from . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, array_merge(array('date'), array_keys($fields)), ',', '"', '\\');
        $d = new DateTime($from);
        $end = new DateTime($to);
        while ($d <= $end) {
            $date = $d->format('Y-m-d');
            $line = array($date);
            foreach ($fields as $k => $l) {
                $line[] = isset($rows[$date]) ? (string)$rows[$date][$k] : '';
            }
            if ($template || isset($rows[$date])) {
                fputcsv($out, $line, ',', '"', '\\');
            }
            $d->modify('+1 day');
        }
        fclose($out);
        exit;
    }
    if (!is_post()) {
        return;
    }
    require_post_csrf();
    $action = post('action');
    $back = $self . '?' . http_build_query(array_filter(array('venue' => $area === 'admin' ? $venueId : null, 'tab' => post('tab', 'calendar'), 'month' => post('month'), 'date' => post('date'))));
    $meta = prayer_meta($venueId);
    try {
        if ($action === 'save_day') {
            $date = post('date');
            if (!valid_ymd($date)) {
                throw new InvalidArgumentException('Choose a valid date.');
            }
            $vals = array();
            foreach ($fields as $k => $label) {
                $hm = to_hm(isset($_POST['t'][$k]) ? $_POST['t'][$k] : '');
                if ($hm === null) {
                    throw new InvalidArgumentException($label . ' is not a valid time.');
                }
                $vals[$k] = $hm;
            }
            if (!array_filter($vals)) {
                throw new InvalidArgumentException('Enter at least one time.');
            }
            prayer_save_day($venueId, $date, $vals, 'manual', $meta);
            prayer_meta_save($venueId, $meta);
            log_me('prayer.save_day', 'prayer_timings', $venueId, null, array('date' => $date) + $vals);
            flash('success', 'Prayer times for ' . fmt_date($date, 'D j M Y') . ' saved.');
        } elseif ($action === 'delete_day') {
            $date = post('date');
            $row = valid_ymd($date) ? prayer_row($venueId, $date) : null;
            if ($row) {
                db_exec('DELETE FROM prayer_timings WHERE id = ? AND venue_id = ?', 'ii', array((int)$row['id'], $venueId));
                unset($meta['dates'][$date]);
                prayer_meta_save($venueId, $meta);
                log_me('prayer.delete_day', 'prayer_timings', $venueId, $row, null);
                flash('success', 'Prayer times for ' . fmt_date($date, 'D j M Y') . ' deleted.');
            }
        } elseif ($action === 'copy_range') {
            $src = post('source');
            $from = post('from');
            $to = post('to');
            $onlyEmpty = post('only_empty') === '1';
            $row = valid_ymd($src) ? prayer_row($venueId, $src) : null;
            if (!$row) {
                throw new InvalidArgumentException('The source date has no prayer times.');
            }
            if (!date_range_ok($from, $to, 120)) {
                throw new InvalidArgumentException('Choose a target range of up to 120 days.');
            }
            $vals = array();
            foreach ($fields as $k => $l) {
                $vals[$k] = (string)to_hm($row[$k]);
            }
            $existing = prayer_month_rows($venueId, $from, $to);
            $n = 0;
            $d = new DateTime($from);
            while ($d <= new DateTime($to)) {
                $date = $d->format('Y-m-d');
                if ($date !== $src && (!$onlyEmpty || !isset($existing[$date]))) {
                    prayer_save_day($venueId, $date, $vals, 'manual', $meta);
                    $n++;
                }
                $d->modify('+1 day');
            }
            prayer_meta_save($venueId, $meta);
            log_me('prayer.copy_range', 'prayer_timings', $venueId, null, array('source' => $src, 'from' => $from, 'to' => $to, 'days' => $n));
            flash('success', 'Copied the times of ' . fmt_date($src) . ' to ' . plural($n, 'day') . '.');
        } elseif ($action === 'bulk_adjust') {
            $from = post('from');
            $to = post('to');
            $minutes = max(-180, min(180, post_int('minutes')));
            $targets = array_values(array_intersect(array_keys($fields), (array)(isset($_POST['fields']) ? $_POST['fields'] : array())));
            if (!date_range_ok($from, $to, 400) || !$targets || $minutes === 0) {
                throw new InvalidArgumentException('Choose a date range, at least one prayer and a number of minutes.');
            }
            $rows = prayer_month_rows($venueId, $from, $to);
            foreach ($rows as $date => $row) {
                $vals = array();
                foreach ($fields as $k => $l) {
                    $hm = (string)to_hm($row[$k]);
                    if ($hm !== '' && in_array($k, $targets, true)) {
                        $hm = date('H:i', strtotime('2000-01-01 ' . $hm) + $minutes * 60);
                    }
                    $vals[$k] = $hm;
                }
                prayer_save_day($venueId, $date, $vals, 'manual', $meta);
            }
            prayer_meta_save($venueId, $meta);
            log_me('prayer.bulk_adjust', 'prayer_timings', $venueId, null, array('from' => $from, 'to' => $to, 'minutes' => $minutes, 'fields' => $targets, 'days' => count($rows)));
            flash('success', 'Adjusted ' . plural(count($rows), 'day') . ' by ' . ($minutes > 0 ? '+' : '') . $minutes . ' minutes.');
        } elseif ($action === 'import_preview') {
            list($rows, $err) = read_table_upload('file');
            if ($err !== '') {
                throw new InvalidArgumentException($err);
            }
            if (count($rows) < 2) {
                throw new InvalidArgumentException('The file has no data rows.');
            }
            $map = import_header_map($rows[0]);
            if (!isset($map['date'])) {
                throw new InvalidArgumentException('The first row must contain column names, including a "date" column. Download the template to see the format.');
            }
            $limitFrom = valid_ymd(post('limit_from')) ? post('limit_from') : '';
            $limitTo = valid_ymd(post('limit_to')) ? post('limit_to') : '';
            $parsed = array();
            $seen = array();
            for ($i = 1; $i < count($rows); $i++) {
                $r = $rows[$i];
                if (!array_filter(array_map('trim', $r), 'strlen')) {
                    continue;
                }
                $line = array('line' => $i + 1, 'date' => '', 'vals' => array(), 'errors' => array(), 'status' => 'new');
                $date = parse_any_date(isset($r[$map['date']]) ? $r[$map['date']] : '');
                if (!$date) {
                    $line['errors'][] = 'invalid date';
                } else {
                    $line['date'] = $date;
                    if (($limitFrom && $date < $limitFrom) || ($limitTo && $date > $limitTo)) {
                        continue;
                    }
                    if (isset($seen[$date])) {
                        $line['errors'][] = 'duplicate date in file (line ' . $seen[$date] . ')';
                    }
                    $seen[$date] = $i + 1;
                }
                foreach ($fields as $k => $label) {
                    $raw = isset($map[$k]) && isset($r[$map[$k]]) ? $r[$map[$k]] : '';
                    $hm = parse_any_time($raw);
                    if ($hm === null) {
                        $line['errors'][] = $label . ' "' . mb_substr($raw, 0, 12) . '" is not a time';
                        $hm = '';
                    }
                    $line['vals'][$k] = $hm;
                }
                if (!array_filter($line['vals'])) {
                    $line['errors'][] = 'no times';
                }
                $parsed[] = $line;
            }
            if (!$parsed) {
                throw new InvalidArgumentException('No rows matched the selected dates.');
            }
            $dates = array_filter(array_map(function ($l) { return $l['date']; }, $parsed));
            if ($dates) {
                $existing = prayer_month_rows($venueId, min($dates), max($dates));
                foreach ($parsed as $i => $l) {
                    if ($l['errors']) {
                        $parsed[$i]['status'] = 'error';
                    } elseif (isset($existing[$l['date']])) {
                        $parsed[$i]['status'] = 'replace';
                        $parsed[$i]['source'] = prayer_source($meta, $l['date'], $existing[$l['date']]);
                    }
                }
            }
            $token = bin2hex(random_bytes(8));
            $_SESSION['prayer_import'] = array('token' => $token, 'venue' => $venueId, 'rows' => $parsed, 'file' => mb_substr($_FILES['file']['name'], 0, 80), 'missing' => array_values(array_diff(array_keys($fields), array_keys($map))));
            header('Location: ' . $self . '?' . http_build_query(array_filter(array('venue' => $area === 'admin' ? $venueId : null, 'tab' => 'import', 'preview' => $token))), true, 303);
            exit;
        } elseif ($action === 'import_confirm') {
            $imp = isset($_SESSION['prayer_import']) ? $_SESSION['prayer_import'] : null;
            if (!$imp || !hash_equals($imp['token'], (string)post('token')) || (int)$imp['venue'] !== $venueId) {
                throw new InvalidArgumentException('The preview expired. Please upload the file again.');
            }
            $replace = post('replace') === '1';
            $n = $skipped = 0;
            db_begin();
            foreach ($imp['rows'] as $l) {
                if ($l['status'] === 'error' || ($l['status'] === 'replace' && !$replace)) {
                    $skipped++;
                    continue;
                }
                prayer_save_day($venueId, $l['date'], $l['vals'], 'import', $meta);
                $n++;
            }
            db_commit();
            prayer_meta_save($venueId, $meta);
            unset($_SESSION['prayer_import']);
            log_me('prayer.import', 'prayer_timings', $venueId, null, array('file' => $imp['file'], 'saved' => $n, 'skipped' => $skipped));
            flash('success', 'Imported ' . plural($n, 'day') . ($skipped ? ' · ' . plural($skipped, 'row') . ' skipped' : '') . '.');
            $back = $self . '?' . http_build_query(array_filter(array('venue' => $area === 'admin' ? $venueId : null, 'tab' => 'calendar')));
        } elseif ($action === 'calc_preview' || $action === 'calc_save') {
            $o = calc_options_from_post();
            if (!valid_lat_lng($o['lat'], $o['lng'])) {
                throw new InvalidArgumentException('Enter the mosque\'s latitude and longitude (set them on the profile map, or type them here).');
            }
            if (!in_array($o['tz'], DateTimeZone::listIdentifiers(), true)) {
                throw new InvalidArgumentException('Choose a valid time zone.');
            }
            $from = post('from');
            $to = post('to');
            if (!date_range_ok($from, $to, 366)) {
                throw new InvalidArgumentException('Choose a date range of up to one year.');
            }
            $meta['calc'] = $o;
            prayer_meta_save($venueId, $meta);
            if ($action === 'calc_preview') {
                $_SESSION['prayer_calc'] = array('venue' => $venueId, 'from' => $from, 'to' => $to);
                header('Location: ' . $self . '?' . http_build_query(array_filter(array('venue' => $area === 'admin' ? $venueId : null, 'tab' => 'calculate', 'preview' => 1))), true, 303);
                exit;
            }
            $mode = post('save_mode', 'empty'); // empty | calculated | all
            if ($mode === 'all' && strtoupper(post('confirm_word')) !== 'REPLACE') {
                throw new InvalidArgumentException('To replace manually entered times, type REPLACE in the confirmation box.');
            }
            $rows = calc_range_rows($o, $from, $to);
            $existing = prayer_month_rows($venueId, $from, $to);
            $n = $skipped = 0;
            db_begin();
            foreach ($rows as $date => $vals) {
                if (isset($existing[$date])) {
                    $src = prayer_source($meta, $date, $existing[$date]);
                    if ($mode === 'empty' || ($mode === 'calculated' && $src !== 'calculated')) {
                        $skipped++;
                        continue;
                    }
                }
                prayer_save_day($venueId, $date, $vals, 'calculated', $meta);
                $n++;
            }
            db_commit();
            prayer_meta_save($venueId, $meta);
            unset($_SESSION['prayer_calc']);
            log_me('prayer.calculate', 'prayer_timings', $venueId, null, array('from' => $from, 'to' => $to, 'method' => $o['method'], 'fajr' => $o['fajr'], 'isha' => $o['isha'], 'asr' => $o['asr'], 'saved' => $n, 'skipped' => $skipped, 'mode' => $mode));
            flash('success', 'Saved calculated times for ' . plural($n, 'day') . ($skipped ? ' · kept ' . plural($skipped, 'existing day') : '') . '.');
            $back = $self . '?' . http_build_query(array_filter(array('venue' => $area === 'admin' ? $venueId : null, 'tab' => 'calendar', 'month' => substr($from, 0, 7))));
        }
    } catch (InvalidArgumentException $e) {
        flash('danger', $e->getMessage());
    } catch (Exception $e) {
        error_log('Prayer action failed: ' . $e->getMessage());
        try { db_rollback(); } catch (Exception $e2) {}
        flash('danger', 'Something went wrong while saving. Nothing was changed for the failing row. ' . (DEBUG ? $e->getMessage() : ''));
    }
    header('Location: ' . $back, true, 303);
    exit;
}

// ---------------------------------------------------------------------------
// UI
// ---------------------------------------------------------------------------
function prayer_tabs($self, $area, $venueId, $tab)
{
    $tabs = array('calendar' => array('Calendar', 'bi-calendar3'), 'day' => array('Daily entry', 'bi-pencil-square'), 'import' => array('Bulk upload', 'bi-upload'),
        'calculate' => array('Calculate', 'bi-calculator'), 'export' => array('Export', 'bi-download'));
    echo '<ul class="nav nav-pills bo-pills mb-4">';
    foreach ($tabs as $k => $t) {
        $q = array_filter(array('venue' => $area === 'admin' ? $venueId : null, 'tab' => $k));
        echo '<li class="nav-item"><a class="nav-link' . ($tab === $k ? ' active' : '') . '" href="' . e($self . '?' . http_build_query($q)) . '"><i class="bi ' . $t[1] . ' me-1" aria-hidden="true"></i>' . $t[0] . '</a></li>';
    }
    echo '</ul>';
}

function prayer_manager($area, $venueId, $self)
{
    $venueId = (int)$venueId;
    $v = get_venue_any($venueId);
    $tab = in_array(str_param('tab', 12), array('calendar', 'day', 'import', 'calculate', 'export'), true) ? str_param('tab', 12) : 'calendar';
    $fields = prayer_fields();
    $meta = prayer_meta($venueId);
    $tz = venue_timezone($v);
    $today = today_in($tz);
    $range = prayer_date_range($venueId);
    $hidden = '<input type="hidden" name="tab" value="' . e($tab) . '">';
    $sourceLabels = array('manual' => array('Manual', 'text-bg-primary'), 'import' => array('Imported', 'text-bg-info'), 'calculated' => array('Calculated', 'text-bg-warning'), 'existing' => array('Existing', 'text-bg-secondary'));
    ?>
    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
      <div>
        <div class="text-muted small">Prayer schedule for</div>
        <div class="fw-semibold fs-5"><?= e(trim($v['venue_title'])) ?> <span class="text-muted fw-normal fs-6">· <?= e(venue_place_line($v)) ?> · <?= e($tz) ?></span></div>
      </div>
      <div class="ms-auto small text-muted">
        <?php if ($range && $range['first_date']): ?>Times on file: <?= e(fmt_date($range['first_date'])) ?> – <?= e(fmt_date($range['last_date'])) ?><?php else: ?>No daily times on file yet.<?php endif; ?>
      </div>
    </div>
    <?php prayer_tabs($self, $area, $venueId, $tab); ?>

    <?php if ($tab === 'calendar'):
        $month = preg_match('/^\d{4}-\d{2}$/', str_param('month', 7)) ? str_param('month', 7) : substr($today, 0, 7);
        $first = $month . '-01';
        $last = date('Y-m-t', strtotime($first));
        $rows = prayer_month_rows($venueId, $first, $last);
        $prev = date('Y-m', strtotime($first . ' -1 month'));
        $next = date('Y-m', strtotime($first . ' +1 month'));
        $days = (int)date('t', strtotime($first));
        $missing = $days - count($rows);
        $q = function ($extra) use ($area, $venueId) { return '?' . http_build_query(array_filter(array_merge(array('venue' => $area === 'admin' ? $venueId : null), $extra))); };
    ?>
    <div class="card bo-card mb-4">
      <div class="card-header d-flex align-items-center gap-2 flex-wrap">
        <a class="btn btn-sm btn-light" href="<?= e($self . $q(array('tab' => 'calendar', 'month' => $prev))) ?>" aria-label="Previous month"><i class="bi bi-chevron-left"></i></a>
        <h2 class="h6 m-0"><?= e(date('F Y', strtotime($first))) ?></h2>
        <a class="btn btn-sm btn-light" href="<?= e($self . $q(array('tab' => 'calendar', 'month' => $next))) ?>" aria-label="Next month"><i class="bi bi-chevron-right"></i></a>
        <a class="btn btn-sm btn-link" href="<?= e($self . $q(array('tab' => 'calendar'))) ?>">Today</a>
        <span class="ms-auto small"><?= $missing > 0 ? '<span class="text-danger fw-semibold">' . plural($missing, 'day') . ' missing</span>' : '<span class="text-success fw-semibold">All days have times</span>' ?></span>
      </div>
      <div class="card-body p-2 p-md-3">
        <div class="prayer-cal">
          <?php foreach (array('Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat') as $dn): ?><div class="pc-head"><?= $dn ?></div><?php endforeach; ?>
          <?php for ($i = 0; $i < (int)date('w', strtotime($first)); $i++): ?><div class="pc-blank"></div><?php endfor; ?>
          <?php for ($dd = 1; $dd <= $days; $dd++):
              $date = sprintf('%s-%02d', $month, $dd);
              $r = isset($rows[$date]) ? $rows[$date] : null;
              $src = prayer_source($meta, $date, $r);
          ?>
          <a class="pc-day<?= $r ? '' : ' is-missing' ?><?= $date === $today ? ' is-today' : '' ?><?= date('w', strtotime($date)) === '5' ? ' is-fri' : '' ?>" href="<?= e($self . $q(array('tab' => 'day', 'date' => $date))) ?>">
            <span class="pc-num"><?= $dd ?></span>
            <?php if ($r): ?>
              <span class="pc-times"><?php foreach (array('fajr_azan' => 'F', 'dhuhr_azan' => 'D', 'asr_azan' => 'A', 'maghrib_azan' => 'M', 'isha_azan' => 'I') as $k => $ab): $t = fmt_time($r[$k]); ?><span><b><?= $ab ?></b> <?= e($t !== '' ? str_replace(array(' AM', ' PM'), array('a', 'p'), $t) : '–') ?></span><?php endforeach; ?></span>
              <?php if ($src !== 'existing' && isset($sourceLabels[$src])): ?><span class="badge <?= $sourceLabels[$src][1] ?> pc-src"><?= $sourceLabels[$src][0] ?></span><?php endif; ?>
            <?php else: ?><span class="pc-missing">Missing</span><?php endif; ?>
          </a>
          <?php endfor; ?>
        </div>
      </div>
    </div>

    <div class="row g-4">
      <div class="col-lg-6">
        <form method="post" class="card bo-card h-100" data-confirm="Copy these times into the selected days?">
          <?= csrf_field() ?><?= $hidden ?><input type="hidden" name="action" value="copy_range"><input type="hidden" name="month" value="<?= e($month) ?>">
          <div class="card-header"><h2 class="h6 m-0"><i class="bi bi-files me-1"></i> Copy times from a date</h2></div>
          <div class="card-body">
            <div class="row g-2">
              <div class="col-sm-4"><label class="form-label small">Copy from</label><input type="date" name="source" class="form-control" required></div>
              <div class="col-sm-4"><label class="form-label small">To dates from</label><input type="date" name="from" class="form-control" value="<?= e($first) ?>" required></div>
              <div class="col-sm-4"><label class="form-label small">until</label><input type="date" name="to" class="form-control" value="<?= e($last) ?>" required></div>
            </div>
            <div class="form-check mt-3"><input class="form-check-input" type="checkbox" name="only_empty" value="1" id="onlyEmpty" checked><label class="form-check-label" for="onlyEmpty">Only fill days that have no times</label></div>
          </div>
          <div class="card-footer text-end"><button class="btn btn-primary">Copy times</button></div>
        </form>
      </div>
      <div class="col-lg-6">
        <form method="post" class="card bo-card h-100" data-confirm="Shift the selected prayer times for every day in this range?">
          <?= csrf_field() ?><?= $hidden ?><input type="hidden" name="action" value="bulk_adjust"><input type="hidden" name="month" value="<?= e($month) ?>">
          <div class="card-header"><h2 class="h6 m-0"><i class="bi bi-arrow-left-right me-1"></i> Bulk time adjustment</h2></div>
          <div class="card-body">
            <div class="row g-2">
              <div class="col-sm-4"><label class="form-label small">From</label><input type="date" name="from" class="form-control" value="<?= e($first) ?>" required></div>
              <div class="col-sm-4"><label class="form-label small">To</label><input type="date" name="to" class="form-control" value="<?= e($last) ?>" required></div>
              <div class="col-sm-4"><label class="form-label small">Minutes (+/−)</label><input type="number" name="minutes" class="form-control" min="-180" max="180" step="1" placeholder="e.g. 5 or -10" required></div>
            </div>
            <div class="d-flex flex-wrap gap-2 mt-3">
              <?php foreach ($fields as $k => $label): ?>
              <input type="checkbox" class="btn-check" name="fields[]" value="<?= e($k) ?>" id="adj_<?= e($k) ?>"><label class="btn btn-sm btn-outline-secondary" for="adj_<?= e($k) ?>"><?= e($label) ?></label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="card-footer text-end"><button class="btn btn-primary">Apply adjustment</button></div>
        </form>
      </div>
    </div>

    <?php elseif ($tab === 'day'):
        $date = valid_ymd(str_param('date', 10)) ? str_param('date', 10) : $today;
        $row = prayer_row($venueId, $date);
        $prevDate = date('Y-m-d', strtotime($date . ' -1 day'));
        $prevRow = prayer_row($venueId, $prevDate);
        $src = prayer_source($meta, $date, $row);
        $prevVals = array();
        if ($prevRow) {
            foreach ($fields as $k => $l) {
                $prevVals[$k] = (string)to_hm($prevRow[$k]);
            }
        }
    ?>
    <form method="post" class="card bo-card" style="max-width:820px">
      <?= csrf_field() ?><?= $hidden ?><input type="hidden" name="action" value="save_day">
      <div class="card-header d-flex flex-wrap align-items-center gap-2">
        <label class="form-label m-0 me-1 fw-semibold" for="pdate">Date</label>
        <input type="date" class="form-control form-control-sm" style="width:auto" id="pdate" name="date" value="<?= e($date) ?>" data-date-nav="<?= e($self . '?' . http_build_query(array_filter(array('venue' => $area === 'admin' ? $venueId : null, 'tab' => 'day')))) ?>">
        <span class="text-muted small"><?= e(fmt_date($date, 'l')) ?><?= $row && $row['islamic_date'] ? ' · ' . e($row['islamic_date']) : '' ?></span>
        <?php if (isset($sourceLabels[$src])): ?><span class="badge <?= $sourceLabels[$src][1] ?>"><?= $sourceLabels[$src][0] ?></span><?php endif; ?>
        <?php if ($prevRow): ?><button type="button" class="btn btn-sm btn-outline-secondary ms-auto" data-fill-times="<?= e(json_encode($prevVals)) ?>"><i class="bi bi-arrow-down-square me-1"></i>Copy from <?= e(fmt_date($prevDate, 'j M')) ?></button><?php endif; ?>
      </div>
      <div class="card-body">
        <?php if ($src === 'calculated'): ?><div class="alert alert-warning py-2 small"><i class="bi bi-calculator me-1"></i>These times were calculated. Saving here marks them as manually confirmed.</div><?php endif; ?>
        <div class="table-responsive">
          <table class="table align-middle prayer-entry mb-0">
            <thead><tr><th>Prayer</th><th>Adhan</th><th>Iqamah</th></tr></thead>
            <tbody>
            <?php foreach (array('fajr' => 'Fajr', 'sunrise' => 'Sunrise', 'dhuhr' => 'Dhuhr', 'asr' => 'Asr', 'sunset' => 'Sunset', 'maghrib' => 'Maghrib', 'isha' => 'Isha') as $p => $label):
                $single = in_array($p, array('sunrise', 'sunset'), true); ?>
              <tr>
                <th scope="row"><?= e($label) ?></th>
                <?php if ($single): ?>
                  <td><input type="time" class="form-control" name="t[<?= $p ?>]" value="<?= e((string)to_hm($row ? $row[$p] : '')) ?>" aria-label="<?= e($label) ?>"></td><td class="text-muted small">—</td>
                <?php else: ?>
                  <td><input type="time" class="form-control" name="t[<?= $p ?>_azan]" value="<?= e((string)to_hm($row ? $row[$p . '_azan'] : '')) ?>" aria-label="<?= e($label) ?> adhan"></td>
                  <td><input type="time" class="form-control" name="t[<?= $p ?>_iqama]" value="<?= e((string)to_hm($row ? $row[$p . '_iqama'] : '')) ?>" aria-label="<?= e($label) ?> iqamah"></td>
                <?php endif; ?>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <div class="card-footer d-flex gap-2">
        <button class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Save times</button>
        <?php if ($row): ?>
        <button class="btn btn-outline-danger ms-auto" name="action" value="delete_day" data-confirm-click="Delete the prayer times for <?= e(fmt_date($date, 'j M Y')) ?>?" formnovalidate><i class="bi bi-trash me-1"></i>Delete this day</button>
        <?php endif; ?>
      </div>
    </form>

    <?php elseif ($tab === 'import'):
        $prev = isset($_SESSION['prayer_import']) && isset($_GET['preview']) && hash_equals($_SESSION['prayer_import']['token'], (string)$_GET['preview']) && (int)$_SESSION['prayer_import']['venue'] === $venueId ? $_SESSION['prayer_import'] : null;
        $tplQ = function ($kind) use ($area, $venueId, $today) { return '?' . http_build_query(array_filter(array('venue' => $area === 'admin' ? $venueId : null, 'export' => $kind, 'from' => substr($today, 0, 7) . '-01', 'to' => date('Y-m-t', strtotime($today))))); };
    ?>
      <?php if ($prev):
          $counts = array('new' => 0, 'replace' => 0, 'error' => 0);
          foreach ($prev['rows'] as $l) { $counts[$l['status']]++; }
      ?>
      <form method="post" class="card bo-card">
        <?= csrf_field() ?><?= $hidden ?><input type="hidden" name="action" value="import_confirm"><input type="hidden" name="token" value="<?= e($prev['token']) ?>">
        <div class="card-header"><h2 class="h6 m-0">Preview: <?= e($prev['file']) ?></h2></div>
        <div class="card-body">
          <div class="d-flex flex-wrap gap-2 mb-3">
            <span class="badge text-bg-success fs-6"><?= (int)$counts['new'] ?> new</span>
            <span class="badge text-bg-warning fs-6"><?= (int)$counts['replace'] ?> already have times</span>
            <span class="badge text-bg-danger fs-6"><?= (int)$counts['error'] ?> with errors (skipped)</span>
          </div>
          <?php if ($prev['missing']): ?><p class="small text-muted">Columns not found in the file (left empty): <?= e(implode(', ', array_map(function ($k) use ($fields) { return $fields[$k]; }, $prev['missing']))) ?></p><?php endif; ?>
          <div class="table-responsive" style="max-height:480px">
            <table class="table table-sm table-hover small align-middle">
              <thead class="sticky-top bg-white"><tr><th>Line</th><th>Date</th><th>Status</th><?php foreach ($fields as $l): ?><th><?= e(str_replace(array(' Adhan', ' Iqamah'), array(' A', ' I'), $l)) ?></th><?php endforeach; ?></tr></thead>
              <tbody>
              <?php foreach ($prev['rows'] as $l): ?>
                <tr class="<?= $l['status'] === 'error' ? 'table-danger' : ($l['status'] === 'replace' ? 'table-warning' : '') ?>">
                  <td><?= (int)$l['line'] ?></td><td class="text-nowrap"><?= e($l['date']) ?></td>
                  <td><?= $l['status'] === 'error' ? e(implode('; ', $l['errors'])) : ($l['status'] === 'replace' ? 'Replace (' . e(isset($sourceLabels[$l['source']]) ? $sourceLabels[$l['source']][0] : 'existing') . ')' : 'New') ?></td>
                  <?php foreach ($fields as $k => $lab): ?><td><?= e(pt_ampm($l['vals'][$k])) ?></td><?php endforeach; ?>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php if ($counts['replace']): ?>
          <div class="form-check mt-3"><input class="form-check-input" type="checkbox" name="replace" value="1" id="impReplace"><label class="form-check-label" for="impReplace">Replace the <?= (int)$counts['replace'] ?> dates that already have times</label></div>
          <?php endif; ?>
        </div>
        <div class="card-footer d-flex gap-2">
          <button class="btn btn-primary" <?= ($counts['new'] + $counts['replace']) ? '' : 'disabled' ?> data-confirm-click="Import these prayer times?"><i class="bi bi-check2 me-1"></i>Confirm import</button>
          <a class="btn btn-light" href="<?= e($self . '?' . http_build_query(array_filter(array('venue' => $area === 'admin' ? $venueId : null, 'tab' => 'import')))) ?>">Cancel</a>
        </div>
      </form>
      <?php else: ?>
      <div class="row g-4">
        <div class="col-lg-7">
          <form method="post" enctype="multipart/form-data" class="card bo-card">
            <?= csrf_field() ?><?= $hidden ?><input type="hidden" name="action" value="import_preview">
            <div class="card-header"><h2 class="h6 m-0"><i class="bi bi-upload me-1"></i> Upload a timetable</h2></div>
            <div class="card-body">
              <label class="form-label" for="pfile">CSV or Excel (.xlsx) file</label>
              <input class="form-control" type="file" id="pfile" name="file" accept=".csv,.xlsx,text/csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required>
              <div class="row g-2 mt-2">
                <div class="col-sm-6"><label class="form-label small">Only import dates from (optional)</label><input type="date" class="form-control" name="limit_from"></div>
                <div class="col-sm-6"><label class="form-label small">until (optional)</label><input type="date" class="form-control" name="limit_to"></div>
              </div>
              <p class="small text-muted mt-3 mb-0">Nothing is saved yet: you'll see a preview with every row checked for valid dates and times, duplicates, and dates that already have times.</p>
            </div>
            <div class="card-footer text-end"><button class="btn btn-primary">Preview import</button></div>
          </form>
        </div>
        <div class="col-lg-5">
          <div class="card bo-card">
            <div class="card-header"><h2 class="h6 m-0"><i class="bi bi-file-earmark-spreadsheet me-1"></i> File format</h2></div>
            <div class="card-body small">
              <p>First row: column names. One row per day. Columns: <code>date</code>, <code>fajr_azan</code>, <code>fajr_iqama</code>, <code>sunrise</code>, <code>dhuhr_azan</code>, <code>dhuhr_iqama</code>, <code>asr_azan</code>, <code>asr_iqama</code>, <code>sunset</code>, <code>maghrib_azan</code>, <code>maghrib_iqama</code>, <code>isha_azan</code>, <code>isha_iqama</code>. Names like “Fajr Iqamah” or “Zuhr” also work.</p>
              <p>Dates: <code>2026-12-01</code> or <code>12/01/2026</code>. Times: <code>6:07 AM</code>, <code>06:07</code> or <code>18:07</code>. Empty cells are allowed.</p>
              <a class="btn btn-sm btn-outline-primary" href="<?= e($self . $tplQ('template')) ?>"><i class="bi bi-download me-1"></i>Download template (this month)</a>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

    <?php elseif ($tab === 'calculate'):
        $o = venue_calc_defaults($venueId);
        $pv = isset($_SESSION['prayer_calc']) && !empty($_GET['preview']) && (int)$_SESSION['prayer_calc']['venue'] === $venueId ? $_SESSION['prayer_calc'] : null;
        $from = $pv ? $pv['from'] : date('Y-m-01', strtotime($today));
        $to = $pv ? $pv['to'] : date('Y-m-t', strtotime($today));
        $methods = pt_methods();
    ?>
    <form method="post" class="card bo-card mb-4" data-calc-form>
      <?= csrf_field() ?><?= $hidden ?>
      <div class="card-header"><h2 class="h6 m-0"><i class="bi bi-calculator me-1"></i> Angle-based calculation</h2></div>
      <div class="card-body">
        <p class="small text-muted">Calculated times are only a starting point. Preview them, adjust, and save. Dates with manually entered or imported times are never replaced unless you choose so below.</p>
        <div class="row g-3">
          <div class="col-md-3"><label class="form-label small">Latitude</label><input class="form-control" name="lat" value="<?= e($o['lat']) ?>" inputmode="decimal" required></div>
          <div class="col-md-3"><label class="form-label small">Longitude</label><input class="form-control" name="lng" value="<?= e($o['lng']) ?>" inputmode="decimal" required></div>
          <div class="col-md-6"><label class="form-label small">Time zone</label>
            <select class="form-select" name="tz" data-s2><?php foreach (DateTimeZone::listIdentifiers(DateTimeZone::AMERICA | DateTimeZone::EUROPE | DateTimeZone::ASIA | DateTimeZone::AFRICA | DateTimeZone::AUSTRALIA | DateTimeZone::PACIFIC | DateTimeZone::ATLANTIC) as $z): ?><option<?= sel($z, $o['tz']) ?>><?= e($z) ?></option><?php endforeach; ?></select></div>
          <?php if ($o['lat'] === ''): ?><div class="col-12"><div class="alert alert-info small py-2 mb-0">This profile has no confirmed map location yet. Set it on the profile page (map pin), or type the coordinates here.</div></div><?php endif; ?>
          <div class="col-md-6"><label class="form-label small">Calculation method</label>
            <select class="form-select" name="method" data-calc-method><?php foreach ($methods as $k => $m): ?><option value="<?= e($k) ?>" data-fajr="<?= e($m['fajr']) ?>" data-isha="<?= e(isset($m['isha']) ? $m['isha'] : '') ?>" data-isha-min="<?= e(isset($m['isha_min']) ? $m['isha_min'] : '') ?>"<?= sel($k, $o['method']) ?>><?= e($m['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-4 col-md-2"><label class="form-label small">Fajr angle °</label><input class="form-control" name="fajr" value="<?= e($o['fajr']) ?>" inputmode="decimal"></div>
          <div class="col-4 col-md-2"><label class="form-label small">Isha angle °</label><input class="form-control" name="isha" value="<?= e($o['isha']) ?>" inputmode="decimal"></div>
          <div class="col-4 col-md-2"><label class="form-label small">or Isha min after Maghrib</label><input class="form-control" name="isha_min" value="<?= e($o['isha_min']) ?>" inputmode="numeric"></div>
          <div class="col-md-6"><label class="form-label small">Asr juristic method</label>
            <select class="form-select" name="asr"><option value="1"<?= sel(1, $o['asr']) ?>>Standard (Shafi'i, Maliki, Hanbali) — shadow = 1×</option><option value="2"<?= sel(2, $o['asr']) ?>>Hanafi — shadow = 2×</option></select></div>
          <div class="col-md-6"><label class="form-label small">High-latitude rule</label>
            <select class="form-select" name="high_lat"><?php foreach (array('AngleBased' => 'Angle-based (recommended)', 'NightMiddle' => 'Middle of the night', 'OneSeventh' => 'One-seventh of the night', 'None' => 'None') as $k => $l): ?><option value="<?= $k ?>"<?= sel($k, $o['high_lat']) ?>><?= $l ?></option><?php endforeach; ?></select></div>
        </div>
        <h3 class="h6 mt-4">Manual adjustments (minutes)</h3>
        <div class="row g-2">
          <?php foreach (array('fajr' => 'Fajr', 'sunrise' => 'Sunrise', 'dhuhr' => 'Dhuhr', 'asr' => 'Asr', 'maghrib' => 'Maghrib', 'isha' => 'Isha') as $p => $l): ?>
          <div class="col-4 col-md-2"><label class="form-label small"><?= $l ?></label><input class="form-control" type="number" min="-60" max="60" name="adj[<?= $p ?>]" value="<?= (int)(isset($o['adjust'][$p]) ? $o['adjust'][$p] : 0) ?>"></div>
          <?php endforeach; ?>
        </div>
        <h3 class="h6 mt-4">Iqamah</h3>
        <div class="row g-2">
          <?php foreach (array('fajr' => 'Fajr', 'dhuhr' => 'Dhuhr', 'asr' => 'Asr', 'maghrib' => 'Maghrib', 'isha' => 'Isha') as $p => $l): $iq = isset($o['iqamah'][$p]) ? $o['iqamah'][$p] : array('mode' => 'none', 'value' => ''); ?>
          <div class="col-sm-6 col-lg-4"><div class="input-group input-group-sm">
            <span class="input-group-text" style="width:76px"><?= $l ?></span>
            <select class="form-select" name="iq_mode[<?= $p ?>]"><option value="none"<?= sel('none', $iq['mode']) ?>>No iqamah</option><option value="offset"<?= sel('offset', $iq['mode']) ?>>Minutes after adhan</option><option value="fixed"<?= sel('fixed', $iq['mode']) ?>>Fixed time</option></select>
            <input class="form-control" name="iq_val[<?= $p ?>]" value="<?= e($iq['value']) ?>" placeholder="15 or 13:30" style="max-width:90px">
          </div></div>
          <?php endforeach; ?>
        </div>
        <div class="row g-3 mt-2">
          <div class="col-sm-4"><label class="form-label small">From</label><input type="date" class="form-control" name="from" value="<?= e($from) ?>" required></div>
          <div class="col-sm-4"><label class="form-label small">To (max. 1 year)</label><input type="date" class="form-control" name="to" value="<?= e($to) ?>" required></div>
        </div>
      </div>
      <div class="card-footer"><button class="btn btn-outline-primary" name="action" value="calc_preview"><i class="bi bi-eye me-1"></i>Preview times</button></div>

      <?php if ($pv):
          $rows = calc_range_rows($o, $pv['from'], $pv['to']);
          $existing = prayer_month_rows($venueId, $pv['from'], $pv['to']);
          $stats = array('empty' => 0, 'calculated' => 0, 'manual' => 0);
          foreach ($rows as $date => $r) {
              if (!isset($existing[$date])) { $stats['empty']++; } elseif (prayer_source($meta, $date, $existing[$date]) === 'calculated') { $stats['calculated']++; } else { $stats['manual']++; }
          }
      ?>
      <div class="card-body border-top">
        <h3 class="h6">Preview: <?= e(fmt_date($pv['from'])) ?> – <?= e(fmt_date($pv['to'])) ?></h3>
        <p class="small mb-2"><span class="badge text-bg-success"><?= $stats['empty'] ?> days without times</span> <span class="badge text-bg-warning"><?= $stats['calculated'] ?> previously calculated</span> <span class="badge text-bg-primary"><?= $stats['manual'] ?> manual / imported / existing</span></p>
        <div class="table-responsive" style="max-height:420px">
          <table class="table table-sm small align-middle">
            <thead class="sticky-top bg-white"><tr><th>Date</th><th>Fajr</th><th>Sunrise</th><th>Dhuhr</th><th>Asr</th><th>Maghrib</th><th>Isha</th><th>On file now</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $date => $r): $ex = isset($existing[$date]) ? $existing[$date] : null; $src = prayer_source($meta, $date, $ex); ?>
              <tr class="<?= $ex ? ($src === 'calculated' ? '' : 'table-light text-muted') : '' ?>">
                <td class="text-nowrap"><?= e(fmt_date($date, 'D j M')) ?></td>
                <?php foreach (array('fajr', 'sunrise', 'dhuhr', 'asr', 'maghrib', 'isha') as $p): $az = $p === 'sunrise' ? $r['sunrise'] : $r[$p . '_azan']; $iq = $p === 'sunrise' ? '' : $r[$p . '_iqama']; ?>
                <td><?= e(pt_ampm($az)) ?><?= $iq !== '' ? '<br><span class="text-muted">' . e(pt_ampm($iq)) . '</span>' : '' ?></td>
                <?php endforeach; ?>
                <td><?= $ex ? e((isset($sourceLabels[$src]) ? $sourceLabels[$src][0] : 'Existing') . ': ' . fmt_time($ex['fajr_azan']) . ' / ' . fmt_time($ex['maghrib_azan'])) : '<span class="text-success">empty</span>' ?></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <fieldset class="mt-3">
          <legend class="h6">Save</legend>
          <div class="form-check"><input class="form-check-input" type="radio" name="save_mode" id="sm1" value="empty" checked><label class="form-check-label" for="sm1">Only fill the <?= $stats['empty'] ?> days without times <span class="text-muted">(recommended)</span></label></div>
          <div class="form-check"><input class="form-check-input" type="radio" name="save_mode" id="sm2" value="calculated"><label class="form-check-label" for="sm2">Fill empty days and update the <?= $stats['calculated'] ?> previously calculated days</label></div>
          <div class="form-check"><input class="form-check-input" type="radio" name="save_mode" id="sm3" value="all" data-needs-word><label class="form-check-label" for="sm3">Replace every day, including <?= $stats['manual'] ?> manually confirmed days</label></div>
          <div class="mt-2" data-word-box hidden><input class="form-control form-control-sm" style="max-width:260px" name="confirm_word" placeholder="Type REPLACE to confirm" autocomplete="off"></div>
        </fieldset>
      </div>
      <div class="card-footer"><button class="btn btn-primary" name="action" value="calc_save" data-confirm-click="Save the calculated times?"><i class="bi bi-check2 me-1"></i>Save calculated times</button></div>
      <?php endif; ?>
    </form>

    <?php elseif ($tab === 'export'): ?>
    <form method="get" class="card bo-card" style="max-width:640px">
      <?php if ($area === 'admin'): ?><input type="hidden" name="venue" value="<?= $venueId ?>"><?php endif; ?>
      <input type="hidden" name="export" value="data">
      <div class="card-header"><h2 class="h6 m-0"><i class="bi bi-download me-1"></i> Export prayer times (CSV)</h2></div>
      <div class="card-body row g-3">
        <div class="col-sm-6"><label class="form-label small">From</label><input type="date" class="form-control" name="from" value="<?= e(date('Y-m-01', strtotime($today))) ?>" required></div>
        <div class="col-sm-6"><label class="form-label small">To</label><input type="date" class="form-control" name="to" value="<?= e(date('Y-12-31', strtotime($today))) ?>" required></div>
        <p class="small text-muted mb-0">The file uses the same columns as the bulk upload, so you can edit it in Excel and upload it again.</p>
      </div>
      <div class="card-footer"><button class="btn btn-primary">Download CSV</button></div>
    </form>
    <?php endif;
}

// ---------------------------------------------------------------------------
// Jumu'ah
// ---------------------------------------------------------------------------
function jummah_handle($area, $venueId, $self)
{
    if (!is_post()) {
        return;
    }
    require_post_csrf();
    $venueId = (int)$venueId;
    $action = post('action');
    $back = $self . ($area === 'admin' ? '?venue=' . $venueId : '');
    try {
        if ($action === 'save') {
            $id = post_int('id');
            $from = post('from_date');
            $to = post('to_date') !== '' ? post('to_date') : $from;
            $khutbah = to_hm(post('azan_time'));
            $salah = to_hm(post('iqama_time'));
            if (!valid_ymd($from) || !valid_ymd($to) || $from > $to) {
                throw new InvalidArgumentException('Choose valid dates (the end date can\'t be before the start date).');
            }
            if ($khutbah === null || $salah === null || ($khutbah === '' && $salah === '')) {
                throw new InvalidArgumentException('Enter the khutbah and/or salah time.');
            }
            $place = mb_substr(post('place'), 0, 255, 'UTF-8');
            $imam = mb_substr(post('imam_name'), 0, 255, 'UTF-8');
            $type = post('type') === 'auto' || post('type') === '' ? (date('I', strtotime($from . ' 12:00')) === '1' ? 0 : 1) : (post_int('type') === 0 ? 0 : 1);
            $data = array('from_date' => $from, 'to_date' => $to, 'azan_time' => ($khutbah ?: '00:00') . ':00', 'iqama_time' => ($salah ?: '00:00') . ':00', 'place' => $place, 'imam_name' => $imam, 'type' => $type);
            if ($id) {
                $old = db_one('SELECT * FROM th_jummah_prayers WHERE id = ? AND venue_id = ?', 'ii', array($id, $venueId));
                if (!$old) {
                    throw new InvalidArgumentException('Session not found.');
                }
                db_exec('UPDATE th_jummah_prayers SET from_date = ?, to_date = ?, azan_time = ?, iqama_time = ?, place = ?, imam_name = ?, type = ? WHERE id = ? AND venue_id = ?',
                    'ssssssiii', array($from, $to, $data['azan_time'], $data['iqama_time'], $place, $imam, $type, $id, $venueId));
                log_me('jummah.update', 'th_jummah_prayers', $id, $old, $data);
                flash('success', 'Jumu\'ah session updated.');
            } else {
                db_exec('INSERT INTO th_jummah_prayers (venue_id, from_date, to_date, azan_time, iqama_time, place, imam_name, type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    'issssssi', array($venueId, $from, $to, $data['azan_time'], $data['iqama_time'], $place, $imam, $type));
                log_me('jummah.create', 'th_jummah_prayers', db_last_id(), null, $data);
                flash('success', 'Jumu\'ah session added.');
            }
        } elseif ($action === 'delete') {
            $id = post_int('id');
            $old = db_one('SELECT * FROM th_jummah_prayers WHERE id = ? AND venue_id = ?', 'ii', array($id, $venueId));
            if ($old) {
                db_exec('DELETE FROM th_jummah_prayers WHERE id = ? AND venue_id = ?', 'ii', array($id, $venueId));
                log_me('jummah.delete', 'th_jummah_prayers', $id, $old, null);
                flash('success', 'Jumu\'ah session deleted.');
            }
        } elseif ($action === 'copy_season') {
            $ids = post_ids('ids');
            $from = post('from_date');
            $to = post('to_date');
            if (!$ids || !valid_ymd($from) || !valid_ymd($to) || $from > $to) {
                throw new InvalidArgumentException('Select sessions and a valid new date range.');
            }
            list($ph, $t, $vals) = db_in_ints($ids);
            $rows = db_all("SELECT * FROM th_jummah_prayers WHERE venue_id = ? AND id IN ($ph)", 'i' . $t, array_merge(array($venueId), $vals));
            $type = date('I', strtotime($from . ' 12:00')) === '1' ? 0 : 1;
            foreach ($rows as $r) {
                db_exec('INSERT INTO th_jummah_prayers (venue_id, from_date, to_date, azan_time, iqama_time, place, imam_name, type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    'issssssi', array($venueId, $from, $to, $r['azan_time'], $r['iqama_time'], $r['place'], $r['imam_name'], $type));
            }
            log_me('jummah.copy', 'th_jummah_prayers', $venueId, null, array('ids' => $ids, 'from' => $from, 'to' => $to));
            flash('success', 'Copied ' . plural(count($rows), 'session') . ' to ' . fmt_date($from) . ' – ' . fmt_date($to) . '.');
        }
    } catch (InvalidArgumentException $e) {
        flash('danger', $e->getMessage());
    }
    header('Location: ' . $back, true, 303);
    exit;
}

function jummah_manager($area, $venueId, $self)
{
    $venueId = (int)$venueId;
    $v = get_venue_any($venueId);
    $tz = venue_timezone($v);
    $today = today_in($tz);
    $show = str_param('show', 10) === 'all' ? 'all' : 'current';
    $sql = 'SELECT * FROM th_jummah_prayers WHERE venue_id = ?' . ($show === 'current' ? ' AND to_date >= ?' : '') . ' ORDER BY from_date DESC, iqama_time, id LIMIT 300';
    $rows = $show === 'current' ? db_all($sql, 'is', array($venueId, $today)) : db_all($sql, 'i', array($venueId));
    $edit = isset($_GET['edit']) ? db_one('SELECT * FROM th_jummah_prayers WHERE id = ? AND venue_id = ?', 'ii', array(int_param('edit'), $venueId)) : null;
    $nextFri = date('Y-m-d', strtotime('friday', strtotime($today)));
    $q = function ($extra) use ($area, $venueId) { return '?' . http_build_query(array_filter(array_merge(array('venue' => $area === 'admin' ? $venueId : null), $extra))); };
    ?>
    <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
      <div><div class="text-muted small">Jumu'ah schedule for</div><div class="fw-semibold fs-5"><?= e(trim($v['venue_title'])) ?> <span class="text-muted fw-normal fs-6">· <?= e(venue_place_line($v)) ?></span></div></div>
    </div>
    <div class="row g-4">
      <div class="col-xl-5">
        <form method="post" class="card bo-card">
          <?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= $edit ? (int)$edit['id'] : 0 ?>">
          <div class="card-header"><h2 class="h6 m-0"><?= $edit ? 'Edit session' : 'Add a Jumu\'ah session' ?></h2></div>
          <div class="card-body row g-3">
            <div class="col-sm-6"><label class="form-label small">Valid from</label><input type="date" class="form-control" name="from_date" value="<?= e($edit ? $edit['from_date'] : $nextFri) ?>" required></div>
            <div class="col-sm-6"><label class="form-label small">Valid until</label><input type="date" class="form-control" name="to_date" value="<?= e($edit ? $edit['to_date'] : '') ?>"><div class="form-text">Leave empty for a single Friday. A range repeats every Friday.</div></div>
            <div class="col-sm-6"><label class="form-label small">Khutbah time</label><input type="time" class="form-control" name="azan_time" value="<?= e($edit ? (string)to_hm($edit['azan_time']) : '') ?>"></div>
            <div class="col-sm-6"><label class="form-label small">Salah (iqamah) time</label><input type="time" class="form-control" name="iqama_time" value="<?= e($edit ? (string)to_hm($edit['iqama_time']) : '') ?>"></div>
            <div class="col-12"><label class="form-label small">Imam / Khateeb</label><input class="form-control" name="imam_name" maxlength="255" value="<?= e($edit ? $edit['imam_name'] : '') ?>"></div>
            <div class="col-12"><label class="form-label small">Location, topic or notes</label><input class="form-control" name="place" maxlength="255" value="<?= e($edit ? $edit['place'] : '') ?>" placeholder="e.g. Main hall · Khutbah in English"></div>
            <div class="col-12"><label class="form-label small">Season</label>
              <select class="form-select" name="type"><option value="auto">Detect from the start date</option><option value="0"<?= $edit && (int)$edit['type'] === 0 ? ' selected' : '' ?>>Daylight-saving (summer) time</option><option value="1"<?= $edit && (int)$edit['type'] === 1 ? ' selected' : '' ?>>Standard (winter) time</option></select></div>
          </div>
          <div class="card-footer d-flex gap-2"><button class="btn btn-primary"><?= $edit ? 'Save changes' : 'Add session' ?></button><?php if ($edit): ?><a class="btn btn-light" href="<?= e($self . $q(array())) ?>">Cancel</a><?php endif; ?></div>
        </form>
      </div>
      <div class="col-xl-7">
        <form method="post" class="card bo-card" data-confirm="Copy the selected sessions to the new dates?">
          <?= csrf_field() ?><input type="hidden" name="action" value="copy_season">
          <div class="card-header d-flex align-items-center flex-wrap gap-2">
            <h2 class="h6 m-0">Sessions</h2>
            <div class="btn-group btn-group-sm ms-auto">
              <a class="btn btn-<?= $show === 'current' ? 'primary' : 'outline-primary' ?>" href="<?= e($self . $q(array())) ?>">Current &amp; upcoming</a>
              <a class="btn btn-<?= $show === 'all' ? 'primary' : 'outline-primary' ?>" href="<?= e($self . $q(array('show' => 'all'))) ?>">All</a>
            </div>
          </div>
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead><tr><th style="width:32px"></th><th>Valid</th><th>Khutbah</th><th>Salah</th><th>Khateeb / notes</th><th></th></tr></thead>
              <tbody>
              <?php if (!$rows): ?><tr><td colspan="6" class="text-center text-muted py-4">No sessions<?= $show === 'current' ? ' for the current dates' : '' ?>.</td></tr><?php endif; ?>
              <?php foreach ($rows as $r): $cur = $r['from_date'] <= $today && $r['to_date'] >= $today; ?>
                <tr<?= $cur ? ' class="table-success"' : '' ?>>
                  <td><input class="form-check-input" type="checkbox" name="ids[]" value="<?= (int)$r['id'] ?>" aria-label="Select session"></td>
                  <td class="small text-nowrap"><?= e(fmt_date($r['from_date'])) ?><?= $r['to_date'] !== $r['from_date'] ? '<br>– ' . e(fmt_date($r['to_date'])) : '' ?><br><span class="text-muted"><?= (int)$r['type'] === 0 ? 'Summer' : 'Winter' ?></span></td>
                  <td><?= e(fmt_time($r['azan_time']) ?: '—') ?></td>
                  <td class="fw-semibold"><?= e(fmt_time($r['iqama_time']) ?: '—') ?></td>
                  <td class="small"><?= e(trim($r['imam_name'] . ($r['imam_name'] !== '' && $r['place'] !== '' ? ' · ' : '') . $r['place'])) ?></td>
                  <td class="text-end text-nowrap">
                    <a class="btn btn-sm btn-light" href="<?= e($self . $q(array('edit' => (int)$r['id'], 'show' => $show === 'all' ? 'all' : null))) ?>" aria-label="Edit"><i class="bi bi-pencil"></i></a>
                    <button class="btn btn-sm btn-light text-danger" type="button" data-post-action='<?= e(json_encode(array('action' => 'delete', 'id' => (int)$r['id']))) ?>' data-confirm-text="Delete this Jumu'ah session?" aria-label="Delete"><i class="bi bi-trash"></i></button>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?php if ($rows): ?>
          <div class="card-footer">
            <div class="row g-2 align-items-end">
              <div class="col-12 small text-muted">Copy selected sessions to a new season:</div>
              <div class="col-sm-4"><input type="date" class="form-control form-control-sm" name="from_date" aria-label="New start date"></div>
              <div class="col-sm-4"><input type="date" class="form-control form-control-sm" name="to_date" aria-label="New end date"></div>
              <div class="col-sm-4"><button class="btn btn-sm btn-outline-primary w-100">Copy to dates</button></div>
            </div>
          </div>
          <?php endif; ?>
        </form>
      </div>
    </div>
<?php
}
