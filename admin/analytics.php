<?php
require __DIR__ . '/../config/config.php';
require_admin_page();

$from = $_GET['from'] ?? date('Y-m-d', strtotime('-29 days'));
$to = $_GET['to'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d', strtotime('-29 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) $to = date('Y-m-d');

$db = db();

$stmt = mysqli_prepare($db, 'SELECT COUNT(*) AS total, COUNT(DISTINCT visitor_hash) AS uniques FROM page_visits WHERE visit_date BETWEEN ? AND ?');
mysqli_stmt_bind_param($stmt, 'ss', $from, $to);
mysqli_stmt_execute($stmt);
$totals = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($db, 'SELECT visit_date, COUNT(*) AS c FROM page_visits WHERE visit_date BETWEEN ? AND ? GROUP BY visit_date ORDER BY visit_date ASC');
mysqli_stmt_bind_param($stmt, 'ss', $from, $to);
mysqli_stmt_execute($stmt);
$byDay = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);
$maxDay = max(array_merge(array_column($byDay, 'c'), [1]));

$stmt = mysqli_prepare($db, 'SELECT source_type, source_label, COUNT(*) AS c FROM page_visits WHERE visit_date BETWEEN ? AND ? GROUP BY source_type, source_label ORDER BY c DESC LIMIT 10');
mysqli_stmt_bind_param($stmt, 'ss', $from, $to);
mysqli_stmt_execute($stmt);
$sources = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);
$maxSource = max(array_merge(array_column($sources, 'c'), [1]));

$stmt = mysqli_prepare($db, 'SELECT device_type, COUNT(*) AS c FROM page_visits WHERE visit_date BETWEEN ? AND ? GROUP BY device_type ORDER BY c DESC');
mysqli_stmt_bind_param($stmt, 'ss', $from, $to);
mysqli_stmt_execute($stmt);
$devices = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);
$totalDeviceVisits = array_sum(array_column($devices, 'c')) ?: 1;

$stmt = mysqli_prepare($db, 'SELECT browser, COUNT(*) AS c FROM page_visits WHERE visit_date BETWEEN ? AND ? GROUP BY browser ORDER BY c DESC LIMIT 6');
mysqli_stmt_bind_param($stmt, 'ss', $from, $to);
mysqli_stmt_execute($stmt);
$browsers = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);
$maxBrowser = max(array_merge(array_column($browsers, 'c'), [1]));

$stmt = mysqli_prepare($db, "SELECT search_keyword, COUNT(*) AS c FROM page_visits WHERE visit_date BETWEEN ? AND ? AND search_keyword IS NOT NULL AND search_keyword != '' GROUP BY search_keyword ORDER BY c DESC LIMIT 15");
mysqli_stmt_bind_param($stmt, 'ss', $from, $to);
mysqli_stmt_execute($stmt);
$keywords = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

$stmt = mysqli_prepare($db, 'SELECT url, COUNT(*) AS c FROM page_visits WHERE visit_date BETWEEN ? AND ? GROUP BY url ORDER BY c DESC LIMIT 10');
mysqli_stmt_bind_param($stmt, 'ss', $from, $to);
mysqli_stmt_execute($stmt);
$topPages = mysqli_stmt_get_result($stmt)->fetch_all(MYSQLI_ASSOC);
mysqli_stmt_close($stmt);
$maxPage = max(array_merge(array_column($topPages, 'c'), [1]));

$sourceMeta = [
    'direct' => ['ri-link', 'Direct'], 'search' => ['ri-search-line', 'Search'],
    'social' => ['ri-share-forward-line', 'Social'], 'referral' => ['ri-external-link-line', 'Referral'],
    'internal' => ['ri-home-4-line', 'Internal'], 'campaign' => ['ri-megaphone-line', 'Campaign'],
];
$deviceMeta = [
    'desktop' => ['ri-computer-line', 'Desktop'], 'mobile' => ['ri-smartphone-line', 'Mobile'],
    'tablet' => ['ri-tablet-line', 'Tablet'],
];

$pageTitle = 'Analytics';
$heading = 'Analytics';
require __DIR__ . '/includes/header.php';
?>
<form method="get" style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;margin-bottom:24px;">
    <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">From</label>
        <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
    </div>
    <div class="form-group" style="margin-bottom:0;">
        <label class="form-label">To</label>
        <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
    </div>
    <button type="submit" class="btn btn-primary">Apply</button>
</form>

<div class="grid grid-2" style="gap:16px;margin-bottom:24px;">
    <div class="card analytics-stat-card" data-reveal>
        <div class="value"><?= number_format((int) $totals['total']) ?></div>
        <div class="label">Total Page Views</div>
    </div>
    <div class="card analytics-stat-card" data-reveal>
        <div class="value"><?= number_format((int) $totals['uniques']) ?></div>
        <div class="label">Unique Visitors (approx.)</div>
    </div>
</div>

<div class="card table-card" style="padding:24px;margin-bottom:24px;" data-reveal>
    <h3 style="font-size:15px;margin-bottom:16px;">Visits per Day</h3>
    <?php if (!$byDay): ?>
    <p style="color:var(--color-text-muted);font-size:13.5px;">No visits recorded in this range yet.</p>
    <?php else: foreach ($byDay as $d): ?>
    <div class="analytics-bar-row">
        <span class="bar-label"><?= e(format_date($d['visit_date'])) ?></span>
        <div class="analytics-bar-track"><div class="analytics-bar-fill" style="width:<?= round(($d['c'] / $maxDay) * 100) ?>%;"></div></div>
        <span class="bar-count"><?= (int) $d['c'] ?></span>
    </div>
    <?php endforeach; endif; ?>
