<?php
// Body: {device_id, command: restart|pause|resume|sync|clear_cache|screenshot|identify}
require __DIR__ . '/../../includes/bootstrap.php';
require __DIR__ . '/_common.php';
require_method('POST');
$u = require_user();
require_permission($u, 'devices.commands');
$in = input();
$d = owned_row('devices', 'device_id', v_int($in, 'device_id', true, 1), $u);
$cmd = v_enum($in, 'command', DEVICE_COMMANDS, true);
if (!device_capable($d, $cmd)) fail("This display's player does not support '{$cmd}'", 422, 'UNSUPPORTED_COMMAND');
$plan = effective_limits((int)$d['user_id']);
$id = db_insert('INSERT INTO device_commands (device_id, command, payload_json, issued_by, expires_at) VALUES (?, ?, ?, ?, UTC_TIMESTAMP() + INTERVAL 1 HOUR)',
    [$d['device_id'], $cmd, isset($in['payload']) && is_array($in['payload']) ? jencode($in['payload']) : null, $u['user_id']]);
log_activity((int)$u['user_id'], 'device.command', 'device', (int)$d['device_id'], "Sent {$cmd}");
$online = device_is_online($d);
ok(['command_id' => $id, 'command' => $cmd, 'device_online' => $online],
    $online ? 'Command sent. The display will respond within a minute.' : 'Command queued. It will run when the display comes back online.', 201);
