<?php
/** /portal/prayer — the signed-in mosque's own prayer schedule. The venue id never comes from the request. */
$me = require_venue();
if ((int)$me['role_id'] !== 1) {
    bo_redirect('venue', 'dashboard');
}
prayer_handle('venue', (int)$me['id'], PORTAL_SELF);
bo_start('venue', 'Prayer times');
prayer_manager('venue', (int)$me['id'], PORTAL_SELF);
bo_end();
