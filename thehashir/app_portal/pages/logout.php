<?php
/** POST /portal/logout */
if (is_post() && csrf_check(post('csrf'))) {
    logout_all();
    flash('success', 'You are signed out.');
}
bo_redirect('venue', 'login');
