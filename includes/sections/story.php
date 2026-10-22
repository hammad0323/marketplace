<?php
if (!$s['title'] && !$s['text']) {
    return;
}
$pillars = [];
foreach (array_filter(array_map('trim', explode("\n", (string) $s['pillars']))) as $line) {
    $p = array_map('trim', explode('|', $line, 2));
    $pillars[] = ['title' => $p[0], 'text' => $p[1] ?? ''];
}
$video = trim((string) $s['video_url']);
$embed = null;
if ($video) {
    if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([A-Za-z0-9_-]{6,15})#', $video, $m)) {
        $embed = 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?rel=0';
    } elseif (preg_match('#vimeo\.com/(\d+)#', $video, $m)) {
        $embed = 'https://player.vimeo.com/video/' . $m[1];
    }
}
?>
<section <?= section_wrapper_attrs($s, 'story', $sid) ?>>
    <div class="<?= section_container_class($s) ?>">
        <div class="uc-story <?= $s['layout'] === 'image-left' ? '' : 'is-reversed' ?>">
            <div class="uc-story-media"<?= reveal($s) ?>>
                <?php if ($embed): ?>
                    <div class="ratio ratio-4x3"><iframe src="<?= e($embed) ?>" title="<?= e($s['title']) ?>" loading="lazy" allowfullscreen></iframe></div>
                <?php elseif ($video && preg_match('#\.mp4(\?|$)#i', $video)): ?>
                    <video src="<?= e($video) ?>" autoplay muted loop playsinline<?= $s['image'] ? ' poster="' . e(img_url($s['image'])) . '"' : '' ?>></video>
                <?php elseif ($s['image']): ?>
                    <img class="uc-story-main" src="<?= e(img_url($s['image'])) ?>" alt="<?= e($s['title']) ?>" loading="lazy" data-parallax="0.06">
                <?php endif; ?>
                <?php if ($s['image_secondary']): ?>
                    <img class="uc-story-secondary" src="<?= e(img_sized($s['image_secondary'], 700)) ?>" alt="" loading="lazy">
                <?php endif; ?>
            </div>
            <div class="uc-story-text"<?= reveal($s, 150) ?>>
                <?php if ($s['eyebrow']): ?><p class="uc-eyebrow"><?= e($s['eyebrow']) ?></p><?php endif; ?>
                <h2 class="uc-display"><?= e($s['title']) ?></h2>
                <div class="uc-rich"><?= sanitize_html($s['text']) ?></div>
                <?php if ($pillars): ?>
                    <ul class="uc-pillars">
                        <?php foreach ($pillars as $i => $p): ?>
                            <li><span class="uc-pillar-num"><?= str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) ?></span><div><strong><?= e($p['title']) ?></strong><span><?= e($p['text']) ?></span></div></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <?php if ($s['button_text']): ?><a class="uc-btn uc-btn--outline-current" href="<?= e(url($s['button_url'] ?: 'about-us')) ?>"><?= e($s['button_text']) ?></a><?php endif; ?>
            </div>
        </div>
    </div>
</section>
