<?php
/** /portal/jummah — own Jumu'ah sessions. */
$me = require_venue();
if ((int)$me['role_id'] !== 1) {
    bo_redirect('venue', 'dashboard');
}
jummah_handle('venue', (int)$me['id'], PORTAL_SELF);
bo_start('venue', 'Jumu\'ah');
jummah_manager('venue', (int)$me['id'], PORTAL_SELF);
bo_end();
