<?php
/**
 * Connection check: shows exactly where the API setup fails.
 * Works only while QF_DEBUG is true in qf-config.php — turn it off again
 * (or delete this file) once everything works.
 */
require __DIR__ . '/bootstrap.php';

if (!QF_DEBUG) {
    http_response_code(403);
    exit('Set QF_DEBUG to true in qf-config.php to use this page.');
}

header('Content-Type: text/html; charset=utf-8');

function row(string $label, bool $ok, string $detail = ''): void
{
    echo '<tr><td>' . ($ok ? '✅' : '❌') . '</td><td><b>' . qf_e($label) . '</b></td><td>' . $detail . '</td></tr>';
}

echo '<!doctype html><meta charset="utf-8"><title>QF test</title>';
echo '<style>body{font-family:sans-serif;max-width:900px;margin:2rem auto}td{padding:.4rem;vertical-align:top;border-bottom:1px solid #ddd}pre{white-space:pre-wrap}</style>';
echo '<h1>Quran Foundation API check</h1><table>';

$id = trim(QF_CLIENT_ID);
$secret = trim(QF_CLIENT_SECRET);

row('PHP version ' . PHP_VERSION, version_compare(PHP_VERSION, '7.4', '>='), 'Needs 7.4 or newer');
row('cURL extension', function_exists('curl_init'));
row('Environment: ' . QF_ENV, in_array(QF_ENV, ['prelive', 'production'], true),
    QF_ENV === 'prelive' ? 'Pre-live credentials + pre-live URLs (Surah 1–2 only)' : 'Production credentials + production URLs');
row('Client ID filled in', $id !== '' && strpos($id, 'PASTE_') !== 0,
    'Length ' . strlen($id) . ', starts with “' . qf_e(substr($id, 0, 4)) . '…” — compare with Developer Console');
row('Client Secret filled in', $secret !== '' && strpos($secret, 'PASTE_') !== 0,
    'Length ' . strlen($secret) . ' (value hidden)');
row('Cache folder writable', is_dir(QF_CACHE_DIR) && is_writable(QF_CACHE_DIR), qf_e(QF_CACHE_DIR));

try {
    $token = qf()->token(true);
    row('Step 1 — access token', true, 'Received (' . strlen($token) . ' chars, valid 1 hour)');

    try {
        $verses = qf()->arabicText('uthmani', ['chapter_number' => 1]);
        $text = '';
        foreach ($verses as $v) {
            $text .= qf_e($v['text_uthmani'] ?? '') . ' (' . qf_e($v['verse_key']) . ')<br>';
        }
        row('Step 2 — Content API (Surah 1)', $verses !== [], '<div dir="rtl" style="font-size:22px">' . $text . '</div>');
    } catch (QuranApiException $e) {
        row('Step 2 — Content API', false, qf_e($e->getMessage()) . '<pre>' . qf_e(substr($e->body, 0, 500)) . '</pre>'
            . ($e->status === 403 ? 'Check that the app has the <b>content</b> scope and the environment matches.' : ''));
    }
} catch (QuranApiException $e) {
    $hint = '';
    if (strpos($e->body, 'invalid_client') !== false) {
        $hint = '<b>invalid_client</b> = Quran Foundation did not accept this ID + secret. Check: '
            . '(1) the app type is “Backend/server app”, '
            . '(2) QF_ENV matches the credentials (pre-live vs production), '
            . '(3) the secret is current — regenerate it in Developer Console if unsure. '
            . 'Both auth methods (Basic and POST body) were tried.';
    } elseif (strpos($e->body, 'invalid_scope') !== false) {
        $hint = 'The app is not allowed the <b>content</b> scope. Request it in Developer Console.';
    } elseif (strpos($e->body, '1010') !== false) {
        $hint = 'Blocked User-Agent. Set QF_USER_AGENT in qf-config.php.';
    } elseif ($e->status === 0) {
        $hint = 'The server could not connect. Ask your host whether outgoing HTTPS (cURL) is allowed.';
    }
    row('Step 1 — access token', false, qf_e($e->getMessage()) . '<pre>' . qf_e(substr($e->body, 0, 500)) . '</pre>' . $hint);
}

echo '</table><p>Remember to set QF_DEBUG back to false when finished.</p>';
