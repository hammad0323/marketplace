<?php
// Body: {screen_id, widget_ids: [bottom ... top]}  → z_index = position in list
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$u = require_user();
require_permission($u, 'screens.edit');
$in = input();
$s = owned_row('screens', 'screen_id', v_int($in, 'screen_id', true, 1), $u);
$ids = v_int_list($in, 'widget_ids', true);
$own = array_map('intval', array_column(db_all('SELECT widget_id FROM screen_widgets WHERE screen_id = ?', [$s['screen_id']]), 'widget_id'));
if (array_diff($ids, $own)) fail('One or more widgets do not belong to this screen', 400, 'VALIDATION');
db_begin();
foreach ($ids as $i => $id) db_run('UPDATE screen_widgets SET z_index = ? WHERE widget_id = ?', [$i + 1, $id]);
db_run('UPDATE screens SET layout_version = layout_version + 1, has_unpublished_changes = 1 WHERE screen_id = ?', [$s['screen_id']]);
db_commit();
ok(null, 'Layer order updated');
