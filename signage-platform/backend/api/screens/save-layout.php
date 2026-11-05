<?php
/**
 * Replace the full layout (widgets + background + canvas) of a screen.
 * Body: {screen_id, layout_version, layout: {canvas, background, widgets[]}}  (or the layout keys at top level)
 * layout_version must equal the current value, otherwise 409 (someone else saved in between).
 */
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$u = require_user();
require_permission($u, 'screens.edit');
$in = input();
$sid = v_int($in, 'screen_id', true, 1);
$doc = is_array($in['layout'] ?? null) ? $in['layout'] : $in;
db_begin();
$s = owned_row('screens', 'screen_id', $sid, $u);
$s = db_one('SELECT * FROM screens WHERE screen_id = ? FOR UPDATE', [$sid]);
$clientVersion = v_int($in, 'layout_version', false, 0);
if ($clientVersion !== null && $clientVersion !== (int)$s['layout_version'] && !v_bool($in, 'force', false)) {
    fail('This screen was changed on another device. Reload to see the latest version.', 409, 'CONFLICT');
}
$limits = effective_limits((int)$s['user_id']);
write_layout($s, $doc, $limits, false);
if (isset($in['screen_name'])) db_run('UPDATE screens SET screen_name = ? WHERE screen_id = ?', [v_str($in, 'screen_name', true, 150), $sid]);
$publish = v_bool($in, 'publish', false);
$version = null; $republished = [];
if ($publish) {
    require_permission($u, 'screens.publish');
    $fresh = db_one('SELECT * FROM screens WHERE screen_id = ?', [$sid]);
    $version = publish_screen($fresh, (int)$u['user_id']);
    $republished = republish_playlists_with_screen($sid, (int)$u['user_id']);
}
log_activity((int)$u['user_id'], $publish ? 'screen.publish' : 'screen.save', 'screen', $sid, $publish ? "Published v{$version}" : 'Saved layout');
db_commit();
$out = present_screen(db_one('SELECT * FROM screens WHERE screen_id = ?', [$sid]), true);
$out['republished_playlists'] = $republished;
ok($out, $publish ? 'Saved and published' : 'Saved');
