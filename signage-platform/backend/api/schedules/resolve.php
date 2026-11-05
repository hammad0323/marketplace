<?php
// What would device X play at time T?  ?device_id=&at=2026-11-05T09:30:00Z
require __DIR__ . '/../../includes/bootstrap.php';
$u = require_user();
$in = input();
$d = owned_row('devices', 'device_id', v_int($in, 'device_id', true, 1), $u);
$at = new DateTimeImmutable(v_str($in, 'at', false, 40) ?? 'now', new DateTimeZone('UTC'));
$cands = [];
foreach (db_all("SELECT p.playlist_id, p.playlist_name, p.priority, dp.is_default FROM device_playlists dp JOIN playlists p USING (playlist_id)
                  WHERE dp.device_id = ? AND p.deleted_at IS NULL AND p.status = 'published'", [$d['device_id']]) as $c) {
    $c['schedules'] = db_all("SELECT * FROM playlist_schedules WHERE playlist_id = ? AND status = 'active'", [$c['playlist_id']]);
    $c['is_default'] = (bool)$c['is_default'];
    $cands[] = $c;
}
$r = resolve_playlist($cands, $at, $d['timezone'] ?: 'UTC');
$names = array_column($cands, 'playlist_name', 'playlist_id');
ok($r + ['playlist_name' => $r['playlist_id'] ? $names[$r['playlist_id']] : null, 'at' => $at->format(DATE_ATOM)]);
