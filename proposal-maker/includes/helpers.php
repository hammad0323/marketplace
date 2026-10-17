<?php
/**
 * HELPERS — small functions used everywhere.
 *
 *   e()            make text safe to print in HTML
 *   url(), asset() build links to pages and CSS/JS files
 *   csrf_*()       security token for forms
 *   json_out()     send an AJAX answer
 *   money(), fmt_date()                   formatting
 *   handle_image_upload(), image_cover()  upload, shrink and crop images
 *   company()      the company settings row
 */

function e($v)
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url($path = '')
{
    return APP_URL . '/' . ltrim($path, '/');
}

function asset($path)
{
    $file = APP_ROOT . '/assets/' . ltrim($path, '/');
    $v = is_file($file) ? filemtime($file) : APP_VERSION;
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

function public_url($token)
{
    if (!defined('PRETTY_URLS') || PRETTY_URLS) {
        return url('proposal/view/' . $token);
    }
    return url('view.php?token=' . $token);
}

function redirect($path)
{
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : url($path)));
    exit;
}

function flash($type, $msg)
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function take_flash()
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field()
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check()
{
    $t = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($t) || !hash_equals(csrf_token(), $t)) {
        if (is_ajax()) {
            json_out(['ok' => false, 'message' => 'Your session expired. Please reload the page.'], 419);
        }
        http_response_code(419);
        exit('Invalid security token. Please go back and reload the page.');
    }
}

function is_ajax()
{
    return strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'xmlhttprequest'
        || strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false;
}

