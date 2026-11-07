<?php
/**
 * End-to-end API test suite (Phase 2).
 *
 *   1. Load a fresh database:   mysql signage_test < database/schema.sql && mysql signage_test < database/seed.sql
 *   2. Start the API:           DB_NAME=signage_test php -S 127.0.0.1:8080 -t backend backend/router.php
 *   3. Run:                     php backend/tests/api_test.php [http://127.0.0.1:8080]
 *
 * Exercises every workflow in the brief (A–E), limits, permissions, IDOR
 * protection, uploads, publishing, scheduling, device pairing/sync/commands.
 */

$BASE = rtrim($argv[1] ?? 'http://127.0.0.1:8080', '/');
$pass = 0; $fail = 0; $section = '';

function req(string $method, string $path, $body = null, ?string $token = null, array $files = []): array
{
    global $BASE;
    $ch = curl_init($BASE . $path);
    $headers = [];
    if ($token) $headers[] = "Authorization: Bearer {$token}";
    if ($files) {
        $post = is_array($body) ? $body : [];
        foreach ($files as $k => $f) $post[$k] = new CURLFile($f[0], $f[1], $f[2]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    } elseif ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    }
    curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_HEADER => true]);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hsize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    $bodyStr = substr((string)$raw, $hsize);
    return ['code' => $code, 'json' => json_decode($bodyStr, true), 'raw' => $bodyStr, 'headers' => substr((string)$raw, 0, $hsize)];
}

function section(string $s): void { global $section; $section = $s; echo "\n▶ {$s}\n"; }
function check(string $name, bool $cond, $detail = null): void
{
    global $pass, $fail;
    if ($cond) { $pass++; echo "  \033[32m✔\033[0m {$name}\n"; }
    else { $fail++; echo "  \033[31m✘\033[0m {$name}" . ($detail !== null ? '  → ' . substr(is_string($detail) ? $detail : json_encode($detail), 0, 400) : '') . "\n"; }
}
function expect(string $name, array $r, int $code, ?string $errCode = null): array
{
    $ok = $r['code'] === $code && ($errCode === null || ($r['json']['code'] ?? null) === $errCode);
    check("{$name} [{$code}" . ($errCode ? " {$errCode}" : '') . ']', $ok, $r['code'] . ' ' . $r['raw']);
    return $r['json']['data'] ?? [];
}
function login(string $email, string $pw = 'Demo@12345'): string
{
    $r = req('POST', '/api/auth/login', ['email' => $email, 'password' => $pw, 'device_label' => 'api-test']);
    return $r['json']['data']['token'] ?? '';
}

// ---------------------------------------------------------------------------
section('Health & auth');
expect('public settings', req('GET', '/api/settings/public'), 200);
expect('wrong password rejected', req('POST', '/api/auth/login', ['email' => 'bilal@demo.local', 'password' => 'nope']), 401, 'INVALID_CREDENTIALS');
expect('invalid email validated', req('POST', '/api/auth/login', ['email' => 'not-an-email', 'password' => 'x']), 400, 'VALIDATION');
expect('suspended user blocked', req('POST', '/api/auth/login', ['email' => 'eva@demo.local', 'password' => 'Demo@12345']), 403, 'ACCOUNT_SUSPENDED');
$ADMIN = login('admin@signage.local', 'Admin@12345');
$BILAL = login('bilal@demo.local');
$AISHA = login('aisha@demo.local');
check('admin + users signed in', strlen($ADMIN) === 64 && strlen($BILAL) === 64 && strlen($AISHA) === 64);
expect('no token → 401', req('GET', '/api/auth/profile'), 401, 'UNAUTHENTICATED');
expect('garbage token → 401', req('GET', '/api/auth/profile', null, str_repeat('a', 64)), 401, 'UNAUTHENTICATED');
$p = expect('profile', req('GET', '/api/auth/profile', null, $BILAL), 200);
check('profile has limits (5 screens)', ($p['limits']['max_screens'] ?? null) === 5, $p['limits'] ?? null);
check('profile never exposes password hash', !str_contains(json_encode($p), 'password_hash'));
expect('wrong method → 405', req('GET', '/api/auth/login'), 405);
expect('unknown endpoint → 404', req('GET', '/api/nope/nothing'), 404);
expect('internal folder blocked', req('GET', '/config/config.php'), 403);
expect('helper file blocked', req('GET', '/api/devices/_common.php'), 403);
expect('raw uploads blocked', req('GET', '/uploads/images/samples/promo-banner.jpg'), 403);

// ---------------------------------------------------------------------------
section('Dashboard & templates');
$d = expect('user dashboard', req('GET', '/api/users/dashboard', null, $BILAL), 200);
check('dashboard stats: 5 screens, 4 published', ($d['stats']['total_screens'] ?? 0) === 5 && ($d['stats']['published_screens'] ?? 0) === 4, $d['stats'] ?? null);
$cats = expect('template categories', req('GET', '/api/templates/categories', null, $BILAL), 200);
check('14 categories', count($cats) === 14);
$tpls = expect('template list', req('GET', '/api/templates/list', null, $BILAL), 200);
check('>= 20 templates', count($tpls) >= 20);
$locked = array_values(array_filter($tpls, fn($t) => $t['is_locked']));
check('premium templates locked on Starter plan', count($locked) > 0);
$portrait = expect('filter portrait', req('GET', '/api/templates/list?orientation=portrait', null, $BILAL), 200);
check('portrait filter works', $portrait && !array_filter($portrait, fn($t) => $t['orientation'] !== 'portrait'));
expect('favourite toggle', req('POST', '/api/templates/favorite', ['template_id' => $tpls[0]['template_id']], $BILAL), 200);
$types = expect('widget types', req('GET', '/api/widgets/types', null, $BILAL), 200);
check('planned widgets hidden from users', !array_filter($types['types'], fn($t) => $t['availability'] === 'planned'));

