<?php
/** Page header for the Quran pages. Set $pageTitle before requiring. */
$pageTitle = $pageTitle ?? 'Quran';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="google" content="notranslate">
    <title><?= qf_e($pageTitle) ?></title>
    <link rel="stylesheet" href="quran.css">
</head>
<body>
<header class="qf-nav">
    <a class="qf-brand" href="index.php">القرآن الكريم · Quran</a>
    <form class="qf-search-mini" action="search.php" method="get">
        <input type="search" name="q" placeholder="Search the Quran…" value="<?= qf_e($_GET['q'] ?? '') ?>">
    </form>
</header>
<main class="qf-main">
<?php if (QF_ENV === 'prelive'): ?>
    <div class="qf-note">Test mode (pre-live): only Surah 1 and 2 are available until production access is approved.</div>
<?php endif; ?>
