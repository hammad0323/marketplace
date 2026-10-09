</td></tr>
<tr><td style="background:#0A1426;padding:24px 32px;text-align:center;color:#E8DDCC;font-size:12px;line-height:1.6;">
  <?= e(setting('site_name', 'Beglet')) ?><?php if (setting('support_email')): ?> · <a href="mailto:<?= e(setting('support_email')) ?>" style="color:#B99A5B;"><?= e(setting('support_email')) ?></a><?php endif; ?><?php if (setting('contact_phone')): ?> · <?= e(setting('contact_phone')) ?><?php endif; ?><br>
  <?= e(setting('business_address', '')) ?>
</td></tr>
</table></td></tr></table></body></html>
