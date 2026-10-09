<h2 style="font-family:Georgia,serif;font-weight:normal;color:#101D35;margin:0 0 12px;">New contact message</h2>
<p><strong><?= e($m['name']) ?></strong> &lt;<?= e($m['email']) ?>&gt; <?= e($m['phone']) ?><br>Subject: <?= e($m['subject']) ?></p>
<p style="background:#F7F5F0;padding:12px 16px;"><?= nl2br(e($m['message'])) ?></p>
