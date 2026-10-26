<?php
/**
 * One ayah with translation and tafsir.
 * Uses GET /verses/by_key/{key}, /resources/tafsirs, /tafsirs/{id}/by_ayah/{key}.
 *
 *   ayah.php?key=2:255&tr=131&tafsir=169
 */
require __DIR__ . '/bootstrap.php';

$key = preg_match('/^(\d{1,3}):(\d{1,3})$/', $_GET['key'] ?? '', $m) ? $m[0] : '1:1';
$trId = qf_int('tr', QF_DEFAULT_TRANSLATION, 1, 100000);

$error = null;
$verse = [];
$tafsirs = [];
$tafsir = [];
$tafsirId = 0;

try {
    $qf = qf();
    $verse = $qf->verseByKey($key, [
        'translations' => $trId,
        'fields'       => 'text_uthmani,text_indopak_nastaleeq',
    ]);
    $tafsirs = $qf->tafsirs(QF_DEFAULT_LANGUAGE);

    // Default tafsir: Ibn Kathir in English if available, else the first one.
    $tafsirId = qf_int('tafsir', 0, 0, 100000);
    if ($tafsirId === 0) {
        foreach ($tafsirs as $t) {
            if (stripos($t['name'] ?? '', 'kathir') !== false && ($t['language_name'] ?? '') === 'english') {
                $tafsirId = (int)$t['id'];
                break;
            }
        }
        $tafsirId = $tafsirId ?: (int)($tafsirs[0]['id'] ?? 0);
    }
    if ($tafsirId) {
        $tafsir = $qf->tafsirByAyah($tafsirId, $key);
    }
} catch (Throwable $e) {
    $error = $e;
}

[$surah, $ayahNo] = array_map('intval', explode(':', $key));
$pageTitle = "Ayah $key — Quran";

require __DIR__ . '/layout-header.php';
?>
<h1>Surah <?= $surah ?>, Ayah <?= $ayahNo ?></h1>

<?php if ($error): ?>
    <?= qf_error_box($error) ?>
<?php endif; ?>

<?php if ($verse): ?>
    <article class="qf-verse single">
        <p class="qf-arabic ar-uthmani" translate="no" dir="rtl"><?= qf_e($verse['text_uthmani'] ?? '') ?></p>
        <p class="qf-translation"><?= qf_e(QuranClient::plainTranslation($verse['translations'][0]['text'] ?? '')) ?></p>
        <p class="qf-meta">Juz <?= (int)($verse['juz_number'] ?? 0) ?> · Page <?= (int)($verse['page_number'] ?? 0) ?></p>
    </article>
<?php endif; ?>

<form class="qf-controls" method="get">
    <input type="hidden" name="key" value="<?= qf_e($key) ?>">
    <input type="hidden" name="tr" value="<?= (int)$trId ?>">
    <label>Tafsir
        <select name="tafsir" onchange="this.form.submit()">
            <?php foreach ($tafsirs as $t): ?>
                <option value="<?= (int)$t['id'] ?>" <?= (int)$t['id'] === $tafsirId ? 'selected' : '' ?>>
                    <?= qf_e(ucfirst($t['language_name'] ?? '') . ' — ' . $t['name']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </label>
    <noscript><button type="submit">Apply</button></noscript>
</form>

<?php if (!empty($tafsir['text'])): ?>
    <section class="qf-tafsir" <?= in_array($tafsir['translated_name']['language_name'] ?? '', ['arabic', 'urdu'], true) ? 'dir="rtl"' : '' ?>>
        <h2><?= qf_e($tafsir['resource_name'] ?? 'Tafsir') ?></h2>
        <?= QuranClient::safeHtml($tafsir['text']) ?>
    </section>
<?php endif; ?>

<nav class="qf-pager">
    <?php if ($ayahNo > 1): ?><a href="ayah.php?key=<?= $surah ?>:<?= $ayahNo - 1 ?>&tafsir=<?= (int)$tafsirId ?>">← Previous ayah</a><?php endif; ?>
    <a href="surah.php?id=<?= $surah ?>#v<?= $ayahNo ?>">Back to surah</a>
    <a href="ayah.php?key=<?= $surah ?>:<?= $ayahNo + 1 ?>&tafsir=<?= (int)$tafsirId ?>">Next ayah →</a>
</nav>

<?php require __DIR__ . '/layout-footer.php'; ?>