// ---------------------------------------------------------------------------
section('Workflow A — create screen from template, edit, publish');
expect('premium template blocked', req('POST', '/api/screens/create', ['screen_name' => 'X', 'template_id' => $locked[0]['template_id']], $BILAL), 403, 'PREMIUM_REQUIRED');
// Bilal has 5/5 screens → limit
expect('screen limit enforced for Bilal (5/5)', req('POST', '/api/screens/create', ['screen_name' => 'Sixth'], $BILAL), 403, 'LIMIT_SCREENS');
expect('screen limit enforced for Aisha (2/2)', req('POST', '/api/screens/create', ['screen_name' => 'Third'], $AISHA), 403, 'LIMIT_SCREENS');
// Admin raises Bilal to 8
expect('admin raises Bilal limit to 8', req('POST', '/api/admin/update-user-limits', ['user_id' => 3, 'max_screens' => 8], $ADMIN), 200);
$free = array_values(array_filter($tpls, fn($t) => !$t['is_premium'] && $t['template_key'] === 'event_countdown_l'))[0];
$s = expect('create from template', req('POST', '/api/screens/create', ['screen_name' => 'Grand Opening', 'template_id' => $free['template_id']], $BILAL), 201);
$SID = $s['screen_id'] ?? 0;
check('widgets copied from template', count($s['layout']['widgets'] ?? []) === count($free['template_json']['widgets']));
check('template geometry preserved (normalised)', abs(($s['layout']['widgets'][0]['position_x'] ?? -1) - $free['template_json']['widgets'][0]['position_x']) < 1e-6);
$blank = expect('create blank portrait', req('POST', '/api/screens/create', ['screen_name' => 'Blank Portrait', 'orientation' => 'portrait'], $BILAL), 201);
check('portrait canvas 1080x1920', ($blank['width'] ?? 0) === 1080 && ($blank['height'] ?? 0) === 1920);
$custom = expect('create custom ratio', req('POST', '/api/screens/create', ['screen_name' => 'Ultra wide', 'orientation' => 'custom', 'width' => 3840, 'height' => 1080], $BILAL), 201);
check('custom canvas kept', ($custom['width'] ?? 0) === 3840);
expect('limit reached again at 8', req('POST', '/api/screens/create', ['screen_name' => 'Ninth'], $BILAL), 403, 'LIMIT_SCREENS');

$layout = $s['layout'];
$layout['widgets'][0]['configuration']['text'] = 'Grand Opening — Dubai';
$layout['widgets'][] = ['widget_type' => 'ticker', 'position_x' => 0, 'position_y' => 0.92, 'width' => 1, 'height' => 0.08, 'z_index' => 99, 'configuration' => ['messages' => ['Hello', 'World']]];
$layout['background'] = ['type' => 'gradient', 'config' => ['gradient' => ['type' => 'linear', 'angle' => 90, 'colors' => ['#008C86', '#202124'], 'stops' => [0, 1]]]];
$saved = expect('save layout', req('POST', '/api/screens/save-layout', ['screen_id' => $SID, 'layout_version' => $s['layout_version'], 'layout' => $layout], $BILAL), 200);
check('layout_version incremented', ($saved['layout_version'] ?? 0) === $s['layout_version'] + 1);
check('ticker added', count($saved['layout']['widgets'] ?? []) === count($layout['widgets']));
check('background saved', ($saved['layout']['background']['type'] ?? '') === 'gradient');
expect('stale layout_version → 409', req('POST', '/api/screens/save-layout', ['screen_id' => $SID, 'layout_version' => $s['layout_version'], 'layout' => $layout], $BILAL), 409, 'CONFLICT');
$bad = $layout; $bad['widgets'][] = ['widget_type' => 'hacker_widget'];
expect('unknown widget rejected', req('POST', '/api/screens/save-layout', ['screen_id' => $SID, 'layout' => $bad], $BILAL), 400, 'VALIDATION');
$bad = $layout; $bad['widgets'][] = ['widget_type' => 'youtube', 'configuration' => []];
expect('planned widget rejected', req('POST', '/api/screens/save-layout', ['screen_id' => $SID, 'layout' => $bad], $BILAL), 403, 'WIDGET_NOT_ALLOWED');
$bad = $layout; $bad['widgets'][] = ['widget_type' => 'image', 'configuration' => ['media_id' => 999999]];
expect('foreign/missing media rejected', req('POST', '/api/screens/save-layout', ['screen_id' => $SID, 'layout' => $bad], $BILAL), 400, 'VALIDATION');
$pub = expect('publish screen', req('POST', '/api/screens/publish', ['screen_id' => $SID], $BILAL), 200);
check('version 1 published', ($pub['version'] ?? 0) === 1);
$det = expect('screen details', req('GET', "/api/screens/details?screen_id={$SID}", null, $BILAL), 200);
check('status published, no pending changes', ($det['status'] ?? '') === 'published' && $det['has_unpublished_changes'] === false);
$dup = expect('duplicate blocked at limit', req('POST', '/api/screens/duplicate', ['screen_id' => $SID], $BILAL), 403, 'LIMIT_SCREENS');
expect('delete blank screen', req('POST', '/api/screens/delete', ['screen_id' => $blank['screen_id']], $BILAL), 200);
$dup = expect('duplicate after freeing slot', req('POST', '/api/screens/duplicate', ['screen_id' => $SID], $BILAL), 201);
check('duplicate has same widget count', count($dup['layout']['widgets'] ?? []) === count($saved['layout']['widgets']));
check('duplicate has new widget uids', ($dup['layout']['widgets'][0]['widget_uid'] ?? '') !== ($saved['layout']['widgets'][0]['widget_uid'] ?? ''));
$list = expect('screen list search', req('GET', '/api/screens/list?q=Grand&with_layout=0', null, $BILAL), 200);
check('search finds 2 Grand screens', count($list) === 2, $list);
expect('rename screen', req('POST', '/api/screens/update', ['screen_id' => $dup['screen_id'], 'screen_name' => 'Grand Opening (Mall)', 'is_favorite' => true], $BILAL), 200);

