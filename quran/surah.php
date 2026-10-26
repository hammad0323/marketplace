<?php
/**
 * One surah with Arabic text, up to two translations and audio.
 *
 * Audio comes from either reciter list:
 *   c:ID  full-surah reciters (/resources/chapter_reciters): the full list
 *         Quran.com uses, with per-ayah timestamps for highlighting
 *   a:ID  ayah-by-ayah reciters (/resources/recitations): a smaller list
 *
 *   surah.php?id=1&tr=131&tr2=97&script=indopak&audio=c:7&page=1
 */
require __DIR__ . '/bootstrap.php';

$id     = qf_int('id', 1, 1, 114);
$page   = qf_int('page', 1, 1, 100);
$trId   = qf_int('tr', QF_DEFAULT_TRANSLATION, 1, 100000);
$tr2Id  = qf_int('tr2', 0, 0, 100000);
$script = isset(QF_SCRIPTS[$_GET['script'] ?? '']) ? $_GET['script'] : 'uthmani';
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

    $res = $qf->versesByChapter($id, [
        'translations' => implode(',', array_filter([$trId, $tr2Id])),
        'audio'        => $audioMode === 'a' ? $audioId : null,
        'fields'       => 'text_uthmani,text_indopak_nastaleeq',
        'page'         => $page,
        'per_page'     => 50,
    ]);
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
    $trNames[(int)$t['id']] = $t['name'] . (!empty($t['author_name']) && $t['author_name'] !== $t['name'] ? ' — ' . $t['author_name'] : '');
}
ksort($trByLanguage);

// Per-ayah start/end (ms) in the full-surah file, keyed by verse key.
$timings = [];
foreach ($fullAudio['timestamps'] ?? [] as $ts) {
    $timings[$ts['verse_key']] = [(int)$ts['timestamp_from'], (int)$ts['timestamp_to']];
}

$pageTitle = ($chapter['name_simple'] ?? "Surah $id") . ' — Quran';

