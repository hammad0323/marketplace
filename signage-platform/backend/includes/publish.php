<?php
/**
 * Publishing: immutable, versioned snapshots that players consume.
 * Format documented in docs/DATABASE.md §5.
 */

function media_manifest(array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) return [];
    $rows = db_all('SELECT media_id, file_path, mime_type, file_size, checksum_sha256, file_type, width, height FROM media_library WHERE media_id IN (' . placeholders($ids) . ') AND deleted_at IS NULL', $ids);
    return array_map(fn($r) => [
        'media_id' => (int)$r['media_id'], 'file_path' => $r['file_path'], 'mime_type' => $r['mime_type'], 'file_type' => $r['file_type'],
        'file_size' => (int)$r['file_size'], 'checksum_sha256' => $r['checksum_sha256'], 'width' => $r['width'], 'height' => $r['height'],
    ], $rows);
}

/** Build the publication document for a screen from its draft. */
function build_screen_document(array $screen, int $version): array
{
    $layout = screen_layout($screen);
    $ids = [];
    if (!empty($layout['background']['media_id'])) $ids[] = $layout['background']['media_id'];
    $widgets = [];
    foreach ($layout['widgets'] as $w) {
        if (!$w['is_visible']) continue;
        unset($w['widget_id']);
        $ids = array_merge($ids, config_media_ids($w['configuration']));
        $widgets[] = $w;
    }
    return [
        'schema_version' => 1, 'screen_id' => (int)$screen['screen_id'], 'screen_uid' => $screen['screen_uid'], 'version' => $version,
        'screen_name' => $screen['screen_name'], 'canvas' => $layout['canvas'], 'background' => $layout['background'],
        'default_duration' => (int)$screen['default_duration'], 'expires_at' => $screen['expires_at'],
        'widgets' => $widgets, 'media' => media_manifest($ids),
    ];
}

/** Publish a screen (caller holds transaction + verified ownership). Returns version. */
function publish_screen(array $screen, int $byUser): int
{
    $sid = (int)$screen['screen_id'];
    $version = (int)db_val('SELECT COALESCE(MAX(version), 0) + 1 FROM screen_publications WHERE screen_id = ?', [$sid]);
    $doc = build_screen_document($screen, $version);
    $json = jencode($doc);
    db_run("UPDATE screen_publications SET status = 'superseded' WHERE screen_id = ? AND status = 'current'", [$sid]);
    db_run('INSERT INTO screen_publications (screen_id, version, published_json, content_hash, published_by) VALUES (?, ?, ?, ?, ?)',
        [$sid, $version, $json, sha256($json), $byUser]);
    db_run("UPDATE screens SET status = 'published', published_version = ?, has_unpublished_changes = 0, published_at = UTC_TIMESTAMP() WHERE screen_id = ?",
        [$version, $sid]);
    return $version;
}

