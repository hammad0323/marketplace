<?php
/** Quran search: GET /search/api/v1/search (quick mode). */
require __DIR__ . '/bootstrap.php';

$q = trim(mb_substr((string)($_GET['q'] ?? ''), 0, 100));
$error = null;
$navigation = [];
$verses = [];

if ($q !== '') {
    try {
        $res = qf()->search($q, 'quick', [
            'translation_ids'      => QF_DEFAULT_TRANSLATION,
            'versesResultsNumber'  => 20,
        ]);
        $navigation = $res['result']['navigation'] ?? [];
        $verses = $res['result']['verses'] ?? [];
    } catch (Throwable $e) {
        $error = $e;
    }
}

$pageTitle = $q !== '' ? "Search: $q" : 'Search the Quran';
require __DIR__ . '/layout-header.php';
?>
<h1>Search</h1>

<form class="qf-controls" method="get">
    <input type="search" name="q" value="<?= qf_e($q) ?>" placeholder="e.g. mercy, Al-Kahf, 2:255" autofocus>
    <button type="submit">Search</button>
</form>

<?php if ($error): ?>
    <?= qf_error_box($error) ?>
<?php endif; ?>

<?php if ($navigation): ?>
    <h2>Go to</h2>
    <ul class="qf-results">
        <?php foreach ($navigation as $n): ?>
            <?php
            $type = $n['result_type'] ?? '';
            $href = $type === 'surah' ? 'surah.php?id=' . (int)$n['key']
                : ($type === 'ayah' ? 'ayah.php?key=' . rawurlencode((string)$n['key']) : null);
            ?>
            <li>
                <?php if ($href): ?><a href="<?= qf_e($href) ?>"><?php endif; ?>
                <?= qf_e(ucfirst(str_replace('_', ' ', $type))) ?>: <?= qf_e($n['name'] ?? $n['key']) ?>
                <?php if ($href): ?></a><?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
<?php endif; ?>

<?php if ($verses): ?>
    <h2>Ayahs</h2>
    <ul class="qf-results">
        <?php foreach ($verses as $v): ?>
            <li>
                <a href="ayah.php?key=<?= qf_e(rawurlencode((string)$v['key'])) ?>"><?= qf_e($v['key']) ?></a>
                <span <?= !empty($v['isArabic']) ? 'class="ar-uthmani" dir="rtl" translate="no"' : '' ?>>
                    <?= qf_e(QuranClient::plainTranslation((string)($v['name'] ?? ''))) ?>
                </span>
            </li>
        <?php endforeach; ?>
    </ul>
<?php elseif ($q !== '' && !$error): ?>
    <p>No results for “<?= qf_e($q) ?>”.</p>
<?php endif; ?>

<?php require __DIR__ . '/layout-footer.php'; ?>