/** Builds a link to this page keeping the current options. */
function surah_url(array $changes): string
{
    global $id, $trId, $tr2Id, $script, $audio, $page;
    $q = array_merge(['id' => $id, 'tr' => $trId, 'tr2' => $tr2Id, 'script' => $script, 'audio' => $audio, 'page' => $page], $changes);
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

require __DIR__ . '/layout-header.php';
?>

<?php if ($chapter): ?>
    <div class="qf-surah-head">
        <div class="ar" translate="no"><?= qf_e($chapter['name_arabic']) ?></div>
        <h1><?= (int)$id ?>. <?= qf_e($chapter['name_simple']) ?></h1>
        <p><?= qf_e($chapter['translated_name']['name'] ?? '') ?> · <?= (int)$chapter['verses_count'] ?> ayahs ·
            <a href="info.php?id=<?= (int)$id ?>">About this surah</a></p>
    </div>
<?php endif; ?>

<form class="qf-controls" method="get">
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <label>Script
        <select name="script" onchange="this.form.submit()">
            <?php foreach (QF_SCRIPTS as $key => $s): ?>
                <option value="<?= qf_e($key) ?>" <?= $key === $script ? 'selected' : '' ?>><?= qf_e($s['label']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Translation (<?= count($translations) ?>)
        <select name="tr" onchange="this.form.submit()">
            <?= translation_options($trByLanguage, $trId) ?>
        </select>
    </label>
    <label>Second translation
        <select name="tr2" onchange="this.form.submit()">
            <option value="0">— None —</option>
            <?= translation_options($trByLanguage, $tr2Id) ?>
        </select>
    </label>
    <label>Reciter (<?= count($chapterReciters) + count($recitations) ?>)
        <select name="audio" onchange="this.form.submit()">
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
    <noscript><button type="submit">Apply</button></noscript>
</form>

<?php if ($error): ?>
    <?= qf_error_box($error) ?>
<?php endif; ?>

<?php if (!empty($fullAudio['audio_url'])): ?>
    <div class="qf-player">
        <audio id="qf-full" controls preload="none" src="<?= qf_e(QuranClient::audioUrl($fullAudio['audio_url'])) ?>"></audio>
    </div>
<?php endif; ?>

<?php if (!empty($chapter['bismillah_pre']) && $page === 1): ?>
    <p class="qf-bismillah <?= qf_e($scriptCfg['class']) ?>" translate="no">بِسۡمِ ٱللَّهِ ٱلرَّحۡمَٰنِ ٱلرَّحِيمِ</p>
<?php endif; ?>

<div class="qf-verses">
    <?php foreach ($verses as $v): ?>
        <?php
        $arabic = $v[$scriptCfg['field']] ?? ($v['text_uthmani'] ?? '');
        $ayahAudio = QuranClient::audioUrl($v['audio']['url'] ?? '');
        $timing = $timings[$v['verse_key']] ?? null;
        ?>
        <article class="qf-verse" id="v<?= (int)$v['verse_number'] ?>"
            <?php if ($timing): ?>data-from="<?= $timing[0] ?>" data-to="<?= $timing[1] ?>"<?php endif; ?>>
            <div class="qf-verse-tools">
                <a class="key" href="ayah.php?key=<?= qf_e($v['verse_key']) ?>"><?= qf_e($v['verse_key']) ?></a>
                <?php if ($ayahAudio): ?>
                    <button type="button" class="play" data-src="<?= qf_e($ayahAudio) ?>" aria-label="Play ayah <?= qf_e($v['verse_key']) ?>">▶</button>
                <?php elseif ($timing): ?>
                    <button type="button" class="seek" aria-label="Play from ayah <?= qf_e($v['verse_key']) ?>">▶</button>
                <?php endif; ?>
            </div>
            <p class="qf-arabic <?= qf_e($scriptCfg['class']) ?>" translate="no" dir="rtl"><?= qf_e($arabic) ?></p>
            <?php foreach ($v['translations'] ?? [] as $t): ?>
                <?php $text = QuranClient::plainTranslation($t['text'] ?? ''); ?>
                <?php if ($text !== ''): ?>
                    <p class="qf-translation"><?= qf_e($text) ?>
                        <?php if ($tr2Id): ?><small class="qf-meta"> — <?= qf_e($trNames[(int)$t['resource_id']] ?? '') ?></small><?php endif; ?>
                    </p>
                <?php endif; ?>
            <?php endforeach; ?>
        </article>
    <?php endforeach; ?>
</div>

<?php if (($pagination['total_pages'] ?? 1) > 1): ?>
    <nav class="qf-pager">
        <?php if (!empty($pagination['current_page']) && $pagination['current_page'] > 1): ?>
            <a href="<?= qf_e(surah_url(['page' => $page - 1])) ?>">← Previous</a>
        <?php endif; ?>
        <span>Page <?= (int)$pagination['current_page'] ?> of <?= (int)$pagination['total_pages'] ?></span>
        <?php if (!empty($pagination['next_page'])): ?>
            <a href="<?= qf_e(surah_url(['page' => (int)$pagination['next_page']])) ?>">Next →</a>
        <?php endif; ?>
    </nav>
<?php endif; ?>

<nav class="qf-pager">
    <?php if ($id > 1): ?><a href="<?= qf_e(surah_url(['id' => $id - 1, 'page' => 1])) ?>">← Surah <?= $id - 1 ?></a><?php endif; ?>
    <a href="index.php">All surahs</a>
    <?php if ($id < 114): ?><a href="<?= qf_e(surah_url(['id' => $id + 1, 'page' => 1])) ?>">Surah <?= $id + 1 ?> →</a><?php endif; ?>
</nav>

<audio id="qf-player" preload="none"></audio>
<script>
(function () {
    var verses = Array.prototype.slice.call(document.querySelectorAll('.qf-verse'));
    function mark(el) {
        verses.forEach(function (v) { v.classList.toggle('playing', v === el); });
    }

    // Full-surah reciter: ▶ jumps to the ayah, playback highlights the current ayah.
    var full = document.getElementById('qf-full');
    if (full) {
        var current = null;
        verses.forEach(function (v) {
            var btn = v.querySelector('.seek');
            if (!btn) return;
            btn.addEventListener('click', function () {
                full.currentTime = Number(v.dataset.from) / 1000;
                full.play();
            });
        });
        full.addEventListener('timeupdate', function () {
            var ms = full.currentTime * 1000, found = null;
            verses.forEach(function (v) {
                if (v.dataset.from && ms >= Number(v.dataset.from) && ms < Number(v.dataset.to)) found = v;
            });
            if (found !== current) {
                current = found;
                mark(found);
                if (found) found.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
        return;
    }

    // Ayah-by-ayah reciter: plays one ayah and continues with the next.
    var player = document.getElementById('qf-player');
    var buttons = Array.prototype.slice.call(document.querySelectorAll('.qf-verse .play'));
    var index = -1;
    function play(i) {
        buttons.forEach(function (b) { b.textContent = '▶'; });
        if (i < 0 || i >= buttons.length) { index = -1; mark(null); return; }
        index = i;
        player.src = buttons[i].dataset.src;
        player.play();
        buttons[i].textContent = '❚❚';
        mark(buttons[i].closest('.qf-verse'));
    }
    buttons.forEach(function (b, i) {
        b.addEventListener('click', function () {
            if (index === i && !player.paused) { player.pause(); b.textContent = '▶'; return; }
            play(i);
        });
    });
    player.addEventListener('ended', function () { play(index + 1); });
})();
</script>

<?php require __DIR__ . '/layout-footer.php'; ?>
