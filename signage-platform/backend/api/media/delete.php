<?php
// Soft-deletes media. If in use, returns 409 with the screens using it unless force=true,
// in which case references are removed from those screens first.
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$u = require_user();
require_permission($u, 'media.delete');
$in = input();
$ids = isset($in['media_ids']) ? v_int_list($in, 'media_ids', true) : [v_int($in, 'media_id', true, 1)];
$force = v_bool($in, 'force', false);
db_begin();
$deleted = 0;
foreach ($ids as $id) {
    $m = owned_row('media_library', 'media_id', $id, $u);
    $usedBy = db_all('SELECT DISTINCT s.screen_id, s.screen_name FROM widget_assets wa JOIN screen_widgets sw USING (widget_id) JOIN screens s ON s.screen_id = sw.screen_id WHERE wa.media_id = ? AND s.deleted_at IS NULL
                      UNION SELECT s.screen_id, s.screen_name FROM screens s WHERE s.background_media_id = ? AND s.deleted_at IS NULL', [$id, $id]);
    if ($usedBy && !$force) {
        $names = implode(', ', array_column($usedBy, 'screen_name'));
        fail("\"{$m['original_name']}\" is used in: {$names}. Remove it from those screens first, or delete anyway.", 409, 'IN_USE', ['used_in' => $names]);
    }
    if ($usedBy) {
        // Strip references from configs (draft only; published versions keep their own copy until republished)
        foreach (db_all('SELECT sw.widget_id, sw.configuration_json FROM widget_assets wa JOIN screen_widgets sw USING (widget_id) WHERE wa.media_id = ?', [$id]) as $w) {
            $cfg = jdecode($w['configuration_json'], []);
            if (($cfg['media_id'] ?? null) == $id) $cfg['media_id'] = null;
            foreach (['items', 'slides', 'videos'] as $k) {
                if (isset($cfg[$k]) && is_array($cfg[$k])) $cfg[$k] = array_values(array_filter($cfg[$k], fn($it) => !is_array($it) || ($it['media_id'] ?? null) != $id));
            }
            db_run('UPDATE screen_widgets SET configuration_json = ? WHERE widget_id = ?', [jencode($cfg), $w['widget_id']]);
            sync_widget_assets((int)$w['widget_id'], $cfg);
        }
        db_run("UPDATE screens SET background_type = 'color', background_value = '#17191C', background_media_id = NULL, has_unpublished_changes = 1 WHERE background_media_id = ?", [$id]);
    }
    db_run('UPDATE media_library SET deleted_at = UTC_TIMESTAMP() WHERE media_id = ?', [$id]);
    log_activity((int)$u['user_id'], 'media.delete', 'media', $id, "Deleted {$m['original_name']}");
    $deleted++;
}
db_commit();
ok(['deleted' => $deleted], $deleted === 1 ? 'File deleted' : "{$deleted} files deleted");
