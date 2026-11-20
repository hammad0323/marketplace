<?php /** Executive: serif typography, centred name, double rule. */ ?>
<style>
.executive { font-family: "DejaVu Serif", Georgia, serif; } .executive .head { text-align: center; margin-bottom: 12px; }
.executive h1 { font-size: 24pt; letter-spacing: 2px; text-transform: uppercase; color: #1c1917; } .executive .role { font-size: 12pt; color: <?= $accent ?>; font-style: italic; margin: 3px 0 5px; }
.executive .contact { font-size: 9pt; color: #44403c; } .executive .rule { border-top: 3px double <?= $accent ?>; margin: 10px 0 12px; }
.executive .photo { width: 84px; height: 84px; border-radius: 42px; margin-bottom: 6px; }
.executive .sec-title { font-size: 11.5pt; color: <?= $accent ?>; letter-spacing: 1.5px; text-transform: uppercase; margin-bottom: 6px; border-bottom: 1px solid #e7e5e4; padding-bottom: 2px; }
.executive .chip { background: #fafaf9; border: 1px solid #e7e5e4; }
</style>
<div class="executive">
<div class="head"><?php if ($photo): ?><img class="photo" src="<?= $photo ?>" alt=""><br><?php endif; ?><h1><?= e($name) ?></h1>
<?php if ($d['personal']['title']): ?><p class="role"><?= e($d['personal']['title']) ?></p><?php endif; ?><p class="contact"><?= e(implode('  ·  ', cv_contacts($d))) ?></p></div>
<div class="rule"></div>
<?php foreach ($order as $s) echo cv_section_html($s, $d); ?>
</div>
