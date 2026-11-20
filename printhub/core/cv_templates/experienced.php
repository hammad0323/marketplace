<?php /** Experienced Professional: experience-led main column with a narrow right column for skills. */
$side = array_values(array_intersect($order, ['skills', 'languages', 'certifications', 'achievements']));
$main = array_values(array_diff($order, $side));
?>
<style>
.experienced h1 { font-size: 22pt; color: #0f172a; } .experienced .role { font-size: 12pt; color: <?= $accent ?>; margin: 2px 0 4px; }
.experienced .contact { font-size: 9pt; color: #475569; border-bottom: 1px solid #cbd5e1; padding-bottom: 8px; margin-bottom: 12px; }
.experienced td.main { width: 66%; vertical-align: top; padding-right: 16px; } .experienced td.side { vertical-align: top; background: #f8fafc; padding: 10px; }
.experienced .sec-title { font-size: 10.5pt; color: <?= $accent ?>; text-transform: uppercase; letter-spacing: .8px; margin-bottom: 6px; }
.experienced .photo { width: 80px; height: 80px; }
</style>
<div class="experienced">
<table><tr><td><h1><?= e($name) ?></h1><?php if ($d['personal']['title']): ?><p class="role"><?= e($d['personal']['title']) ?></p><?php endif; ?></td>
<?php if ($photo): ?><td style="width:90px;text-align:right"><img class="photo" src="<?= $photo ?>" alt=""></td><?php endif; ?></tr></table>
<p class="contact"><?= e(implode('  ·  ', cv_contacts($d))) ?></p>
<table><tr><td class="main"><?php foreach ($main as $s) echo cv_section_html($s, $d); ?></td>
<td class="side"><?php foreach ($side as $s) echo cv_section_html($s, $d, ['skill_list' => true]); ?></td></tr></table>
</div>