section('Single-widget API');
$w = expect('add widget', req('POST', '/api/widgets/add', ['screen_id' => $SID, 'widget_type' => 'clock', 'configuration' => ['format' => '12h']], $BILAL), 201);
check('defaults merged into config', ($w['configuration_json']['style'] ?? '') === 'digital' && $w['configuration_json']['format'] === '12h');
$w2 = expect('update widget', req('POST', '/api/widgets/update', ['widget_id' => $w['widget_id'], 'position_x' => 0.7, 'configuration' => ['color' => '#008C86']], $BILAL), 200);
check('partial config update keeps other keys', ($w2['configuration_json']['format'] ?? '') === '12h' && $w2['configuration_json']['color'] === '#008C86');
$wd = expect('duplicate widget', req('POST', '/api/widgets/duplicate', ['widget_id' => $w['widget_id']], $BILAL), 201);
$wl = expect('list widgets', req('GET', "/api/widgets/list?screen_id={$SID}", null, $BILAL), 200);
expect('reorder layers', req('POST', '/api/widgets/reorder', ['screen_id' => $SID, 'widget_ids' => array_reverse(array_column($wl, 'widget_id'))], $BILAL), 200);
expect('delete widget', req('POST', '/api/widgets/delete', ['widget_id' => $wd['widget_id']], $BILAL), 200);
expect('screen marked unpublished after edits', req('GET', "/api/screens/details?screen_id={$SID}", null, $BILAL), 200);

// ---------------------------------------------------------------------------
section('Security — cross-user access (IDOR)');
expect("Aisha cannot read Bilal's screen", req('GET', "/api/screens/details?screen_id={$SID}", null, $AISHA), 404, 'NOT_FOUND');
expect("Aisha cannot edit Bilal's screen", req('POST', '/api/screens/save-layout', ['screen_id' => $SID, 'layout' => $layout], $AISHA), 404);
expect("Aisha cannot delete Bilal's screen", req('POST', '/api/screens/delete', ['screen_id' => $SID], $AISHA), 404);
expect("Aisha cannot read Bilal's playlist", req('GET', '/api/playlists/details?playlist_id=1', null, $AISHA), 404);
expect("Aisha cannot control Bilal's device", req('POST', '/api/devices/command', ['device_id' => 1, 'command' => 'restart'], $AISHA), 404);
expect("Aisha cannot use Bilal's widget", req('POST', '/api/widgets/update', ['widget_id' => $w['widget_id'], 'position_x' => 0], $AISHA), 404);
expect("Bilal cannot add Aisha's screen to playlist", req('POST', '/api/playlists/add-screen', ['playlist_id' => 1, 'screen_id' => 6], $BILAL), 400, 'VALIDATION');
expect('user cannot call admin API', req('GET', '/api/admin/dashboard', null, $BILAL), 403, 'FORBIDDEN');
$ul = expect('user screen list only own', req('GET', '/api/screens/list?all=1&with_layout=0', null, $AISHA), 200);
check('Aisha sees only her 2 screens', count($ul) === 2 && !array_filter($ul, fn($x) => $x['user_id'] !== 2));
expect('SQL injection in search is harmless', req('GET', "/api/screens/list?q=" . urlencode("' OR 1=1 -- ") . '&with_layout=0', null, $AISHA), 200);
expect('SQL injection in sort is ignored', req('GET', '/api/screens/list?sort=' . urlencode('screen_id;DROP TABLE users') . '&with_layout=0', null, $AISHA), 200);

