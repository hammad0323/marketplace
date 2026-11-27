<?php
/** /portal/profile — edit own profile (changes may need approval). */
$me = require_venue();
$id = (int)$me['id'];
if (is_post()) {
    require_post_csrf();
    venue_form_submit($id, false);
    bo_redirect('venue', 'profile');
}
$v = get_venue_any($id);
$pending = pending_profile($id);
if ($pending && !empty($pending['data'])) {
    // Show the submitted (pending) values in the form so the mosque can keep editing them
    foreach ($pending['data'] as $k => $val) {
        if ($k !== '__coords') {
            $v[$k] = $val;
        }
    }
    if (!empty($pending['data']['__coords'])) {
        $v['address_iframe'] = coords_iframe($pending['data']['__coords'][0], $pending['data']['__coords'][1]);
    }
}
bo_start('venue', 'Profile', array('leaflet' => true, 'editor' => true));
if ($pending): ?><div class="alert alert-info"><i class="bi bi-hourglass-split me-1"></i>Changes submitted on <?= e(fmt_date($pending['submitted_at'], 'j M Y')) ?> are waiting for review. The form shows your submitted version.</div><?php endif;
if ((int)$v['verify_status'] === 1): ?><p><a class="btn btn-sm btn-outline-secondary" href="<?= e(url(venue_url($v))) ?>" target="_blank" rel="noopener"><i class="bi bi-eye me-1"></i>Preview public profile</a></p><?php endif;
venue_form($v, false, PORTAL_SELF);
bo_end();
