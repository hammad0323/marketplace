<?php
/**
 * Serves /ads.txt (rewritten here by .htaccess) from the AdSense publisher ID
 * in Site Settings, so it can never drift out of sync with the ad code.
 * f08c47fec0942fa0 is Google's fixed certification-authority ID.
 */
require __DIR__ . '/config/config.php';
header('Content-Type: text/plain; charset=utf-8');

$client = get_setting('adsense_client_id');
if (!$client || !preg_match('/^ca-(pub-\d{10,20})$/', $client, $m)) {
    http_response_code(404);
    echo "# AdSense publisher ID not set (Admin -> Site Settings).\n";
    exit;
}
echo 'google.com, ' . $m[1] . ", DIRECT, f08c47fec0942fa0\n";
