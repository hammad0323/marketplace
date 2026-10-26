<?php
/** Background information about a surah: GET /chapters/{id}/info */
require __DIR__ . '/bootstrap.php';

$id = qf_int('id', 1, 1, 114);
$error = null;
$chapter = [];
$info = [];

try {
    $chapter = qf()->chapter($id, QF_DEFAULT_LANGUAGE);
    $info = qf()->chapterInfo($id, QF_DEFAULT_LANGUAGE);
} catch (Throwable $e) {
    $error = $e;
}

$pageTitle = 'About ' . ($chapter['name_simple'] ?? "Surah $id");
require __DIR__ . '/layout-header.php';
?>
<h1>About <?= qf_e($chapter['name_simple'] ?? "Surah $id") ?></h1>

<?php if ($error): ?>
    <?= qf_error_box($error) ?>
<?php endif; ?>

<?php if ($info): ?>
    <section class="qf-tafsir">
        <?= QuranClient::safeHtml($info['text'] ?? '') ?>
        <p class="qf-meta">Source: <?= qf_e($info['source'] ?? '') ?></p>
    </section>
<?php endif; ?>

<nav class="qf-pager"><a href="surah.php?id=<?= (int)$id ?>">← Read the surah</a></nav>

<?php require __DIR__ . '/layout-footer.php'; ?>
