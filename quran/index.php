<?php
/** Surah list: GET /chapters */
require __DIR__ . '/bootstrap.php';

$pageTitle = 'The Holy Quran — Surah List';

try {
    $chapters = qf()->chapters(QF_DEFAULT_LANGUAGE);
    $error = null;
} catch (Throwable $e) {
    $chapters = [];
    $error = $e;
}

require __DIR__ . '/layout-header.php';
?>
<h1>Surahs</h1>

<?php if ($error): ?>
    <?= qf_error_box($error) ?>
<?php endif; ?>

<ul class="qf-chapters">
    <?php foreach ($chapters as $c): ?>
        <li>
            <a href="surah.php?id=<?= (int)$c['id'] ?>">
                <span class="num"><?= (int)$c['id'] ?></span>
                <span class="names">
                    <strong><?= qf_e($c['name_simple']) ?></strong>
                    <small><?= qf_e($c['translated_name']['name'] ?? '') ?> · <?= (int)$c['verses_count'] ?> ayahs · <?= qf_e(ucfirst($c['revelation_place'] ?? '')) ?></small>
                </span>
                <span class="ar" translate="no"><?= qf_e($c['name_arabic']) ?></span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<?php require __DIR__ . '/layout-footer.php'; ?>
