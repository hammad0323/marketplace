<?php
/**
 * PUBLIC: content for a display code ("Show Display" on a TV / tablet).
 *   GET ?code=STORE1&have=<content_hash>
 * Returns a playable playlist document (a single screen is wrapped as a
 * one-item playlist). `doc` is omitted when the caller already has `have`.
 * Only PUBLISHED content of an active account is served.
 */
require __DIR__ . '/../../includes/bootstrap.php';
require_method('GET');
$ip = client_ip();
rate_limit('display:ip:' . $ip, 240, 3600);
$code = normalize_display_code((string)($_GET['code'] ?? ''));
if ($code === '') v_error('code', 'Enter a display code');

$c = db_one('SELECT dc.*, u.status AS user_status, u.deleted_at AS user_deleted, u.account_expires_at FROM display_codes dc JOIN users u ON u.user_id = dc.user_id WHERE dc.code = ?', [$code]);
if (!$c) {
    rate_limit('display-miss:ip:' . $ip, 30, 900);   // slows down code guessing
    fail('No screen or playlist uses this code. Check the code and try again.', 404, 'CODE_NOT_FOUND');
}
if ($c['user_deleted'] !== null || $c['user_status'] !== 'active' || ($c['account_expires_at'] !== null && strtotime($c['account_expires_at'] . ' UTC') < time())) {
    fail('This display code is not active right now.', 403, 'CODE_INACTIVE');
}

$doc = null;
if ($c['playlist_id'] !== null) {
    $p = db_one('SELECT playlist_id, playlist_name, status, deleted_at FROM playlists WHERE playlist_id = ?', [$c['playlist_id']]);
    $pub = db_one("SELECT published_json, content_hash FROM playlist_publications WHERE playlist_id = ? AND status = 'current'", [$c['playlist_id']]);
    if (!$p || $p['deleted_at'] !== null || !$pub || $p['status'] === 'paused') {
        fail('This playlist has not been published yet. Publish it in the app, then try again.', 409, 'NOT_PUBLISHED');
    }
    $name = $p['playlist_name'];
    $hash = $pub['content_hash'];
    $kind = 'playlist';
    if (($_GET['have'] ?? '') !== $hash) $doc = sign_manifest(jdecode($pub['published_json'], []));
} else {
    $s = db_one('SELECT screen_id, screen_name, default_duration, deleted_at FROM screens WHERE screen_id = ?', [$c['screen_id']]);
    $pub = db_one("SELECT published_json, content_hash, version FROM screen_publications WHERE screen_id = ? AND status = 'current'", [$c['screen_id']]);
    if (!$s || $s['deleted_at'] !== null || !$pub) {
        fail('This screen has not been published yet. Publish it in the app, then try again.', 409, 'NOT_PUBLISHED');
    }
    $name = $s['screen_name'];
    $hash = $pub['content_hash'];
    $kind = 'screen';
    if (($_GET['have'] ?? '') !== $hash) {
        $screen = jdecode($pub['published_json'], []);
        $doc = sign_manifest([
            'schema_version' => 1, 'playlist_id' => 0, 'version' => (int)$pub['version'], 'playlist_name' => $name,
            'loop_enabled' => true, 'autoplay' => true, 'shuffle' => false, 'default_duration' => (int)$s['default_duration'],
            'transition' => ['type' => 'none', 'duration_ms' => 0],
            'items' => [['item_id' => 0, 'screen_id' => (int)$s['screen_id'], 'screen_version' => (int)$pub['version'], 'sort_order' => 1,
                'duration' => 3600, 'transition_type' => 'none', 'valid_from' => null, 'valid_until' => null, 'screen' => $screen]],
            'schedules' => [], 'media' => $screen['media'] ?? [],
        ]);
    }
}

// Emergency messages broadcast to "all displays" by the owner also show on code displays.
$emergency = array_map(fn($r) => shape_row($r, ['style_json']), db_all(
    "SELECT message_id, title, body, style_json, ends_at, updated_at FROM emergency_messages
      WHERE user_id = ? AND status = 'active' AND target_all_devices = 1 AND starts_at <= UTC_TIMESTAMP() AND (ends_at IS NULL OR ends_at > UTC_TIMESTAMP())", [$c['user_id']]));

db_run('UPDATE display_codes SET view_count = view_count + 1, last_used_at = UTC_TIMESTAMP() WHERE code_id = ?', [$c['code_id']]);
ok([
    'code' => $code, 'kind' => $kind, 'name' => $name, 'content_hash' => $hash,
    'doc' => $doc, 'emergency' => $emergency,
    'refresh_seconds' => 60,
    'server_time' => gmdate(DATE_ATOM),
]);
