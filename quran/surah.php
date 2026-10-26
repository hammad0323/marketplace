<?php
/**
 * One surah, with Quran.com-style settings.
 *
 * Server-side options (in the URL, so links can be shared):
 *   id       surah 1-114              page    result page (50 ayahs each)
 *   ayah     jump to this ayah        view    'translation' | 'reading'
 *   script   uthmani | indopak | tajweed
 *   tr, tr2  translation IDs (tr2 = 0 for none)
 *   audio    c:ID full-surah reciter (/resources/chapter_reciters)
 *            a:ID ayah-by-ayah reciter (/resources/recitations)
 *   wbw      1 = word-by-word          wbwlang word-by-word language
 *
 * Browser-side options (remembered in localStorage): font sizes, show
 * translation / transliteration under words, repeat, speed, auto-scroll.
 */
require __DIR__ . '/bootstrap.php';

const PER_PAGE = 50;
const WBW_LANGUAGES = [
    'en' => 'English', 'ur' => 'Urdu', 'bn' => 'Bengali', 'id' => 'Indonesian',
    'tr' => 'Turkish', 'hi' => 'Hindi', 'fa' => 'Persian', 'ta' => 'Tamil',
];

$id      = qf_int('id', 1, 1, 114);
$jumpTo  = qf_int('ayah', 0, 0, 286);
$page    = $jumpTo ? (int)ceil($jumpTo / PER_PAGE) : qf_int('page', 1, 1, 10);
$trId    = qf_int('tr', QF_DEFAULT_TRANSLATION, 1, 100000);
$tr2Id   = qf_int('tr2', 0, 0, 100000);
$script  = isset(QF_SCRIPTS[$_GET['script'] ?? '']) ? $_GET['script'] : 'uthmani';
$view    = ($_GET['view'] ?? '') === 'reading' ? 'reading' : 'translation';
$wbw     = ($_GET['wbw'] ?? '') === '1';
$wbwLang = isset(WBW_LANGUAGES[$_GET['wbwlang'] ?? '']) ? $_GET['wbwlang'] : QF_DEFAULT_LANGUAGE;
$scriptCfg = QF_SCRIPTS[$script];

$audio = preg_match('/^([ca]):(\d{1,6})$/', $_GET['audio'] ?? '', $m) ? $m[0] : '';
$audioMode = $m[1] ?? '';
$audioId = (int)($m[2] ?? 0);

$error = null;
$chapter = [];
$verses = [];
$pagination = [];
$translations = [];
$recitations = [];
$chapterReciters = [];
$fullAudio = [];

try {
    $qf = qf();
    $chapter         = $qf->chapter($id, QF_DEFAULT_LANGUAGE);
    $translations    = $qf->translations(QF_DEFAULT_LANGUAGE);
    $recitations     = $qf->recitations(QF_DEFAULT_LANGUAGE);
    $chapterReciters = $qf->chapterReciters(QF_DEFAULT_LANGUAGE);

    // Default audio: Mishary Alafasy from the full list, else the first reciter of either list.
    if ($audio === '') {
        foreach ($chapterReciters as $r) {
            if (stripos($r['name'] ?? '', 'afasy') !== false) {
                [$audioMode, $audioId] = ['c', (int)$r['id']];
                break;
            }
        }
        if (!$audioId && $chapterReciters) {
            [$audioMode, $audioId] = ['c', (int)$chapterReciters[0]['id']];
        } elseif (!$audioId && $recitations) {
            [$audioMode, $audioId] = ['a', (int)$recitations[0]['id']];
        }
        $audio = $audioId ? "$audioMode:$audioId" : '';
    }

    $opts = [
        'translations' => implode(',', array_filter([$trId, $tr2Id])),
        'audio'        => $audioMode === 'a' ? $audioId : null,
        'fields'       => 'text_uthmani,text_indopak_nastaleeq' . ($script === 'tajweed' ? ',text_uthmani_tajweed' : ''),
        'language'     => $wbwLang,
        'page'         => $page,
        'per_page'     => PER_PAGE,
    ];
    if ($wbw) {
        $opts['words'] = true;
        $opts['word_fields'] = 'text_uthmani,text_indopak_nastaleeq';
    }
    $res = $qf->versesByChapter($id, $opts);
    $verses     = $res['verses'] ?? [];
    $pagination = $res['pagination'] ?? [];

    if ($audioMode === 'c') {
        $fullAudio = $qf->chapterAudio($audioId, $id, true);
    }
} catch (Throwable $e) {
    $error = $e;
}