/** Republish every published playlist containing a screen so devices pick up the change. */
function republish_playlists_with_screen(int $screenId, int $byUser): array
{
    $pls = db_all("SELECT DISTINCT p.* FROM playlists p JOIN playlist_items pi ON pi.playlist_id = p.playlist_id
                    WHERE pi.screen_id = ? AND p.status = 'published' AND p.deleted_at IS NULL", [$screenId]);
    $out = [];
    foreach ($pls as $p) $out[] = ['playlist_id' => (int)$p['playlist_id'], 'version' => publish_playlist($p, $byUser)['version']];
    return $out;
}

/** Publish a playlist. Returns ['version' => n, 'skipped_screens' => [...]]. */
function publish_playlist(array $playlist, int $byUser): array
{
    $pid = (int)$playlist['playlist_id'];
    $items = db_all('SELECT pi.*, s.screen_name, s.default_duration, s.status AS screen_status, s.deleted_at AS screen_deleted,
                            sp.version AS pub_version, sp.published_json
                       FROM playlist_items pi
                       JOIN screens s ON s.screen_id = pi.screen_id
                  LEFT JOIN screen_publications sp ON sp.screen_id = s.screen_id AND sp.status = "current"
                      WHERE pi.playlist_id = ? ORDER BY pi.sort_order, pi.item_id', [$pid]);
    $pubItems = [];
    $skipped = [];
    $media = [];
    foreach ($items as $it) {
        if (!$it['is_enabled']) continue;
        if ($it['screen_deleted'] !== null || $it['published_json'] === null) { $skipped[] = $it['screen_name']; continue; }
        $screenDoc = jdecode($it['published_json'], []);
        foreach ($screenDoc['media'] ?? [] as $m) $media[$m['media_id']] = $m;
        $pubItems[] = [
            'item_id' => (int)$it['item_id'], 'screen_id' => (int)$it['screen_id'], 'screen_version' => (int)$it['pub_version'],
            'sort_order' => (int)$it['sort_order'], 'duration' => (int)($it['duration'] ?? $it['default_duration'] ?? $playlist['default_duration']),
            'transition_type' => $it['transition_type'], 'valid_from' => $it['valid_from'], 'valid_until' => $it['valid_until'],
            'screen' => $screenDoc,
        ];
    }
    $schedules = array_map(fn($s) => [
        'schedule_id' => (int)$s['schedule_id'], 'schedule_name' => $s['schedule_name'], 'schedule_type' => $s['schedule_type'],
        'start_date' => $s['start_date'], 'end_date' => $s['end_date'], 'start_time' => $s['start_time'], 'end_time' => $s['end_time'],
        'days_of_week' => jdecode($s['days_of_week_json']), 'timezone' => $s['timezone'], 'priority' => (int)$s['priority'],
    ], db_all("SELECT * FROM playlist_schedules WHERE playlist_id = ? AND status = 'active' ORDER BY priority DESC, schedule_id", [$pid]));

    $version = (int)db_val('SELECT COALESCE(MAX(version), 0) + 1 FROM playlist_publications WHERE playlist_id = ?', [$pid]);
    $doc = [
        'schema_version' => 1, 'playlist_id' => $pid, 'playlist_uid' => $playlist['playlist_uid'], 'version' => $version,
        'playlist_name' => $playlist['playlist_name'], 'loop_enabled' => (bool)$playlist['loop_enabled'], 'autoplay' => (bool)$playlist['autoplay'],
        'shuffle' => (bool)$playlist['shuffle'], 'default_duration' => (int)$playlist['default_duration'], 'priority' => (int)$playlist['priority'],
        'transition' => ['type' => $playlist['transition_type'], 'duration_ms' => (int)$playlist['transition_duration']],
        'items' => $pubItems, 'schedules' => $schedules, 'media' => array_values($media),
    ];
    $json = jencode($doc);
    db_run("UPDATE playlist_publications SET status = 'superseded' WHERE playlist_id = ? AND status = 'current'", [$pid]);
    db_run('INSERT INTO playlist_publications (playlist_id, version, published_json, content_hash, published_by) VALUES (?, ?, ?, ?, ?)',
        [$pid, $version, $json, sha256($json), $byUser]);
    db_run("UPDATE playlists SET status = 'published', published_version = ?, has_unpublished_changes = 0, published_at = UTC_TIMESTAMP() WHERE playlist_id = ?",
        [$version, $pid]);
    return ['version' => $version, 'skipped_screens' => $skipped];
}

/** Mark a playlist dirty and, if it is live, republish so schedules/order changes reach devices. */
function playlist_changed(int $playlistId, int $byUser, bool $autoRepublish = true): ?int
{
    db_run('UPDATE playlists SET has_unpublished_changes = 1 WHERE playlist_id = ?', [$playlistId]);
    if (!$autoRepublish) return null;
    $p = db_one("SELECT * FROM playlists WHERE playlist_id = ? AND status = 'published' AND deleted_at IS NULL", [$playlistId]);
    return $p ? publish_playlist($p, $byUser)['version'] : null;
}

/** Add signed URLs to a publication's media manifest (for delivery to a player). */
function sign_manifest(array $doc): array
{
    $doc['media'] = array_map(function ($m) {
        $m['url'] = media_url((int)$m['media_id']);
        unset($m['file_path']);
        return $m;
    }, $doc['media'] ?? []);
    if (!empty($doc['items'])) {
        foreach ($doc['items'] as &$it) {
            if (isset($it['screen']['media'])) {
                $it['screen']['media'] = array_map(function ($m) { unset($m['file_path']); return $m; }, $it['screen']['media']);
            }
        }
        unset($it);
    }
    return $doc;
}
