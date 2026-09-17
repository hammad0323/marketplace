<?php
/**
 * functions.php — every reusable procedural helper, grouped by topic.
 * All function names are prefixed wh_ to avoid collisions.
 * Uses the global mysqli connection $conn created in config.php.
 */

// =======================================================================
// Generic / output helpers
// =======================================================================

function e($value)
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function wh_redirect($url)
{
    header('Location: ' . $url);
    exit;
}

function wh_slugify($text)
{
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = trim($text, '-');
    if (function_exists('iconv')) {
        $converted = @iconv('utf-8', 'ascii//TRANSLIT', $text);
        if ($converted !== false) {
            $text = $converted;
        }
    }
    $text = strtolower($text);
    $text = preg_replace('~[^-a-z0-9]+~', '', $text);
    return $text === '' ? 'n-a' : $text;
}

function wh_format_money($amount)
{
    return 'PKR ' . number_format((float) $amount, 0);
}

function wh_format_date($date, $format = 'd M Y')
{
    if (empty($date) || $date === '0000-00-00') {
        return '';
    }
    $ts = is_numeric($date) ? (int) $date : strtotime($date);
    return $ts ? date($format, $ts) : '';
}

function wh_format_time($time, $format = 'g:i A')
{
    if (empty($time)) {
        return '';
    }
    $ts = strtotime($time);
    return $ts ? date($format, $ts) : '';
}

function wh_flash_set($type, $message)
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function wh_flash_get()
{
    $flash = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flash;
}

function wh_input_post($key, $default = '')
{
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
}

function wh_input_get($key, $default = '')
{
    return isset($_GET[$key]) ? trim((string) $_GET[$key]) : $default;
}

function wh_decimal($value)
{
    $value = (float) str_replace(',', '', (string) $value);
    return round($value, 2);
}

// =======================================================================
// CSRF protection
// =======================================================================

function wh_csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function wh_csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . e(wh_csrf_token()) . '">';
}

