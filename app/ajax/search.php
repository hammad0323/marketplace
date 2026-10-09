<?php
$q = mb_substr(input('q', '', 'get'), 0, 100);
if (!rate_limit('search_api', client_ip(), 60, 60)) {
    json_response(['ok' => false, 'products' => [], 'categories' => []], 429);
}
header('Cache-Control: private, max-age=60');
json_response(['ok' => true, 'q' => $q, 'search_url' => path_url('search', ['q' => $q])] + search_suggestions($q));