// ---------------------------------------------------------------------------
section('Media library & uploads');
$img = sys_get_temp_dir() . '/sig_test.png';
$im = imagecreatetruecolor(640, 360); imagefill($im, 0, 0, imagecolorallocate($im, 0, 140, 134)); imagepng($im, $img);
$m = expect('upload image', req('POST', '/api/media/upload', ['folder_id' => ''], $BILAL, ['file' => [$img, 'image/png', 'Launch banner.png']]), 201);
check('dimensions + thumbnail', ($m['width'] ?? 0) === 640 && !empty($m['thumbnail_url']));
check('server paths hidden', !isset($m['file_path']) && !isset($m['thumbnail_path']));
$fileRes = req('GET', parse_url($m['url'], PHP_URL_PATH) . '?' . parse_url($m['url'], PHP_URL_QUERY));
check('signed URL serves image', $fileRes['code'] === 200 && str_starts_with($fileRes['raw'], "\x89PNG"));
$range = (function () use ($m) { $ch = curl_init($m['url']); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => ['Range: bytes=0-9']]); $b = curl_exec($ch); $c = curl_getinfo($ch, CURLINFO_HTTP_CODE); return [$c, strlen($b)]; })();
check('HTTP Range supported (206, 10 bytes)', $range === [206, 10], $range);
$tampered = preg_replace('/sig=[a-f0-9]+/', 'sig=' . str_repeat('0', 32), $m['url']);
check('tampered signature rejected', req('GET', parse_url($tampered, PHP_URL_PATH) . '?' . parse_url($tampered, PHP_URL_QUERY))['code'] === 403);
$php = sys_get_temp_dir() . '/evil.jpg';
file_put_contents($php, "<?php system(\$_GET['c']); ?>");
expect('PHP disguised as JPG rejected', req('POST', '/api/media/upload', [], $BILAL, ['file' => [$php, 'image/jpeg', 'evil.jpg']]), 415, 'BAD_MEDIA');
$vid = sys_get_temp_dir() . '/fake.mp4';
file_put_contents($vid, hex2bin('000000186674797069736f6d0000020069736f6d69736f32') . str_repeat("\0", 64));
expect('video upload blocked by permission override (Aisha)', req('POST', '/api/media/upload', [], $AISHA, ['file' => [$vid, 'video/mp4', 'clip.mp4']]), 403, 'VIDEO_NOT_ALLOWED');
$f = expect('create folder', req('POST', '/api/media/create-folder', ['folder_name' => 'Launch'], $BILAL), 201);
expect('move to folder', req('POST', '/api/media/move', ['media_ids' => [$m['media_id']], 'folder_id' => $f['folder_id']], $BILAL), 200);
$ml = req('GET', "/api/media/list?folder_id={$f['folder_id']}", null, $BILAL);
check('folder filter', ($ml['json']['meta']['total'] ?? 0) === 1, $ml['raw']);
check('storage usage reported', ($ml['json']['storage']['used_bytes'] ?? 0) > 0);
expect('rename media', req('POST', '/api/media/rename', ['media_id' => $m['media_id'], 'original_name' => 'Opening banner.png'], $BILAL), 200);
// Use the media in the screen, then try to delete it
$layout2 = req('GET', "/api/screens/details?screen_id={$SID}", null, $BILAL)['json']['data'];
$L = $layout2['layout'];
$L['widgets'][] = ['widget_type' => 'image', 'position_x' => 0.05, 'position_y' => 0.05, 'width' => 0.3, 'height' => 0.3, 'configuration' => ['media_id' => $m['media_id']]];
expect('use uploaded image in screen', req('POST', '/api/screens/save-layout', ['screen_id' => $SID, 'layout_version' => $layout2['layout_version'], 'layout' => $L, 'publish' => true], $BILAL), 200);
expect('in-use media cannot be deleted', req('POST', '/api/media/delete', ['media_id' => $m['media_id']], $BILAL), 409, 'IN_USE');
expect("Aisha cannot use Bilal's media", req('POST', '/api/widgets/add', ['screen_id' => 6, 'widget_type' => 'image', 'configuration' => ['media_id' => $m['media_id']]], $AISHA), 400, 'VALIDATION');

// ---------------------------------------------------------------------------
section('Workflow B — playlist (screen carousel)');
$pl = expect('create playlist limit (3) ok', req('POST', '/api/playlists/create', [
    'playlist_name' => 'Opening Week', 'transition_type' => 'slide_left', 'transition_duration' => 600, 'loop_enabled' => true,
    'items' => [['screen_id' => $SID, 'duration' => 15], ['screen_id' => 1, 'duration' => 8], ['screen_id' => 5, 'duration' => 8]],
], $BILAL), 201);
$PID = $pl['playlist_id'] ?? 0;
check('3 items in order', array_column($pl['items'] ?? [], 'screen_id') === [$SID, 1, 5]);
expect('playlist limit reached (3/3)', req('POST', '/api/playlists/create', ['playlist_name' => 'Too many'], $BILAL), 403, 'LIMIT_PLAYLISTS');
$items = $pl['items'];
$rev = expect('reorder items', req('POST', '/api/playlists/reorder', ['playlist_id' => $PID, 'item_ids' => array_reverse(array_column($items, 'item_id'))], $BILAL), 200);
check('order reversed', array_column($rev['items'], 'screen_id') === [5, 1, $SID]);
$pp = expect('publish playlist', req('POST', '/api/playlists/publish', ['playlist_id' => $PID], $BILAL), 200);
check('draft screen (Contact) skipped with warning', in_array('Contact Screen', $pp['skipped_screens'] ?? [], true));
$pp2 = expect('publish with publish_screens', req('POST', '/api/playlists/publish', ['playlist_id' => $PID, 'publish_screens' => true], $BILAL), 200);
check('all screens included, version 2', ($pp2['skipped_screens'] ?? ['x']) === [] && $pp2['version'] === 2);
$prev = expect('playlist preview (published)', req('GET', "/api/playlists/preview?playlist_id={$PID}&draft=0", null, $BILAL), 200);
check('preview has 3 items + signed media', count($prev['items'] ?? []) === 3 && !empty($prev['media'][0]['url']) && !isset($prev['media'][0]['file_path']));
$ui = expect('update item duration', req('POST', '/api/playlists/update-item', ['item_id' => $items[0]['item_id'], 'duration' => 20], $BILAL), 200);
check('live playlist auto-republished (v3)', ($ui['published_version'] ?? 0) === 3, $ui['published_version'] ?? null);

