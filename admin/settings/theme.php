<?php
/** Theme & design: colours, typography, buttons, cards, layout and animation preferences. */
require __DIR__ . '/../includes/init.php';
admin_require('settings');
$defaults = theme_defaults();
if (is_post()) {
    if (input('action', '', 'post') === 'reset') {
        db_exec("DELETE FROM site_settings WHERE setting_group = 'theme'");
        audit_log('theme_reset', 'settings');
        flash('success', 'Theme restored to the Urban Collections defaults.');
        redirect('admin/settings/theme.php');
    }
    $fonts = theme_font_options();
    foreach ($defaults as $k => $def) {
        $v = (string) ($_POST[$k] ?? $def);
        if (str_starts_with($def, '#')) {
            $v = safe_color($v, $def);
        } elseif ($k === 'theme_font_heading') {
            $v = in_array($v, $fonts['heading'], true) ? $v : $def;
        } elseif ($k === 'theme_font_body') {
            $v = in_array($v, $fonts['body'], true) ? $v : $def;
        } elseif (in_array($k, ['theme_button_uppercase', 'theme_card_hover_swap', 'theme_card_show_colors', 'theme_card_show_rating', 'theme_sticky_header', 'theme_animations'], true)) {
            $v = !empty($_POST[$k]) ? '1' : '0';
        } else {
            $v = preg_replace('/[^a-z0-9\-\/]/i', '', $v);
        }
        setting_save($k, $v, 'theme');
    }
    audit_log('theme_saved', 'settings');
    flash('success', 'Theme saved.');
    redirect('admin/settings/theme.php');
}
$t = fn($k) => theme($k);
$fonts = theme_font_options();
$colorGroups = [
    'Brand palette' => ['theme_primary' => 'Primary (jet black)', 'theme_secondary' => 'Secondary (charcoal)', 'theme_graphite' => 'Graphite', 'theme_accent' => 'Accent (muted gold)', 'theme_beige' => 'Warm beige', 'theme_sand' => 'Sand', 'theme_body_bg' => 'Page background', 'theme_text' => 'Text'],
    'Header, footer & bars' => ['theme_header_bg' => 'Header background', 'theme_header_text' => 'Header text', 'theme_footer_bg' => 'Footer background', 'theme_footer_text' => 'Footer text', 'theme_announce_bg' => 'Announcement background', 'theme_announce_text' => 'Announcement text', 'theme_btn_bg' => 'Button background', 'theme_btn_text' => 'Button text'],
];
admin_header('Theme & design');
?>
<form method="post"><?= csrf_field() ?>
<div class="row g-4"><div class="col-xl-7">
<?php foreach ($colorGroups as $title => $keys): ?>
<div class="card a-card"><div class="card-header"><?= e($title) ?></div><div class="card-body"><div class="row">
    <?php foreach ($keys as $k => $l): ?><div class="col-md-6"><?= a_color($k, $l, $t($k)) ?></div><?php endforeach; ?>
</div></div></div>
<?php endforeach; ?>
<div class="card a-card"><div class="card-header">Typography</div><div class="card-body"><div class="row">
    <div class="col-md-6"><?= a_select('theme_font_heading', 'Heading font', array_combine($fonts['heading'], $fonts['heading']), $t('theme_font_heading')) ?></div>
    <div class="col-md-6"><?= a_select('theme_font_body', 'Body font', array_combine($fonts['body'], $fonts['body']), $t('theme_font_body')) ?></div>
    <div class="col-md-6"><?= a_select('theme_heading_transform', 'Heading style', ['none' => 'Title case', 'uppercase' => 'UPPERCASE'], $t('theme_heading_transform')) ?></div>
    <div class="col-md-6"><?= a_select('theme_heading_weight', 'Heading weight', ['400' => 'Regular', '500' => 'Medium', '600' => 'Semi-bold', '700' => 'Bold'], $t('theme_heading_weight')) ?></div>
</div></div></div>
</div><div class="col-xl-5">
<div class="card a-card"><div class="card-header">Buttons &amp; shapes</div><div class="card-body">
    <?= a_select('theme_button_style', 'Button shape', ['square' => 'Square (uses corner radius)', 'rounded' => 'Rounded', 'pill' => 'Pill'], $t('theme_button_style')) ?>
    <?= a_check('theme_button_uppercase', 'Uppercase button labels', $t('theme_button_uppercase') === '1') ?>
    <?= a_input('theme_radius', 'Corner radius (px)', $t('theme_radius'), 'number', ['min' => 0, 'max' => 24]) ?>
</div></div>
<div class="card a-card"><div class="card-header">Layout</div><div class="card-body">
    <?= a_input('theme_container', 'Content width (px)', $t('theme_container'), 'number', ['min' => 960, 'max' => 1920, 'step' => 10]) ?>
    <?= a_input('theme_section_spacing', 'Section spacing (px)', $t('theme_section_spacing'), 'number', ['min' => 24, 'max' => 200]) ?>
    <?= a_select('theme_nav_layout', 'Header layout', ['logo-left' => 'Logo left, menu centre', 'logo-center' => 'Menu left, logo centre'], $t('theme_nav_layout')) ?>
    <?= a_check('theme_sticky_header', 'Sticky header (hides on scroll down, returns on scroll up)', $t('theme_sticky_header') === '1') ?>
    <?= a_select('theme_mobile_nav', 'Mobile menu', ['drawer-left' => 'Slide-in drawer', 'fullscreen' => 'Full-screen'], $t('theme_mobile_nav')) ?>
    <?= a_select('theme_footer_layout', 'Footer', ['columns' => 'Full (columns + newsletter)', 'minimal' => 'Minimal'], $t('theme_footer_layout')) ?>
</div></div>
<div class="card a-card"><div class="card-header">Product cards</div><div class="card-body">
    <?= a_select('theme_card_ratio', 'Image ratio', ['3/4' => 'Portrait 3:4', '4/5' => 'Portrait 4:5', '2/3' => 'Tall 2:3', '1/1' => 'Square'], $t('theme_card_ratio')) ?>
    <?= a_select('theme_card_align', 'Text alignment', ['left' => 'Left', 'center' => 'Centre'], $t('theme_card_align')) ?>
    <?= a_check('theme_card_hover_swap', 'Show second image on hover', $t('theme_card_hover_swap') === '1') ?>
    <?= a_check('theme_card_show_colors', 'Show colour swatches', $t('theme_card_show_colors') === '1') ?>
    <?= a_check('theme_card_show_rating', 'Show star ratings', $t('theme_card_show_rating') === '1') ?>
</div></div>
<div class="card a-card"><div class="card-header">Animation</div><div class="card-body">
    <?= a_check('theme_animations', 'Scroll reveal & parallax effects', $t('theme_animations') === '1', 'Visitors with “reduce motion” enabled on their device never see motion effects.') ?>
    <?= a_input('theme_animation_speed', 'Reveal duration (ms)', $t('theme_animation_speed'), 'number', ['min' => 200, 'max' => 2000, 'step' => 50]) ?>
</div></div>
</div></div>
<div class="a-savebar"><button class="btn btn-dark px-4"><i class="bi bi-check2"></i> Save theme</button> <a class="btn btn-outline-dark" href="<?= e(url()) ?>" target="_blank">View store</a>
<button class="btn btn-link text-danger" form="resetTheme">Restore defaults</button></div>
</form>
<form id="resetTheme" method="post" data-confirm="Restore the original Urban Collections theme?"><?= csrf_field() ?><input type="hidden" name="action" value="reset"></form>
<?php admin_footer();
