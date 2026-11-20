<?php /** Corporate: solid header band with photo, single column, skills in two columns. */ ?>
<style>
.corporate .band { background: <?= $accent ?>; color: #fff; padding: 16px 18px; margin-bottom: 14px; }
.corporate .band td { vertical-align: middle; } .corporate h1 { font-size: 21pt; } .corporate .role { font-size: 11pt; opacity: .9; margin-top: 2px; }
.corporate .contact { font-size: 8.5pt; margin-top: 6px; opacity: .95; } .corporate .photo { width: 86px; height: 86px; border: 2px solid #fff; }
.corporate .sec-title { font-size: 11pt; color: <?= $accent ?>; text-transform: uppercase; border-bottom: 1px solid #d1d5db; padding-bottom: 2px; margin-bottom: 6px; }
.corporate .chip { background: #eef2ff; color: #1f2937; border-radius: 2px; }
</style>
<div class="corporate">
<div class="band"><table><tr><td><h1><?= e($name) ?></h1><?php if ($d['personal']['title']): ?><p class="role"><?= e($d['personal']['title']) ?></p><?php endif; ?>
<p class="contact"><?= e(implode('  |  ', cv_contacts($d))) ?></p></td>
<?php if ($photo): ?><td style="width:96px;text-align:right"><img class="photo" src="<?= $photo ?>" alt=""></td><?php endif; ?></tr></table></div>
<?php foreach ($order as $s) echo cv_section_html($s, $d); ?>
</div>
