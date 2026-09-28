<?php /** Inner-page banner. Set $heroTitle, $heroScript, $heroText, $heroImage before including. */ ?>
<section class="page-hero">
    <div class="parallax-layer" data-parallax="0.3" style="background-image:url('<?= e($heroImage ?? 'assets/img/model.jpg') ?>')"></div>
    <div class="container">
        <?php if (!empty($heroScript)): ?><p class="script reveal"><?= e($heroScript) ?></p><?php endif; ?>
        <h1 class="reveal" data-delay="100"><?= e($heroTitle) ?></h1>
        <?php if (!empty($heroText)): ?><p class="reveal" data-delay="200"><?= e($heroText) ?></p><?php endif; ?>
        <div class="crumbs reveal" data-delay="300"><a href="index.php">Home</a> &nbsp;/&nbsp; <?= e($heroTitle) ?></div>
    </div>
</section>
