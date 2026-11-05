<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$u = require_user();
require_permission($u, 'screens.publish');
$sid = v_int(input(), 'screen_id', true, 1);
db_begin();
owned_row('screens', 'screen_id', $sid, $u);
$s = db_one('SELECT * FROM screens WHERE screen_id = ? FOR UPDATE', [$sid]);
if ((int)db_val('SELECT COUNT(*) FROM screen_widgets WHERE screen_id = ?', [$sid]) === 0 && in_array($s['background_type'], ['color', 'gradient', 'preset'], true)) {
    // Allowed, but warn: an empty screen shows only its background.
    $warning = 'This screen has no widgets — only the background will be shown.';
}
$version = publish_screen($s, (int)$u['user_id']);
$republished = republish_playlists_with_screen($sid, (int)$u['user_id']);
log_activity((int)$u['user_id'], 'screen.publish', 'screen', $sid, "Published {$s['screen_name']} v{$version}");
db_commit();
ok(['screen_id' => $sid, 'version' => $version, 'republished_playlists' => $republished, 'warning' => $warning ?? null],
    $republished ? 'Screen published. Updated ' . count($republished) . ' playlist(s) on connected displays.' : 'Screen published');
