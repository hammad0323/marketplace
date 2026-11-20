<?php /** Simple ATS-Friendly: plain single column, standard headings, no photo, no colour blocks or tables for layout. */ ?>
<style>
.ats { color: #000; } .ats h1 { font-size: 18pt; } .ats .role { font-size: 11pt; margin: 2px 0; } .ats .contact { font-size: 9.5pt; margin-bottom: 10px; }
.ats .sec-title { font-size: 11pt; text-transform: uppercase; border-bottom: 1px solid #000; margin: 4px 0 6px; padding-bottom: 1px; }
.ats .entry-date, .ats .entry-sub, .ats .muted { color: #000; } .ats .chip { padding: 0; margin: 0; } .ats .chip:after { content: ","; } .ats .chip:last-child:after { content: ""; }
</style>
<div class="ats">
<h1><?= e($name) ?></h1><?php if ($d['personal']['title']): ?><p class="role"><?= e($d['personal']['title']) ?></p><?php endif; ?>
<p class="contact"><?= e(implode(' | ', cv_contacts($d))) ?></p>
<?php foreach ($order as $s) echo cv_section_html($s, $d, ['labels' => ['summary' => 'Summary', 'experience' => 'Experience']]); ?>
</div>
