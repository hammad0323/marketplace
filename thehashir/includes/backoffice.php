<?php
/**
 * Shared layout and helpers for the Super Admin panel and the mosque/organization portal.
 */

function bo_nav($area)
{
    if ($area === 'admin') {
        $items = array(
            array('dashboard', 'Dashboard', 'bi-speedometer2', ''),
            array('approvals', 'Approvals', 'bi-check2-square', 'approvals'),
            array('-', 'Directory'),
            array('venues?type=mosque', 'Mosques', 'bi-building', 'venues'),
            array('venues?type=org', 'Organizations', 'bi-diagram-3', 'venues'),
            array('details?gc=2', 'Programs', 'bi-journal-bookmark', 'details'),
            array('details?gc=3', 'Services', 'bi-hand-thumbs-up', 'details'),
            array('details?gc=1', 'Events', 'bi-calendar-event', 'details'),
            array('announcements', 'Announcements', 'bi-megaphone', 'announcements'),
            array('locations', 'Locations', 'bi-geo-alt', 'locations'),
            array('-', 'Prayer'),
            array('prayer', 'Prayer times', 'bi-clock', 'prayer'),
            array('jummah', 'Jumu\'ah', 'bi-people', 'prayer'),
            array('-', 'Content'),
            array('blogs', 'Blogs', 'bi-newspaper', 'blogs'),
            array('pages', 'Pages', 'bi-file-earmark-text', 'pages'),
            array('-', 'System'),
            array('users', 'Users', 'bi-person-gear', 'users'),
            array('settings', 'Settings', 'bi-sliders', 'settings'),
            array('subscribers', 'Subscribers', 'bi-envelope-paper', 'settings'),
            array('logs', 'Activity log', 'bi-clock-history', 'logs'),
        );
        $base = ADMIN_PATH;
    } else {
        $v = current_venue();
        $isMosque = $v && (int)$v['role_id'] === 1;
        $items = array(
            array('dashboard', 'Dashboard', 'bi-speedometer2', ''),
            array('profile', 'Profile', 'bi-building', ''),
            array('details?gc=2', 'Programs', 'bi-journal-bookmark', ''),
            array('details?gc=3', 'Services', 'bi-hand-thumbs-up', ''),
            array('details?gc=1', 'Events', 'bi-calendar-event', ''),
            array('announcements', 'Announcements', 'bi-megaphone', ''),
        );
        if ($isMosque) {
            $items[] = array('prayer', 'Prayer times', 'bi-clock', '');
            $items[] = array('jummah', 'Jumu\'ah', 'bi-people', '');
        }
        $items[] = array('account', 'Password', 'bi-key', '');
        $base = PORTAL_PATH;
    }
    return array($items, $base);
}

function bo_current_key()
{
    $p = current_path();
    $parts = explode('/', trim($p, '/'));
    $key = isset($parts[1]) ? $parts[1] : 'dashboard';
    $qs = '';
    if (isset($_GET['type']) && $key === 'venues') {
        $qs = '?type=' . $_GET['type'];
    } elseif (isset($_GET['gc']) && $key === 'details') {
        $qs = '?gc=' . (int)$_GET['gc'];
    }
    return $key . $qs;
}

