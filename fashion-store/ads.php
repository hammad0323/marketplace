<?php
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: text/plain; charset=utf-8');

$content = trim(get_setting('ads_txt_content'));
if ($content) {
    echo $content . "\n";
}
