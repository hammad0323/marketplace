<?php
function present_playlist(array $p, bool $withItems = true): array
{
    $out = [
        'playlist_id' => (int)$p['playlist_id'], 'playlist_uid' => $p['playlist_uid'], 'user_id' => (int)$p['user_id'],
        'playlist_name' => $p['playlist_name'], 'description' => $p['description'], 'loop_enabled' => (bool)$p['loop_enabled'],
        'autoplay' => (bool)$p['autoplay'], 'shuffle' => (bool)$p['shuffle'], 'transition_type' => $p['transition_type'],
        'transition_duration' => (int)$p['transition_duration'], 'default_duration' => (int)$p['default_duration'], 'priority' => (int)$p['priority'],
        'status' => $p['status'], 'published_version' => $p['published_version'], 'has_unpublished_changes' => (bool)$p['has_unpublished_changes'],
        'created_at' => $p['created_at'], 'updated_at' => $p['updated_at'], 'published_at' => $p['published_at'],
    ];
    foreach (['item_count', 'total_duration', 'device_count', 'schedule_count'] as $k) if (isset($p[$k])) $out[$k] = (int)$p[$k];
    if ($withItems) {
        $items = db_all('SELECT pi.*, s.screen_name, s.orientation, s.status AS screen_status, s.default_duration AS screen_default_duration, s.width, s.height
                           FROM playlist_items pi JOIN screens s ON s.screen_id = pi.screen_id
                          WHERE pi.playlist_id = ? AND s.deleted_at IS NULL ORDER BY pi.sort_order, pi.item_id', [$p['playlist_id']]);
        $out['items'] = array_map(fn($i) => [
            'item_id' => (int)$i['item_id'], 'screen_id' => (int)$i['screen_id'], 'screen_name' => $i['screen_name'],
            'orientation' => $i['orientation'], 'screen_status' => $i['screen_status'], 'sort_order' => (int)$i['sort_order'],
            'duration' => $i['duration'] !== null ? (int)$i['duration'] : null, 'effective_duration' => (int)($i['duration'] ?? $i['screen_default_duration']),
            'transition_type' => $i['transition_type'], 'is_enabled' => (bool)$i['is_enabled'],
            'valid_from' => $i['valid_from'], 'valid_until' => $i['valid_until'],
        ], $items);
        $out['schedules'] = array_map(fn($s) => shape_row($s, ['days_of_week_json']), db_all('SELECT * FROM playlist_schedules WHERE playlist_id = ? ORDER BY priority DESC, schedule_id', [$p['playlist_id']]));
        $out['devices'] = db_all('SELECT d.device_id, d.device_name, dp.is_default FROM device_playlists dp JOIN devices d USING (device_id) WHERE dp.playlist_id = ? AND d.deleted_at IS NULL', [$p['playlist_id']]);
    }
    return $out;
}

/** Validate screen ids belong to the playlist owner and are not deleted. */
function assert_owned_screens(array $screenIds, int $userId): void
{
    if (!$screenIds) return;
    $n = (int)db_val('SELECT COUNT(*) FROM screens WHERE user_id = ? AND deleted_at IS NULL AND screen_id IN (' . placeholders($screenIds) . ')', array_merge([$userId], $screenIds));
    if ($n !== count(array_unique($screenIds))) fail('One or more screens were not found in your account', 400, 'VALIDATION');
}

/**
 * Replace items from input: items: [{screen_id, duration?, transition_type?, is_enabled?, valid_from?, valid_until?}]
 * or screen_ids: [..] (+ durations: {screen_id: seconds}).
 */
function write_items(int $playlistId, int $userId, array $in): void
{
    $items = [];
    if (isset($in['items']) && is_array($in['items'])) {
        foreach ($in['items'] as $i => $it) {
            if (!is_array($it) || empty($it['screen_id'])) fail("Item #" . ($i + 1) . " needs a screen_id", 400, 'VALIDATION');
            $items[] = [
                'screen_id' => (int)$it['screen_id'],
                'duration' => isset($it['duration']) && $it['duration'] !== null && $it['duration'] !== '' ? max(1, min(86400, (int)$it['duration'])) : null,
                'transition_type' => in_array($it['transition_type'] ?? 'inherit', array_merge(['inherit'], TRANSITIONS), true) ? ($it['transition_type'] ?? 'inherit') : 'inherit',
                'is_enabled' => !array_key_exists('is_enabled', $it) || !empty($it['is_enabled']),
                'valid_from' => !empty($it['valid_from']) ? gmdate('Y-m-d H:i:s', strtotime((string)$it['valid_from'])) : null,
                'valid_until' => !empty($it['valid_until']) ? gmdate('Y-m-d H:i:s', strtotime((string)$it['valid_until'])) : null,
            ];
        }
    } elseif (isset($in['screen_ids'])) {
        $durations = is_array($in['durations'] ?? null) ? $in['durations'] : [];
        foreach (v_int_list($in, 'screen_ids') as $sid) {
            $items[] = ['screen_id' => $sid, 'duration' => isset($durations[$sid]) ? max(1, (int)$durations[$sid]) : null,
                'transition_type' => 'inherit', 'is_enabled' => true, 'valid_from' => null, 'valid_until' => null];
        }
    } else {
        return;
    }
    if (count($items) > 500) fail('A playlist can contain at most 500 screens', 400, 'VALIDATION');
    assert_owned_screens(array_column($items, 'screen_id'), $userId);
    db_run('DELETE FROM playlist_items WHERE playlist_id = ?', [$playlistId]);
    foreach ($items as $o => $it) {
        db_run('INSERT INTO playlist_items (playlist_id, screen_id, user_id, sort_order, duration, transition_type, is_enabled, valid_from, valid_until) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$playlistId, $it['screen_id'], $userId, $o + 1, $it['duration'], $it['transition_type'], $it['is_enabled'], $it['valid_from'], $it['valid_until']]);
    }
}

function playlist_settings_from_input(array $in, ?array $current = null): array
{
    return [
        'playlist_name' => array_key_exists('playlist_name', $in) || !$current ? v_str($in, 'playlist_name', true, 150) : $current['playlist_name'],
        'description' => array_key_exists('description', $in) ? v_str($in, 'description', false, 255) : ($current['description'] ?? null),
        'loop_enabled' => v_bool($in, 'loop_enabled', $current ? (bool)$current['loop_enabled'] : true),
        'autoplay' => v_bool($in, 'autoplay', $current ? (bool)$current['autoplay'] : true),
        'shuffle' => v_bool($in, 'shuffle', $current ? (bool)$current['shuffle'] : false),
        'transition_type' => v_enum($in, 'transition_type', TRANSITIONS, false, $current['transition_type'] ?? 'fade'),
        'transition_duration' => v_int($in, 'transition_duration', false, 0, 10000, $current ? (int)$current['transition_duration'] : 800),
        'default_duration' => v_int($in, 'default_duration', false, 1, 86400, $current ? (int)$current['default_duration'] : 10),
        'priority' => v_int($in, 'priority', false, -1000, 1000, $current ? (int)$current['priority'] : 0),
    ];
}