function bo_start($area, $title, $opts = array())
{
    $user = $area === 'admin' ? current_admin() : current_venue();
    list($items, $base) = bo_nav($area);
    $cur = isset($opts['nav']) ? $opts['nav'] : bo_current_key();
    $pending = 0;
    if ($area === 'admin' && $user) {
        $pending = approvals_count();
    }
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e($title) ?> · <?= $area === 'admin' ? 'Super Admin' : 'Portal' ?> · <?= e(SITE_NAME) ?></title>
<link rel="icon" href="<?= e(media('images/logos/hashirLogosite.png')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap/bootstrap.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/select2/select2.min.css')) ?>">
<link rel="stylesheet" href="<?= e(asset('vendor/sweetalert2/sweetalert2.min.css')) ?>">
<?php if (!empty($opts['leaflet'])): ?><link rel="stylesheet" href="<?= e(asset('vendor/leaflet/leaflet.css')) ?>"><?php endif; ?>
<?php if (!empty($opts['editor'])): ?><link rel="stylesheet" href="<?= e(asset('vendor/quill/quill.snow.css')) ?>"><?php endif; ?>
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="bo bo-<?= e($area) ?>" data-base="<?= e(BASE_PATH) ?>" data-bo-base="<?= e(url($base)) ?>">
<?php if (!$user || !empty($opts['bare'])): ?>
<main class="bo-auth">
<?php else: ?>
<div class="bo-shell">
  <aside class="bo-side offcanvas-lg offcanvas-start" id="boSide" tabindex="-1" aria-label="Back-office navigation">
    <div class="bo-brand">
      <a href="<?= e(url($base)) ?>"><?php logo_img('bo-logo'); ?></a>
      <span class="bo-area"><?= $area === 'admin' ? 'Super Admin' : 'Portal' ?></span>
      <button type="button" class="btn-close d-lg-none ms-auto" data-bs-dismiss="offcanvas" data-bs-target="#boSide" aria-label="Close menu"></button>
    </div>
    <nav class="bo-nav">
      <?php foreach ($items as $it):
          if ($it[0] === '-') { echo '<div class="bo-nav-label">' . e($it[1]) . '</div>'; continue; }
          if ($area === 'admin' && $it[3] !== '' && !admin_can($it[3])) { continue; }
          $active = ($cur === $it[0]) || ($cur !== '' && strpos($it[0], '?') === false && strpos($cur, $it[0]) === 0 && strpos($cur, '?') === false);
      ?>
        <a class="bo-nav-link<?= $active ? ' active' : '' ?>" href="<?= e(url($base . '/' . $it[0])) ?>"<?= $active ? ' aria-current="page"' : '' ?>>
          <i class="bi <?= e($it[2]) ?>" aria-hidden="true"></i><span><?= e($it[1]) ?></span>
          <?php if ($it[0] === 'approvals' && $pending): ?><span class="badge rounded-pill text-bg-warning ms-auto"><?= (int)$pending ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>
  </aside>
  <div class="bo-main">
    <header class="bo-top">
      <button class="btn btn-light d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#boSide" aria-controls="boSide" aria-label="Open menu"><i class="bi bi-list" aria-hidden="true"></i></button>
      <h1 class="bo-title"><?= e($title) ?></h1>
      <div class="ms-auto d-flex align-items-center gap-2">
        <?php if ($area === 'venue'): $vv = get_venue_any($user['id']); if ($vv): ?>
          <a class="btn btn-sm btn-outline-secondary d-none d-md-inline-flex" href="<?= e(url(venue_url($vv))) ?>" target="_blank" rel="noopener"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>View public profile</a>
        <?php endif; endif; ?>
        <a class="btn btn-sm btn-outline-secondary d-none d-md-inline-flex" href="<?= e(url('/')) ?>" target="_blank" rel="noopener"><i class="bi bi-globe2 me-1" aria-hidden="true"></i>Website</a>
        <div class="dropdown">
          <button class="btn btn-sm btn-light dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="bi bi-person-circle me-1" aria-hidden="true"></i><span class="d-none d-sm-inline"><?= e(excerpt($user['name'], 28)) ?></span></button>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="<?= e(url($base . '/account')) ?>"><i class="bi bi-key me-2" aria-hidden="true"></i>Change password</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><form method="post" action="<?= e(url($base . '/logout')) ?>"><?= csrf_field() ?><button class="dropdown-item" type="submit"><i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Log out</button></form></li>
          </ul>
        </div>
      </div>
    </header>
    <div class="bo-content">
<?php endif; ?>
    <?php foreach (flashes() as $f): ?>
      <div class="alert alert-<?= e($f[0]) ?> alert-dismissible fade show" role="alert"><?= e($f[1]) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>
    <?php endforeach; ?>
<?php
    $GLOBALS['BO_OPTS'] = $opts;
    $GLOBALS['BO_USER'] = $user;
}

