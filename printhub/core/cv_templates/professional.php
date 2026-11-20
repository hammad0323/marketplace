<?php /** Professional: strong name block with accent rule, headings with a left accent bar. */ ?>
<style>
.professional .head td { vertical-align: top; } .professional h1 { font-size: 23pt; color: #111827; } .professional .role { font-size: 12pt; color: <?= $accent ?>; margin: 2px 0 4px; }
.professional .contact { font-size: 9pt; color: #4b5563; } .professional .rule { border-bottom: 3px solid <?= $accent ?>; margin: 10px 0 12px; }
.professional .photo { width: 92px; height: 92px; border-radius: 6px; }
.professional .sec-title { font-size: 11pt; color: #111827; border-left: 4px solid <?= $accent ?>; padding-left: 7px; margin-bottom: 6px; text-transform: uppercase; letter-spacing: .5px; }
.professional .chip { background: #ecfdf5; border: 1px solid #d1fae5; }
</style>
<div class="professional">
<table class="head"><tr><td><h1><?= e($name) ?></h1><?php if ($d['personal']['title']): ?><p class="role"><?= e($d['personal']['title']) ?></p><?php endif; ?><p class="contact"><?= e(implode('  ·  ', cv_contacts($d))) ?></p></td>
<?php if ($photo): ?><td style="width:100px;text-align:right"><img class="photo" src="<?= $photo ?>" alt=""></td><?php endif; ?></tr></table>
<div class="rule"></div>
<?php foreach ($order as $s) echo cv_section_html($s, $d); ?>
</div>
