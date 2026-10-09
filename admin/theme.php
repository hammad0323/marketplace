<?php
require __DIR__ . '/_inc/bootstrap.php';
require_admin('settings.theme');
$defaults = theme_defaults();
if (is_post()) {
    require_csrf();
    if (input('action') === 'reset') {
        foreach ($defaults as $k => $v) {
            save_setting($k, $v, 'theme');
        }
        audit_log('theme_reset', 'settings');
        flash('success', 'Theme reset to the Beglet defaults.');
        redirect(admin_url('theme'));
    }
    foreach ($defaults as $k => $v) {
        $val = input($k, $v);
        if (strpos($k, 'theme_') === 0 && preg_match('/^#/', $v)) {
            $val = valid_hex($val) ? strtoupper($val) : $v;
        }
        switch ($k) {
            case 'theme_fonts':
                $val = array_key_exists($val, font_options()) ? $val : $v;
                break;
            case 'theme_card_style':
                $val = in_array($val, ['classic', 'minimal', 'bordered'], true) ? $val : $v;
                break;
            case 'theme_header_layout':
                $val = in_array($val, ['logo_left', 'logo_center'], true) ? $val : $v;
                break;
            case 'theme_footer_layout':
                $val = in_array($val, ['columns', 'centered'], true) ? $val : $v;
                break;
            case 'theme_mobile_columns':
                $val = in_array($val, ['1', '2'], true) ? $val : $v;
                break;
            case 'theme_mobile_sticky_cart':
            case 'theme_animations':
                $val = input_bool($k) ? '1' : '0';
                break;
            case 'theme_base_font_size':
            case 'theme_container':
            case 'theme_section_spacing':
            case 'theme_radius':
                $val = (string) (int) $val;
                break;
        }
        save_setting($k, $val, 'theme');
    }
    audit_log('theme_updated', 'settings');
    flash('success', 'Theme saved.');
    redirect(admin_url('theme'));
}
$t = fn($k) => theme($k);
admin_header('Theme', 'theme');
?>
<form method="post"><?= csrf_field() ?>
<div class="row g-3">
  <div class="col-xl-6"><div class="card"><div class="card-header">Colours</div><div class="card-body"><div class="row">
    <?php foreach (['theme_primary' => 'Primary (royal blue — highlights)', 'theme_secondary' => 'Secondary (navy)', 'theme_dark' => 'Deep midnight', 'theme_accent' => 'Accent (antique gold)', 'theme_ivory' => 'Ivory', 'theme_beige' => 'Warm beige', 'theme_body_bg' => 'Page background', 'theme_text' => 'Body text', 'theme_header_bg' => 'Header background', 'theme_header_text' => 'Header text', 'theme_footer_bg' => 'Footer background', 'theme_footer_text' => 'Footer text', 'theme_button_bg' => 'Button background', 'theme_button_text' => 'Button text'] as $k => $label): ?>
      <div class="col-md-6"><?= f_color($k, $label, $t($k)) ?></div>
    <?php endforeach; ?>
  </div></div></div></div>
  <div class="col-xl-6">
    <div class="card mb-3"><div class="card-header">Typography & layout</div><div class="card-body"><div class="row">
      <div class="col-md-8"><?= f_select('theme_fonts', 'Font pairing', array_map(fn($f) => $f['label'], font_options()), $t('theme_fonts')) ?></div>
      <div class="col-md-4"><?= f_number('theme_base_font_size', 'Base size (px)', $t('theme_base_font_size'), ['min' => 14, 'max' => 19, 'step' => 1]) ?></div>
      <div class="col-md-4"><?= f_number('theme_container', 'Container width (px)', $t('theme_container'), ['min' => 960, 'max' => 1800, 'step' => 10]) ?></div>
      <div class="col-md-4"><?= f_number('theme_section_spacing', 'Section spacing (px)', $t('theme_section_spacing'), ['min' => 32, 'max' => 200, 'step' => 4]) ?></div>
      <div class="col-md-4"><?= f_number('theme_radius', 'Corner radius (px)', $t('theme_radius'), ['min' => 0, 'max' => 32, 'step' => 1]) ?></div>
    </div></div></div>
    <div class="card"><div class="card-header">Components</div><div class="card-body"><div class="row">
      <div class="col-md-6"><?= f_select('theme_card_style', 'Product card style', ['classic' => 'Classic', 'minimal' => 'Minimal (centred, no category)', 'bordered' => 'Bordered card'], $t('theme_card_style')) ?></div>
      <div class="col-md-6"><?= f_select('theme_header_layout', 'Header layout', ['logo_left' => 'Logo left, menu centre', 'logo_center' => 'Logo centred'], $t('theme_header_layout')) ?></div>
      <div class="col-md-6"><?= f_select('theme_footer_layout', 'Footer layout', ['columns' => 'Columns', 'centered' => 'Centred'], $t('theme_footer_layout')) ?></div>
      <div class="col-md-6"><?= f_select('theme_mobile_columns', 'Mobile product grid', ['2' => '2 columns', '1' => '1 column'], $t('theme_mobile_columns')) ?></div>
      <div class="col-md-6"><?= f_check('theme_mobile_sticky_cart', 'Sticky add-to-bag bar on mobile product pages', $t('theme_mobile_sticky_cart') === '1') ?></div>
      <div class="col-md-6"><?= f_check('theme_animations', 'Enable animations (reveal, parallax)', $t('theme_animations') === '1', 'Visitors with "reduce motion" enabled never see animations.') ?></div>
    </div></div></div>
  </div>
</div>
<div class="sticky-actions d-flex gap-2"><?= f_submit('Save theme') ?><a class="btn btn-light" href="<?= e(path_url('/')) ?>" target="_blank">View store</a>
  <button class="btn btn-outline-danger ms-auto" name="action" value="reset" formnovalidate onclick="return confirm('Reset all theme settings to defaults?')">Reset to defaults</button></div>
</form>
<?php admin_footer();
