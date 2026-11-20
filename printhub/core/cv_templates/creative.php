<?php /** Creative: bold accent header block and a timeline for experience. */ ?>
<style>
.creative .hero { background: <?= $accent ?>; color: #fff; padding: 20px 20px 16px; margin-bottom: 14px; border-radius: 0 0 18px 0; }
.creative h1 { font-size: 26pt; letter-spacing: -.5px; } .creative .role { font-size: 12pt; margin: 4px 0 6px; opacity: .92; } .creative .contact { font-size: 8.5pt; opacity: .95; }
.creative .photo { width: 96px; height: 96px; border-radius: 48px; border: 3px solid #fff; }
.creative .sec-title { font-size: 12pt; color: <?= $accent ?>; margin-bottom: 6px; }
.creative .sec-experience .entry { border-left: 2px solid <?= $accent ?>; padding-left: 10px; }
.creative .chip { background: #fdf2f8; color: #831843; border: 1px solid #fbcfe8; }
</style>
<div class="creative">
<div class="hero"><table><tr><td><h1><?= e($name) ?></h1><?php if ($d['personal']['title']): ?><p class="role"><?= e($d['personal']['title']) ?></p><?php endif; ?>
<p class="contact"><?= e(implode('   ', cv_contacts($d))) ?></p></td><?php if ($photo): ?><td style="width:106px;text-align:right"><img class="photo" src="<?= $photo ?>" alt=""></td><?php endif; ?></tr></table></div>
<?php foreach ($order as $s) echo cv_section_html($s, $d); ?>
</div>
