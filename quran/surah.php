<?php
/**
 * One surah with Arabic text, a translation and per-ayah audio.
 * Uses GET /chapters/{id}, /verses/by_chapter/{id}, /resources/translations
 * and /resources/recitations.
 *
 *   surah.php?id=1&tr=131&script=indopak&reciter=7&page=1
 */
require __DIR__ . '/bootstrap.php';

$id      = qf_int('id', 1, 1, 114);
$page    = qf_int('page', 1, 1, 100);
$trId    = qf_int('tr', QF_DEFAULT_TRANSLATION, 1, 100000);
$script  = isset(QF_SCRIPTS[$_GET['script'] ?? '']) ? $_GET['script'] : 'uthmani';
$scriptCfg = QF_SCRIPTS[$script];

$error = null;
$chapter = [];
$verses = [];
$pagination = [];
$translations = [];
$recitations = [];
$reciterId = 0;

try {
    $qf = qf();
    $chapter      = $qf->chapter($id, QF_DEFAULT_LANGUAGE);
    $translations = $qf->translations(QF_DEFAULT_LANGUAGE);
    $recitations  = $qf->recitations(QF_DEFAULT_LANGUAGE);

    // Default reciter: Mishary Alafasy if present, else the first one.
    $reciterId = qf_int('reciter', 0, 0, 100000);
    if ($reciterId === 0) {
        foreach ($recitations as $r) {
            if (stripos($r['reciter_name'] ?? '', 'afasy') !== false) {
                $reciterId = (int)$r['id'];
                break;
            }
        }
        $reciterId = $reciterId ?: (int)($recitations[0]['id'] ?? 0);
    }

    $res = $qf->versesByChapter($id, [
        'translations' => $trId,
        'audio'        => $reciterId ?: null,
        'fields'       => 'text_uthmani,text_indopak_nastaleeq',
        'page'         => $page,
        'per_page'     => 50,
    ]);
    $verses     = $res['verses'] ?? [];
    $pagination = $res['pagination'] ?? [];
} catch (Throwable $e) {
    $error = $e;
}

$pageTitle = ($chapter['name_simple'] ?? "Surah $id") . ' — Quran';

/** Builds a link to this page keeping the current options. */
function surah_url(array $changes): string
{
    global $id, $trId, $script, $reciterId, $page;
    $q = array_merge(['id' => $id, 'tr' => $trId, 'script' => $script, 'reciter' => $reciterId, 'page' => $page], $changes);
    return 'surah.php?' . http_build_query($q);
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
    <label>Translation
        <select name="tr" onchange="this.form.submit()">
            <?php foreach ($translations as $t): ?>
                <option value="<?= (int)$t['id'] ?>" <?= (int)$t['id'] === $trId ? 'selected' : '' ?>>
                    <?= qf_e(ucfirst($t['language_name']) . ' — ' . $t['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <label>Reciter
        <select name="reciter" onchange="this.form.submit()">
            <?php foreach ($recitations as $r): ?>
                <option value="<?= (int)$r['id'] ?>" <?= (int)$r['id'] === $reciterId ? 'selected' : '' ?>>
                    <?= qf_e($r['reciter_name'] . (!empty($r['style']) ? ' (' . $r['style'] . ')' : '')) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <noscript><button type="submit">Apply</button></noscript>
</form>

<?php if ($error): ?>
    <?= qf_error_box($error) ?>
<?php endif; ?>

<?php if (!empty($chapter['bismillah_pre']) && $page === 1): ?>
    <p class="qf-bismillah <?= qf_e($scriptCfg['class']) ?>" translate="no">بِسۡمِ ٱللَّهِ ٱلرَّحۡمَٰنِ ٱلرَّحِيمِ</p>
<?php endif; ?>

<div class="qf-verses">
    <?php foreach ($verses as $v): ?>
        <?php
        $arabic = $v[$scriptCfg['field']] ?? ($v['text_uthmani'] ?? '');
        $translation = QuranClient::plainTranslation($v['translations'][0]['text'] ?? '');
        $audio = QuranClient::audioUrl($v['audio']['url'] ?? '');
        ?>
        <article class="qf-verse" id="v<?= (int)$v['verse_number'] ?>">
            <div class="qf-verse-tools">
                <a class="key" href="ayah.php?key=<?= qf_e($v['verse_key']) ?>"><?= qf_e($v['verse_key']) ?></a>
                <?php if ($audio): ?>
                    <button type="button" class="play" data-src="<?= qf_e($audio) ?>" aria-label="Play ayah <?= qf_e($v['verse_key']) ?>">▶</button>
                <?php endif; ?>
            </div>
            <p class="qf-arabic <?= qf_e($scriptCfg['class']) ?>" translate="no" dir="rtl"><?= qf_e($arabic) ?></p>
            <?php if ($translation !== ''): ?>
                <p class="qf-translation"><?= qf_e($translation) ?></p>
            <?php endif; ?>
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
// Plays one ayah at a time and continues with the next ayah automatically.
(function () {
    var player = document.getElementById('qf-player');
    var buttons = Array.prototype.slice.call(document.querySelectorAll('.qf-verse .play'));
    var current = -1;
    function play(i) {
        buttons.forEach(function (b) { b.textContent = '▶'; b.closest('.qf-verse').classList.remove('playing'); });
        if (i < 0 || i >= buttons.length) { current = -1; return; }
        current = i;
        player.src = buttons[i].dataset.src;
        player.play();
        buttons[i].textContent = '❚❚';
        buttons[i].closest('.qf-verse').classList.add('playing');
    }
    buttons.forEach(function (b, i) {
        b.addEventListener('click', function () {
            if (current === i && !player.paused) { player.pause(); b.textContent = '▶'; return; }
            play(i);
        });
    });
    player.addEventListener('ended', function () { play(current + 1); });
})();
</script>

<?php require __DIR__ . '/layout-footer.php'; ?>
