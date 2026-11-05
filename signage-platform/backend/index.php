<?php
require __DIR__ . '/includes/bootstrap.php';
ok(['name' => app_setting('app_name', 'Signage') . ' API', 'version' => API_VERSION, 'time' => gmdate(DATE_ATOM)], 'API is running');