section('Scheduling');
$sch = expect('create schedule', req('POST', '/api/schedules/create', ['playlist_id' => $PID, 'schedule_name' => 'Lunch rush', 'start_time' => '12:00', 'end_time' => '14:00', 'days_of_week' => [1, 2, 3, 4, 5], 'priority' => 30], $BILAL), 201);
expect('invalid time rejected', req('POST', '/api/schedules/create', ['playlist_id' => $PID, 'start_time' => '25:00'], $BILAL), 400, 'VALIDATION');
expect('end before start date rejected', req('POST', '/api/schedules/create', ['playlist_id' => $PID, 'start_date' => '2026-12-10', 'end_date' => '2026-12-01'], $BILAL), 400, 'VALIDATION');
expect('update schedule', req('POST', '/api/schedules/update', ['schedule_id' => $sch['schedule_id'], 'end_time' => '15:00'], $BILAL), 200);
expect('list schedules', req('GET', "/api/schedules/list?playlist_id={$PID}", null, $BILAL), 200);


section('Show Display — content by code (no pairing)');
$dc = expect('public content by code (playlist)', req('GET', '/api/display/content?code=store1'), 200);
check('playlist doc with items + signed media', ($dc['kind'] ?? '') === 'playlist' && count($dc['doc']['items'] ?? []) >= 3 && !empty($dc['doc']['media'][0]['url']) && !isset($dc['doc']['media'][0]['file_path']));
$dc2 = expect('unchanged content omits doc', req('GET', '/api/display/content?code=STORE1&have=' . ($dc['content_hash'] ?? '')), 200);
check('doc is null when hash matches', array_key_exists('doc', $dc2) && $dc2['doc'] === null);
$ds = expect('public content by code (single screen)', req('GET', '/api/display/content?code=WELCOME'), 200);
check('screen wrapped as one-item playlist', ($ds['kind'] ?? '') === 'screen' && count($ds['doc']['items'] ?? []) === 1);
expect('unknown code → 404', req('GET', '/api/display/content?code=NOPE99'), 404, 'CODE_NOT_FOUND');
$gc = expect('owner gets code for a screen (auto-created)', req('GET', '/api/display-codes/get?screen_id=' . $SID, null, $BILAL), 200);
check('6-char generated code', strlen($gc['code'] ?? '') === 6);
expect("Aisha cannot read Bilal's code", req('GET', '/api/display-codes/get?screen_id=' . $SID, null, $AISHA), 404);
$sc = expect('choose own code (normalised)', req('POST', '/api/display-codes/set', ['screen_id' => $SID, 'code' => 'grand-open'], $BILAL), 200);
check('stored upper-case without symbols', ($sc['code'] ?? '') === 'GRANDOPEN');
expect('duplicate code rejected', req('POST', '/api/display-codes/set', ['screen_id' => $SID, 'code' => 'STORE1'], $BILAL), 409, 'CONFLICT');
expect('too short code rejected', req('POST', '/api/display-codes/set', ['screen_id' => $SID, 'code' => 'ab'], $BILAL), 400, 'VALIDATION');
$byCode = expect('new code plays the screen', req('GET', '/api/display/content?code=GRANDOPEN'), 200);
check('right screen', ($byCode['doc']['items'][0]['screen_id'] ?? 0) === $SID);
$rg = expect('regenerate', req('POST', '/api/display-codes/regenerate', ['screen_id' => $SID], $BILAL), 200);
expect('old code stops working', req('GET', '/api/display/content?code=GRANDOPEN'), 404);
$pc = expect('code for a playlist', req('GET', '/api/display-codes/get?playlist_id=' . $PID, null, $BILAL), 200);
expect('playlist code plays', req('GET', '/api/display/content?code=' . $pc['code']), 200);
$drafty = expect('draft screen code', req('GET', '/api/display-codes/get?screen_id=' . $blank['screen_id'], null, $BILAL), 404);

