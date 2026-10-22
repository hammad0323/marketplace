<?php
if (is_post()) {
    csrf_check();
    customer_logout();
    flash('success', 'You have been signed out.');
}
redirect('');
