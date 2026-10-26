<?php
/**
 * JSON API for mobile apps.
 *
 * Your app calls this file; this file calls Quran Foundation with the
 * secret that stays on the server (QF forbids putting the client_secret
 * in a mobile app). Every response is:
 *
 *   { "ok": true,  "data": { ... } }
 *   { "ok": false, "error": { "code": "...", "message": "..." } }
 *
 * Example:  GET /quran/api.php?action=verses&chapter=1&translations=131
 *           Header: X-App-Key: <QF_APP_API_KEY>
 *
 * GET /quran/api.php (no action) lists every action and its parameters.
 */
require __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: ' . QF_API_CORS_ORIGIN);
header('Access-Control-Allow-Headers: X-App-Key, Content-Type');
header('Access-Control-Allow-Methods: GET, OPTIONS');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function api_send(int $status, array $payload, int $maxAge = 0): void
{
    http_response_code($status);
    header('Cache-Control: ' . ($maxAge > 0 ? "public, max-age=$maxAge" : 'no-store'));
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_fail(int $status, string $code, string $message): void
{
    api_send($status, ['ok' => false, 'error' => ['code' => $code, 'message' => $message]]);
}

/** Required/optional integer parameter within a range. */
function p_int(string $name, ?int $default, int $min, int $max): int
{
    $raw = $_GET[$name] ?? null;
    if ($raw === null || $raw === '') {
        if ($default === null) {
            api_fail(400, 'missing_parameter', "Parameter '$name' is required.");
        }
        return $default;
    }
    $v = filter_var($raw, FILTER_VALIDATE_INT);
    if ($v === false || $v < $min || $v > $max) {
        api_fail(422, 'invalid_parameter', "Parameter '$name' must be a number from $min to $max.");
    }
    return $v;
}

/** Optional string parameter that must match a pattern. */
function p_str(string $name, ?string $default, string $pattern, string $hint): ?string
{
    $raw = $_GET[$name] ?? null;
    if ($raw === null || $raw === '') {
        return $default;
    }
    if (!preg_match($pattern, (string)$raw)) {
        api_fail(422, 'invalid_parameter', "Parameter '$name' $hint");
    }
    return (string)$raw;
}

function p_ids(string $name): ?string
{
    return p_str($name, null, '/^\d{1,6}(,\d{1,6}){0,9}$/', 'must be IDs separated by commas, e.g. 131,97.');
}

function p_key(string $name): string
{
    $key = p_str($name, null, '/^\d{1,3}:\d{1,3}$/', "must look like '2:255'.");
    if ($key === null) {
        api_fail(400, 'missing_parameter', "Parameter '$name' is required, e.g. 2:255.");
    }
    return $key;
}

/** Shared options for the verse actions. */
function verse_opts(): array
{
    return [
        'translations'       => p_ids('translations'),
        'tafsirs'            => p_ids('tafsirs'),
        'audio'              => isset($_GET['audio']) ? p_int('audio', null, 1, 100000) : null,
        'words'              => isset($_GET['words']) ? in_array($_GET['words'], ['1', 'true'], true) : null,
        'fields'             => p_str('fields', 'text_uthmani', '/^[a-z0-9_,]{1,300}$/', 'must be field names separated by commas.'),
        'word_fields'        => p_str('word_fields', null, '/^[a-z0-9_,]{1,300}$/', 'must be field names separated by commas.'),
        'translation_fields' => p_str('translation_fields', null, '/^[a-z0-9_,]{1,300}$/', 'must be field names separated by commas.'),
        'language'           => p_str('language', QF_DEFAULT_LANGUAGE, '/^[a-z]{2,3}$/', "must be a language code like 'en' or 'ur'."),
        'page'               => p_int('page', 1, 1, 1000),
        'per_page'           => p_int('per_page', 10, 1, 50),
    ];
}

/** Makes every relative audio URL inside verses absolute. */
function absolute_audio(array $verses): array
{
    foreach ($verses as &$v) {
        if (isset($v['audio']['url'])) {
            $v['audio']['url'] = QuranClient::audioUrl($v['audio']['url']);
        }
        foreach ($v['words'] ?? [] as $i => $w) {
            if (!empty($w['audio_url'])) {
                $v['words'][$i]['audio_url'] = QuranClient::audioUrl($w['audio_url']);
            }
        }
    }
    return $verses;
}

/** Simple per-IP limit using small files in the cache folder. */
function rate_limit(): void
{
    if (QF_API_RATE_LIMIT <= 0) {
        return;
    }
    $file = QF_CACHE_DIR . '/rl-' . md5($_SERVER['REMOTE_ADDR'] ?? 'x') . '-' . intdiv(time(), 60) . '.php';
    $count = is_file($file) ? (int)substr((string)file_get_contents($file), 15) : 0;
    if ($count >= QF_API_RATE_LIMIT) {
        header('Retry-After: ' . (60 - time() % 60));
        api_fail(429, 'rate_limited', 'Too many requests. Try again in a minute.');
    }
    @file_put_contents($file, '<?php exit; ?>' . "\n" . ($count + 1), LOCK_EX);
    if (mt_rand(1, 200) === 1) {
        foreach (glob(QF_CACHE_DIR . '/rl-*.php') ?: [] as $old) {
            if (filemtime($old) < time() - 120) {
                @unlink($old);
            }
        }
    }
}

/* ------------------------------------------------------------------ */

$actions = [
    'chapters'         => 'All 114 surahs. Optional: language',
    'chapter'          => 'One surah. Required: id (1-114). Optional: language',
    'chapter_info'     => 'Background of a surah. Required: id. Optional: language',
    'verses'           => 'Verses of a surah. Required: chapter. Optional: translations, tafsirs, audio, words, fields, word_fields, translation_fields, language, page, per_page (max 50)',
    'verses_by_page'   => 'Verses on a Mushaf page. Required: page_number (1-604). Optional: same as verses',
    'verses_by_juz'    => 'Verses of a juz. Required: juz (1-30). Optional: same as verses',
    'verse'            => 'One verse. Required: key (e.g. 2:255). Optional: translations, tafsirs, audio, words, fields, language',
    'random'           => 'Random verse. Optional: translations, fields, language',
    'arabic'           => 'Arabic text only. Required: script + one of chapter, key, page_number, juz',
    'juzs'             => 'All 30 juz with verse ranges',
    'translations'     => 'Available translations (use their id). Optional: language',
    'tafsirs'          => 'Available tafsirs (use their id). Optional: language',
    'tafsir'           => 'Tafsir of one ayah. Required: id, key',
    'languages'        => 'Available languages',
    'recitations'      => 'Small list of reciters with ayah-by-ayah audio (use id as audio= on verse actions)',
    'chapter_reciters' => 'Full list of reciters (as on Quran.com) with full-surah audio (use id with chapter_audio)',
    'chapter_audio'    => 'Full-surah MP3. Required: reciter (from chapter_reciters), chapter. Optional: timestamps=1 for per-ayah times in ms',
    'ayah_audio'       => 'Per-ayah MP3s of a surah. Required: recitation, chapter',
    'search'           => 'Search. Required: q. Optional: mode (quick|advanced), translations, size (1-50)',
];

$scripts = ['uthmani', 'uthmani_simple', 'uthmani_tajweed', 'indopak_nastaleeq', 'imlaei', 'imlaei_simple', 'qpc_hafs', 'qpc_nastaleeq', 'code_v1', 'code_v2'];

if (QF_APP_API_KEY !== '') {
    $sent = $_SERVER['HTTP_X_APP_KEY'] ?? ($_GET['app_key'] ?? '');
    if (!is_string($sent) || !hash_equals(QF_APP_API_KEY, $sent)) {
        api_fail(401, 'unauthorized', 'Missing or wrong X-App-Key header.');
    }
}

rate_limit();

$action = $_GET['action'] ?? '';
if ($action === '') {
    api_send(200, ['ok' => true, 'data' => [
        'environment' => QF_ENV,
        'usage'       => 'GET api.php?action=<name>&<parameters>',
        'actions'     => $actions,
        'scripts'     => $scripts,
    ]]);
}
if (!isset($actions[$action])) {
    api_fail(404, 'unknown_action', "Unknown action '$action'. Call api.php without parameters for the list.");
}

$qf = qf();
$lang = p_str('language', QF_DEFAULT_LANGUAGE, '/^[a-z]{2,3}$/', "must be a language code like 'en' or 'ur'.");
$day = 86400;

try {
    switch ($action) {
        case 'chapters':
            api_send(200, ['ok' => true, 'data' => ['chapters' => $qf->chapters($lang)]], $day);

        case 'chapter':
            api_send(200, ['ok' => true, 'data' => ['chapter' => $qf->chapter(p_int('id', null, 1, 114), $lang)]], $day);

        case 'chapter_info':
            api_send(200, ['ok' => true, 'data' => ['chapter_info' => $qf->chapterInfo(p_int('id', null, 1, 114), $lang)]], $day);

        case 'verses':
        case 'verses_by_page':
        case 'verses_by_juz':
            $opts = verse_opts();
            if ($action === 'verses') {
                $res = $qf->versesByChapter(p_int('chapter', null, 1, 114), $opts);
            } elseif ($action === 'verses_by_page') {
                $res = $qf->versesByPage(p_int('page_number', null, 1, 604), $opts);
            } else {
                $res = $qf->versesByJuz(p_int('juz', null, 1, 30), $opts);
            }
            api_send(200, ['ok' => true, 'data' => [
                'verses'     => absolute_audio($res['verses'] ?? []),
                'pagination' => $res['pagination'] ?? null,
            ]], $day);

        case 'verse':
            $opts = verse_opts();
            unset($opts['page'], $opts['per_page']);
            $verse = $qf->verseByKey(p_key('key'), $opts);
            api_send(200, ['ok' => true, 'data' => ['verse' => absolute_audio([$verse])[0]]], $day);

        case 'random':
            $opts = verse_opts();
            unset($opts['page'], $opts['per_page']);
            api_send(200, ['ok' => true, 'data' => ['verse' => absolute_audio([$qf->randomVerse($opts)])[0]]]);

        case 'arabic':
            $script = $_GET['script'] ?? 'uthmani';
            if (!in_array($script, $scripts, true)) {
                api_fail(422, 'invalid_parameter', "Parameter 'script' must be one of: " . implode(', ', $scripts));
            }
            if (isset($_GET['chapter'])) {
                $filter = ['chapter_number' => p_int('chapter', null, 1, 114)];
            } elseif (isset($_GET['key'])) {
                $filter = ['verse_key' => p_key('key')];
            } elseif (isset($_GET['page_number'])) {
                $filter = ['page_number' => p_int('page_number', null, 1, 604)];
            } elseif (isset($_GET['juz'])) {
                $filter = ['juz_number' => p_int('juz', null, 1, 30)];
            } else {
                api_fail(400, 'missing_parameter', 'Give one of: chapter, key, page_number, juz.');
            }
            api_send(200, ['ok' => true, 'data' => ['script' => $script, 'verses' => $qf->arabicText($script, $filter)]], $day);

        case 'juzs':
            api_send(200, ['ok' => true, 'data' => ['juzs' => $qf->juzs()]], $day);

        case 'translations':
            api_send(200, ['ok' => true, 'data' => ['translations' => $qf->translations($lang)]], $day);

        case 'tafsirs':
            api_send(200, ['ok' => true, 'data' => ['tafsirs' => $qf->tafsirs($lang)]], $day);

        case 'tafsir':
            $tafsir = $qf->tafsirByAyah(p_int('id', null, 1, 100000), p_key('key'));
            api_send(200, ['ok' => true, 'data' => ['tafsir' => $tafsir]], $day);

        case 'languages':
            api_send(200, ['ok' => true, 'data' => ['languages' => $qf->languages()]], $day);

        case 'recitations':
            api_send(200, ['ok' => true, 'data' => ['recitations' => $qf->recitations($lang)]], $day);

        case 'chapter_reciters':
            api_send(200, ['ok' => true, 'data' => ['reciters' => $qf->chapterReciters($lang)]], $day);

        case 'chapter_audio':
            $file = $qf->chapterAudio(
                p_int('reciter', null, 1, 100000),
                p_int('chapter', null, 1, 114),
                in_array($_GET['timestamps'] ?? '', ['1', 'true'], true)
            );
            if (isset($file['audio_url'])) {
                $file['audio_url'] = QuranClient::audioUrl($file['audio_url']);
            }
            api_send(200, ['ok' => true, 'data' => ['audio_file' => $file]], $day);

        case 'ayah_audio':
            $files = $qf->ayahAudioByChapter(p_int('recitation', null, 1, 100000), p_int('chapter', null, 1, 114));
            api_send(200, ['ok' => true, 'data' => ['audio_files' => $files]], $day);

        case 'search':
            $q = trim((string)($_GET['q'] ?? ''));
            if ($q === '' || mb_strlen($q) > 100) {
                api_fail(400, 'missing_parameter', "Parameter 'q' is required (max 100 characters).");
            }
            $mode = ($_GET['mode'] ?? 'quick') === 'advanced' ? 'advanced' : 'quick';
            $opts = ['translation_ids' => p_ids('translations') ?? (string)QF_DEFAULT_TRANSLATION];
            if ($mode === 'quick') {
                $opts['versesResultsNumber'] = p_int('size', 20, 1, 50);
            }
            $res = $qf->search($q, $mode, $opts);
            api_send(200, ['ok' => true, 'data' => [
                'result'     => $res['result'] ?? [],
                'pagination' => $res['pagination'] ?? null,
            ]], 3600);
    }
} catch (QuranApiException $e) {
    $status = in_array($e->status, [400, 404, 422, 429], true) ? $e->status : 502;
    $code = [400 => 'bad_request', 404 => 'not_found', 422 => 'invalid_parameter', 429 => 'rate_limited'][$status] ?? 'upstream_error';
    $message = $status === 404 && QF_ENV === 'prelive'
        ? 'Not found. Note: the pre-live environment only has Surah 1 and 2.'
        : (QF_DEBUG ? $e->getMessage() : 'Quran service error. Please try again later.');
    api_fail($status, $code, $message);
} catch (Throwable $e) {
    api_fail(500, 'server_error', QF_DEBUG ? $e->getMessage() : 'Server error.');
}