// ---------------------------------------------------------------------------
section('Workflow C — connect a TV');
$uidDev = 'test-' . bin2hex(random_bytes(8));
$reg = expect('player registers', req('POST', '/api/devices/register', ['device_uid' => $uidDev, 'model' => 'Test TV', 'resolution' => '1920x1080', 'app_version' => '1.0.0', 'timezone' => 'Asia/Dubai'], null), 201);
$DTOK = $reg['device_token'] ?? '';
check('device token issued', strlen($DTOK) === 64);
$code = expect('player gets pairing code', req('POST', '/api/devices/generate-pairing-code', [], $DTOK), 200);
check('6-char code', strlen($code['pairing_code'] ?? '') === 6);
expect('pairing status: not paired', req('GET', '/api/devices/pairing-status', null, $DTOK), 200);
expect('wrong code rejected', req('POST', '/api/devices/pair', ['pairing_code' => 'AAAAAA'], $BILAL), 400, 'PAIRING_INVALID');
$dev = expect('user pairs with code (lowercase + spaces ok)', req('POST', '/api/devices/pair', ['pairing_code' => ' ' . strtolower(substr($code['pairing_code'], 0, 3)) . ' ' . substr($code['pairing_code'], 3), 'device_name' => 'Mall TV', 'location_id' => 2], $BILAL), 201);
$DID = $dev['device_id'] ?? 0;
expect('code cannot be reused', req('POST', '/api/devices/pair', ['pairing_code' => $code['pairing_code']], $AISHA), 400, 'PAIRING_INVALID');
expect('device limit (3/3) reached', (function () use ($BILAL) {
    $r = req('POST', '/api/devices/register', ['device_uid' => 'test-' . bin2hex(random_bytes(8))]);
    $c = req('POST', '/api/devices/generate-pairing-code', [], $r['json']['data']['device_token'])['json']['data']['pairing_code'];
    return req('POST', '/api/devices/pair', ['pairing_code' => $c], $BILAL);
})(), 403, 'LIMIT_DEVICES');
expect("can't assign other user's playlist", req('POST', '/api/devices/assign-playlist', ['device_id' => $DID, 'playlist_ids' => [99999]], $BILAL), 400, 'VALIDATION');
$as = expect('assign playlists + default', req('POST', '/api/devices/assign-playlist', ['device_id' => $DID, 'playlist_ids' => [$PID, 1], 'default_playlist_id' => $PID], $BILAL), 200);
check('2 assignments, default set', count($as['assignments'] ?? []) === 2 && array_values(array_filter($as['assignments'], fn($a) => $a['is_default']))[0]['playlist_id'] === $PID);
$hb = expect('heartbeat', req('POST', '/api/devices/heartbeat', ['sync_token' => '', 'playback_state' => 'idle', 'storage_free_mb' => 4000], $DTOK), 200);
check('heartbeat says changed', ($hb['changed'] ?? false) === true);
$sync = expect('sync', req('GET', '/api/devices/sync', null, $DTOK), 200);
check('sync returns 2 playlists with docs', count($sync['assignments'] ?? []) === 2 && count($sync['playlists'] ?? []) === 2);
$doc = array_values(array_filter($sync['playlists'], fn($p) => $p['playlist_id'] === $PID))[0] ?? [];
check('doc has items with screen layouts', !empty($doc['items'][0]['screen']['widgets']) && !empty($doc['items'][0]['screen']['canvas']));
check('doc media is signed, no paths', !empty($doc['media'][0]['url']) && !isset($doc['media'][0]['file_path']) && !empty($doc['media'][0]['checksum_sha256']));
check('doc has schedules', count($doc['schedules'] ?? []) === 1);
$dl = req('GET', parse_url($doc['media'][0]['url'], PHP_URL_PATH) . '?' . parse_url($doc['media'][0]['url'], PHP_URL_QUERY));
check('player can download + verify checksum', $dl['code'] === 200 && hash('sha256', $dl['raw']) === $doc['media'][0]['checksum_sha256']);
$have = implode(',', array_map(fn($a) => "{$a['playlist_id']}:{$a['version']}", $sync['assignments']));
$sync2 = expect('incremental sync', req('GET', "/api/devices/sync?have={$have}", null, $DTOK), 200);
check('no docs re-sent when up to date', count($sync2['playlists'] ?? [1]) === 0);
$hb2 = expect('heartbeat with token', req('POST', '/api/devices/heartbeat', ['sync_token' => $sync2['sync_token'], 'current_playlist_id' => $PID, 'current_screen_id' => $SID], $DTOK), 200);
check('unchanged when nothing changed', ($hb2['changed'] ?? true) === false);
expect('republish screen', req('POST', '/api/screens/publish', ['screen_id' => $SID], $BILAL), 200);
$hb3 = expect('heartbeat after change', req('POST', '/api/devices/heartbeat', ['sync_token' => $sync2['sync_token']], $DTOK), 200);
check('device notified of change', ($hb3['changed'] ?? false) === true);
$sync3 = expect('sync after change', req('GET', "/api/devices/sync?have={$have}", null, $DTOK), 200);
check('only changed playlist re-sent', count($sync3['playlists'] ?? []) === 1 && $sync3['playlists'][0]['playlist_id'] === $PID);
$res = expect('schedule resolve', req('GET', "/api/schedules/resolve?device_id={$DID}&at=" . urlencode('2026-11-09T09:00:00Z'), null, $BILAL), 200);
check('Monday 13:00 Dubai → Lunch rush', ($res['playlist_id'] ?? 0) === $PID && str_contains($res['reason'] ?? '', 'Lunch'), $res);
$res2 = expect('resolve evening', req('GET', "/api/schedules/resolve?device_id={$DID}&at=" . urlencode('2026-11-09T15:00:00Z'), null, $BILAL), 200);
check('Monday 19:00 Dubai → no schedule matches → default playlist', ($res2['playlist_id'] ?? 0) === $PID && str_contains($res2['reason'], 'Default'), $res2);
$res3 = expect('resolve morning', req('GET', "/api/schedules/resolve?device_id={$DID}&at=" . urlencode('2026-11-09T05:00:00Z'), null, $BILAL), 200);
check('Monday 09:00 Dubai → Store Front Loop morning schedule', ($res3['playlist_id'] ?? 0) === 1, $res3);

