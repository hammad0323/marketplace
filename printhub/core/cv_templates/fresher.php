<?php /** Fresh Graduate: centred header, skills as chips, achievements highlighted. */ ?>
<style>
.fresher .head { text-align: center; border-bottom: 2px solid <?= $accent ?>; padding-bottom: 10px; margin-bottom: 12px; }
.fresher h1 { font-size: 22pt; color: #0f172a; } .fresher .role { color: <?= $accent ?>; font-size: 11.5pt; margin: 2px 0 4px; } .fresher .contact { font-size: 9pt; color: #475569; }
.fresher .photo { width: 80px; height: 80px; border-radius: 40px; margin-bottom: 6px; }
.fresher .sec-title { font-size: 11pt; color: <?= $accent ?>; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 6px; }
.fresher .chip { background: <?= $accent ?>; color: #fff; } .fresher .sec-achievements { background: #f0f9ff; padding: 8px 10px; border-left: 3px solid <?= $accent ?>; }
</style>
<div class="fresher">
<div class="head"><?php if ($photo): ?><img class="photo" src="<?= $photo ?>" alt=""><br><?php endif; ?><h1><?= e($name) ?></h1>
<?php if ($d['personal']['title']): ?><p class="role"><?= e($d['personal']['title']) ?></p><?php endif; ?><p class="contact"><?= e(implode('  ·  ', cv_contacts($d))) ?></p></div>
<?php foreach ($order as $s) echo cv_section_html($s, $d); ?>
</div>
