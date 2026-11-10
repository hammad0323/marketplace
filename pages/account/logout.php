<?php
if (!defined('FS_BOOT')) { http_response_code(403); exit; }
customer_logout();
flash('success', 'You have been logged out.');
redirect('');