section('Remote commands, logs, emergency');
expect('unsupported command rejected', req('POST', '/api/devices/command', ['device_id' => $DID, 'command' => 'screenshot'], $BILAL), 422, 'UNSUPPORTED_COMMAND');
$cmd = expect('send restart', req('POST', '/api/devices/command', ['device_id' => $DID, 'command' => 'restart'], $BILAL), 201);
$s4 = expect('sync delivers command', req('GET', "/api/devices/sync?have={$have}", null, $DTOK), 200);
check('command delivered once', count($s4['commands'] ?? []) === 1 && $s4['commands'][0]['command'] === 'restart');
check('not delivered twice', count(req('GET', "/api/devices/sync?have={$have}", null, $DTOK)['json']['data']['commands']) === 0);
expect('ack command', req('POST', '/api/devices/command-ack', ['command_id' => $cmd['command_id'], 'status' => 'completed'], $DTOK), 200);
expect('batch logs', req('POST', '/api/devices/logs', ['logs' => [['log_type' => 'playback_error', 'message' => 'Video failed', 'logged_at' => '2026-11-05T10:00:00Z'], ['message' => 'ok']]], $DTOK), 200);
expect('report error', req('POST', '/api/devices/report-error', ['message' => 'Network lost', 'log_type' => 'warning'], $DTOK), 200);
expect('proof of play', req('POST', '/api/devices/playback', ['entries' => [['playlist_id' => $PID, 'screen_id' => $SID, 'started_at' => '2026-11-05T10:00:00Z', 'duration_ms' => 15000], ['playlist_id' => 1, 'screen_id' => 6, 'started_at' => '2026-11-05T10:00:15Z']]], $DTOK), 200);
$dd = expect('device details w/ logs', req('GET', "/api/devices/details?device_id={$DID}", null, $BILAL), 200);
check('logs visible to owner', count($dd['recent_logs'] ?? []) >= 3);
$em = expect('broadcast emergency', req('POST', '/api/emergency/create', ['title' => 'Evacuate', 'body' => 'Use nearest exit', 'device_ids' => [$DID], 'duration_minutes' => 30], $BILAL), 201);
$s5 = expect('sync shows emergency', req('GET', "/api/devices/sync?have={$have}", null, $DTOK), 200);
check('emergency delivered', count($s5['emergency'] ?? []) === 1 && $s5['emergency'][0]['title'] === 'Evacuate');
expect('cancel emergency', req('POST', '/api/emergency/cancel', ['message_id' => $em['message_id']], $BILAL), 200);
check('emergency cleared', count(req('GET', "/api/devices/sync?have={$have}", null, $DTOK)['json']['data']['emergency']) === 0);
$rep = req('GET', '/api/reports/playback?from=2026-11-01&to=2026-11-30&format=csv', null, $BILAL);
check('CSV report export', $rep['code'] === 200 && str_contains($rep['raw'], 'Display,Playlist,Screen'), $rep['code']);
expect('uptime report', req('GET', '/api/reports/uptime', null, $BILAL), 200);
expect('rename device', req('POST', '/api/devices/update', ['device_id' => $DID, 'device_name' => 'Mall Entrance'], $BILAL), 200);
$dl2 = req('GET', '/api/devices/list', null, $BILAL);
check('device list shows 3 devices with online status', ($dl2['json']['meta']['total'] ?? 0) === 3 && is_bool($dl2['json']['data'][0]['is_online'] ?? null));
expect('unpair device', req('POST', '/api/devices/unpair', ['device_id' => $DID], $BILAL), 200);
$s6 = expect('unpaired device sync', req('GET', '/api/devices/sync', null, $DTOK), 200);
check('unpaired device gets nothing', ($s6['device']['is_paired'] ?? true) === false && count($s6['assignments']) === 0);
expect('unpaired device can get new code', req('POST', '/api/devices/generate-pairing-code', [], $DTOK), 200);

// ---------------------------------------------------------------------------
section('Workflow D — admin creates a user');
$ad = expect('admin dashboard', req('GET', '/api/admin/dashboard', null, $ADMIN), 200);
check('admin stats present', isset($ad['stats']['total_users'], $ad['stats']['connected_devices'], $ad['stats']['storage_used_bytes']));
$nu = expect('create user w/ generated password', req('POST', '/api/admin/create-user', ['full_name' => 'Farah Test', 'email' => 'farah@demo.local', 'plan_id' => 2, 'max_screens' => 1, 'send_email' => true], $ADMIN), 201);
$NUID = $nu['user']['user_id'] ?? 0;
check('credentials returned once', !empty($nu['credentials']['password']) && str_contains($nu['credentials']['share_text'], 'farah@demo.local'));
expect('duplicate email rejected', req('POST', '/api/admin/create-user', ['full_name' => 'Dup', 'email' => 'farah@demo.local'], $ADMIN), 409, 'CONFLICT');
$FARAH = login('farah@demo.local', $nu['credentials']['password']);
check('new user can sign in', strlen($FARAH) === 64);
$fp = req('GET', '/api/auth/profile', null, $FARAH)['json']['data'];
check('must_change_password flagged', ($fp['user']['must_change_password'] ?? false) === true);
expect('first screen allowed', req('POST', '/api/screens/create', ['screen_name' => 'Hello'], $FARAH), 201);
expect('second blocked (max 1)', req('POST', '/api/screens/create', ['screen_name' => 'Hello 2'], $FARAH), 403, 'LIMIT_SCREENS');
expect('admin sets unlimited', req('POST', '/api/admin/update-user-limits', ['user_id' => $NUID, 'max_screens' => -1, 'permissions' => ['templates.premium' => true]], $ADMIN), 200);
expect('now allowed', req('POST', '/api/screens/create', ['screen_name' => 'Hello 2'], $FARAH), 201);
expect('change password', req('POST', '/api/auth/change-password', ['current_password' => $nu['credentials']['password'], 'new_password' => 'Farah2026!x'], $FARAH), 200);
$ud = expect('admin user details', req('GET', "/api/admin/user-details?user_id={$NUID}", null, $ADMIN), 200);
check('details include screens + activity', count($ud['screens'] ?? []) === 2 && isset($ud['activity']));
expect('admin user search', req('GET', '/api/admin/users?q=farah', null, $ADMIN), 200);
expect('suspend user', req('POST', '/api/admin/update-user-status', ['user_id' => $NUID, 'status' => 'suspended'], $ADMIN), 200);
expect('suspended session revoked', req('GET', '/api/auth/profile', null, $FARAH), 401);
expect('reactivate', req('POST', '/api/admin/update-user-status', ['user_id' => $NUID, 'status' => 'active'], $ADMIN), 200);
$rp = expect('admin reset password', req('POST', '/api/admin/update-user', ['user_id' => $NUID, 'reset_password' => true], $ADMIN), 200);
check('new password works', strlen(login('farah@demo.local', $rp['credentials']['password'] ?? '')) === 64);
expect('admin cannot suspend self', req('POST', '/api/admin/update-user-status', ['user_id' => 1, 'status' => 'suspended'], $ADMIN), 400);
$plans = expect('plans list', req('GET', '/api/admin/plans', null, $ADMIN), 200);
check('5 plans', count($plans) === 5);
expect('create plan', req('POST', '/api/admin/plans', ['plan_name' => 'Agency', 'max_screens' => 100, 'max_playlists' => 100, 'max_devices' => 50, 'max_storage_mb' => 51200, 'features' => ['video_upload' => true, 'allowed_widgets' => ['*']]], $ADMIN), 200);
expect('plan in use cannot be deleted', req('POST', '/api/admin/plans', ['action' => 'delete', 'plan_id' => 2], $ADMIN), 409, 'IN_USE');
expect('widget types admin', req('POST', '/api/admin/widget-types', ['widget_key' => 'weather', 'availability' => 'stable'], $ADMIN), 200);
expect('templates admin', req('GET', '/api/admin/templates', null, $ADMIN), 200);
expect('admin devices', req('GET', '/api/admin/devices', null, $ADMIN), 200);
expect('admin media', req('GET', '/api/admin/media', null, $ADMIN), 200);
expect('activity logs', req('GET', '/api/admin/activity-logs?action=admin.', null, $ADMIN), 200);
expect('app settings save', req('POST', '/api/settings/app', ['settings' => ['support_email' => 'help@example.com']], $ADMIN), 200);
expect('delete user', req('POST', '/api/admin/delete-user', ['user_id' => $NUID], $ADMIN), 200);
check('deleted user cannot sign in', req('POST', '/api/auth/login', ['email' => 'farah@demo.local', 'password' => $rp['credentials']['password'] ?? ''])['code'] === 401);

