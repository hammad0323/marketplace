<?php
/**
 * Shared setup for the Quran pages: loads settings and the API client,
 * and provides small view helpers. Every page requires this first.
 */

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) {
    exit('Direct access not permitted.');
}

require __DIR__ . '/qf-config.php';
require __DIR__ . '/QuranClient.php';

function qf(): QuranClient
{
    static $client = null;
    if ($client === null) {
        $client = new QuranClient(QF_CLIENT_ID, QF_CLIENT_SECRET, QF_ENV, [
            'auth_method' => QF_AUTH_METHOD,
            'cache_dir'   => QF_CACHE_DIR,
            'cache_ttl'   => QF_CACHE_TTL,
            'user_agent'  => QF_USER_AGENT,
        ]);
    }
    return $client;
}

function qf_e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** Reads an int from the query string, clamped to [$min, $max]. */
function qf_int(string $key, int $default, int $min, int $max): int
{
    $v = filter_input(INPUT_GET, $key, FILTER_VALIDATE_INT);
    return ($v === null || $v === false) ? $default : max($min, min($max, $v));
}

/** Friendly error box; shows API details only when QF_DEBUG is on. */
function qf_error_box(Throwable $e): string
{
    $msg = 'Quran content is temporarily unavailable. Please try again later.';
    if (QF_DEBUG) {
        $msg .= '<br><small>' . qf_e($e->getMessage()) . '</small>';
    }
    if (QF_ENV === 'prelive' && $e instanceof QuranApiException && $e->status === 404) {
        $msg .= '<br><small>Note: the pre-live environment only contains Surah 1 and 2.</small>';
    }
    return '<div class="qf-error">' . $msg . '</div>';
}

/** Arabic-script choices offered on the reading pages => response field. */
const QF_SCRIPTS = [
    'uthmani' => ['label' => 'Uthmani', 'field' => 'text_uthmani', 'class' => 'ar-uthmani'],
    'indopak' => ['label' => 'IndoPak', 'field' => 'text_indopak_nastaleeq', 'class' => 'ar-indopak'],
    'tajweed' => ['label' => 'Uthmani Tajweed (colour)', 'field' => 'text_uthmani_tajweed', 'class' => 'ar-uthmani ar-tajweed'],
];