// Translations grouped by language for the dropdowns, plus id => name.
$trByLanguage = [];
$trNames = [];
foreach ($translations as $t) {
    $trByLanguage[ucfirst($t['language_name'] ?? 'other')][] = $t;
    $trNames[(int)$t['id']] = $t['name'];
}
ksort($trByLanguage);

// Per-ayah start/end (ms) inside the full-surah file.
$timings = [];
foreach ($fullAudio['timestamps'] ?? [] as $ts) {
    $timings[$ts['verse_key']] = [(int)$ts['timestamp_from'], (int)$ts['timestamp_to']];
}
$fullUrl = QuranClient::audioUrl($fullAudio['audio_url'] ?? '');

$pageTitle = ($chapter['name_simple'] ?? "Surah $id") . ' — Quran';

/** Link to this page keeping the current options. */
function surah_url(array $changes): string
{
    global $id, $trId, $tr2Id, $script, $audio, $page, $view, $wbw, $wbwLang;
    $q = array_merge([
        'id' => $id, 'view' => $view, 'script' => $script, 'tr' => $trId, 'tr2' => $tr2Id,
        'audio' => $audio, 'wbw' => $wbw ? 1 : 0, 'wbwlang' => $wbwLang, 'page' => $page,
    ], $changes);
    return 'surah.php?' . http_build_query($q);
}

function translation_options(array $byLanguage, int $selected): string
{
    $html = '';
    foreach ($byLanguage as $language => $items) {
        usort($items, static function ($a, $b) {
            return strcmp($a['name'], $b['name']);
        });
        $html .= '<optgroup label="' . qf_e($language) . ' (' . count($items) . ')">';
        foreach ($items as $t) {
            $html .= '<option value="' . (int)$t['id'] . '"' . ((int)$t['id'] === $selected ? ' selected' : '') . '>'
                . qf_e($t['name']) . '</option>';
        }
        $html .= '</optgroup>';
    }
    return $html;
}

/** Arabic of one verse in the chosen script (tajweed keeps its colour tags). */
function verse_arabic(array $v, string $script): string
{
    if ($script === 'tajweed' && !empty($v['text_uthmani_tajweed'])) {
        return QuranClient::tajweedHtml($v['text_uthmani_tajweed']);
    }
    $field = $script === 'indopak' ? 'text_indopak_nastaleeq' : 'text_uthmani';
    return qf_e($v[$field] ?? ($v['text_uthmani'] ?? ''));
}

/** Arabic ayah number, e.g. 12 => ١٢. */
function arabic_number(int $n): string
{
    return strtr((string)$n, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);
}

require __DIR__ . '/layout-header.php';
?>

<?php if ($chapter): ?>
    <div class="qf-surah-head">
        <div class="ar" translate="no"><?= qf_e($chapter['name_arabic']) ?></div>
        <h1><?= (int)$id ?>. <?= qf_e($chapter['name_simple']) ?></h1>
        <p><?= qf_e($chapter['translated_name']['name'] ?? '') ?> · <?= (int)$chapter['verses_count'] ?> ayahs ·
            <?= qf_e(ucfirst($chapter['revelation_place'] ?? '')) ?> ·
            <a href="info.php?id=<?= (int)$id ?>">About this surah</a></p>
    </div>
<?php endif; ?>