// ---------------------------------------------------------------------------
section('Workflow E — change display background');
$sc = req('GET', '/api/screens/details?screen_id=1', null, $BILAL)['json']['data'];
expect('set image background', req('POST', '/api/screens/update', ['screen_id' => 1, 'background' => ['type' => 'image', 'media_id' => $m['media_id'], 'config' => ['fit' => 'cover', 'blur' => 6, 'overlay_color' => '#000000', 'overlay_opacity' => 0.3]]], $BILAL), 200);
expect('video bg with image media rejected', req('POST', '/api/screens/update', ['screen_id' => 1, 'background' => ['type' => 'video', 'media_id' => $m['media_id']]], $BILAL), 400);
$g = expect('global background usable', req('POST', '/api/screens/update', ['screen_id' => 1, 'background' => ['type' => 'image', 'media_id' => 4]], $BILAL), 200);
check('background persisted', ($g['layout']['background']['media_id'] ?? 0) === 4);
expect('publish', req('POST', '/api/screens/publish', ['screen_id' => 1], $BILAL), 200);

section('Password reset');
expect('forgot password', req('POST', '/api/auth/forgot-password', ['email' => 'chloe@demo.local']), 200);
expect('unknown email gives same answer', req('POST', '/api/auth/forgot-password', ['email' => 'nobody@demo.local']), 200);
$mail = @file_get_contents(__DIR__ . '/../logs/mail.log') ?: '';
preg_match_all('/reset code is: ([A-Z0-9]{8})/', $mail, $mm);
$rc = end($mm[1]) ?: '';
check('reset code emailed (mail.log)', $rc !== '');
expect('bad code rejected', req('POST', '/api/auth/reset-password', ['token' => 'ZZZZZZZZ', 'new_password' => 'Newpass123']), 400, 'RESET_INVALID');
expect('weak password rejected', req('POST', '/api/auth/reset-password', ['token' => $rc, 'new_password' => 'short']), 400, 'VALIDATION');
expect('reset with code', req('POST', '/api/auth/reset-password', ['token' => $rc, 'new_password' => 'Chloe2026new']), 200);
check('login with new password', strlen(login('chloe@demo.local', 'Chloe2026new')) === 64);
expect('code is single-use', req('POST', '/api/auth/reset-password', ['token' => $rc, 'new_password' => 'Another123']), 400);

section('Notifications & logout');
$n = req('GET', '/api/notifications/list', null, $BILAL);
check('notifications list', $n['code'] === 200 && ($n['json']['meta']['total'] ?? 0) >= 1);
expect('mark all read', req('POST', '/api/notifications/mark-read', ['all' => true], $BILAL), 200);
expect('sessions', req('GET', '/api/auth/sessions', null, $BILAL), 200);
expect('logout', req('POST', '/api/auth/logout', [], $BILAL), 200);
expect('token revoked after logout', req('GET', '/api/auth/profile', null, $BILAL), 401);

section('Rate limiting');
$hit = 0;
for ($i = 0; $i < 12; $i++) { if (req('POST', '/api/auth/login', ['email' => 'daniel@demo.local', 'password' => 'wrong'])['code'] === 429) { $hit = $i; break; } }
check('login locked after 10 failures', $hit === 10, $hit);

echo "\n" . str_repeat('─', 50) . "\nResult: {$pass} passed, {$fail} failed\n";
exit($fail ? 1 : 0);
