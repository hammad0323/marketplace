<?php
function device_is_online(array $d): bool
{
    if (!$d['last_heartbeat']) return false;
    return strtotime($d['last_heartbeat'] . ' UTC') >= time() - (int)app_setting('offline_after_seconds', 180);
}

function present_device(array $d, bool $full = false): array
{
    $out = [
        'device_id' => (int)$d['device_id'], 'device_uid' => $d['device_uid'], 'user_id' => $d['user_id'], 'location_id' => $d['location_id'],
        'device_name' => $d['device_name'], 'device_code' => $d['device_code'], 'device_type' => $d['device_type'],
        'platform' => $d['platform'], 'os_version' => $d['os_version'], 'manufacturer' => $d['manufacturer'], 'model' => $d['model'],
        'resolution' => $d['resolution'], 'orientation' => $d['orientation'], 'app_version' => $d['app_version'], 'timezone' => $d['timezone'],
        'storage_total_mb' => $d['storage_total_mb'], 'storage_free_mb' => $d['storage_free_mb'],
        'capabilities' => jdecode($d['capabilities_json'], ['commands' => []]),
        'current_playlist_id' => $d['current_playlist_id'], 'current_screen_id' => $d['current_screen_id'],
        'current_content_version' => $d['current_content_version'], 'playback_state' => $d['playback_state'],
        'last_heartbeat' => $d['last_heartbeat'], 'last_sync_at' => $d['last_sync_at'], 'paired_at' => $d['paired_at'],
        'status' => $d['status'], 'is_online' => device_is_online($d), 'created_at' => $d['created_at'],
    ];
    foreach (['location_name', 'current_playlist_name', 'current_screen_name', 'owner_name'] as $k) if (array_key_exists($k, $d)) $out[$k] = $d[$k];
    $out['assignments'] = db_all('SELECT dp.playlist_id, p.playlist_name, dp.is_default, p.status, p.published_version FROM device_playlists dp JOIN playlists p USING (playlist_id)
                                   WHERE dp.device_id = ? AND p.deleted_at IS NULL ORDER BY dp.is_default DESC, p.playlist_name', [$d['device_id']]);
    foreach ($out['assignments'] as &$a) $a['is_default'] = (bool)$a['is_default'];
    unset($a);
    if ($full) {
        $out['ip_address'] = $d['ip_address'];
        $out['recent_logs'] = shape_rows(db_all('SELECT * FROM device_logs WHERE device_id = ? ORDER BY log_id DESC LIMIT 30', [$d['device_id']]), ['context_json']);
        $out['pending_commands'] = db_all("SELECT command_id, command, status, created_at FROM device_commands WHERE device_id = ? ORDER BY command_id DESC LIMIT 10", [$d['device_id']]);
        $out['uptime_24h'] = (int)db_val('SELECT COALESCE(SUM(duration_ms),0) FROM playback_logs WHERE device_id = ? AND started_at > UTC_TIMESTAMP() - INTERVAL 1 DAY', [$d['device_id']]);
    }
    return $out;
}

function device_select_sql(): string
{
    return 'SELECT d.*, l.location_name, cp.playlist_name AS current_playlist_name, cs.screen_name AS current_screen_name, u.full_name AS owner_name
              FROM devices d
         LEFT JOIN locations l ON l.location_id = d.location_id
         LEFT JOIN playlists cp ON cp.playlist_id = d.current_playlist_id
         LEFT JOIN screens cs ON cs.screen_id = d.current_screen_id
         LEFT JOIN users u ON u.user_id = d.user_id';
}

/** Fingerprint of everything a player must react to. */
function device_sync_token(array $d): string
{
    $parts = [];
    foreach (db_all("SELECT dp.playlist_id, dp.is_default, p.published_version, p.status FROM device_playlists dp JOIN playlists p USING (playlist_id)
                      WHERE dp.device_id = ? AND p.deleted_at IS NULL ORDER BY dp.playlist_id", [$d['device_id']]) as $a) {
        $parts[] = "p{$a['playlist_id']}:{$a['published_version']}:{$a['is_default']}:{$a['status']}";
    }
    foreach (active_emergencies($d) as $e) $parts[] = "e{$e['message_id']}:{$e['updated_at']}";
    $parts[] = 'n:' . $d['device_name'] . ':' . $d['orientation'] . ':' . $d['status'] . ':' . ($d['user_id'] ?? '-');
    $parts[] = 'c:' . (int)db_val("SELECT COUNT(*) FROM device_commands WHERE device_id = ? AND status = 'pending' AND expires_at > UTC_TIMESTAMP()", [$d['device_id']]);
    return substr(sha256(implode('|', $parts)), 0, 24);
}

function active_emergencies(array $d): array
{
    if (!$d['user_id']) return [];
    $rows = db_all("SELECT message_id, title, body, style_json, target_all_devices, target_device_ids_json, starts_at, ends_at, updated_at FROM emergency_messages
                     WHERE user_id = ? AND status = 'active' AND starts_at <= UTC_TIMESTAMP() AND (ends_at IS NULL OR ends_at > UTC_TIMESTAMP())", [$d['user_id']]);
    return array_values(array_filter(array_map(fn($r) => shape_row($r, ['style_json', 'target_device_ids_json']), $rows), function ($r) use ($d) {
        return $r['target_all_devices'] || in_array((int)$d['device_id'], array_map('intval', $r['target_device_ids_json'] ?? []), true);
    }));
}

function device_capable(array $d, string $command): bool
{
    $caps = jdecode($d['capabilities_json'], []);
    return in_array($command, $caps['commands'] ?? [], true);
}