function bo_end($extra = '')
{
    $opts = isset($GLOBALS['BO_OPTS']) ? $GLOBALS['BO_OPTS'] : array();
    $user = isset($GLOBALS['BO_USER']) ? $GLOBALS['BO_USER'] : null;
    if (!$user || !empty($opts['bare'])) {
        echo '</main>';
    } else {
        echo '</div></div></div>';
    }
    ?>
<script src="<?= e(asset('vendor/jquery/jquery.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/select2/select2.min.js')) ?>"></script>
<script src="<?= e(asset('vendor/sweetalert2/sweetalert2.min.js')) ?>"></script>
<?php if (!empty($opts['leaflet'])): ?><script src="<?= e(asset('vendor/leaflet/leaflet.js')) ?>"></script><?php endif; ?>
<?php if (!empty($opts['editor'])): ?><script src="<?= e(asset('vendor/quill/quill.js')) ?>"></script><?php endif; ?>
<?php if (!empty($opts['sortable'])): ?><script src="<?= e(asset('vendor/sortable/Sortable.min.js')) ?>"></script><?php endif; ?>
<script>window.TH_MAP = <?= json_encode(array('tiles' => setting('map_tiles'), 'attribution' => setting('map_attribution'), 'center' => setting('map_center')), JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="<?= e(asset('js/admin.js')) ?>"></script>
<?= $extra ?>
</body>
</html>
<?php
}

/** Venue row regardless of verify status (back office). */
function get_venue_any($id)
{
    return db_one('SELECT ' . VENUE_COLS . ', v.username, v.comment, v.cover_alt_status FROM th_venue v ' . VENUE_JOINS . ' WHERE v.venue_id = ?', 'i', array((int)$id));
}

function approvals_count()
{
    static $n = null;
    if ($n === null) {
        $n = (int)db_val('SELECT COUNT(*) FROM th_details WHERE status_verify = 0')
            + (int)db_val('SELECT COUNT(*) FROM th_venue WHERE verify_status = 0')
            + count(storage_list('pending'));
    }
    return $n;
}

// ---------------------------------------------------------------------------
// Form helpers
// ---------------------------------------------------------------------------
function post($key, $default = '')
{
    if (!isset($_POST[$key])) {
        return $default;
    }
    return is_string($_POST[$key]) ? trim($_POST[$key]) : $_POST[$key];
}

function post_int($key, $default = 0)
{
    return isset($_POST[$key]) && is_numeric($_POST[$key]) ? (int)$_POST[$key] : $default;
}

function post_ids($key)
{
    $v = isset($_POST[$key]) ? (array)$_POST[$key] : array();
    return array_values(array_unique(array_filter(array_map('intval', $v))));
}

function sel($a, $b)
{
    return (string)$a === (string)$b ? ' selected' : '';
}

function chk($cond)
{
    return $cond ? ' checked' : '';
}

function bo_url($area, $path)
{
    return url(($area === 'admin' ? ADMIN_PATH : PORTAL_PATH) . '/' . ltrim($path, '/'));
}

function bo_redirect($area, $path)
{
    header('Location: ' . bo_url($area, $path), true, 303);
    exit;
}

/** Simple pager for back-office lists (keeps the query string). */
function bo_pager($total, $per, $page)
{
    $pages = (int)ceil($total / max(1, $per));
    if ($pages <= 1) {
        return;
    }
    echo '<nav aria-label="Pages"><ul class="pagination pagination-sm flex-wrap mb-0">';
    $start = max(1, $page - 3);
    $end = min($pages, $page + 3);
    if ($page > 1) {
        echo '<li class="page-item"><a class="page-link" href="' . e(query_with(array('page' => $page - 1))) . '">‹</a></li>';
    }
    if ($start > 1) {
        echo '<li class="page-item"><a class="page-link" href="' . e(query_with(array('page' => 1))) . '">1</a></li><li class="page-item disabled"><span class="page-link">…</span></li>';
    }
    for ($i = $start; $i <= $end; $i++) {
        echo '<li class="page-item' . ($i === $page ? ' active' : '') . '"><a class="page-link" href="' . e(query_with(array('page' => $i))) . '">' . $i . '</a></li>';
    }
    if ($end < $pages) {
        echo '<li class="page-item disabled"><span class="page-link">…</span></li><li class="page-item"><a class="page-link" href="' . e(query_with(array('page' => $pages))) . '">' . $pages . '</a></li>';
    }
    if ($page < $pages) {
        echo '<li class="page-item"><a class="page-link" href="' . e(query_with(array('page' => $page + 1))) . '">›</a></li>';
    }
    echo '</ul></nav>';
}

function status_badge($ok, $yes = 'Published', $no = 'Pending')
{
    return $ok ? '<span class="badge text-bg-success-subtle text-success-emphasis">' . e($yes) . '</span>' : '<span class="badge text-bg-warning-subtle text-warning-emphasis">' . e($no) . '</span>';
}

/** Countries / states / cities for selects. */
function all_states()
{
    return db_all('SELECT s.state_id, s.state_title, co.country_title FROM th_state s LEFT JOIN th_country co ON co.country_id = s.country_id ORDER BY co.country_id, s.state_title');
}

function city_option($cityId)
{
    if (!$cityId) {
        return null;
    }
    return get_city($cityId);
}

/** Select2 city picker (AJAX /api/cities?all=1 returns every city, not only those with listings). */
function bo_city_select($name, $cityId, $required = false, $id = '')
{
    $c = city_option($cityId);
    $id = $id !== '' ? $id : $name;
    echo '<select class="form-select" name="' . e($name) . '" id="' . e($id) . '" data-bo-city' . ($required ? ' required' : '') . '>';
    echo '<option value="">Choose a city…</option>';
    if ($c) {
        echo '<option value="' . (int)$c['city_id'] . '" selected>' . e($c['city_title'] . ', ' . $c['state_title']) . '</option>';
    }
    echo '</select>';
}

/** Select2 venue picker (AJAX). $role: 1 mosques, 'org', or '' all. */
function bo_venue_select($name, $venueId, $role = '', $required = false, $id = '', $placeholder = 'Search mosques & organizations…')
{
    $id = $id !== '' ? $id : $name;
    echo '<select class="form-select" name="' . e($name) . '" id="' . e($id) . '" data-bo-venue data-role="' . e($role) . '" data-placeholder="' . e($placeholder) . '"' . ($required ? ' required' : '') . '>';
    echo '<option value="">' . e($placeholder) . '</option>';
    if ($venueId) {
        $v = db_one('SELECT v.venue_id, v.venue_title, c.city_title FROM th_venue v LEFT JOIN th_city c ON c.city_id = v.city_id WHERE v.venue_id = ?', 'i', array((int)$venueId));
        if ($v) {
            echo '<option value="' . (int)$v['venue_id'] . '" selected>' . e(trim($v['venue_title']) . ($v['city_title'] ? ' — ' . $v['city_title'] : '')) . '</option>';
        }
    }
    echo '</select>';
}

function rich_editor($name, $value, $id = '', $height = 220)
{
    $id = $id !== '' ? $id : 'ed_' . preg_replace('/[^a-z0-9_]/i', '_', $name);
    echo '<div class="rich-editor" data-rich-editor data-target="' . e($id) . '" style="--h:' . (int)$height . 'px"></div>';
    echo '<textarea class="d-none" name="' . e($name) . '" id="' . e($id) . '">' . e($value) . '</textarea>';
}

/** Card layout for login / reset pages. */
function auth_card_start($area, $title, $sub = '')
{
    bo_start($area, $title, array('bare' => true));
    echo '<div class="auth-card"><a href="' . e(url('/')) . '" class="d-inline-block mb-4">';
    logo_img('bo-logo');
    echo '</a><h1 class="h4 fw-bold mb-1">' . e($title) . '</h1>';
    if ($sub !== '') {
        echo '<p class="text-muted mb-4">' . e($sub) . '</p>';
    }
}

function auth_card_end($links = array())
{
    if ($links) {
        echo '<div class="auth-links">';
        foreach ($links as $l) {
            echo '<a href="' . e($l[1]) . '">' . e($l[0]) . '</a>';
        }
        echo '</div>';
    }
    echo '</div>';
    bo_end();
}

/** Send a plain-text email from MAIL_FROM. */
function send_mail($to, $subject, $body)
{
    $headers = 'From: ' . SITE_NAME . ' <' . MAIL_FROM . ">\r\n" . 'Content-Type: text/plain; charset=UTF-8' . "\r\n";
    $subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    return @mail($to, $subject, $body, $headers, '-f' . MAIL_FROM);
}

/** Venue id/title pairs in the given order, any status (for select2 pre-selection). */
function venues_by_ids_any($ids)
{
    $ids = array_values(array_unique(array_filter(array_map('intval', (array)$ids))));
    if (!$ids) {
        return array();
    }
    list($ph, $t, $vals) = db_in_ints($ids);
    $by = array();
    foreach (db_all("SELECT venue_id, venue_title, address_iframe FROM th_venue WHERE venue_id IN ($ph)", $t, $vals) as $r) {
        $by[(int)$r['venue_id']] = $r;
    }
    $out = array();
    foreach ($ids as $id) {
        if (isset($by[$id])) {
            $out[] = $by[$id];
        }
    }
    return $out;
}
