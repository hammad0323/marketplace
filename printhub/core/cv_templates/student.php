<?php /** Student: education and projects first, friendly accent headings. */
$first = array_values(array_intersect(['education', 'projects', 'skills'], $order));
$rest = array_values(array_diff($order, $first));
?>
<style>
.student h1 { font-size: 22pt; color: <?= $accent ?>; } .student .role { font-size: 11pt; margin: 2px 0 4px; color: #374151; }
.student .contact { font-size: 9pt; color: #4b5563; margin-bottom: 12px; } .student .photo { width: 84px; height: 84px; border-radius: 42px; }
.student .sec-title { font-size: 11pt; color: #fff; background: <?= $accent ?>; padding: 3px 8px; border-radius: 3px; margin-bottom: 6px; }
.student .chip { background: #f5f3ff; color: #4c1d95; }
</style>
<div class="student">
<table><tr><?php if ($photo): ?><td style="width:96px"><img class="photo" src="<?= $photo ?>" alt=""></td><?php endif; ?>
<td><h1><?= e($name) ?></h1><?php if ($d['personal']['title']): ?><p class="role"><?= e($d['personal']['title']) ?></p><?php endif; ?><p class="contact"><?= e(implode('  ·  ', cv_contacts($d))) ?></p></td></tr></table>
<?= cv_section_html('summary', $d, ['labels' => ['summary' => 'Objective']]) ?>
<?php foreach ($first as $s) echo cv_section_html($s, $d); ?>
<?php foreach ($rest as $s) if ($s !== 'summary') echo cv_section_html($s, $d); ?>
</div>
