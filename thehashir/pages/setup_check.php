<?php
/**
 * /setup-check?key=SETUP_KEY — deployment self-test for cPanel.
 * Disabled unless SETUP_KEY is set in config/config.php. Read-only.
 */
$key = isset($_GET['key']) ? (string)$_GET['key'] : '';
if (SETUP_KEY === '' || !hash_equals(SETUP_KEY, $key)) {
    route_page('404');
}
header('X-Robots-Tag: noindex, nofollow', true);
header('Cache-Control: no-store');

$checks = array();
function chk(&$checks, $label, $ok, $detail = '', $warnOnly = false)
{
    $checks[] = array('label' => $label, 'state' => $ok ? 'ok' : ($warnOnly ? 'warn' : 'fail'), 'detail' => $detail);
}

chk($checks, 'PHP version', version_compare(PHP_VERSION, '7.2.0', '>='), PHP_VERSION . ' (7.2 or newer needed)');
foreach (array('mysqli', 'mbstring', 'json', 'dom', 'intl') as $ext) {
    chk($checks, 'PHP extension: ' . $ext, extension_loaded($ext), extension_loaded($ext) ? 'loaded' : 'missing', in_array($ext, array('dom', 'intl'), true));
}
chk($checks, 'MySQLi native driver (mysqlnd)', function_exists('mysqli_stmt_get_result'), function_exists('mysqli_stmt_get_result') ? 'available' : 'not available — the site falls back to a slower method; still works', true);
chk($checks, 'Clean URLs (mod_rewrite)', true, 'this page opened at ' . e(current_path()) . ' through index.php');
chk($checks, 'Installed in folder', true, BASE_PATH === '' ? '(web root)' : BASE_PATH);
chk($checks, 'Beta mode', true, BETA_MODE ? 'ON — pages are noindex and show the beta bar' : 'OFF — site is indexable');
chk($checks, 'Canonical / live address (SITE_URL)', preg_match('#^https://#', SITE_URL) === 1, SITE_URL);
chk($checks, 'Debug output', !DEBUG, DEBUG ? 'ON — turn off before inviting testers' : 'off', true);
chk($checks, 'mail() function', function_exists('mail'), function_exists('mail') ? 'available (send yourself a test from the footer form)' : 'disabled by host — contact form cannot send', false);
chk($checks, 'config/ protected (check by hand)', false, 'open <a href="' . e(url('/config/config.php')) . '">' . e(url('/config/config.php')) . '</a> — it must show 403 Forbidden. If it shows a blank page instead, the .htaccess file was not uploaded.', true);

$dbOk = false;
try {
    $counts = array(
        'Mosques (th_venue role 1)' => (int)db_val('SELECT COUNT(*) FROM th_venue WHERE role_id = 1 AND verify_status = 1'),
        'Organizations' => (int)db_val('SELECT COUNT(*) FROM th_venue WHERE role_id <> 1 AND verify_status = 1'),
        'Programs & services (th_details)' => (int)db_val('SELECT COUNT(*) FROM th_details WHERE status_verify = 1'),
        'Daily prayer rows for today' => (int)db_val('SELECT COUNT(*) FROM prayer_timings WHERE georgian_date = ?', 's', array(today_in(DEFAULT_TIMEZONE))),
        'Mosques with current Jumu\'ah' => (int)db_val('SELECT COUNT(DISTINCT venue_id) FROM th_jummah_prayers WHERE CURDATE() BETWEEN from_date AND to_date'),
        'Blogs' => (int)db_val('SELECT COUNT(*) FROM blogs'),
    );
    $dbOk = true;
    chk($checks, 'Database connection', true, e(DB_NAME) . ' on ' . e(DB_HOST) . ' · MySQL ' . e(db()->server_info));
    foreach ($counts as $label => $n) {
        chk($checks, $label, $n > 0, number_format($n), true);
    }
} catch (Exception $ex) {
    chk($checks, 'Database connection', false, 'failed — check DB_HOST, DB_USER, DB_PASS, DB_NAME');
}
$logoUrl = media('images/logos/hashirLogo.png');
$sample = $dbOk ? db_one("SELECT venue_id, role_id, url, venue_title, profile_image, back_image FROM th_venue WHERE role_id = 1 AND verify_status = 1 AND back_image <> '' ORDER BY venue_id LIMIT 1") : null;
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex, nofollow">
<title>Setup check · The Hashir</title>
<style>
body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;max-width:860px;margin:2rem auto;padding:0 1rem;color:#1d2327}
h1{font-size:1.4rem}table{width:100%;border-collapse:collapse;font-size:.95rem}td{padding:.55rem .6rem;border-bottom:1px solid #e3e7ea;vertical-align:top}
.ok{color:#0a7a3f;font-weight:700}.warn{color:#9a6a00;font-weight:700}.fail{color:#b3261e;font-weight:700}
.imgs{display:flex;gap:1rem;align-items:center;flex-wrap:wrap;margin:1rem 0}.imgs img{max-height:90px;border:1px solid #e3e7ea;border-radius:8px;padding:4px;background:#fff}
code{background:#f4f6f6;padding:.1rem .3rem;border-radius:4px}a{color:#005957}
</style></head><body>
<h1>The Hashir — setup check</h1>
<table>
<?php foreach ($checks as $c): ?>
<tr><td class="<?= $c['state'] ?>"><?= $c['state'] === 'ok' ? '✓' : ($c['state'] === 'warn' ? '!' : '✗') ?></td><td><?= e($c['label']) ?></td><td><?= $c['detail'] ?></td></tr>
<?php endforeach; ?>
</table>
<h2 style="font-size:1.1rem;margin-top:2rem">Images from your existing uploads</h2>
<p>Both images below must be visible. If they are broken, set <code>MEDIA_URL</code> in the config.</p>
<div class="imgs">
  <img src="<?= e($logoUrl) ?>" alt="Logo test"> <code><?= e($logoUrl) ?></code>
  <?php if ($sample): ?><img src="<?= e(venue_cover($sample)) ?>" alt="Cover test"> <code><?= e(venue_cover($sample)) ?></code><?php endif; ?>
</div>
<h2 style="font-size:1.1rem;margin-top:2rem">Pages to open</h2>
<ul>
  <li><a href="<?= e(url('/')) ?>">Home</a> · <a href="<?= e(url('/mosques')) ?>">Mosques</a> · <a href="<?= e(url('/prayer-times')) ?>">Prayer times</a> · <a href="<?= e(url('/program-catalog')) ?>">Programs</a></li>
  <?php if ($sample): ?><li><a href="<?= e(url(venue_url($sample))) ?>">A mosque profile</a></li><?php endif; ?>
  <li><a href="<?= e(url('/config/config.php')) ?>">config/config.php</a> — must say <strong>Forbidden</strong></li>
</ul>
<p style="color:#5f6b75;font-size:.9rem">When everything is green, clear <code>SETUP_KEY</code> in the config to switch this page off.</p>
</body></html>