</div>

<div class="grid grid-2" style="gap:16px;margin-bottom:24px;">
    <div class="card table-card" style="padding:24px;" data-reveal>
        <h3 style="font-size:15px;margin-bottom:16px;">Where Visits Come From</h3>
        <?php if (!$sources): ?>
        <p style="color:var(--color-text-muted);font-size:13.5px;">No data yet.</p>
        <?php else: foreach ($sources as $s):
            $meta = $sourceMeta[$s['source_type']] ?? ['ri-question-line', ucfirst($s['source_type'])];
            $label = $s['source_label'] ?: $meta[1];
        ?>
        <div class="analytics-bar-row" style="grid-template-columns:30px 1fr 120px 40px;">
            <span class="analytics-source-icon"><i class="<?= e($meta[0]) ?>"></i></span>
            <span class="bar-label" style="white-space:normal;"><?= e($label) ?></span>
            <div class="analytics-bar-track"><div class="analytics-bar-fill" style="width:<?= round(($s['c'] / $maxSource) * 100) ?>%;"></div></div>
            <span class="bar-count"><?= (int) $s['c'] ?></span>
        </div>
        <?php endforeach; endif; ?>
    </div>

    <div class="card table-card" style="padding:24px;" data-reveal>
        <h3 style="font-size:15px;margin-bottom:16px;">Devices</h3>
        <?php if (!$devices): ?>
        <p style="color:var(--color-text-muted);font-size:13.5px;">No data yet.</p>
        <?php else: foreach ($devices as $d):
            $meta = $deviceMeta[$d['device_type']] ?? ['ri-question-line', ucfirst($d['device_type'])];
            $pct = round(($d['c'] / $totalDeviceVisits) * 100);
        ?>
        <div class="analytics-bar-row" style="grid-template-columns:30px 1fr 40px;">
            <span class="analytics-source-icon"><i class="<?= e($meta[0]) ?>"></i></span>
            <div class="analytics-bar-track"><div class="analytics-bar-fill" style="width:<?= $pct ?>%;"></div></div>
            <span class="bar-count"><?= $pct ?>%</span>
        </div>
        <p style="font-size:12.5px;color:var(--color-text-muted);margin:-4px 0 10px 42px;"><?= e($meta[1]) ?> — <?= (int) $d['c'] ?> visits</p>
        <?php endforeach; endif; ?>

        <?php if ($browsers): ?>
        <div class="divider-fade" style="margin:16px 0;"></div>
        <h4 style="font-size:13px;margin-bottom:12px;color:var(--color-text-muted);">Browsers</h4>
        <?php foreach ($browsers as $b): ?>
        <div class="analytics-bar-row">
            <span class="bar-label"><?= e($b['browser'] ?: 'Other') ?></span>
            <div class="analytics-bar-track"><div class="analytics-bar-fill" style="width:<?= round(($b['c'] / $maxBrowser) * 100) ?>%;"></div></div>
            <span class="bar-count"><?= (int) $b['c'] ?></span>
        </div>
        <?php endforeach; endif; ?>
    </div>
</div>

<div class="card table-card" style="padding:24px;margin-bottom:24px;" data-reveal>
    <h3 style="font-size:15px;margin-bottom:6px;">Search Keywords</h3>
    <p style="font-size:12.5px;color:var(--color-text-muted);margin-bottom:16px;">
        Only search engines that disclose the query in their referrer link show up here (Bing, Yahoo, DuckDuckGo) —
        Google has not passed organic search terms to websites since 2011, for any site, so "Google keywords" isn't
        something any code on your own server can recover. For that, you'd need to connect Google Search Console
        separately, which is a different kind of integration (an API + verified property) — let me know if you want that added.
    </p>
    <?php if (!$keywords): ?>
    <p style="color:var(--color-text-muted);font-size:13.5px;">No disclosed search keywords in this range yet.</p>
    <?php else: ?>
    <div class="table-scroll"><table class="data-table">
        <thead><tr><th>Keyword</th><th>Visits</th></tr></thead>
        <tbody>
        <?php foreach ($keywords as $k): ?>
        <tr><td><?= e($k['search_keyword']) ?></td><td><?= (int) $k['c'] ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table></div>
    <?php endif; ?>
</div>

<div class="card table-card" style="padding:24px;" data-reveal>
    <h3 style="font-size:15px;margin-bottom:16px;">Top Pages</h3>
    <?php if (!$topPages): ?>
    <p style="color:var(--color-text-muted);font-size:13.5px;">No data yet.</p>
    <?php else: foreach ($topPages as $p): ?>
    <div class="analytics-bar-row" style="grid-template-columns:1fr 200px 50px;">
        <span class="bar-label" style="white-space:normal;"><?= e($p['url']) ?></span>
        <div class="analytics-bar-track"><div class="analytics-bar-fill" style="width:<?= round(($p['c'] / $maxPage) * 100) ?>%;"></div></div>
        <span class="bar-count"><?= (int) $p['c'] ?></span>
    </div>
    <?php endforeach; endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
