<?php
if (is_post()) {
    require_csrf();
    customer_logout();
    flash('success', 'You have been signed out.');
}
redirect(path_url('/'));