function wh_csrf_verify()
{
    $token = $_POST['csrf_token'] ?? ($_GET['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
    if (!$token || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        exit('Invalid or expired form submission. Please go back and try again.');
    }
    return true;
}

// =======================================================================
// Database helpers (thin mysqli wrappers using prepared statements)
// =======================================================================

/**
 * Run a prepared statement. $types is the mysqli bind_param type string,
 * e.g. 'sdi'. Pass [] / '' when there are no parameters.
 */
function wh_stmt($sql, $types = '', array $params = [])
{
    global $conn;
    $stmt = mysqli_prepare($conn, $sql);
    if ($stmt === false) {
        error_log('SQL prepare failed: ' . mysqli_error($conn) . ' | ' . $sql);
        return false;
    }
    if ($types !== '' && $params) {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    return $stmt;
}

function wh_fetch_all($sql, $types = '', array $params = [])
{
    $stmt = wh_stmt($sql, $types, $params);
    if (!$stmt) {
        return [];
    }
    $result = mysqli_stmt_get_result($stmt);
    $rows = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
    mysqli_stmt_close($stmt);
    return $rows;
}

function wh_fetch_one($sql, $types = '', array $params = [])
{
    $rows = wh_fetch_all($sql, $types, $params);
    return $rows[0] ?? null;
}

function wh_execute($sql, $types = '', array $params = [])
{
    global $conn;
    $stmt = wh_stmt($sql, $types, $params);
    if (!$stmt) {
        return false;
    }
    $ok = mysqli_stmt_affected_rows($stmt) !== -1;
    $insertId = mysqli_stmt_insert_id($stmt);
    mysqli_stmt_close($stmt);
    return $ok ? ($insertId ?: true) : false;
}

/**
 * Infers a mysqli bind_param type char from a PHP value. Used by
 * wh_insert()/wh_update() so call sites never hand-count type strings
 * (a manual 'iissd...' string is an easy, hard-to-spot source of bugs).
 */
function wh_infer_type($value)
{
    if (is_int($value)) {
        return 'i';
    }
    if (is_float($value)) {
        return 'd';
    }
    return 's'; // strings, null, bool — mysqli sends NULL regardless of the type char
}

/**
 * Generic INSERT helper: wh_insert('halls', ['name' => $name, 'city' => $city, ...]).
 * Column names must be literal keys written by the calling code, never
 * user-controlled strings, since they're interpolated into the SQL.
 */
function wh_insert($table, array $data)
{
    $cols = array_keys($data);
    $placeholders = implode(',', array_fill(0, count($cols), '?'));
    $types = '';
    $values = [];
    foreach ($data as $v) {
        $types .= wh_infer_type($v);
        $values[] = $v;
    }
    $sql = "INSERT INTO {$table} (" . implode(',', $cols) . ") VALUES ({$placeholders})";
    return wh_execute($sql, $types, $values);
}

/**
 * Generic UPDATE helper:
 * wh_update('halls', ['name' => $name], 'id = ? AND business_id = ?', [$id, $businessId]);
 */
function wh_update($table, array $data, $whereSql, array $whereParams = [])
{
    $sets = [];
    $types = '';
    $values = [];
    foreach ($data as $col => $v) {
        $sets[] = "{$col} = ?";
        $types .= wh_infer_type($v);
        $values[] = $v;
    }
    foreach ($whereParams as $wp) {
        $types .= wh_infer_type($wp);
        $values[] = $wp;
    }
    $sql = "UPDATE {$table} SET " . implode(',', $sets) . " WHERE {$whereSql}";
    return wh_execute($sql, $types, $values);
}

// =======================================================================
// Business / tenant context
// =======================================================================

function wh_current_business_id()
{
    if (!empty($_SESSION['admin_business_id'])) {
        return (int) $_SESSION['admin_business_id'];
    }
    return DEFAULT_BUSINESS_ID;
}

// =======================================================================
// Settings (key/value store per business)
// =======================================================================

function wh_get_settings($businessId = null)
{
    static $cache = [];
    $businessId = $businessId ?: wh_current_business_id();
    if (isset($cache[$businessId])) {
        return $cache[$businessId];
    }
    $rows = wh_fetch_all('SELECT setting_key, setting_value FROM settings WHERE business_id = ?', 'i', [$businessId]);
    $out = [];
    foreach ($rows as $row) {
        $out[$row['setting_key']] = $row['setting_value'];
    }
    $cache[$businessId] = $out;
    return $out;
}

function wh_get_setting($key, $default = '', $businessId = null)
{
    $settings = wh_get_settings($businessId);
    return $settings[$key] ?? $default;
}

function wh_setting_bool($key, $default = false, $businessId = null)
{
    $value = wh_get_setting($key, $default ? '1' : '0', $businessId);
    return $value === '1' || $value === 1 || $value === true;
}

function wh_set_setting($key, $value, $businessId = null)
{
    $businessId = $businessId ?: wh_current_business_id();
    return wh_execute(
        'INSERT INTO settings (business_id, setting_key, setting_value) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        'iss',
        [$businessId, $key, $value]
    );
}

// =======================================================================
// Halls
// =======================================================================

function wh_get_halls($businessId = null, $publicOnly = false)
{
    $businessId = $businessId ?: wh_current_business_id();
    $sql = 'SELECT * FROM halls WHERE business_id = ?';
    if ($publicOnly) {
        $sql .= " AND status = 'active' AND is_public = 1";
    }
    $sql .= ' ORDER BY sort_order ASC, name ASC';
    return wh_fetch_all($sql, 'i', [$businessId]);
}

function wh_get_hall($id, $businessId = null)
{
    $businessId = $businessId ?: wh_current_business_id();
    return wh_fetch_one('SELECT * FROM halls WHERE id = ? AND business_id = ?', 'ii', [$id, $businessId]);
}

function wh_get_hall_by_slug($slug, $businessId = null)
{
    $businessId = $businessId ?: wh_current_business_id();
    return wh_fetch_one('SELECT * FROM halls WHERE slug = ? AND business_id = ?', 'si', [$slug, $businessId]);
}

function wh_hall_facilities($hallId)
{
    return wh_fetch_all('SELECT * FROM hall_facilities WHERE hall_id = ? ORDER BY sort_order ASC', 'i', [$hallId]);
}

function wh_hall_images($hallId)
{
    return wh_fetch_all('SELECT * FROM hall_images WHERE hall_id = ? ORDER BY is_featured DESC, sort_order ASC', 'i', [$hallId]);
}

function wh_unique_hall_slug($name, $businessId, $ignoreId = 0)
{
    $base = wh_slugify($name);
    $slug = $base;
    $i = 1;
    while (true) {
        $exists = wh_fetch_one(
            'SELECT id FROM halls WHERE business_id = ? AND slug = ? AND id != ?',
            'isi',
            [$businessId, $slug, $ignoreId]
        );
        if (!$exists) {
            return $slug;
        }
        $i++;
        $slug = $base . '-' . $i;
    }
}

// =======================================================================
// Time slots & event types
// =======================================================================

function wh_get_time_slots($businessId = null, $activeOnly = true)
{
    $businessId = $businessId ?: wh_current_business_id();
    $sql = 'SELECT * FROM time_slots WHERE business_id = ?';
    if ($activeOnly) {
        $sql .= " AND status = 'active'";
    }
    $sql .= ' ORDER BY sort_order ASC, start_time ASC';
    return wh_fetch_all($sql, 'i', [$businessId]);
}

function wh_get_time_slot($id, $businessId = null)
{
    $businessId = $businessId ?: wh_current_business_id();
    return wh_fetch_one('SELECT * FROM time_slots WHERE id = ? AND business_id = ?', 'ii', [$id, $businessId]);
}

function wh_get_event_types($businessId = null, $activeOnly = true)
{
    $businessId = $businessId ?: wh_current_business_id();
    $sql = 'SELECT * FROM event_types WHERE business_id = ?';
    if ($activeOnly) {
        $sql .= " AND status = 'active'";
    }
    $sql .= ' ORDER BY sort_order ASC, name ASC';
    return wh_fetch_all($sql, 'i', [$businessId]);
}

// =======================================================================
// Availability — the core booking-conflict logic
// =======================================================================

/**
 * Statuses that currently occupy a slot (hold it in slot_locks).
 */
function wh_locking_statuses()
{
    return ['confirmed', 'hold', 'completed'];
}

/**
 * Whether a *new* booking in the given status should immediately write a
 * slot_locks row. Pending only locks if the business turned that setting on.
 */
function wh_status_should_lock($status, $businessId = null)
{
    if (in_array($status, wh_locking_statuses(), true)) {
        return true;
    }
    if ($status === 'pending') {
        return wh_setting_bool('hold_pending_slots', true, $businessId);
    }
    return false; // cancelled never locks
}

/**
 * Availability matrix for one date across all halls (or a single hall).
 * Returns: [hall_id => [time_slot_id => 'available'|'booked']]
 */
function wh_availability_matrix($businessId, $date, $hallId = null)
{
    $halls = $hallId ? [wh_get_hall($hallId, $businessId)] : wh_get_halls($businessId);
    $halls = array_filter($halls);
    $slots = wh_get_time_slots($businessId);

    $locked = wh_fetch_all(
        'SELECT hall_id, time_slot_id FROM slot_locks WHERE business_id = ? AND booking_date = ?',
        'is',
        [$businessId, $date]
    );
    $lockedSet = [];
    foreach ($locked as $row) {
        $lockedSet[$row['hall_id']][$row['time_slot_id']] = true;
    }

    $matrix = [];
    foreach ($halls as $hall) {
        foreach ($slots as $slot) {
            $matrix[$hall['id']][$slot['id']] = isset($lockedSet[$hall['id']][$slot['id']]) ? 'booked' : 'available';
        }
    }
    return $matrix;
}

function wh_is_slot_available($businessId, $hallId, $date, $timeSlotId)
{
    $row = wh_fetch_one(
        'SELECT id FROM slot_locks WHERE business_id = ? AND hall_id = ? AND booking_date = ? AND time_slot_id = ?',
        'iisi',
        [$businessId, $hallId, $date, $timeSlotId]
    );
    return $row === null;
}

/**
 * Month calendar summary for the dashboard/admin calendar.
 * Returns [ 'YYYY-MM-DD' => ['total_slots'=>n,'booked_slots'=>n] ]
 */
function wh_month_calendar_summary($businessId, $year, $month, $hallId = null)
{
    $totalSlots = count(wh_get_time_slots($businessId));
    $totalHalls = $hallId ? 1 : count(wh_get_halls($businessId));
    $slotsPerDay = max(1, $totalSlots * $totalHalls);

    $start = sprintf('%04d-%02d-01', $year, $month);
    $end = date('Y-m-t', strtotime($start));

    $sql = 'SELECT booking_date, COUNT(*) AS booked FROM slot_locks WHERE business_id = ? AND booking_date BETWEEN ? AND ?';
    $types = 'iss';
    $params = [$businessId, $start, $end];
    if ($hallId) {
        $sql .= ' AND hall_id = ?';
        $types .= 'i';
        $params[] = $hallId;
    }
    $sql .= ' GROUP BY booking_date';

    $rows = wh_fetch_all($sql, $types, $params);
    $summary = [];
    foreach ($rows as $row) {
        $booked = (int) $row['booked'];
        $summary[$row['booking_date']] = [
            'booked_slots' => $booked,
            'total_slots' => $slotsPerDay,
            'state' => $booked >= $slotsPerDay ? 'full' : ($booked > 0 ? 'partial' : 'available'),
        ];
    }
    return $summary;
}

// =======================================================================
// Customers
// =======================================================================

function wh_find_or_create_customer($businessId, $data)
{
    $existing = wh_fetch_one(
        'SELECT id FROM customers WHERE business_id = ? AND phone = ? LIMIT 1',
        'is',
        [$businessId, $data['phone']]
    );
    if ($existing) {
        wh_execute(
            'UPDATE customers SET name = ?, whatsapp = ?, email = ?, address = ? WHERE id = ?',
            'ssssi',
            [$data['name'], $data['whatsapp'] ?? $data['phone'], $data['email'] ?? '', $data['address'] ?? '', $existing['id']]
        );
        return (int) $existing['id'];
    }
    $id = wh_execute(
        'INSERT INTO customers (business_id, name, father_husband_name, phone, whatsapp, email, address, cnic)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        'isssssss',
        [
            $businessId,
            $data['name'],
            $data['father_husband_name'] ?? null,
            $data['phone'],
            $data['whatsapp'] ?? $data['phone'],
            $data['email'] ?? null,
            $data['address'] ?? null,
            $data['cnic'] ?? null,
        ]
    );
    return (int) $id;
}

// =======================================================================
// Bookings — conflict-safe create + status transitions
// =======================================================================

function wh_generate_booking_code($businessId, $eventDate = null)
{
    $year = $eventDate ? date('Y', strtotime($eventDate)) : date('Y');
    $row = wh_fetch_one(
        "SELECT COUNT(*) AS c FROM bookings WHERE business_id = ? AND booking_code LIKE ?",
        'is',
        [$businessId, 'WH-' . $year . '-%']
    );
    $next = ((int) ($row['c'] ?? 0)) + 1;
    do {
        $code = sprintf('WH-%s-%06d', $year, $next);
        $exists = wh_fetch_one('SELECT id FROM bookings WHERE booking_code = ?', 's', [$code]);
        $next++;
    } while ($exists);
    return $code;
}

/**
 * Create a booking. Returns ['ok'=>true,'booking_id'=>..,'booking_code'=>..]
 * or ['ok'=>false,'error'=>'conflict'|'invalid'] on failure.
 * This is the single choke point that guarantees Hall+Date+TimeSlot
 * uniqueness even under a simultaneous double-submit, because the
 * uniqueness is enforced by slot_locks' UNIQUE KEY at the database level
 * inside a transaction, not by a prior SELECT check alone.
 */
function wh_create_booking(array $data)
{
    global $conn;
    $businessId = (int) $data['business_id'];

    $hall = wh_get_hall($data['hall_id'], $businessId);
    $slot = wh_get_time_slot($data['time_slot_id'], $businessId);
    if (!$hall || !$slot || empty($data['booking_date']) || empty($data['customer_id'])) {
        return ['ok' => false, 'error' => 'invalid'];
    }

    $status = $data['booking_status'] ?? 'pending';
    $bookingCode = wh_generate_booking_code($businessId, $data['booking_date']);

    mysqli_begin_transaction($conn);
    try {
        $insertId = wh_execute(
            'INSERT INTO bookings
                (business_id, booking_code, hall_id, customer_id, event_type_id, time_slot_id, booking_date,
                 guests, package_name, per_person_price, total_amount, additional_charges, discount, final_total,
                 advance_required, paid_amount, balance, booking_status, payment_status, source, next_payment_date,
                 notes, created_by)
             VALUES (?,?,?,?,?,?,?, ?,?,?,?,?,?,?, ?,0,?, ?,?,?,?, ?,?)',
            'isiiiis' . 'isddddd' . 'dd' . 'ssss' . 'si',
            [
                $businessId, $bookingCode, $data['hall_id'], $data['customer_id'],
                $data['event_type_id'] ?: null, $data['time_slot_id'], $data['booking_date'],
                $data['guests'] ?? 0, $data['package_name'] ?? null, $data['per_person_price'] ?? 0,
                $data['total_amount'] ?? 0, $data['additional_charges'] ?? 0, $data['discount'] ?? 0,
                $data['final_total'] ?? 0,
                $data['advance_required'] ?? 0, $data['final_total'] ?? 0,
                $status, 'unpaid', $data['source'] ?? 'admin', (!empty($data['next_payment_date']) ? $data['next_payment_date'] : null),
                $data['notes'] ?? null, (!empty($data['created_by']) ? $data['created_by'] : null),
            ]
        );

        if (!$insertId) {
            throw new RuntimeException('insert_failed');
        }

        if (wh_status_should_lock($status, $businessId)) {
            $lockOk = wh_execute(
                'INSERT INTO slot_locks (business_id, hall_id, booking_date, time_slot_id, booking_id) VALUES (?,?,?,?,?)',
                'iisii',
                [$businessId, $data['hall_id'], $data['booking_date'], $data['time_slot_id'], $insertId]
            );
            if (!$lockOk) {
                throw new RuntimeException('conflict');
            }
        }

        mysqli_commit($conn);
        return ['ok' => true, 'booking_id' => (int) $insertId, 'booking_code' => $bookingCode];
    } catch (Throwable $ex) {
        mysqli_rollback($conn);
        $isDup = mysqli_errno($conn) === 1062 || $ex->getMessage() === 'conflict';
        return ['ok' => false, 'error' => $isDup ? 'conflict' : 'invalid'];
    }
}

function wh_get_booking($id, $businessId = null)
{
    $businessId = $businessId ?: wh_current_business_id();
    return wh_fetch_one(
        'SELECT b.*, h.name AS hall_name, h.slug AS hall_slug, c.name AS customer_name, c.phone AS customer_phone,
                c.whatsapp AS customer_whatsapp, c.email AS customer_email, c.address AS customer_address,
                c.cnic AS customer_cnic, c.father_husband_name,
                ts.name AS slot_name, ts.start_time, ts.end_time, et.name AS event_type_name
         FROM bookings b
         JOIN halls h ON h.id = b.hall_id
         JOIN customers c ON c.id = b.customer_id
         JOIN time_slots ts ON ts.id = b.time_slot_id
         LEFT JOIN event_types et ON et.id = b.event_type_id
         WHERE b.id = ? AND b.business_id = ?',
        'ii',
        [$id, $businessId]
    );
}

/**
 * Change a booking's status, keeping slot_locks in sync (release on
 * cancel, re-acquire on reconfirm — with conflict detection).
 */
function wh_set_booking_status($bookingId, $newStatus, $businessId = null, $adminUserId = null)
{
    global $conn;
    $businessId = $businessId ?: wh_current_business_id();
    $booking = wh_get_booking($bookingId, $businessId);
    if (!$booking) {
        return ['ok' => false, 'error' => 'not_found'];
    }

    $wasLocked = wh_status_should_lock($booking['booking_status'], $businessId);
    $willLock = wh_status_should_lock($newStatus, $businessId);

    mysqli_begin_transaction($conn);
    try {
        if ($wasLocked && !$willLock) {
            wh_execute('DELETE FROM slot_locks WHERE booking_id = ?', 'i', [$bookingId]);
        } elseif (!$wasLocked && $willLock) {
            $lockOk = wh_execute(
                'INSERT INTO slot_locks (business_id, hall_id, booking_date, time_slot_id, booking_id) VALUES (?,?,?,?,?)',
                'iisii',
                [$businessId, $booking['hall_id'], $booking['booking_date'], $booking['time_slot_id'], $bookingId]
            );
            if (!$lockOk) {
                throw new RuntimeException('conflict');
            }
        }

        wh_execute('UPDATE bookings SET booking_status = ? WHERE id = ? AND business_id = ?', 'sii', [$newStatus, $bookingId, $businessId]);
        mysqli_commit($conn);

        wh_add_notification($businessId, 'booking_status', 'Booking ' . $booking['booking_code'] . ' → ' . ucfirst($newStatus),
            $booking['customer_name'] . ' / ' . $booking['hall_name'] . ' / ' . wh_format_date($booking['booking_date']),
            '/admin/booking-view.php?id=' . $bookingId);

        return ['ok' => true];
    } catch (Throwable $ex) {
        mysqli_rollback($conn);
        return ['ok' => false, 'error' => 'conflict'];
    }
}

// =======================================================================
// Payments
// =======================================================================

function wh_get_payments($bookingId)
{
    return wh_fetch_all('SELECT * FROM booking_payments WHERE booking_id = ? ORDER BY payment_date DESC, id DESC', 'i', [$bookingId]);
}

function wh_add_payment($businessId, $bookingId, $amount, $date, $method, $notes = null, $adminUserId = null)
{
    $ok = wh_execute(
        'INSERT INTO booking_payments (business_id, booking_id, amount, payment_date, payment_method, notes, created_by)
         VALUES (?,?,?,?,?,?,?)',
        'iidsssi',
        [$businessId, $bookingId, $amount, $date, $method, $notes, $adminUserId]
    );
    if ($ok) {
        wh_recalc_booking_payment($bookingId, $businessId);
        $booking = wh_get_booking($bookingId, $businessId);
        if ($booking) {
            wh_add_notification($businessId, 'payment', wh_format_money($amount) . ' received',
                $booking['customer_name'] . ' / ' . $booking['booking_code'], '/admin/booking-view.php?id=' . $bookingId);
        }
    }
    return $ok;
}

function wh_recalc_booking_payment($bookingId, $businessId = null)
{
    $businessId = $businessId ?: wh_current_business_id();
    $row = wh_fetch_one('SELECT COALESCE(SUM(amount),0) AS paid FROM booking_payments WHERE booking_id = ?', 'i', [$bookingId]);
    $paid = (float) ($row['paid'] ?? 0);
    $booking = wh_fetch_one('SELECT final_total, payment_status FROM bookings WHERE id = ?', 'i', [$bookingId]);
    if (!$booking) {
        return;
    }
    $final = (float) $booking['final_total'];
    $balance = max(0, $final - $paid);
    if ($booking['payment_status'] === 'refunded') {
        $status = 'refunded';
    } else {
        $status = $paid <= 0 ? 'unpaid' : ($paid >= $final ? 'paid' : 'partial');
    }
    wh_execute(
        'UPDATE bookings SET paid_amount = ?, balance = ?, payment_status = ? WHERE id = ? AND business_id = ?',
        'ddsii',
        [$paid, $balance, $status, $bookingId, $businessId]
    );
}

// =======================================================================
// File uploads
// =======================================================================

function wh_handle_image_upload($fileField, $subDir, $prefix = 'img')
{
    if (empty($_FILES[$fileField]) || $_FILES[$fileField]['error'] === UPLOAD_ERR_NO_FILE) {
        return null; // optional upload, nothing submitted
    }
    $file = $_FILES[$fileField];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    if ($file['size'] > MAX_UPLOAD_BYTES) {
        return false;
    }
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    if (!in_array($mime, ALLOWED_IMAGE_TYPES, true)) {
        return false;
    }
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime];
    $destDir = rtrim(UPLOAD_DIR, '/') . '/' . trim($subDir, '/');
    if (!is_dir($destDir)) {
        mkdir($destDir, 0755, true);
    }
    $filename = $prefix . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
    $destPath = $destDir . '/' . $filename;
    if (!move_uploaded_file($file['tmp_name'], $destPath)) {
        return false;
    }
    return 'uploads/' . trim($subDir, '/') . '/' . $filename;
}

// =======================================================================
// Notifications
// =======================================================================

function wh_add_notification($businessId, $type, $title, $message = null, $link = null)
{
    return wh_execute(
        'INSERT INTO notifications (business_id, type, title, message, link) VALUES (?,?,?,?,?)',
        'issss',
        [$businessId, $type, $title, $message, $link]
    );
}

function wh_unread_notifications($businessId, $limit = 10)
{
    return wh_fetch_all(
        'SELECT * FROM notifications WHERE business_id = ? ORDER BY id DESC LIMIT ?',
        'ii',
        [$businessId, $limit]
    );
}

function wh_unread_notification_count($businessId)
{
    $row = wh_fetch_one('SELECT COUNT(*) AS c FROM notifications WHERE business_id = ? AND is_read = 0', 'i', [$businessId]);
    return (int) ($row['c'] ?? 0);
}

function wh_mark_notifications_read($businessId)
{
    wh_execute('UPDATE notifications SET is_read = 1 WHERE business_id = ? AND is_read = 0', 'i', [$businessId]);
}

// =======================================================================
// Dashboard stats
// =======================================================================

function wh_dashboard_stats($businessId)
{
    $today = date('Y-m-d');
    $monthStart = date('Y-m-01');
    $monthEnd = date('Y-m-t');

    $stats = [];
    $stats['total_halls'] = (int) (wh_fetch_one('SELECT COUNT(*) c FROM halls WHERE business_id=?', 'i', [$businessId])['c'] ?? 0);
    $stats['total_bookings'] = (int) (wh_fetch_one("SELECT COUNT(*) c FROM bookings WHERE business_id=? AND booking_status != 'cancelled'", 'i', [$businessId])['c'] ?? 0);
    $stats['todays_bookings'] = (int) (wh_fetch_one('SELECT COUNT(*) c FROM bookings WHERE business_id=? AND booking_date=?', 'is', [$businessId, $today])['c'] ?? 0);
    $stats['upcoming_bookings'] = (int) (wh_fetch_one("SELECT COUNT(*) c FROM bookings WHERE business_id=? AND booking_date > ? AND booking_status IN ('pending','confirmed','hold')", 'is', [$businessId, $today])['c'] ?? 0);
    $stats['pending_payments'] = (float) (wh_fetch_one("SELECT COALESCE(SUM(balance),0) c FROM bookings WHERE business_id=? AND booking_status != 'cancelled'", 'i', [$businessId])['c'] ?? 0);
    $stats['total_revenue'] = (float) (wh_fetch_one("SELECT COALESCE(SUM(paid_amount),0) c FROM bookings WHERE business_id=? AND booking_status != 'cancelled'", 'i', [$businessId])['c'] ?? 0);
    $stats['advance_received'] = (float) (wh_fetch_one("SELECT COALESCE(SUM(amount),0) c FROM booking_payments WHERE business_id=? AND payment_date BETWEEN ? AND ?", 'iss', [$businessId, $monthStart, $monthEnd])['c'] ?? 0);
    $stats['month_bookings'] = (int) (wh_fetch_one("SELECT COUNT(*) c FROM bookings WHERE business_id=? AND booking_date BETWEEN ? AND ? AND booking_status != 'cancelled'", 'iss', [$businessId, $monthStart, $monthEnd])['c'] ?? 0);
    $stats['pending_requests'] = (int) (wh_fetch_one("SELECT COUNT(*) c FROM bookings WHERE business_id=? AND booking_status='pending'", 'i', [$businessId])['c'] ?? 0);

    return $stats;
}

function wh_upcoming_events($businessId, $limit = 5)
{
    return wh_fetch_all(
        "SELECT b.*, h.name AS hall_name, c.name AS customer_name, ts.name AS slot_name, et.name AS event_type_name
         FROM bookings b
         JOIN halls h ON h.id=b.hall_id JOIN customers c ON c.id=b.customer_id
         JOIN time_slots ts ON ts.id=b.time_slot_id LEFT JOIN event_types et ON et.id=b.event_type_id
         WHERE b.business_id=? AND b.booking_date >= CURDATE() AND b.booking_status IN ('pending','confirmed','hold')
         ORDER BY b.booking_date ASC LIMIT ?",
        'ii',
        [$businessId, $limit]
    );
}

// =======================================================================
// Pagination
// =======================================================================

function wh_paginate($totalRows, $perPage = 20)
{
    $page = max(1, (int) wh_input_get('page', 1));
    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    $page = min($page, $totalPages);
    return ['page' => $page, 'per_page' => $perPage, 'offset' => ($page - 1) * $perPage, 'total_pages' => $totalPages];
}