<!-- Settings -->
<details class="qf-settings" id="qf-settings" open>
    <summary>Settings</summary>
    <form method="get" id="qf-settings-form">
        <input type="hidden" name="id" value="<?= (int)$id ?>">

        <div class="qf-tabs" role="radiogroup" aria-label="View">
            <label><input type="radio" name="view" value="translation" <?= $view === 'translation' ? 'checked' : '' ?>> Translation</label>
            <label><input type="radio" name="view" value="reading" <?= $view === 'reading' ? 'checked' : '' ?>> Reading</label>
        </div>

        <div class="qf-controls">
            <label>Script
                <select name="script">
                    <?php foreach (QF_SCRIPTS as $key => $s): ?>
                        <option value="<?= qf_e($key) ?>" <?= $key === $script ? 'selected' : '' ?>><?= qf_e($s['label']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Translation (<?= count($translations) ?>)
                <select name="tr"><?= translation_options($trByLanguage, $trId) ?></select>
            </label>
            <label>Second translation
                <select name="tr2">
                    <option value="0">— None —</option>
                    <?= translation_options($trByLanguage, $tr2Id) ?>
                </select>
            </label>
            <label>Reciter (<?= count($chapterReciters) + count($recitations) ?>)
                <select name="audio">
                    <?php if ($chapterReciters): ?>
                        <optgroup label="Full surah — <?= count($chapterReciters) ?> reciters">
                            <?php foreach ($chapterReciters as $r): ?>
                                <?php $val = 'c:' . (int)$r['id']; ?>
                                <option value="<?= qf_e($val) ?>" <?= $val === $audio ? 'selected' : '' ?>>
                                    <?= qf_e(($r['name'] ?? '') . (!empty($r['style']['name']) ? ' (' . $r['style']['name'] . ')' : '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                    <?php if ($recitations): ?>
                        <optgroup label="Ayah by ayah — <?= count($recitations) ?> reciters">
                            <?php foreach ($recitations as $r): ?>
                                <?php $val = 'a:' . (int)$r['id']; ?>
                                <option value="<?= qf_e($val) ?>" <?= $val === $audio ? 'selected' : '' ?>>
                                    <?= qf_e($r['reciter_name'] . (!empty($r['style']) ? ' (' . $r['style'] . ')' : '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                </select>
            </label>
            <label class="qf-check"><input type="checkbox" name="wbw" value="1" <?= $wbw ? 'checked' : '' ?>> Word by word</label>
            <label>Word-by-word language
                <select name="wbwlang">
                    <?php foreach (WBW_LANGUAGES as $code => $name): ?>
                        <option value="<?= qf_e($code) ?>" <?= $code === $wbwLang ? 'selected' : '' ?>><?= qf_e($name) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <!-- Browser-only settings (no reload) -->
        <div class="qf-controls">
            <label>Arabic size <input type="range" min="20" max="56" step="2" data-pref="arSize"></label>
            <label>Translation size <input type="range" min="13" max="28" step="1" data-pref="trSize"></label>
            <label class="qf-check"><input type="checkbox" data-pref="showWordTr"> Word translation</label>
            <label class="qf-check"><input type="checkbox" data-pref="showWordTl"> Transliteration</label>
            <label>Repeat each ayah
                <select data-pref="repeat">
                    <option value="1">1×</option><option value="2">2×</option><option value="3">3×</option>
                    <option value="5">5×</option><option value="0">∞</option>
                </select>
            </label>
            <label>Speed
                <select data-pref="speed">
                    <option value="0.75">0.75×</option><option value="1">1×</option>
                    <option value="1.25">1.25×</option><option value="1.5">1.5×</option><option value="2">2×</option>
                </select>
            </label>
            <label class="qf-check"><input type="checkbox" data-pref="autoNext"> Continue to next ayah</label>
            <label class="qf-check"><input type="checkbox" data-pref="autoScroll"> Auto-scroll</label>
        </div>
        <noscript><button type="submit">Apply</button></noscript>
    </form>

    <form method="get" class="qf-controls qf-jump">
        <input type="hidden" name="id" value="<?= (int)$id ?>">
        <?php foreach (['view' => $view, 'script' => $script, 'tr' => $trId, 'tr2' => $tr2Id, 'audio' => $audio, 'wbw' => $wbw ? 1 : 0, 'wbwlang' => $wbwLang] as $k => $val): ?>
            <input type="hidden" name="<?= qf_e($k) ?>" value="<?= qf_e($val) ?>">
        <?php endforeach; ?>
        <label>Go to ayah
            <input type="number" name="ayah" min="1" max="<?= (int)($chapter['verses_count'] ?? 286) ?>" value="<?= $jumpTo ?: '' ?>" placeholder="1–<?= (int)($chapter['verses_count'] ?? 286) ?>">
        </label>
        <button type="submit">Go</button>
        <button type="button" id="qf-play-all">▶ Play from start</button>
        <button type="button" id="qf-stop-all" class="secondary">■ Stop</button>
    </form>
</details>

<?php if ($error): ?>
    <?= qf_error_box($error) ?>
<?php endif; ?>

<?php if (!empty($chapter['bismillah_pre']) && $page === 1): ?>
    <p class="qf-bismillah ar-uthmani" translate="no">بِسۡمِ ٱللَّهِ ٱلرَّحۡمَٰنِ ٱلرَّحِيمِ</p>
<?php endif; ?>

<?php if ($view === 'reading'): ?>
    <!-- Reading view: continuous Arabic; tap an ayah to play / stop it -->
    <div class="qf-reading <?= qf_e($scriptCfg['class']) ?>" dir="rtl" translate="no">
        <?php foreach ($verses as $v): ?>
            <?php
            $timing = $timings[$v['verse_key']] ?? null;
            $ayahAudio = QuranClient::audioUrl($v['audio']['url'] ?? '');
            ?>
            <span class="qf-verse qf-inline" id="v<?= (int)$v['verse_number'] ?>"
                  data-key="<?= qf_e($v['verse_key']) ?>"
                  <?php if ($ayahAudio): ?>data-src="<?= qf_e($ayahAudio) ?>"<?php endif; ?>
                  <?php if ($timing): ?>data-from="<?= $timing[0] ?>" data-to="<?= $timing[1] ?>"<?php endif; ?>
                  role="button" tabindex="0" title="Play / stop <?= qf_e($v['verse_key']) ?>"><?= verse_arabic($v, $script) ?>
                <span class="qf-ayah-no">﴿<?= arabic_number((int)$v['verse_number']) ?>﴾</span></span>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <!-- Translation view: one row per ayah with its own play / stop button -->
    <div class="qf-verses">
        <?php foreach ($verses as $v): ?>
            <?php
            $timing = $timings[$v['verse_key']] ?? null;
            $ayahAudio = QuranClient::audioUrl($v['audio']['url'] ?? '');
            $hasAudio = $ayahAudio !== '' || ($timing && $fullUrl !== '');
            ?>
            <article class="qf-verse" id="v<?= (int)$v['verse_number'] ?>"
                     data-key="<?= qf_e($v['verse_key']) ?>"
                     <?php if ($ayahAudio): ?>data-src="<?= qf_e($ayahAudio) ?>"<?php endif; ?>
                     <?php if ($timing): ?>data-from="<?= $timing[0] ?>" data-to="<?= $timing[1] ?>"<?php endif; ?>>
                <div class="qf-verse-tools">
                    <span class="key"><?= qf_e($v['verse_key']) ?></span>
                    <?php if ($hasAudio): ?>
                        <button type="button" class="play" aria-label="Play ayah <?= qf_e($v['verse_key']) ?>">▶</button>
                    <?php endif; ?>
                    <a class="tool" href="ayah.php?key=<?= qf_e($v['verse_key']) ?>&amp;tr=<?= (int)$trId ?>">Tafsir</a>
                    <button type="button" class="tool copy">Copy</button>
                    <span class="qf-meta">Juz <?= (int)($v['juz_number'] ?? 0) ?> · Page <?= (int)($v['page_number'] ?? 0) ?>
                        <?php if (!empty($v['sajdah_number'])): ?> · ۩ Sajdah<?php endif; ?></span>
                </div>

                <?php if ($wbw && !empty($v['words'])): ?>
                    <div class="qf-words" dir="rtl" translate="no">
                        <?php foreach ($v['words'] as $w): ?>
                            <?php if (($w['char_type_name'] ?? 'word') === 'end'): ?>
                                <span class="qf-word end ar-uthmani"><?= qf_e($w['text_uthmani'] ?? '') ?></span>
                            <?php else: ?>
                                <span class="qf-word">
                                    <span class="ar <?= qf_e($scriptCfg['class']) ?>"><?= qf_e(
                                        $script === 'indopak' ? ($w['text_indopak_nastaleeq'] ?? $w['text_uthmani'] ?? '') : ($w['text_uthmani'] ?? '')
                                    ) ?></span>
                                    <small class="w-tl" dir="ltr"><?= qf_e($w['transliteration']['text'] ?? '') ?></small>
                                    <small class="w-tr" dir="auto"><?= qf_e($w['translation']['text'] ?? '') ?></small>
                                </span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p class="qf-arabic <?= qf_e($scriptCfg['class']) ?>" translate="no" dir="rtl"><?= verse_arabic($v, $script) ?></p>
                <?php endif; ?>

                <?php foreach ($v['translations'] ?? [] as $t): ?>
                    <?php $text = QuranClient::plainTranslation($t['text'] ?? ''); ?>
                    <?php if ($text !== ''): ?>
                        <p class="qf-translation" dir="auto"><?= qf_e($text) ?>
                            <small class="qf-meta"> — <?= qf_e($trNames[(int)$t['resource_id']] ?? '') ?></small></p>
                    <?php endif; ?>
                <?php endforeach; ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if (($pagination['total_pages'] ?? 1) > 1): ?>
    <nav class="qf-pager">
        <?php if ($page > 1): ?>
            <a href="<?= qf_e(surah_url(['page' => $page - 1])) ?>">← Previous ayahs</a>
        <?php endif; ?>
        <span>Page <?= (int)($pagination['current_page'] ?? $page) ?> of <?= (int)$pagination['total_pages'] ?></span>
        <?php if (!empty($pagination['next_page'])): ?>
            <a href="<?= qf_e(surah_url(['page' => (int)$pagination['next_page']])) ?>">Next ayahs →</a>
        <?php endif; ?>
    </nav>
<?php endif; ?>

<nav class="qf-pager">
    <?php if ($id > 1): ?><a href="<?= qf_e(surah_url(['id' => $id - 1, 'page' => 1])) ?>">← Surah <?= $id - 1 ?></a><?php endif; ?>
    <a href="index.php">All surahs</a>
    <?php if ($id < 114): ?><a href="<?= qf_e(surah_url(['id' => $id + 1, 'page' => 1])) ?>">Surah <?= $id + 1 ?> →</a><?php endif; ?>
</nav>

<audio id="qf-player" preload="none" <?php if ($fullUrl): ?>data-full="<?= qf_e($fullUrl) ?>"<?php endif; ?>></audio>

<script>
(function () {
    /* ---------- Settings: reload for server options, save browser options ---------- */
    var form = document.getElementById('qf-settings-form');
    form.querySelectorAll('select[name], input[name]').forEach(function (el) {
        el.addEventListener('change', function () { form.submit(); });
    });

    var defaults = { arSize: 32, trSize: 16, showWordTr: true, showWordTl: true, repeat: '1', speed: '1', autoNext: true, autoScroll: true };
    var prefs = {};
    try { prefs = JSON.parse(localStorage.getItem('qfPrefs') || '{}'); } catch (e) {}
    function pref(k) { return prefs[k] !== undefined ? prefs[k] : defaults[k]; }
    function applyPrefs() {
        var root = document.documentElement.style;
        root.setProperty('--ar-size', pref('arSize') + 'px');
        root.setProperty('--tr-size', pref('trSize') + 'px');
        document.body.classList.toggle('hide-wtr', !pref('showWordTr'));
        document.body.classList.toggle('hide-wtl', !pref('showWordTl'));
        player.playbackRate = Number(pref('speed'));
    }
    document.querySelectorAll('[data-pref]').forEach(function (el) {
        var k = el.dataset.pref;
        if (el.type === 'checkbox') el.checked = !!pref(k); else el.value = pref(k);
        el.addEventListener(el.type === 'range' ? 'input' : 'change', function () {
            prefs[k] = el.type === 'checkbox' ? el.checked : el.value;
            try { localStorage.setItem('qfPrefs', JSON.stringify(prefs)); } catch (e) {}
            applyPrefs();
        });
    });

    /* ---------- Audio: one play / stop button per ayah ---------- */
    var player = document.getElementById('qf-player');
    var fullSrc = player.dataset.full || '';
    var rows = Array.prototype.slice.call(document.querySelectorAll('.qf-verse'))
        .filter(function (r) { return r.dataset.src || (fullSrc && r.dataset.from); });
    var current = -1, playsLeft = 0;

    function setUi(i) {
        rows.forEach(function (r, n) {
            r.classList.toggle('playing', n === i);
            var b = r.querySelector('.play');
            if (b) { b.textContent = n === i ? '■' : '▶'; b.setAttribute('aria-label', (n === i ? 'Stop ayah ' : 'Play ayah ') + r.dataset.key); }
        });
    }

    function stop() {
        player.pause();
        current = -1;
        setUi(-1);
    }

    function start(i, isRepeat) {
        if (i < 0 || i >= rows.length) { stop(); return; }
        var row = rows[i];
        if (!isRepeat) {
            var r = Number(pref('repeat'));
            playsLeft = r === 0 ? Infinity : r;
        }
        current = i;
        if (row.dataset.src) {
            if (player.getAttribute('src') !== row.dataset.src) player.src = row.dataset.src;
            player.currentTime = 0;
        } else {
            if (player.getAttribute('src') !== fullSrc) player.src = fullSrc;
            player.currentTime = Number(row.dataset.from) / 1000;
        }
        player.playbackRate = Number(pref('speed'));
        player.play();
        setUi(i);
        if (pref('autoScroll')) row.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    function ayahFinished() {
        playsLeft--;
        if (playsLeft > 0) { start(current, true); return; }
        if (pref('autoNext')) start(current + 1, false); else stop();
    }

    // Ayah-by-ayah files end on their own; full-surah audio is cut at the ayah's end time.
    player.addEventListener('ended', function () { if (current >= 0) ayahFinished(); });
    player.addEventListener('timeupdate', function () {
        if (current < 0) return;
        var row = rows[current];
        if (!row.dataset.src && row.dataset.to && player.currentTime * 1000 >= Number(row.dataset.to)) ayahFinished();
    });

    rows.forEach(function (row, i) {
        var trigger = row.querySelector('.play') || row;
        function toggle(e) {
            if (e && e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') return;
            if (e) e.preventDefault();
            if (current === i && !player.paused) stop(); else start(i, false);
        }
        trigger.addEventListener('click', toggle);
        if (trigger === row) row.addEventListener('keydown', toggle);
    });

    document.getElementById('qf-play-all').addEventListener('click', function () { start(0, false); });
    document.getElementById('qf-stop-all').addEventListener('click', stop);

    /* ---------- Copy ayah ---------- */
    document.querySelectorAll('.qf-verse .copy').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var row = btn.closest('.qf-verse');
            var parts = [row.querySelector('.qf-arabic, .qf-words')];
            row.querySelectorAll('.qf-translation').forEach(function (t) { parts.push(t); });
            var text = parts.filter(Boolean).map(function (el) { return el.innerText.trim(); }).join('\n\n') + '\n— Quran ' + row.dataset.key;
            (navigator.clipboard ? navigator.clipboard.writeText(text) : Promise.reject()).then(function () {
                btn.textContent = 'Copied ✓';
                setTimeout(function () { btn.textContent = 'Copy'; }, 1500);
            }).catch(function () {});
        });
    });

    applyPrefs();

    /* ---------- Settings panel: collapsed on phones, remembers open / closed ---------- */
    var panel = document.getElementById('qf-settings');
    panel.open = prefs.settingsOpen !== undefined ? prefs.settingsOpen : window.innerWidth > 700;
    panel.addEventListener('toggle', function () {
        prefs.settingsOpen = panel.open;
        try { localStorage.setItem('qfPrefs', JSON.stringify(prefs)); } catch (e) {}
    });

    /* ---------- Jump to ayah ---------- */
    <?php if ($jumpTo): ?>
    var target = document.getElementById('v<?= (int)$jumpTo ?>');
    if (target) { target.scrollIntoView({ block: 'center' }); target.classList.add('jumped'); }
    <?php endif; ?>
})();
</script>

<?php require __DIR__ . '/layout-footer.php'; ?>
