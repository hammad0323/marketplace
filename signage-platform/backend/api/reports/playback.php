<?php
// ?from=YYYY-MM-DD&to=YYYY-MM-DD&device_id=&format=json|csv
require __DIR__ . '/../../includes/bootstrap.php';
$u = require_user();
require_permission($u, 'analytics.view');
$in = input();
$from = v_date($in, 'from') ?? gmdate('Y-m-d', strtotime('-7 days'));
$to = v_date($in, 'to') ?? gmdate('Y-m-d');
$where = 'd.user_id = ? AND pl.started_at BETWEEN ? AND ?';
$params = [$u['user_id'], "{$from} 00:00:00", "{$to} 23:59:59"];
if ($did = v_int($in, 'device_id', false, 1)) { $where .= ' AND pl.device_id = ?'; $params[] = $did; }
$rows = db_all("SELECT d.device_name, p.playlist_name, s.screen_name, COUNT(*) AS plays, ROUND(SUM(pl.duration_ms)/1000) AS seconds_played, MIN(pl.started_at) AS first_play, MAX(pl.started_at) AS last_play
                  FROM playback_logs pl JOIN devices d ON d.device_id = pl.device_id
             LEFT JOIN playlists p ON p.playlist_id = pl.playlist_id LEFT JOIN screens s ON s.screen_id = pl.screen_id
                 WHERE {$where} GROUP BY d.device_name, p.playlist_name, s.screen_name ORDER BY plays DESC LIMIT 5000", $params);
if (($in['format'] ?? 'json') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"playback-{$from}-{$to}.csv\"");
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Display', 'Playlist', 'Screen', 'Plays', 'Seconds played', 'First play (UTC)', 'Last play (UTC)']);
    foreach ($rows as $r) fputcsv($out, array_map(fn($v) => is_string($v) && preg_match('/^[=+\-@]/', $v) ? "'" . $v : $v, array_values($r)));
    exit;
}
ok(['from' => $from, 'to' => $to, 'rows' => $rows]);
