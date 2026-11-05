<?php
// {title, body?, background?, color?, flash?, device_ids?: [..] (empty = all), duration_minutes?}
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$u = require_user();
require_permission($u, 'emergency.broadcast');
$in = input();
$ids = v_int_list($in, 'device_ids');
if ($ids) {
    $n = (int)db_val('SELECT COUNT(*) FROM devices WHERE user_id = ? AND deleted_at IS NULL AND device_id IN (' . placeholders($ids) . ')', array_merge([$u['user_id']], $ids));
    if ($n !== count($ids)) fail('One or more displays were not found', 400, 'VALIDATION');
}
$mins = v_int($in, 'duration_minutes', false, 1, 10080);
$id = db_insert('INSERT INTO emergency_messages (user_id, title, body, style_json, target_all_devices, target_device_ids_json, starts_at, ends_at) VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), ' . ($mins ? 'UTC_TIMESTAMP() + INTERVAL ? MINUTE' : 'NULL') . ')',
    array_merge([$u['user_id'], v_str($in, 'title', true, 150), v_str($in, 'body', false, 2000),
        jencode(['background' => v_color($in, 'background') ?? '#B00020', 'color' => v_color($in, 'color') ?? '#FFFFFF', 'flash' => v_bool($in, 'flash', true)]),
        $ids ? 0 : 1, $ids ? jencode($ids) : null], $mins ? [$mins] : []));
log_activity((int)$u['user_id'], 'emergency.create', 'emergency', $id, 'Broadcast emergency message');
ok(shape_row(db_one('SELECT * FROM emergency_messages WHERE message_id = ?', [$id]), ['style_json', 'target_device_ids_json']), 'Emergency message is now showing on your displays', 201);
