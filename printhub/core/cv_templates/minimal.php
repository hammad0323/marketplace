<?php /** Minimal: single column, generous white space, thin rules. */ ?>
<style>
.minimal h1 { font-size: 24pt; font-weight: normal; letter-spacing: 1px; color: #111827; } .minimal .role { font-size: 11pt; color: #6b7280; margin: 2px 0 6px; }
.minimal .contact { font-size: 9pt; color: #4b5563; padding-bottom: 10px; border-bottom: 1px solid #e5e7eb; margin-bottom: 14px; }
.minimal .sec-title { font-size: 9pt; letter-spacing: 2px; text-transform: uppercase; color: <?= $accent ?>; margin-bottom: 6px; font-weight: bold; }
.minimal .sec { border-bottom: 1px solid #f3f4f6; padding-bottom: 8px; } .minimal .chip { background: #f3f4f6; }
</style>
<div class="minimal">
<h1><?= e($name) ?></h1><?php if ($d['personal']['title']): ?><p class="role"><?= e($d['personal']['title']) ?></p><?php endif; ?>
<p class="contact"><?= e(implode('  ·  ', cv_contacts($d))) ?></p>
<?php foreach ($order as $s) echo cv_section_html($s, $d); ?>
</div>