function json_out($data, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function post($key, $default = '')
{
    $v = $_POST[$key] ?? $default;
    return is_string($v) ? trim($v) : $v;
}

function jdecode($json, $default = [])
{
    if (is_array($json)) {
        return $json;
    }
    if (!is_string($json) || $json === '') {
        return $default;
    }
    $d = json_decode($json, true);
    return is_array($d) ? $d : $default;
}

function jencode($data)
{
    return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function uid($prefix = '')
{
    return $prefix . bin2hex(random_bytes(5));
}

function client_ip()
{
    foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $k) {
        if (!empty($_SERVER[$k])) {
            $ip = trim(explode(',', $_SERVER[$k])[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    return '0.0.0.0';
}

function is_hex_color($c)
{
    return is_string($c) && preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i', $c);
}

function clean_color($c, $fallback = '#000000')
{
    return is_hex_color($c) ? strtolower($c) : $fallback;
}

/** Hex → [r,g,b] */
function hex_rgb($hex)
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}

/** Mix a color with white (amount>0) or black (amount<0). */
function color_mix($hex, $amount)
{
    list($r, $g, $b) = hex_rgb($hex);
    $t = $amount > 0 ? 255 : 0;
    $p = abs($amount);
    // move each channel (red, green, blue) towards white or black
    $r = (int) round($r + ($t - $r) * $p);
    $g = (int) round($g + ($t - $g) * $p);
    $b = (int) round($b + ($t - $b) * $p);
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

function color_is_dark($hex)
{
    list($r, $g, $b) = hex_rgb($hex);
    return (0.299 * $r + 0.587 * $g + 0.114 * $b) < 140;
}

function currencies()
{
    return [
        'USD' => ['symbol' => '$', 'name' => 'US Dollar'],
        'CAD' => ['symbol' => 'C$', 'name' => 'Canadian Dollar'],
        'AED' => ['symbol' => 'AED ', 'name' => 'UAE Dirham'],
        'GBP' => ['symbol' => '£', 'name' => 'British Pound'],
        'EUR' => ['symbol' => '€', 'name' => 'Euro'],
        'PKR' => ['symbol' => 'Rs ', 'name' => 'Pakistani Rupee'],
        'AUD' => ['symbol' => 'A$', 'name' => 'Australian Dollar'],
        'INR' => ['symbol' => '₹', 'name' => 'Indian Rupee'],
        'SAR' => ['symbol' => 'SAR ', 'name' => 'Saudi Riyal'],
    ];
}

function money($amount, $symbol = '$')
{
    $amount = (float) $amount;
    $neg = $amount < 0;
    $s = $symbol . number_format(abs($amount), 2);
    return $neg ? '-' . $s : $s;
}

function fmt_date($date, $format = 'M j, Y')
{
    if (!$date || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
        return '';
    }
    $t = strtotime($date);
    return $t ? date($format, $t) : '';
}

function time_ago($datetime)
{
    if (!$datetime) {
        return '—';
    }
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . ' min ago';
    if ($diff < 86400) return floor($diff / 3600) . ' h ago';
    if ($diff < 604800) return floor($diff / 86400) . ' d ago';
    return fmt_date($datetime);
}

/** Fonts bundled in assets/fonts — usable in browser AND PDF. */
function font_list()
{
    return [
        'Inter'              => ['file' => 'Inter', 'type' => 'sans-serif'],
        'Poppins'            => ['file' => 'Poppins', 'type' => 'sans-serif'],
        'Montserrat'         => ['file' => 'Montserrat', 'type' => 'sans-serif'],
        'DM Sans'            => ['file' => 'DMSans', 'type' => 'sans-serif'],
        'Space Grotesk'      => ['file' => 'SpaceGrotesk', 'type' => 'sans-serif'],
        'Playfair Display'   => ['file' => 'PlayfairDisplay', 'type' => 'serif'],
        'Lora'               => ['file' => 'Lora', 'type' => 'serif'],
        'Cormorant Garamond' => ['file' => 'CormorantGaramond', 'type' => 'serif'],
    ];
}

function valid_font($f, $fallback = 'Inter')
{
    return array_key_exists((string) $f, font_list()) ? $f : $fallback;
}

/**
 * @font-face rules. $mode 'pdf' uses filesystem paths for Dompdf.
 */
function font_face_css($families, $mode = 'screen')
{
    $css = '';
    $list = font_list();
    foreach (array_unique($families) as $fam) {
        if (!isset($list[$fam])) continue;
        $file = $list[$fam]['file'];
        foreach (['Regular' => 'normal', 'Bold' => 'bold'] as $suffix => $weight) {
            $src = $mode === 'pdf'
                ? APP_ROOT . '/assets/fonts/' . $file . '-' . $suffix . '.ttf'
                : url('assets/fonts/' . $file . '-' . $suffix . '.ttf');
            $css .= "@font-face{font-family:'{$fam}';src:url('{$src}') format('truetype');font-weight:{$weight};font-style:normal;}\n";
            if ($mode !== 'pdf' && $suffix === 'Bold') {
                // map 600/800 weights for browsers
                $css .= "@font-face{font-family:'{$fam}';src:url('{$src}') format('truetype');font-weight:600 900;font-style:normal;}\n";
            }
        }
    }
    return $css;
}

function font_stack($fam)
{
    $type = font_list()[$fam]['type'] ?? 'sans-serif';
    return "'{$fam}', " . ($type === 'serif' ? "'DejaVu Serif', Georgia, serif" : "'DejaVu Sans', Arial, sans-serif");
}

/* ---------------------------------------------------------------
 *  Company settings
 * ------------------------------------------------------------- */
function company($refresh = false)
{
    static $c = null;
    if ($c === null || $refresh) {
        $c = db_row('SELECT * FROM company_settings WHERE id = 1') ?: [];
        if (!$c) {
            db_query("INSERT INTO company_settings (id, company_name) VALUES (1, 'Your Company')");
            $c = db_row('SELECT * FROM company_settings WHERE id = 1');
        }
    }
    return $c;
}

/* ---------------------------------------------------------------
 *  Media / uploads
 * ------------------------------------------------------------- */

/** Turn a stored media path (uploads/...) or URL into a URL (screen) or local path (pdf). */
function media_src($path, $mode = 'screen')
{
    $path = trim((string) $path);
    if ($path === '') return '';
    if (preg_match('#^(https?:)?//#i', $path) || strpos($path, 'data:') === 0) {
        // Absolute URL — if it points to our own uploads, map to local file for PDF.
        if ($mode === 'pdf' && strpos($path, APP_URL . '/uploads/') === 0) {
            return APP_ROOT . substr($path, strlen(APP_URL));
        }
        return $path;
    }
    $path = ltrim($path, '/');
    if (strpos($path, 'uploads/') !== 0 && strpos($path, 'assets/') !== 0) {
        return '';
    }
    if ($mode === 'pdf') {
        $full = APP_ROOT . '/' . $path;
        return is_file($full) ? $full : '';
    }
    return url($path);
}

/**
 * Handle an uploaded image: validates, resizes (max $maxW px) and compresses.
 * Returns relative path (uploads/...) or throws RuntimeException.
 */
function handle_image_upload($file, $folder = 'images', $maxW = 1800)
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Upload failed (error code ' . ($file['error'] ?? '?') . ').');
    }
    if ($file['size'] > 10 * 1024 * 1024) {
        throw new RuntimeException('Image is larger than 10 MB.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'image/svg+xml' => 'svg'];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Only JPG, PNG, GIF, WEBP or SVG images are allowed.');
    }
    $ext = $allowed[$mime];
    $sub = $folder . '/' . date('Y/m');
    $dir = APP_ROOT . '/uploads/' . $sub;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        throw new RuntimeException('Upload folder is not writable.');
    }
    $name = date('His') . '-' . bin2hex(random_bytes(6));

    if ($ext === 'svg') {
        $svg = file_get_contents($file['tmp_name']);
        if (preg_match('/<script|on\w+\s*=|javascript:/i', $svg)) {
            throw new RuntimeException('SVG contains scripts and was rejected.');
        }
        file_put_contents("$dir/$name.svg", $svg);
        return "uploads/$sub/$name.svg";
    }

    // WEBP is converted to PNG/JPG because Dompdf has limited WEBP support.
    if (function_exists('imagecreatefromstring')) {
        $img = @imagecreatefromstring(file_get_contents($file['tmp_name']));
        if ($img) {
            $w = imagesx($img);
            $h = imagesy($img);
            $hasAlpha = in_array($ext, ['png', 'gif', 'webp'], true);
            if ($w > $maxW) {
                $nh = (int) round($h * $maxW / $w);
                $dst = imagecreatetruecolor($maxW, $nh);
                if ($hasAlpha) {
                    imagealphablending($dst, false);
                    imagesavealpha($dst, true);
                }
                imagecopyresampled($dst, $img, 0, 0, 0, 0, $maxW, $nh, $w, $h);
                imagedestroy($img);
                $img = $dst;
            }
            if ($hasAlpha) {
                imagesavealpha($img, true);
                imagepng($img, "$dir/$name.png", 7);
                imagedestroy($img);
                return "uploads/$sub/$name.png";
            }
            imageinterlace($img, true);
            imagejpeg($img, "$dir/$name.jpg", 82);
            imagedestroy($img);
            return "uploads/$sub/$name.jpg";
        }
    }
    if (!move_uploaded_file($file['tmp_name'], "$dir/$name.$ext")) {
        throw new RuntimeException('Could not save the uploaded file.');
    }
    return "uploads/$sub/$name.$ext";
}

/**
 * Crop a local image to an exact aspect ratio (object-fit: cover substitute,
 * so the browser and the PDF engine show the same crop). Returns new relative path.
 */
function image_cover($path, $w, $h)
{
    $path = (string) $path;
    if ($path === '' || !function_exists('imagecreatefromstring')) return $path;
    $local = null;
    if (strpos($path, APP_URL . '/uploads/') === 0) {
        $path = substr($path, strlen(APP_URL) + 1);
    }
    if (strpos($path, 'uploads/') === 0 && is_file(APP_ROOT . '/' . $path)) {
        $local = APP_ROOT . '/' . $path;
    }
    if (!$local || preg_match('/\.svg$/i', $local)) return $path;

    $key = 'uploads/cache/' . substr(md5($path . filemtime($local)), 0, 16) . "-{$w}x{$h}.jpg";
    if (is_file(APP_ROOT . '/' . $key)) return $key;

    $src = @imagecreatefromstring(file_get_contents($local));
    if (!$src) return $path;
    $sw = imagesx($src);
    $sh = imagesy($src);
    $ratio = $w / $h;
    if ($sw / $sh > $ratio) {
        $cw = (int) round($sh * $ratio);
        $ch = $sh;
        $sx = (int) round(($sw - $cw) / 2);
        $sy = 0;
    } else {
        $cw = $sw;
        $ch = (int) round($sw / $ratio);
        $sx = 0;
        $sy = (int) round(($sh - $ch) / 2);
    }
    // render at 2x for crispness, capped
    $scale = min(2, max(1, $cw / $w));
    $tw = (int) round($w * $scale);
    $th = (int) round($h * $scale);
    $dst = imagecreatetruecolor($tw, $th);
    imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
    imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $tw, $th, $cw, $ch);
    @mkdir(APP_ROOT . '/uploads/cache', 0755, true);
    imagejpeg($dst, APP_ROOT . '/' . $key, 84);
    imagedestroy($src);
    imagedestroy($dst);
    return $key;
}

/** Save a base64 PNG (signature pad) to uploads/signatures. */
function save_data_png($dataUrl, $folder = 'signatures')
{
    if (!preg_match('#^data:image/png;base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) {
        return null;
    }
    $bin = base64_decode($m[1], true);
    if ($bin === false || strlen($bin) > 2 * 1024 * 1024) return null;
    if (function_exists('imagecreatefromstring') && !@imagecreatefromstring($bin)) return null;
    $dir = APP_ROOT . '/uploads/' . $folder;
    @mkdir($dir, 0755, true);
    $name = date('Ymd-His') . '-' . bin2hex(random_bytes(5)) . '.png';
    file_put_contents("$dir/$name", $bin);
    return "uploads/$folder/$name";
}

function status_meta()
{
    return [
        'draft'    => ['label' => 'Draft',    'color' => 'secondary', 'icon' => 'bi-pencil'],
        'sent'     => ['label' => 'Sent',     'color' => 'primary',   'icon' => 'bi-send'],
        'viewed'   => ['label' => 'Viewed',   'color' => 'info',      'icon' => 'bi-eye'],
        'accepted' => ['label' => 'Accepted', 'color' => 'success',   'icon' => 'bi-check-circle'],
        'rejected' => ['label' => 'Rejected', 'color' => 'danger',    'icon' => 'bi-x-circle'],
        'expired'  => ['label' => 'Expired',  'color' => 'warning',   'icon' => 'bi-hourglass-bottom'],
    ];
}

function status_badge($status)
{
    $m = status_meta()[$status] ?? ['label' => ucfirst($status), 'color' => 'secondary', 'icon' => 'bi-circle'];
    return '<span class="badge-status bs-' . e($status) . '"><i class="bi ' . $m['icon'] . '"></i> ' . e($m['label']) . '</span>';
}

/** Allow-list HTML sanitiser for rich text fields. */
function clean_html($html)
{
    $html = (string) $html;
    if ($html === '') return '';
    // strip dangerous elements entirely
    $html = preg_replace('#<(script|style|iframe|object|embed|form|input|button|textarea|select|meta|link|base)[^>]*>.*?</\1>#is', '', $html);
    $html = preg_replace('#<(script|style|iframe|object|embed|form|input|button|textarea|select|meta|link|base)[^>]*/?>#is', '', $html);
    $html = strip_tags($html, '<p><br><b><strong><i><em><u><s><strike><span><a><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><font><sub><sup><hr><div><table><thead><tbody><tr><td><th><img><small><mark><code><pre>');
    // remove event handlers & javascript: urls
    $html = preg_replace('/\s+on[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
    $html = preg_replace('/(href|src)\s*=\s*(["\'])\s*(javascript|vbscript|data:text)[^"\']*\2/i', '$1="#"', $html);
    return $html;
}

function log_activity($proposalId, $action, $details = '', $userId = null)
{
    db_insert('proposal_activity', [
        'proposal_id' => $proposalId,
        'user_id'     => $userId ?? (current_user()['id'] ?? null),
        'action'      => $action,
        'details'     => mb_substr($details, 0, 250),
        'ip_address'  => client_ip(),
        'created_at'  => date('Y-m-d H:i:s'),
    ]);
}

function slugify($s)
{
    $s = preg_replace('/[^A-Za-z0-9]+/', '_', $s);
    return trim($s, '_') ?: 'Proposal';
}
