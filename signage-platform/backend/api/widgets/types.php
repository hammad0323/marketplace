<?php
require __DIR__ . '/../../includes/bootstrap.php';
$u = require_user();
$limits = effective_limits((int)$u['user_id']);
$rows = shape_rows(db_all("SELECT * FROM widget_types WHERE status = 'active' ORDER BY sort_order"), ['default_config_json'], ['default_width', 'default_height']);
$recent = array_column(db_all('SELECT widget_key FROM user_recent_widgets WHERE user_id = ? ORDER BY last_used_at DESC LIMIT 6', [$u['user_id']]), 'widget_key');
$out = [];
foreach ($rows as $r) {
    if ($r['availability'] === 'planned' && !is_admin($u)) continue;
    $r['is_allowed'] = widget_allowed($limits, $r['widget_key']) && $r['availability'] !== 'planned';
    $r['is_premium'] = (bool)$r['is_premium'];
    $r['requires_network'] = (bool)$r['requires_network'];
    $out[] = $r;
}
ok(['types' => $out, 'recent' => $recent]);
