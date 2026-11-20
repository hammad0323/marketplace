<?php
/** Modern: coloured sidebar (photo, contact, skills, languages, certifications) + main column. */
$side = array_values(array_intersect($order, ['skills', 'languages', 'certifications']));
$main = array_values(array_diff($order, $side));
?>
<style>
.modern td.side { width: 33%; background: <?= $accent ?>; color: #fff; padding: 18px 14px; vertical-align: top; }
.modern td.main { padding: 4px 0 0 18px; vertical-align: top; }
.modern .photo { width: 110px; height: 110px; border-radius: 55px; border: 3px solid rgba(255,255,255,.6); display: block; margin: 0 auto 12px; }
.modern h1 { font-size: 22pt; line-height: 1.1; color: #111827; } .modern .role { color: <?= $accent ?>; font-size: 12pt; margin: 3px 0 12px; }
.modern .side .sec-title { color: #fff; border-bottom: 1px solid rgba(255,255,255,.4); font-size: 10.5pt; text-transform: uppercase; letter-spacing: 1px; padding-bottom: 3px; margin-bottom: 6px; }
.modern .main .sec-title { color: <?= $accent ?>; font-size: 11pt; text-transform: uppercase; letter-spacing: 1px; border-bottom: 2px solid <?= $accent ?>; padding-bottom: 2px; margin-bottom: 6px; }
.modern .side .chip { background: rgba(255,255,255,.18); color: #fff; } .modern .side .muted { color: rgba(255,255,255,.75); }
.modern .contact p { font-size: 9pt; margin-bottom: 4px; word-wrap: break-word; }
</style>
<table class="modern"><tr><td class="side">
<?php if ($photo): ?><img class="photo" src="<?= $photo ?>" alt=""><?php endif; ?>
<div class="sec contact"><h2 class="sec-title">Contact</h2><?php foreach (cv_contacts($d) as $c): ?><p><?= e($c) ?></p><?php endforeach; ?></div>
<?php foreach ($side as $s) echo cv_section_html($s, $d, ['skill_list' => true]); ?>
</td><td class="main">
<h1><?= e($name) ?></h1><?php if ($d['personal']['title']): ?><p class="role"><?= e($d['personal']['title']) ?></p><?php endif; ?>
<?php foreach ($main as $s) echo cv_section_html($s, $d); ?>
</td></tr></table>
