<?php
$items = [];
foreach (array_filter(array_map('trim', explode("\n", (string) $s['items']))) as $line) {
    $parts = array_map('trim', explode('|', $line));
    $items[] = ['icon' => preg_replace('/[^a-z0-9\-]/', '', strtolower($parts[0] ?? 'check')), 'title' => $parts[1] ?? '', 'text' => $parts[2] ?? ''];
}
if (!$items) {
    return;
}
?>
<section <?= section_wrapper_attrs($s, 'usp', $sid) ?>>
    <div class="<?= section_container_class($s) ?>">
        <ul class="uc-usp">
            <?php foreach ($items as $i => $it): ?>
                <li<?= reveal($s, $i * 80) ?>>
                    <i class="bi bi-<?= e($it['icon']) ?>" aria-hidden="true"></i>
                    <div><strong><?= e($it['title']) ?></strong><?php if ($it['text']): ?><span><?= e($it['text']) ?></span><?php endif; ?></div>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>
</section>
