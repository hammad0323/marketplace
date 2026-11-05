<?php
require __DIR__ . '/../../includes/bootstrap.php';
require_method('POST');
$u = require_user();
$m = owned_row('emergency_messages', 'message_id', v_int(input(), 'message_id', true, 1), $u);
db_run("UPDATE emergency_messages SET status = 'cancelled', ends_at = UTC_TIMESTAMP() WHERE message_id = ?", [$m['message_id']]);
log_activity((int)$u['user_id'], 'emergency.cancel', 'emergency', (int)$m['message_id'], 'Cancelled emergency message');
ok(null, 'Emergency message cancelled. Normal content resumes within a minute.');
