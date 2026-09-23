<?php
/**
 * Shared helper library — sanitization, CSRF, flashes, uploads, formatting,
 * notifications, activity logging, and small DB query helpers. Included
 * once via config/config.php.
 */

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    http_response_code(403);
    exit('Direct access not permitted.');
}

/** @return mysqli */
function db()
{
    return $GLOBALS['db'];
}

// ---------------------------------------------------------------------------
// Input / output safety
// ---------------------------------------------------------------------------

function e($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function clean($value)
{
    return trim((string) $value);
}

function slugify($text)
{
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = trim($text, '-');
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text) ?: $text;
    $text = strtolower(preg_replace('~[^-\w]+~', '', $text));
    return $text ?: 'item';
}

function unique_slug(mysqli $db, string $table, string $base)
{
    $slug = slugify($base);
    $original = $slug;
    $i = 1;
    while (true) {
        $stmt = mysqli_prepare($db, "SELECT id FROM `$table` WHERE slug = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, 's', $slug);
        mysqli_stmt_execute($stmt);
        $exists = mysqli_stmt_get_result($stmt)->fetch_assoc();
        mysqli_stmt_close($stmt);
        if (!$exists) {
            return $slug;
        }
        $slug = $original . '-' . (++$i);
    }
}

// ---------------------------------------------------------------------------
// CSRF protection
// ---------------------------------------------------------------------------

function csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field()
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify($token)
{
    return !empty($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

function require_csrf_or_fail()
{
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (!csrf_verify($token)) {
        http_response_code(419);
        if (is_ajax_request()) {
            json_response(false, [], 'Your session expired. Please refresh the page and try again.');
        }
        exit('Security check failed. Please go back and try again.');
    }
}

function is_ajax_request()
{
    return (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest')
        || str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json');
}

function json_response($success, $data = [], $message = '')
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

// ---------------------------------------------------------------------------
// Flash messages / redirects
// ---------------------------------------------------------------------------

function flash_set($type, $message)
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_get()
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

function current_url()
{
    return $_SERVER['REQUEST_URI'] ?? '/';
}

// ---------------------------------------------------------------------------
// Rate limiting (login attempts)
// ---------------------------------------------------------------------------

function client_ip()
{
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

function record_login_attempt($identifier, $success)
{
    $db = db();
    $ip = client_ip();
    $stmt = mysqli_prepare($db, 'INSERT INTO login_attempts (identifier, ip_address, success) VALUES (?, ?, ?)');
    $successInt = $success ? 1 : 0;
    mysqli_stmt_bind_param($stmt, 'ssi', $identifier, $ip, $successInt);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function is_rate_limited($identifier, $maxAttempts = 5, $windowMinutes = 15)
{
    $db = db();
    $ip = client_ip();
    $stmt = mysqli_prepare($db, 'SELECT COUNT(*) AS c FROM login_attempts
        WHERE (identifier = ? OR ip_address = ?) AND success = 0
        AND attempted_at > (NOW() - INTERVAL ? MINUTE)');
    mysqli_stmt_bind_param($stmt, 'ssi', $identifier, $ip, $windowMinutes);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);
    return ((int) $row['c']) >= $maxAttempts;
}

// ---------------------------------------------------------------------------
// File uploads
// ---------------------------------------------------------------------------

/**
 * Downscales a just-uploaded raster image to fit within $maxWidth x
 * $maxHeight (aspect-ratio preserved, never upscaled) and re-encodes it as
 * WebP, replacing the original file in place. This is what actually fixes
 * "oversized image" / "use a modern format" audit warnings — uploads land
 * at whatever the source camera/screenshot size was (often 2000px+), while
 * every on-page use here is a small fixed box, so serving the original is
 * pure waste; WebP then shrinks it further at equivalent visual quality.
 * Non-raster uploads (SVG, ICO, PDF) or anything GD can't decode are left
 * untouched — this only ever narrows what a file *is*, never breaks it.
 * Returns the (possibly .webp) relative path to store/use.
 */
function optimize_uploaded_image($relativePath, $maxWidth, $maxHeight, $quality = 82)
{
    if (!function_exists('imagewebp')) {
        return $relativePath;
    }
    $absPath = UPLOAD_PATH . '/' . $relativePath;
    $info = @getimagesize($absPath);
    if (!$info) {
        return $relativePath;
    }
    [$width, $height, $type] = $info;
    if ($type === IMAGETYPE_GIF) {
        return $relativePath; // could be animated — flattening to WebP would lose that
    }
    $src = match ($type) {
        IMAGETYPE_JPEG => @imagecreatefromjpeg($absPath),
        IMAGETYPE_PNG => @imagecreatefrompng($absPath),
        IMAGETYPE_WEBP => @imagecreatefromwebp($absPath),
        default => null,
    };
    if (!$src) {
        return $relativePath;
    }

    $scale = min(1, $maxWidth / $width, $maxHeight / $height);
    if ($scale < 1) {
        $newWidth = max(1, (int) round($width * $scale));
        $newHeight = max(1, (int) round($height * $scale));
        $resized = imagecreatetruecolor($newWidth, $newHeight);
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $src, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        imagedestroy($src);
        $src = $resized;
    } else {
        imagepalettetotruecolor($src);
        imagealphablending($src, true);
        imagesavealpha($src, true);
    }

    $webpAbsPath = preg_replace('/\.[a-zA-Z0-9]+$/', '', $absPath) . '.webp';
    $ok = imagewebp($src, $webpAbsPath, $quality);
    imagedestroy($src);
    if (!$ok) {
        return $relativePath;
    }
    if ($webpAbsPath !== $absPath) {
        @unlink($absPath);
    }
    return preg_replace('/\.[a-zA-Z0-9]+$/', '', $relativePath) . '.webp';
}

/**
 * Validates and moves an uploaded file into UPLOAD_PATH/$subdir. When
 * $imageMaxSize is given as [maxWidth, maxHeight], the saved file is also
 * run through optimize_uploaded_image() — appropriate for photos (avatars,
 * logos, featured images) but left null for documents (certificates,
 * chat attachments) where exact fidelity/format matters.
 * Returns the relative path (for UPLOAD_URL) on success, or [false, error].
 */
function handle_upload($fileKey, $subdir, array $allowedExt, $maxBytes = 5242880, $imageMaxSize = null)
{
    if (empty($_FILES[$fileKey]) || $_FILES[$fileKey]['error'] === UPLOAD_ERR_NO_FILE) {
        return [false, 'No file uploaded.'];
    }
    $file = $_FILES[$fileKey];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return [false, 'Upload failed. Please try again.'];
    }
    if ($file['size'] > $maxBytes) {
        return [false, 'File is too large (max ' . round($maxBytes / 1048576, 1) . 'MB).'];
    }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExt, true)) {
        return [false, 'File type not allowed. Allowed: ' . implode(', ', $allowedExt)];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    $allowedMimes = [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'gif' => 'image/gif', 'webp' => 'image/webp', 'pdf' => 'application/pdf',
    ];
    if (isset($allowedMimes[$ext]) && $mime !== $allowedMimes[$ext]) {
        return [false, 'File content does not match its extension.'];
    }

    $dir = UPLOAD_PATH . '/' . trim($subdir, '/');
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $filename)) {
        return [false, 'Could not save the uploaded file.'];
    }
    $relativePath = trim($subdir, '/') . '/' . $filename;
    if ($imageMaxSize !== null) {
        $relativePath = optimize_uploaded_image($relativePath, $imageMaxSize[0], $imageMaxSize[1]);
    }
    return [true, $relativePath];
}

/**
 * Same validation/storage as handle_upload(), for a <input multiple> field
 * (e.g. name="certificates[]"). Returns a list of [bool ok, pathOrError]
 * pairs, one per selected file, in selection order. Empty array if no
 * files were selected at all.
 */
function handle_multi_upload($fileKey, $subdir, array $allowedExt, $maxBytes = 5242880)
{
    if (empty($_FILES[$fileKey]) || !is_array($_FILES[$fileKey]['name'] ?? null)) {
        return [];
    }
    $count = count($_FILES[$fileKey]['name']);
    $results = [];
    $original = $_FILES[$fileKey];
    for ($i = 0; $i < $count; $i++) {
        if (($original['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        $_FILES[$fileKey] = [
            'name' => $original['name'][$i], 'type' => $original['type'][$i],
            'tmp_name' => $original['tmp_name'][$i], 'error' => $original['error'][$i], 'size' => $original['size'][$i],
        ];
        $results[] = handle_upload($fileKey, $subdir, $allowedExt, $maxBytes);
    }
    $_FILES[$fileKey] = $original;
    return $results;
}

// ---------------------------------------------------------------------------
// Formatting
// ---------------------------------------------------------------------------

function format_currency($amount)
{
    return get_setting('currency_symbol', '$') . number_format((float) $amount, 2);
}

function format_date($date, $format = 'M j, Y')
{
    if (!$date) {
        return '';
    }
    return date($format, strtotime($date));
}

function format_time12($time)
{
    if (!$time) {
        return '';
    }
    return date('g:i A', strtotime($time));
}

function time_ago($datetime)
{
    $diff = time() - strtotime($datetime);
    if ($diff < 60) {
        return 'just now';
    }
    $units = [31536000 => 'year', 2592000 => 'month', 86400 => 'day', 3600 => 'hour', 60 => 'minute'];
    foreach ($units as $seconds => $label) {
        $value = floor($diff / $seconds);
        if ($value >= 1) {
            return $value . ' ' . $label . ($value > 1 ? 's' : '') . ' ago';
        }
    }
    return 'just now';
}

function excerpt($text, $length = 140)
{
    $text = trim(strip_tags($text));
    return mb_strlen($text) > $length ? mb_substr($text, 0, $length) . '…' : $text;
}

/**
 * Renders rich-editor HTML (bio/description/etc.) safely inside its wrapping
 * card. Content saved before the editor's paste-as-plain-text fix (or any
 * other source of hand-pasted HTML) can carry an unbalanced tag — a stray
 * closing tag closes the real ".rich-content"/".card" wrapper early in the
 * browser, so the rest of the content renders outside it on the page
 * background. Parsing as an HTML fragment and re-serializing repairs
 * unbalanced/unclosed tags before they ever reach the page's HTML string.
 */
function render_rich_html($html)
{
    $html = trim((string) $html);
    if ($html === '') {
        return '';
    }
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML('<?xml encoding="utf-8"?><html><body>' . $html . '</body></html>', LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    $body = $dom->getElementsByTagName('body')->item(0);
    if (!$body) {
        return $html;
    }
    $out = '';
    foreach ($body->childNodes as $child) {
        $out .= $dom->saveHTML($child);
    }
    return $out;
}

// ---------------------------------------------------------------------------
// Sitewide structured data (JSON-LD). organization_schema()/website_schema()
// are the site-level nodes shared by every page; render_page_schema() combines
// them with a per-page WebPage node (and an optional BreadcrumbList) into one
// @graph, and is called once from includes/header.php so every page — static
// or dynamic — gets it without repeating the boilerplate itself.
// ---------------------------------------------------------------------------
function organization_schema()
{
    $ratingRow = mysqli_fetch_assoc(mysqli_query(db(), "
        SELECT AVG(rating_avg) AS avg_rating, SUM(rating_count) AS total_reviews
        FROM doctors WHERE verification_status = 'verified' AND rating_count > 0
    "));
    $logoUrl = get_setting('site_logo') ? APP_URL . '/uploads/' . get_setting('site_logo') : APP_URL . '/assets/img/favicon.svg';
    return array_filter([
        '@type' => 'MedicalBusiness',
        '@id' => APP_URL . '/#organization',
        'name' => get_setting('site_name', SITE_NAME),
        'alternateName' => SITE_NAME,
        'url' => APP_URL,
        'description' => get_setting('site_tagline') ?: null,
        'logo' => $logoUrl,
        'image' => $logoUrl,
        'priceRange' => '$$',
        'medicalSpecialty' => array_values(array_filter(array_map(fn($s) => $s['name'] ?? null,
            mysqli_query(db(), 'SELECT name FROM specializations WHERE is_active = 1 ORDER BY sort_order LIMIT 10')->fetch_all(MYSQLI_ASSOC)
        ))),
        'address' => array_filter([
            '@type' => 'PostalAddress',
            'streetAddress' => get_setting('contact_address') ?: null,
        ]) ?: null,
        'contactPoint' => array_filter([
            '@type' => 'ContactPoint',
            'contactType' => 'customer support',
            'telephone' => get_setting('contact_phone') ?: null,
            'email' => get_setting('contact_email') ?: null,
            'availableLanguage' => ['English'],
        ]) ?: null,
        'sameAs' => array_values(array_filter([
            get_setting('facebook_url') ?: null,
            get_setting('twitter_url') ?: null,
            get_setting('instagram_url') ?: null,
            get_setting('linkedin_url') ?: null,
        ])) ?: null,
        'aggregateRating' => ($ratingRow && $ratingRow['avg_rating']) ? [
            '@type' => 'AggregateRating',
            'ratingValue' => number_format((float) $ratingRow['avg_rating'], 1),
            'reviewCount' => (string) (int) $ratingRow['total_reviews'],
        ] : null,
    ]);
}

function website_schema()
{
    return array_filter([
        '@type' => 'WebSite',
        '@id' => APP_URL . '/#website',
        'name' => get_setting('site_name', SITE_NAME),
        'url' => APP_URL,
        'description' => get_setting('site_tagline') ?: null,
        'publisher' => ['@id' => APP_URL . '/#organization'],
        'potentialAction' => [
            '@type' => 'SearchAction',
            'target' => ['@type' => 'EntryPoint', 'urlTemplate' => APP_URL . '/doctors?q={search_term_string}'],
            'query-input' => 'required name=search_term_string',
        ],
    ]);
}

/**
 * @param array $items List of ['name' => ..., 'url' => ...] in trail order
 *   (Home first). 'url' is optional on the last/current item.
 */
function breadcrumb_schema($items, $pageUrl)
{
    if (!$items) {
        return null;
    }
    $position = 0;
    return [
        '@type' => 'BreadcrumbList',
        '@id' => $pageUrl . '#breadcrumb',
        'itemListElement' => array_map(function ($item) use (&$position) {
            $position++;
            return array_filter([
                '@type' => 'ListItem',
                'position' => $position,
                'name' => $item['name'],
                'item' => $item['url'] ?? null,
            ]);
        }, $items),
    ];
}

/**
 * Echoes the sitewide JSON-LD graph for the current page. $breadcrumbs is
 * optional — pages that don't set one (before requiring header.php) simply
 * get Organization + WebSite + WebPage, no BreadcrumbList.
 */
function render_page_schema($pageTitle, $metaDescription, $pageUrl, $breadcrumbs = null)
{
    $graph = [organization_schema(), website_schema()];
    $graph[] = array_filter([
        '@type' => 'WebPage',
        '@id' => $pageUrl . '#webpage',
        'url' => $pageUrl,
        'name' => $pageTitle,
        'description' => $metaDescription,
        'isPartOf' => ['@id' => APP_URL . '/#website'],
        'breadcrumb' => $breadcrumbs ? ['@id' => $pageUrl . '#breadcrumb'] : null,
    ]);
    $crumbSchema = breadcrumb_schema($breadcrumbs, $pageUrl);
    if ($crumbSchema) {
        $graph[] = $crumbSchema;
    }
    echo '<script type="application/ld+json">' . json_encode(['@context' => 'https://schema.org', '@graph' => $graph]) . '</script>' . "\n";
}

function day_name($index)
{
    return ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'][$index] ?? '';
}

/**
 * Cache-busted URL for a local static asset (CSS/JS under /assets). Appends
 * the file's own last-modified time as a query string, so a long-lived
 * Cache-Control header (see .htaccess) can safely be set to a full year —
 * editing the file changes its mtime, which changes this URL, which forces
 * every browser to fetch the new copy regardless of what it had cached.
 */
function asset_url($path)
{
    $file = APP_ROOT . $path;
    $mtime = @filemtime($file);
    return $path . ($mtime ? '?v=' . $mtime : '');
}

function avatar_url($path, $fallbackSeed = 'U')
{
    if ($path) {
        return UPLOAD_URL . '/' . $path;
    }
    $initial = strtoupper(substr($fallbackSeed, 0, 1));
    return 'data:image/svg+xml;utf8,' . rawurlencode(
        '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">'
        . '<rect width="100" height="100" rx="50" fill="#0C6B5D"/>'
        . '<text x="50" y="58" font-size="42" font-family="sans-serif" fill="#fff" text-anchor="middle">' . $initial . '</text>'
        . '</svg>'
    );
}

// ---------------------------------------------------------------------------
// Doctor specializations (many-to-many)
// ---------------------------------------------------------------------------

/** @return array<int,array{id:int,name:string,slug:string}> */
function get_doctor_specializations($doctorId)
{
    $stmt = mysqli_prepare(db(), 'SELECT s.id, s.name, s.slug FROM doctor_specializations ds
        JOIN specializations s ON s.id = ds.specialization_id WHERE ds.doctor_id = ? ORDER BY s.name');
    mysqli_stmt_bind_param($stmt, 'i', $doctorId);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

function specialization_names($specializations)
{
    return implode(', ', array_column($specializations, 'name'));
}

/**
 * SEO-optimized fallback <title> for a doctor's public profile — used when
 * the doctor/admin hasn't set a custom meta title, and to pre-fill the SEO
 * form field so they can see and tweak the suggestion before saving.
 */
function doctor_meta_title($fullName, $designation, $qualification, $specNames)
{
    $role = $designation ?: $qualification;
    return implode(' — ', array_filter([$fullName, $role ?: null, $specNames ?: null]));
}

/**
 * SEO-optimized fallback meta description for a doctor's public profile —
 * same "pre-fill the form, fall back on the live page" role as
 * doctor_meta_title() above. Deliberately built from name/designation/
 * specialization rather than the bio, so it stays a consistent length and
 * keyword-focused regardless of how (or whether) the doctor writes their bio.
 */
function doctor_meta_description($fullName, $designation, $qualification, $specNames)
{
    if (!$fullName) {
        return '';
    }
    $role = $designation ?: ($qualification ?: 'doctor');
    $spec = $specNames ?: 'multiple specialties';
    return $fullName . ' is a ' . $role . ' specializing in ' . $spec
        . '. View profile, qualifications, specialization and professional details on '
        . get_setting('site_name', SITE_NAME) . '.';
}

/** Replaces a doctor's specialization set with the given ids (validated against the specializations table). */
function set_doctor_specializations($doctorId, array $specializationIds)
{
    $db = db();
    $specializationIds = array_values(array_unique(array_map('intval', $specializationIds)));
    mysqli_begin_transaction($db);
    try {
        $stmt = mysqli_prepare($db, 'DELETE FROM doctor_specializations WHERE doctor_id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $doctorId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($specializationIds) {
            $stmt = mysqli_prepare($db, 'INSERT INTO doctor_specializations (doctor_id, specialization_id)
                SELECT ?, id FROM specializations WHERE id = ?');
            foreach ($specializationIds as $specId) {
                mysqli_stmt_bind_param($stmt, 'ii', $doctorId, $specId);
                mysqli_stmt_execute($stmt);
            }
            mysqli_stmt_close($stmt);
        }
        mysqli_commit($db);
    } catch (Exception $e) {
        mysqli_rollback($db);
        error_log('set_doctor_specializations failed: ' . $e->getMessage());
        throw $e;
    }
}

/**
 * Replaces a medicine's FAQ list wholesale (delete + re-insert), same
 * pattern as set_doctor_specializations(). $questions/$answers are the raw
 * faq_question[]/faq_answer[] POST arrays — pairs where either side is
 * blank are skipped, so an admin can leave trailing empty rows in the form
 * without them being saved as junk FAQs.
 */
function save_medicine_faqs($medicineId, array $questions, array $answers)
{
    $db = db();
    mysqli_begin_transaction($db);
    try {
        $stmt = mysqli_prepare($db, 'DELETE FROM medicine_faqs WHERE medicine_id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $medicineId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($db, 'INSERT INTO medicine_faqs (medicine_id, question, answer, sort_order) VALUES (?, ?, ?, ?)');
        $order = 0;
        foreach ($questions as $i => $question) {
            $question = clean($question);
            $answer = clean($answers[$i] ?? '');
            if ($question === '' || $answer === '') {
                continue;
            }
            $question = mb_substr($question, 0, 255);
            mysqli_stmt_bind_param($stmt, 'issi', $medicineId, $question, $answer, $order);
            mysqli_stmt_execute($stmt);
            $order++;
        }
        mysqli_stmt_close($stmt);
        mysqli_commit($db);
    } catch (Exception $e) {
        mysqli_rollback($db);
        error_log('save_medicine_faqs failed: ' . $e->getMessage());
        throw $e;
    }
}

/** @return array<int,array{question:string,answer:string}> */
function get_medicine_faqs($medicineId)
{
    $stmt = mysqli_prepare(db(), 'SELECT question, answer FROM medicine_faqs WHERE medicine_id = ? ORDER BY sort_order');
    mysqli_stmt_bind_param($stmt, 'i', $medicineId);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

// ---------------------------------------------------------------------------
// Doctor weekly availability — each day can carry two independent timing
// blocks (online / physical), stored as separate doctor_availability rows.
// ---------------------------------------------------------------------------

/** @return array<int,array{online?:array,physical?:array}> keyed by day_of_week (0=Sunday..6=Saturday) */
function get_doctor_availability_by_day($doctorId)
{
    $byDay = [];
    $stmt = mysqli_prepare(db(), 'SELECT * FROM doctor_availability WHERE doctor_id = ? ORDER BY day_of_week');
    mysqli_stmt_bind_param($stmt, 'i', $doctorId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $day = (int) $row['day_of_week'];
        if ($row['consultation_type'] === 'both') {
            // Legacy single-schedule rows applied to both types; carry them over
            // to each block so a doctor's existing hours aren't lost on first edit.
            $byDay[$day]['online'] = $byDay[$day]['online'] ?? $row;
            $byDay[$day]['physical'] = $byDay[$day]['physical'] ?? $row;
        } else {
            $byDay[$day][$row['consultation_type']] = $row;
        }
    }
    mysqli_stmt_close($stmt);
    return $byDay;
}

/** Replaces a doctor's whole weekly schedule with the given rows (delete + re-insert). */
function save_doctor_availability($doctorId, array $rows)
{
    $db = db();
    mysqli_begin_transaction($db);
    try {
        $stmt = mysqli_prepare($db, 'DELETE FROM doctor_availability WHERE doctor_id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $doctorId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $insert = mysqli_prepare($db, 'INSERT INTO doctor_availability (doctor_id, day_of_week, start_time, end_time, slot_duration_mins, consultation_type) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ($rows as $row) {
            $day = (int) ($row['day_of_week'] ?? -1);
            $start = $row['start_time'] ?? '';
            $end = $row['end_time'] ?? '';
            $duration = max(5, (int) ($row['slot_duration_mins'] ?? 30));
            $type = in_array($row['consultation_type'] ?? '', ['online', 'physical', 'both'], true) ? $row['consultation_type'] : 'both';

            if ($day < 0 || $day > 6 || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $start) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $end)) {
                continue;
            }
            if (strtotime($start) >= strtotime($end)) {
                continue;
            }
            mysqli_stmt_bind_param($insert, 'iissis', $doctorId, $day, $start, $end, $duration, $type);
            mysqli_stmt_execute($insert);
        }
        mysqli_stmt_close($insert);
        mysqli_commit($db);
        return true;
    } catch (Exception $e) {
        mysqli_rollback($db);
        error_log('save_doctor_availability failed: ' . $e->getMessage());
        return false;
    }
}

/** @return array<int,array{id:int,start_time:string,end_time:string,is_active:int}> keyed by day_of_week */
function get_doctor_ticket_schedule($doctorId)
{
    $byDay = [];
    $stmt = mysqli_prepare(db(), 'SELECT * FROM doctor_ticket_schedule WHERE doctor_id = ? ORDER BY day_of_week');
    mysqli_stmt_bind_param($stmt, 'i', $doctorId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($row = mysqli_fetch_assoc($res)) {
        $byDay[(int) $row['day_of_week']] = $row;
    }
    mysqli_stmt_close($stmt);
    return $byDay;
}

/** Replaces a doctor's weekly ticket-queue hours with the given rows (delete + re-insert), same shape as save_doctor_availability(). */
function save_doctor_ticket_schedule($doctorId, array $rows)
{
    $db = db();
    mysqli_begin_transaction($db);
    try {
        $stmt = mysqli_prepare($db, 'DELETE FROM doctor_ticket_schedule WHERE doctor_id = ?');
        mysqli_stmt_bind_param($stmt, 'i', $doctorId);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $insert = mysqli_prepare($db, 'INSERT INTO doctor_ticket_schedule (doctor_id, day_of_week, start_time, end_time) VALUES (?, ?, ?, ?)');
        foreach ($rows as $row) {
            $day = (int) ($row['day_of_week'] ?? -1);
            $start = $row['start_time'] ?? '';
            $end = $row['end_time'] ?? '';
            if ($day < 0 || $day > 6 || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $start) || !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $end)) {
                continue;
            }
            if (strtotime($start) >= strtotime($end)) {
                continue;
            }
            mysqli_stmt_bind_param($insert, 'iiss', $doctorId, $day, $start, $end);
            mysqli_stmt_execute($insert);
        }
        mysqli_stmt_close($insert);
        mysqli_commit($db);
        return true;
    } catch (Exception $e) {
        mysqli_rollback($db);
        error_log('save_doctor_ticket_schedule failed: ' . $e->getMessage());
        return false;
    }
}

// ---------------------------------------------------------------------------
// Settings, notifications, activity log, pagination
// ---------------------------------------------------------------------------

function get_setting($key, $default = '')
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        $res = mysqli_query(db(), 'SELECT setting_key, setting_value FROM site_settings');
        while ($row = mysqli_fetch_assoc($res)) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $cache[$key] ?? $default;
}

/**
 * Records an in-app notification for the user and — if email notifications
 * are enabled (Admin → Site Settings → Email) — emails them the same
 * message. Every appointment, verification, and message event in the app
 * routes through this single function, so email delivery for "all
 * activity" is handled in one place rather than at each call site.
 */
function notify_user($userId, $type, $title, $message, $link = null)
{
    $stmt = mysqli_prepare(db(), 'INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)');
    mysqli_stmt_bind_param($stmt, 'issss', $userId, $type, $title, $message, $link);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if (!email_enabled()) {
        return;
    }
    $stmt = mysqli_prepare(db(), 'SELECT full_name, email FROM users WHERE id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    $recipient = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);
    if (!$recipient) {
        return;
    }

    $ctaUrl = $link ? (APP_URL . $link) : null;
    $body = '<p>Hi ' . e(explode(' ', $recipient['full_name'])[0]) . ',</p><p>' . e($message) . '</p>';
    send_email($recipient['email'], $recipient['full_name'], $title, email_template($title, $body, $link ? 'View Details' : null, $ctaUrl));
}

/** Notifies (and emails) every admin account — used for platform-level events like a new doctor application or contact message. */
function notify_admins($type, $title, $message, $link = null)
{
    $res = mysqli_query(db(), "SELECT id FROM users WHERE role = 'admin' AND status = 'active'");
    while ($row = mysqli_fetch_assoc($res)) {
        notify_user((int) $row['id'], $type, $title, $message, $link);
    }
}

// ---------------------------------------------------------------------------
// Doctor chat presence
// ---------------------------------------------------------------------------

/** Marks the logged-in user as recently active — call once per page load. Drives doctor online status. */
function touch_last_active($userId)
{
    $stmt = mysqli_prepare(db(), 'UPDATE users SET last_active_at = NOW() WHERE id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/**
 * Whether a doctor is "online" for chat right now: enabled, within their
 * configured daily hours (if set), and active within the last 15 minutes.
 * $doctorRow needs chat_enabled, chat_start_time, chat_end_time, last_active_at.
 */
function doctor_chat_available(array $doctorRow)
{
    if (empty($doctorRow['chat_enabled'])) {
        return false;
    }
    if (!empty($doctorRow['chat_start_time']) && !empty($doctorRow['chat_end_time'])) {
        $now = date('H:i:s');
        if ($now < $doctorRow['chat_start_time'] || $now > $doctorRow['chat_end_time']) {
            return false;
        }
    }
    if (empty($doctorRow['last_active_at']) || strtotime($doctorRow['last_active_at']) < time() - 900) {
        return false;
    }
    return true;
}

/**
 * Whether a patient may START a brand-new conversation with this doctor
 * right now: enabled, and (if a daily window is set) the current time falls
 * inside it. Deliberately does NOT require recent activity like
 * doctor_chat_available() does — a doctor who set hours 9-6 is "accepting
 * messages" all through that window whether or not they've clicked around
 * the site in the last 15 minutes; that check is only for the "online now"
 * dot. An already-existing conversation is never subject to this — either
 * side can always reply to a thread that's already open.
 */
function doctor_chat_can_start(array $doctorRow)
{
    if (empty($doctorRow['chat_enabled'])) {
        return false;
    }
    if (!empty($doctorRow['chat_start_time']) && !empty($doctorRow['chat_end_time'])) {
        $now = date('H:i:s');
        if ($now < $doctorRow['chat_start_time'] || $now > $doctorRow['chat_end_time']) {
            return false;
        }
    }
    return true;
}

/** Whether the chat entry point should appear at all for the current viewer (guest vs logged-in patient). */
function doctor_chat_visible(array $doctorRow, $isGuest)
{
    if (empty($doctorRow['chat_enabled'])) {
        return false;
    }
    return !$isGuest || !empty($doctorRow['chat_visible_to_guests']);
}

function doctor_chat_hours_label(array $doctorRow)
{
    if (empty($doctorRow['chat_start_time']) || empty($doctorRow['chat_end_time'])) {
        return 'Available anytime';
    }
    return 'Available ' . date('g:i A', strtotime($doctorRow['chat_start_time'])) . ' – ' . date('g:i A', strtotime($doctorRow['chat_end_time']));
}

function log_activity($userId, $role, $action, $description = '')
{
    $ip = client_ip();
    $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);
    $stmt = mysqli_prepare(db(), 'INSERT INTO activity_logs (user_id, role, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?)');
    mysqli_stmt_bind_param($stmt, 'isssss', $userId, $role, $action, $description, $ip, $ua);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function paginate($totalRows, $perPage = 12, $page = null)
{
    $page = max(1, (int) ($page ?? ($_GET['page'] ?? 1)));
    $totalPages = max(1, (int) ceil($totalRows / $perPage));
    $page = min($page, $totalPages);
    return [
        'page' => $page,
        'per_page' => $perPage,
        'offset' => ($page - 1) * $perPage,
        'total_pages' => $totalPages,
        'total_rows' => $totalRows,
    ];
}

function pagination_links($pagination, $baseUrl)
{
    if ($pagination['total_pages'] <= 1) {
        return '';
    }
    $sep = str_contains($baseUrl, '?') ? '&' : '?';
    $html = '<nav class="pagination" aria-label="Pagination">';
    for ($i = 1; $i <= $pagination['total_pages']; $i++) {
        $active = $i === $pagination['page'] ? ' active' : '';
        $html .= '<a class="page-link' . $active . '" href="' . e($baseUrl . $sep . 'page=' . $i) . '">' . $i . '</a>';
    }
    $html .= '</nav>';
    return $html;
}

// ---------------------------------------------------------------------------
// Public detail-page URLs — path-based (/doctors/{slug}), not ?slug=, so
// every doctor/product/medicine/post is a distinct crawlable path instead of
// a query-string variant of one generic page. Routed by .htaccess.
// ---------------------------------------------------------------------------
function doctor_url($slug) { return '/doctors/' . $slug; }
function product_url($slug) { return '/products/' . $slug; }
function medicine_url($slug) { return '/medicines/' . $slug; }
function blog_url($slug) { return '/blog/' . $slug; }
function pharmacy_url($slug) { return '/pharmacies/' . $slug; }

/**
 * The DoctorApna wordmark for the .brand logo lockup — the "R" in "Doctor"
 * is rendered as a styled plus/medical-cross so the logo reads "Docto+Apna".
 * Only for actual logo/brand-mark HTML; plain-text contexts (page <title>,
 * email subjects, JSON-LD names) should keep using e(SITE_NAME) as normal
 * text, since a "+" glyph substitution only makes sense visually.
 */
function brand_wordmark_html()
{
    return '<span class="brand-text">Docto<span class="brand-plus">+</span>Apna</span>';
}

/**
 * Brand mark shown inside the .brand link in every header partial (site,
 * admin, doctor, patient, pharmacy). An admin-uploaded logo (Site Settings →
 * Branding) replaces the icon+wordmark everywhere from this one place; with
 * none uploaded it falls back to the existing icon glyph + text wordmark.
 * $iconClass lets each panel keep its own fallback icon (e.g. admin uses a
 * shield instead of the heart-pulse glyph) when no logo is set.
 */
function brand_logo_html($iconClass = 'ri-heart-pulse-fill')
{
    $logo = get_setting('site_logo', '');
    if ($logo) {
        return '<img src="' . e(UPLOAD_URL . '/' . $logo) . '" alt="' . e(get_setting('site_name', SITE_NAME)) . '" class="brand-logo-img">';
    }
    return '<span class="brand-mark"><i class="' . e($iconClass) . '"></i></span>' . brand_wordmark_html();
}

/** <link rel="icon"> tag — an admin-uploaded favicon (Site Settings → Branding) if set, else the default SVG. */
function favicon_tag_html()
{
    $favicon = get_setting('site_favicon', '');
    if ($favicon === '') {
        return '<link rel="icon" type="image/svg+xml" href="/assets/img/favicon.svg">';
    }
    $ext = strtolower(pathinfo($favicon, PATHINFO_EXTENSION));
    $mime = ['ico' => 'image/x-icon', 'png' => 'image/png', 'svg' => 'image/svg+xml'][$ext] ?? 'image/png';
    return '<link rel="icon" type="' . e($mime) . '" href="' . e(UPLOAD_URL . '/' . $favicon) . '">';
}

/** Where a logged-in user of a given role lands after login/registration. */
function role_home_url($role)
{
    return match ($role) {
        'doctor' => '/doctor/dashboard',
        'pharmacy' => '/pharmacy/dashboard',
        'admin' => '/admin/dashboard',
        'manager' => '/manager/queue',
        default => '/patient/dashboard',
    };
}

/**
 * doctor_id whose ticket queue the CURRENT session may manage — the doctor
 * themselves, or a manager account linked to that doctor. Used by every
 * ticket-management ajax endpoint so the same code path serves both
 * doctor/queue.php and manager/queue.php. Returns null (and the caller
 * should reject the request) for anyone else.
 */
function resolve_ticket_doctor_id()
{
    $role = current_role();
    if ($role === 'doctor') {
        return current_profile_id();
    }
    if ($role === 'manager') {
        return current_manager_doctor_id();
    }
    return null;
}

/** doctor_id a logged-in manager account manages, or null if the current user isn't a manager. */
function current_manager_doctor_id()
{
    $user = current_user();
    if (!$user || $user['role'] !== 'manager') {
        return null;
    }
    $stmt = mysqli_prepare(db(), 'SELECT doctor_id FROM doctor_managers WHERE user_id = ? LIMIT 1');
    mysqli_stmt_bind_param($stmt, 'i', $user['id']);
    mysqli_stmt_execute($stmt);
    $row = mysqli_stmt_get_result($stmt)->fetch_assoc();
    mysqli_stmt_close($stmt);
    return $row ? (int) $row['doctor_id'] : null;
}

/**
 * Allocates the next ticket number for a doctor's queue on a given date and
 * returns it. Safe under concurrent bookings: the counter row is created if
 * missing, then locked with SELECT ... FOR UPDATE inside a transaction the
 * caller must have already opened with mysqli_begin_transaction() — the row
 * lock serializes concurrent callers so two patients booking at the same
 * instant can never receive the same number.
 */
function allocate_ticket_number($doctorId, $date)
{
    $db = db();
    $stmt = mysqli_prepare($db, 'INSERT INTO doctor_ticket_counters (doctor_id, ticket_date) VALUES (?, ?) ON DUPLICATE KEY UPDATE doctor_id = doctor_id');
    mysqli_stmt_bind_param($stmt, 'is', $doctorId, $date);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $stmt = mysqli_prepare($db, 'SELECT last_number FROM doctor_ticket_counters WHERE doctor_id = ? AND ticket_date = ? FOR UPDATE');
    mysqli_stmt_bind_param($stmt, 'is', $doctorId, $date);
    mysqli_stmt_execute($stmt);
    $current = (int) (mysqli_stmt_get_result($stmt)->fetch_assoc()['last_number'] ?? 0);
    mysqli_stmt_close($stmt);

    $next = $current + 1;
    $stmt = mysqli_prepare($db, 'UPDATE doctor_ticket_counters SET last_number = ? WHERE doctor_id = ? AND ticket_date = ?');
    mysqli_stmt_bind_param($stmt, 'iis', $next, $doctorId, $date);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    return $next;
}

/** Display name for a ticket row (real account name, or the walk-in/guest name captured at booking). */
function ticket_display_name($ticket)
{
    return $ticket['patient_name'] ?: ($ticket['guest_name'] ?: 'Patient');
}

/**
 * A SQL expression computing great-circle distance in kilometers from
 * ($lat, $lng) to a row's $latCol/$lngCol, via the Haversine formula — no
 * spatial extension or geocoding API required, just plain trig functions
 * every MySQL/MariaDB version supports. Embed directly in a SELECT list
 * (aliased, e.g. "AS distance_km") and/or ORDER BY.
 */
function haversine_distance_sql($latCol, $lngCol, $lat, $lng)
{
    $lat = (float) $lat;
    $lng = (float) $lng;
    return "(6371 * ACOS(LEAST(1, GREATEST(-1,
        COS(RADIANS($lat)) * COS(RADIANS($latCol)) * COS(RADIANS($lngCol) - RADIANS($lng))
        + SIN(RADIANS($lat)) * SIN(RADIANS($latCol))
    ))))";
}

/**
 * Canonical URL for a filterable listing page (doctors/products/medicines).
 * Each distinct combination of content-defining filters (specialization,
 * city, search term, price range...) is real, differently-ranking-worthy
 * content and gets its own self-referencing canonical — but $params must
 * NOT include `sort`: re-sorting the same filter combination doesn't change
 * what the page is "about", so every sort variant of one filter combination
 * shares that combination's single canonical (collapsing to unsorted).
 *
 * $page DOES get included (when > 1): each page of results shows different
 * doctors/products, i.e. genuinely different content, so per Google's
 * current pagination guidance every page number gets its own
 * self-referencing canonical. Collapsing every page to page 1's canonical
 * — the previous behavior here — told Google to ignore pages 2+ entirely,
 * which both stops those doctors/products from ever being indexed under
 * this path AND surfaces as a "duplicate, Google chose different canonical"
 * warning in Search Console for a listing with more than one page.
 */
function filtered_canonical($path, array $params, $page = 1)
{
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null && $v !== false);
    ksort($params);
    if ((int) $page > 1) {
        $params['page'] = (int) $page;
    }
    $qs = http_build_query($params);
    return APP_URL . $path . ($qs !== '' ? '?' . $qs : '');
}

// ---------------------------------------------------------------------------
// SEO
// ---------------------------------------------------------------------------

/** Builds the full sitemap XML. Used by both the always-fresh sitemap.php
 * (dynamic, generated per-request) and the admin "Generate Sitemap" action
 * (writes the same output to a static sitemap.xml file at the project root). */
function build_sitemap_xml()
{
    $db = db();
    $staticPages = ['/', '/doctors', '/specializations', '/products', '/medicines', '/pharmacies', '/blog', '/about', '/contact', '/faq', '/privacy-policy', '/terms', '/doctor-register', '/pharmacy-register'];

    $doctors = mysqli_query($db, "SELECT slug, updated_at FROM doctors WHERE verification_status = 'verified'");
    $pharmacies = mysqli_query($db, "SELECT slug, updated_at FROM pharmacies WHERE verification_status = 'verified'");
    $specs = mysqli_query($db, 'SELECT slug FROM specializations WHERE is_active = 1');
    $blogPosts = mysqli_query($db, "SELECT slug, published_at FROM blog_posts WHERE status = 'published'");
    $storeProducts = mysqli_query($db, "
        SELECT dp.slug, dp.created_at FROM doctor_products dp
        LEFT JOIN doctors d ON d.id = dp.doctor_id AND dp.seller_type = 'doctor'
        LEFT JOIN pharmacies ph ON ph.id = dp.pharmacy_id AND dp.seller_type = 'pharmacy'
        WHERE dp.is_active = 1
            AND ((dp.seller_type = 'doctor' AND d.is_premium = 1 AND d.verification_status = 'verified')
                OR (dp.seller_type = 'pharmacy' AND ph.verification_status = 'verified'))
    ");
    $medicines = mysqli_query($db, "SELECT slug, updated_at FROM medicine_info WHERE status = 'published'");

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    foreach ($staticPages as $path) {
        $xml .= '<url><loc>' . e(APP_URL . $path) . '</loc><changefreq>weekly</changefreq><priority>0.8</priority></url>' . "\n";
    }
    while ($d = mysqli_fetch_assoc($doctors)) {
        $xml .= '<url><loc>' . e(APP_URL . doctor_url($d['slug'])) . '</loc><lastmod>' . date('Y-m-d', strtotime($d['updated_at'])) . '</lastmod><changefreq>weekly</changefreq><priority>0.7</priority></url>' . "\n";
    }
    while ($ph = mysqli_fetch_assoc($pharmacies)) {
        $xml .= '<url><loc>' . e(APP_URL . pharmacy_url($ph['slug'])) . '</loc><lastmod>' . date('Y-m-d', strtotime($ph['updated_at'])) . '</lastmod><changefreq>weekly</changefreq><priority>0.7</priority></url>' . "\n";
    }
    while ($s = mysqli_fetch_assoc($specs)) {
        $xml .= '<url><loc>' . e(APP_URL . '/specializations/' . $s['slug']) . '</loc><changefreq>weekly</changefreq><priority>0.6</priority></url>' . "\n";
    }
    while ($b = mysqli_fetch_assoc($blogPosts)) {
        $xml .= '<url><loc>' . e(APP_URL . blog_url($b['slug'])) . '</loc><lastmod>' . date('Y-m-d', strtotime($b['published_at'])) . '</lastmod><changefreq>monthly</changefreq><priority>0.5</priority></url>' . "\n";
    }
    while ($p = mysqli_fetch_assoc($storeProducts)) {
        $xml .= '<url><loc>' . e(APP_URL . product_url($p['slug'])) . '</loc><lastmod>' . date('Y-m-d', strtotime($p['created_at'])) . '</lastmod><changefreq>weekly</changefreq><priority>0.6</priority></url>' . "\n";
    }
    while ($m = mysqli_fetch_assoc($medicines)) {
        $xml .= '<url><loc>' . e(APP_URL . medicine_url($m['slug'])) . '</loc><lastmod>' . date('Y-m-d', strtotime($m['updated_at'])) . '</lastmod><changefreq>monthly</changefreq><priority>0.6</priority></url>' . "\n";
    }

    $xml .= '</urlset>';
    return $xml;
}

/**
 * Builds llms.txt (https://llmstxt.org) — a plain-text, curated index of the
 * site aimed at AI agents/crawlers that increasingly read this convention
 * before (or instead of) rendering full HTML. This is the core "GEO"
 * (generative engine optimization) artifact: a concise map of what the site
 * is and where its key content lives, in a format made for LLM consumption
 * rather than for search-engine ranking. Regenerated alongside sitemap.xml
 * from the admin "Generate Sitemap" action.
 */
function build_llms_txt()
{
    $db = db();
    $lines = [];
    $lines[] = '# ' . SITE_NAME;
    $lines[] = '';
    $lines[] = '> ' . get_setting('site_tagline', 'Book verified doctors online or in-person, message and order medicine from verified pharmacies.');
    $lines[] = '';
    $lines[] = '## Site';
    $lines[] = '- [Find Doctors](' . APP_URL . '/doctors): Search verified doctors by specialty, city, and price.';
    $lines[] = '- [Specializations](' . APP_URL . '/specializations): Browse doctors by medical specialty.';
    $lines[] = '- [Pharmacies](' . APP_URL . '/pharmacies): Verified medicine stores selling directly online.';
    $lines[] = '- [Products & Services](' . APP_URL . '/products): Health products and service packages sold by doctors and pharmacies.';
    $lines[] = '- [Medicine Info](' . APP_URL . '/medicines): Reference information on specific medicines (uses, dosage, side effects).';
    $lines[] = '- [Blog](' . APP_URL . '/blog): Health articles and platform news.';
    $lines[] = '- [FAQ](' . APP_URL . '/faq): Frequently asked questions about the platform.';
    $lines[] = '- [About](' . APP_URL . '/about): What ' . SITE_NAME . ' is and who it serves.';

    $doctors = mysqli_query($db, "
        SELECT d.slug, u.full_name,
            (SELECT GROUP_CONCAT(s.name SEPARATOR ', ') FROM doctor_specializations ds JOIN specializations s ON s.id = ds.specialization_id WHERE ds.doctor_id = d.id) AS specs
        FROM doctors d JOIN users u ON u.id = d.user_id
        WHERE d.verification_status = 'verified' ORDER BY d.rating_avg DESC LIMIT 40
    ");
    if (mysqli_num_rows($doctors) > 0) {
        $lines[] = '';
        $lines[] = '## Doctors';
        while ($d = mysqli_fetch_assoc($doctors)) {
            $lines[] = '- [' . $d['full_name'] . '](' . APP_URL . doctor_url($d['slug']) . ')' . ($d['specs'] ? ': ' . $d['specs'] : '');
        }
    }

    $pharmacies = mysqli_query($db, "SELECT slug, store_name, city FROM pharmacies WHERE verification_status = 'verified' ORDER BY rating_avg DESC LIMIT 40");
    if (mysqli_num_rows($pharmacies) > 0) {
        $lines[] = '';
        $lines[] = '## Pharmacies';
        while ($p = mysqli_fetch_assoc($pharmacies)) {
            $lines[] = '- [' . $p['store_name'] . '](' . APP_URL . pharmacy_url($p['slug']) . ')' . ($p['city'] ? ': ' . $p['city'] : '');
        }
    }

    $medicines = mysqli_query($db, "SELECT slug, name, generic_name FROM medicine_info WHERE status = 'published' ORDER BY updated_at DESC LIMIT 60");
    if (mysqli_num_rows($medicines) > 0) {
        $lines[] = '';
        $lines[] = '## Medicine Info';
        while ($m = mysqli_fetch_assoc($medicines)) {
            $lines[] = '- [' . $m['name'] . '](' . APP_URL . medicine_url($m['slug']) . ')' . ($m['generic_name'] ? ': ' . $m['generic_name'] : '');
        }
    }

    $lines[] = '';
    $lines[] = '## Full index';
    $lines[] = '- [XML Sitemap](' . APP_URL . '/sitemap.xml): Complete, machine-readable list of every public URL.';

    return implode("\n", $lines) . "\n";
}

/**
 * Yoast-style on-page SEO completeness score (0-100) for a medicine info
 * entry. Mirrored in assets/js/seo-score.js for the live progress bar in the
 * admin/doctor editor — keep the two weightings in sync if either changes.
 */
function seo_score_for_medicine(array $data)
{
    $name = trim($data['name'] ?? '');
    $keyword = trim($data['focus_keyword'] ?? '');
    $metaTitle = trim($data['meta_title'] ?? '');
    $metaDescription = trim($data['meta_description'] ?? '');
    $content = trim(strip_tags(($data['content'] ?? '') . ' ' . ($data['uses'] ?? '')));
    $wordCount = $content === '' ? 0 : str_word_count($content);
    $kwLower = mb_strtolower($keyword);

    $score = 0;
    if ($keyword !== '') $score += 5;
    if ($metaTitle !== '') $score += 10;
    if ($metaTitle !== '' && $keyword !== '' && mb_stripos($metaTitle, $keyword) !== false) $score += 10;
    if (mb_strlen($metaTitle) >= 30 && mb_strlen($metaTitle) <= 60) $score += 5;
    if ($metaDescription !== '') $score += 10;
    if ($metaDescription !== '' && $keyword !== '' && mb_stripos($metaDescription, $keyword) !== false) $score += 10;
    if (mb_strlen($metaDescription) >= 120 && mb_strlen($metaDescription) <= 160) $score += 5;
    if ($wordCount >= 300) $score += 20;
    elseif ($wordCount >= 150) $score += 10;
    if ($content !== '' && $keyword !== '' && mb_stripos($content, $keyword) !== false) $score += 15;
    if ($name !== '' && $keyword !== '' && mb_stripos($name, $keyword) !== false) $score += 10;

    return min(100, $score);
}

/**
 * Block-based blog editor. A post's `blocks` column is a JSON array of
 * {type, ...type-specific fields}; render_blog_blocks() walks it in order.
 * Posts written before this feature existed have blocks = NULL and keep
 * rendering their old single `content` HTML field (see blog-post.php).
 */
function get_blog_blocks($post)
{
    if (empty($post['blocks'])) {
        return null;
    }
    $blocks = json_decode($post['blocks'], true);
    return (is_array($blocks) && $blocks) ? $blocks : null;
}

/** Doctor rows for doctor/doctor_carousel/comparison_table blocks, verified only, in the author's chosen order. */
function fetch_doctors_for_blocks(array $doctorIds)
{
    $doctorIds = array_values(array_unique(array_filter(array_map('intval', $doctorIds))));
    if (!$doctorIds) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($doctorIds), '?'));
    $stmt = mysqli_prepare(db(), "SELECT d.*, u.full_name, u.avatar FROM doctors d JOIN users u ON u.id = d.user_id
        WHERE d.id IN ($placeholders) AND d.verification_status = 'verified' AND u.status = 'active'");
    mysqli_stmt_bind_param($stmt, str_repeat('i', count($doctorIds)), ...$doctorIds);
    mysqli_stmt_execute($stmt);
    $byId = [];
    foreach (mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC) as $row) {
        $row['specializations'] = get_doctor_specializations($row['id']);
        $byId[(int) $row['id']] = $row;
    }
    mysqli_stmt_close($stmt);
    $ordered = [];
    foreach ($doctorIds as $id) {
        if (isset($byId[$id])) $ordered[] = $byId[$id];
    }
    return $ordered;
}

/** Medicine rows for medicine blocks, published only, in the author's chosen order. */
function fetch_medicines_for_blocks(array $medicineIds)
{
    $medicineIds = array_values(array_unique(array_filter(array_map('intval', $medicineIds))));
    if (!$medicineIds) {
        return [];
    }
    $placeholders = implode(',', array_fill(0, count($medicineIds), '?'));
    $stmt = mysqli_prepare(db(), "SELECT * FROM medicine_info WHERE id IN ($placeholders) AND status = 'published'");
    mysqli_stmt_bind_param($stmt, str_repeat('i', count($medicineIds)), ...$medicineIds);
    mysqli_stmt_execute($stmt);
    $byId = [];
    foreach (mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC) as $row) {
        $byId[(int) $row['id']] = $row;
    }
    mysqli_stmt_close($stmt);
    $ordered = [];
    foreach ($medicineIds as $id) {
        if (isset($byId[$id])) $ordered[] = $byId[$id];
    }
    return $ordered;
}

/** youtube.com/watch, youtu.be, or vimeo.com URL -> embeddable iframe src, or null if unrecognized. */
function video_embed_url($url)
{
    $url = trim((string) $url);
    if ($url === '') return null;
    if (preg_match('~(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([\w-]{11})~i', $url, $m)) {
        return 'https://www.youtube.com/embed/' . $m[1];
    }
    if (preg_match('~vimeo\.com/(?:video/)?(\d+)~i', $url, $m)) {
        return 'https://player.vimeo.com/video/' . $m[1];
    }
    return null;
}

/** A map block's address (or lat/lng) -> a keyless Google Maps embed URL. */
function map_embed_url(array $block)
{
    $q = trim($block['address'] ?? '');
    if ($q === '' && isset($block['lat'], $block['lng']) && is_numeric($block['lat']) && is_numeric($block['lng'])) {
        $q = $block['lat'] . ',' . $block['lng'];
    }
    return $q === '' ? null : 'https://www.google.com/maps?q=' . urlencode($q) . '&output=embed';
}

/** Stable, unique #anchor slug for a heading block's text, used by both the heading itself and any Table of Contents block. */
function _blog_heading_slug($text, array &$seen)
{
    $base = trim(preg_replace('/[^a-z0-9]+/', '-', mb_strtolower($text)), '-');
    if ($base === '') $base = 'section';
    $slug = $base;
    $n = 2;
    while (isset($seen[$slug])) {
        $slug = $base . '-' . $n++;
    }
    $seen[$slug] = true;
    return $slug;
}

/** Plain-text extract of a block-based post's readable content, for excerpt/meta-description auto-fallback when the author didn't write one. */
function blog_blocks_to_text(array $blocks)
{
    $parts = [];
    foreach ($blocks as $b) {
        switch ($b['type'] ?? '') {
            case 'heading': $parts[] = $b['text'] ?? ''; break;
            case 'richtext': $parts[] = strip_tags($b['html'] ?? ''); break;
            case 'callout': $parts[] = strip_tags($b['html'] ?? ''); break;
            case 'quote': $parts[] = $b['text'] ?? ''; break;
        }
    }
    return trim(implode(' ', array_filter($parts)));
}

/** Renders every block in order. Pre-scans headings first so a `toc` block anywhere in the post can list all of them regardless of position. */
function render_blog_blocks(array $blocks)
{
    $seenSlugs = [];
    $headings = [];
    foreach ($blocks as $i => $b) {
        if (($b['type'] ?? '') === 'heading' && trim($b['text'] ?? '') !== '') {
            $blocks[$i]['_id'] = _blog_heading_slug($b['text'], $seenSlugs);
            $headings[] = ['id' => $blocks[$i]['_id'], 'text' => $b['text'], 'level' => $b['level'] ?? 'h2'];
        }
    }
    foreach ($blocks as $block) {
        render_blog_block($block, $headings);
    }
}

function render_blog_block(array $block, array $headings = [])
{
    $type = $block['type'] ?? '';
    switch ($type) {
        case 'heading':
            if (trim($block['text'] ?? '') === '') break;
            $level = ($block['level'] ?? 'h2') === 'h3' ? 'h3' : 'h2';
            echo '<' . $level . ' id="' . e($block['_id'] ?? '') . '" style="margin:36px 0 14px;scroll-margin-top:100px;">' . e($block['text']) . '</' . $level . '>';
            break;

        case 'richtext':
            if (trim(strip_tags($block['html'] ?? '')) === '') break;
            echo '<div class="rich-content">' . render_rich_html($block['html']) . '</div>';
            break;

        case 'image':
            if (empty($block['url'])) break;
            echo '<figure class="blog-block-figure">';
            echo '<img src="' . e($block['url']) . '" alt="' . e($block['alt'] ?? '') . '" loading="lazy">';
            if (!empty($block['caption'])) echo '<figcaption>' . e($block['caption']) . '</figcaption>';
            echo '</figure>';
            break;

        case 'gallery':
            $images = array_filter($block['images'] ?? [], fn($img) => !empty($img['url']));
            if (!$images) break;
            echo '<div class="city-carousel-wrap blog-block-gallery" data-carousel>';
            echo '<button type="button" class="carousel-nav-btn" data-carousel-prev aria-label="Scroll left"><i class="ri-arrow-left-s-line"></i></button>';
            echo '<div class="city-carousel-track" data-carousel-track>';
            foreach ($images as $img) {
                echo '<img src="' . e($img['url']) . '" alt="' . e($img['alt'] ?? '') . '" loading="lazy">';
            }
            echo '</div>';
            echo '<button type="button" class="carousel-nav-btn" data-carousel-next aria-label="Scroll right"><i class="ri-arrow-right-s-line"></i></button>';
            echo '</div>';
            break;

        case 'video':
            $embed = video_embed_url($block['url'] ?? '');
            if (!$embed) break;
            echo '<div class="blog-video-wrap"><iframe src="' . e($embed) . '" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen title="Video"></iframe></div>';
            break;

        case 'map':
            $embed = map_embed_url($block);
            if (!$embed) break;
            echo '<div class="blog-map-wrap"><iframe src="' . e($embed) . '" loading="lazy" title="Map"></iframe></div>';
            break;

        case 'doctor':
            $doctors = fetch_doctors_for_blocks([(int) ($block['doctor_id'] ?? 0)]);
            if (!$doctors) break;
            $d = $doctors[0];
            echo '<div class="blog-block-single-card">';
            require __DIR__ . '/doctor-card.php';
            echo '</div>';
            break;

        case 'doctor_carousel':
            $doctors = fetch_doctors_for_blocks($block['doctor_ids'] ?? []);
            if (!$doctors) break;
            echo '<div class="blog-block-carousel-section">';
            if (!empty($block['heading'])) echo '<h3>' . e($block['heading']) . '</h3>';
            echo '<div class="city-carousel-wrap" data-carousel>';
            echo '<button type="button" class="carousel-nav-btn" data-carousel-prev aria-label="Scroll left"><i class="ri-arrow-left-s-line"></i></button>';
            echo '<div class="city-carousel-track" data-carousel-track>';
            foreach ($doctors as $d) {
                echo '<div class="blog-block-carousel-item">';
                require __DIR__ . '/doctor-card.php';
                echo '</div>';
            }
            echo '</div>';
            echo '<button type="button" class="carousel-nav-btn" data-carousel-next aria-label="Scroll right"><i class="ri-arrow-right-s-line"></i></button>';
            echo '</div></div>';
            break;

        case 'medicine':
            $medicines = fetch_medicines_for_blocks([(int) ($block['medicine_id'] ?? 0)]);
            if (!$medicines) break;
            $m = $medicines[0];
            echo '<a href="' . e(medicine_url($m['slug'])) . '" class="card card-hover blog-block-medicine-card">';
            if ($m['featured_image']) echo '<img src="/uploads/' . e($m['featured_image']) . '" alt="' . e($m['name']) . '" loading="lazy">';
            else echo '<div class="blog-block-medicine-icon"><i class="ri-capsule-line"></i></div>';
            echo '<div><strong>' . e($m['name']) . '</strong>';
            if ($m['category']) echo '<span>' . e($m['category']) . '</span>';
            echo '</div><i class="ri-arrow-right-line"></i></a>';
            break;

        case 'comparison_table':
            $doctors = fetch_doctors_for_blocks($block['doctor_ids'] ?? []);
            if (!$doctors) break;
            echo '<div class="card table-card blog-block-comparison">';
            if (!empty($block['heading'])) echo '<h3 style="padding:20px 20px 0;">' . e($block['heading']) . '</h3>';
            echo '<div class="table-scroll"><table class="data-table"><thead><tr><th>Doctor</th><th>Specialization</th><th>Experience</th><th>Online Fee</th><th>In-Person Fee</th><th>Rating</th><th></th></tr></thead><tbody>';
            foreach ($doctors as $d) {
                $specNames = specialization_names($d['specializations']) ?: '—';
                $fee = (float) $d['consultation_fee_online'];
                $feeInPerson = (float) $d['consultation_fee_physical'];
                echo '<tr><td class="table-user"><img src="' . e(avatar_url($d['avatar'], $d['full_name'])) . '" alt=""><strong>' . e($d['full_name']) . '</strong></td>';
                echo '<td>' . e($specNames) . '</td>';
                echo '<td>' . ((int) $d['experience_years'] > 0 ? (int) $d['experience_years'] . ' yrs' : '—') . '</td>';
                echo '<td>' . ($fee > 0 ? format_currency($fee) : ($d['free_consultation'] ? 'Free' : '—')) . '</td>';
                echo '<td>' . ($feeInPerson > 0 ? format_currency($feeInPerson) : '—') . '</td>';
                echo '<td>' . ((int) $d['rating_count'] > 0 ? '<i class="ri-star-fill" style="color:#F59E0B;"></i> ' . number_format($d['rating_avg'], 1) : 'New') . '</td>';
                echo '<td><a href="' . e(doctor_url($d['slug'])) . '" class="btn btn-outline btn-sm">View</a></td></tr>';
            }
            echo '</tbody></table></div></div>';
            break;

        case 'fact_box':
            $facts = array_filter($block['facts'] ?? [], fn($f) => trim($f['label'] ?? '') !== '');
            if (!$facts) break;
            echo '<div class="blog-fact-box">';
            if (!empty($block['heading'])) echo '<h3>' . e($block['heading']) . '</h3>';
            echo '<dl>';
            foreach ($facts as $f) {
                echo '<div><dt>' . e($f['label']) . '</dt><dd>' . e($f['value'] ?? '') . '</dd></div>';
            }
            echo '</dl></div>';
            break;

        case 'callout':
            if (trim(strip_tags($block['html'] ?? '')) === '') break;
            $style = in_array($block['style'] ?? 'info', ['info', 'success', 'warning'], true) ? $block['style'] : 'info';
            echo '<div class="blog-callout blog-callout-' . e($style) . '">' . render_rich_html($block['html']) . '</div>';
            break;

        case 'faq':
            $items = array_filter($block['items'] ?? [], fn($f) => trim($f['q'] ?? '') !== '');
            if (!$items) break;
            echo '<div class="blog-block-faq">';
            if (!empty($block['heading'])) echo '<h3>' . e($block['heading']) . '</h3>';
            echo '<div data-faq-group>';
            foreach ($items as $f) {
                echo '<div class="card" style="margin-bottom:12px;overflow:hidden;">
                    <button type="button" class="faq-q" style="width:100%;text-align:left;padding:18px 22px;display:flex;justify-content:space-between;align-items:center;gap:12px;background:none;border:none;font-weight:700;font-size:14.5px;color:var(--color-text);">'
                    . e($f['q']) . '<i class="ri-add-line" style="transition:var(--transition);flex-shrink:0;"></i></button>
                    <div class="faq-a" style="max-height:0;overflow:hidden;transition:max-height 0.35s ease;"><p style="padding:0 22px 18px;color:var(--color-text-muted);font-size:14px;">' . e($f['a'] ?? '') . '</p></div>
                </div>';
            }
            echo '</div></div>';
            break;

        case 'quote':
            if (trim($block['text'] ?? '') === '') break;
            echo '<blockquote class="blog-block-quote"><p>' . e($block['text']) . '</p>';
            if (!empty($block['cite'])) echo '<cite>' . e($block['cite']) . '</cite>';
            echo '</blockquote>';
            break;

        case 'toc':
            if (!$headings) break;
            echo '<nav class="blog-toc-box" aria-label="Table of contents">';
            echo '<h3>' . e($block['heading'] ?? 'Table of Contents') . '</h3><ol>';
            foreach ($headings as $h) {
                echo '<li class="toc-' . e($h['level']) . '"><a href="#' . e($h['id']) . '">' . e($h['text']) . '</a></li>';
            }
            echo '</ol></nav>';
            break;

        case 'divider':
            echo '<hr class="blog-block-divider">';
            break;
    }
}
