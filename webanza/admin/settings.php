<?php
/**
 * Site-wide settings, grouped into tabs. Add a key here to make it editable.
 */
require __DIR__ . '/inc.php';
require_admin();
require_csrf();

$tabs = [
    'general' => ['General', 'fa-solid fa-gear', [
        'site_name'         => ['text', 'Company name'],
        'site_tagline'      => ['text', 'Tagline'],
        'logo'              => ['image', 'Logo (for light backgrounds)', 'Transparent PNG/SVG recommended.'],
        'logo_light'        => ['image', 'Logo (for dark backgrounds)', 'Shown on the hero, footer and preloader.'],
        'favicon'           => ['image', 'Favicon / icon', 'Square image, at least 192×192.'],
        'email'             => ['email', 'Email'],
        'phone'             => ['text', 'Phone'],
        'whatsapp'          => ['text', 'WhatsApp number', 'With country code, e.g. +92 300 1234567. Leave empty to hide the WhatsApp button.'],
        'address'           => ['text', 'Address'],
        'working_hours'     => ['text', 'Working hours'],
        'map_embed'         => ['url', 'Google Maps embed URL', 'Google Maps → Share → Embed a map → copy only the src="…" link.'],
        'currency_symbol'   => ['text', 'Currency symbol', 'e.g. $, Rs, PKR, £, €'],
        'currency_position' => ['select', 'Currency position', '', ['before' => 'Before the amount ($499)', 'after' => 'After the amount (499 PKR)']],
        'notify_email'      => ['email', 'Send new inquiries to', 'Leave empty to use the main email. Your hosting must support PHP mail().'],
        'announcement'      => ['text', 'Announcement bar text', 'Leave empty to hide the bar at the top.'],
        'announcement_link' => ['text', 'Announcement link', 'e.g. packages.php'],
    ]],
    'social' => ['Social Links', 'fa-solid fa-share-nodes', [
        'social_facebook'  => ['url', 'Facebook'],
        'social_linkedin'  => ['url', 'LinkedIn'],
        'social_instagram' => ['url', 'Instagram'],
        'social_twitter'   => ['url', 'X / Twitter'],
        'social_youtube'   => ['url', 'YouTube'],
        'social_tiktok'    => ['url', 'TikTok'],
        'social_behance'   => ['url', 'Behance'],
        'social_github'    => ['url', 'GitHub'],
    ]],
    'hero' => ['Hero Section', 'fa-solid fa-wand-magic-sparkles', [
        'hero_badge'       => ['text', 'Small badge text'],
        'hero_title_1'     => ['text', 'Heading — first line'],
        'hero_rotating'    => ['lines', 'Rotating words', 'One per line — they animate in the middle of the heading.'],
        'hero_title_2'     => ['text', 'Heading — last line'],
        'hero_subtitle'    => ['textarea', 'Sub-heading'],
        'hero_btn1_text'   => ['text', 'Main button text'],
        'hero_btn1_link'   => ['text', 'Main button link', 'e.g. packages.php or a full URL'],
        'hero_btn2_text'   => ['text', 'Second button text'],
        'hero_btn2_link'   => ['text', 'Second button link'],
        'hero_rating_text' => ['text', 'Rating line'],
        'marquee_words'    => ['lines', 'Scrolling ribbon words', 'One per line — shown in the angled ribbons under the hero.'],
    ]],
    'about' => ['About', 'fa-solid fa-circle-info', [
        'about_eyebrow' => ['text', 'Small label'],
        'about_title'   => ['text', 'Heading', 'Wrap words in *asterisks* to highlight them in green.'],
        'about_text'    => ['textarea', 'Text', 'Each line becomes a paragraph (only the first shows on the home page).'],
        'about_points'  => ['lines', 'Check-list points', 'One per line.'],
        'about_image'   => ['image', 'Image', 'Optional — replaces the animated code card.'],
        'mission'       => ['textarea', 'Mission'],
        'vision'        => ['textarea', 'Vision'],
    ]],
    'ceo' => ['CEO Message', 'fa-solid fa-user-tie', [
        'ceo_name'     => ['text', 'Name'],
        'ceo_role'     => ['text', 'Title'],
        'ceo_photo'    => ['image', 'Photo', 'Portrait image works best (4:5).'],
        'ceo_message'  => ['textarea', 'Message'],
        'ceo_linkedin' => ['url', 'LinkedIn URL'],
    ]],
    'homepage' => ['Homepage & Headings', 'fa-solid fa-table-cells-large', [
        'home_show_services'     => ['checkbox', 'Show Services'],
        'home_show_stats'        => ['checkbox', 'Show Counters'],
        'home_show_about'        => ['checkbox', 'Show About'],
        'home_show_packages'     => ['checkbox', 'Show Packages'],
        'home_show_process'      => ['checkbox', 'Show Process'],
        'home_show_portfolio'    => ['checkbox', 'Show Portfolio'],
        'home_show_ceo'          => ['checkbox', 'Show CEO message'],
        'home_show_team'         => ['checkbox', 'Show Team'],
        'home_show_clients'      => ['checkbox', 'Show Technologies'],
        'home_show_testimonials' => ['checkbox', 'Show Testimonials'],
        'home_show_faq'          => ['checkbox', 'Show FAQ'],
        'home_show_blog'         => ['checkbox', 'Show Blog'],
        'services_heading'       => ['text', 'Services heading', 'Tip: wrap words in *asterisks* to highlight them.'],
        'services_subheading'    => ['textarea', 'Services sub-heading'],
        'packages_heading'       => ['text', 'Packages heading'],
        'packages_subheading'    => ['textarea', 'Packages sub-heading'],
        'process_heading'        => ['text', 'Process heading'],
        'portfolio_heading'      => ['text', 'Portfolio heading'],
        'team_heading'           => ['text', 'Team heading'],
        'clients_heading'        => ['text', 'Technologies heading'],
        'testimonials_heading'   => ['text', 'Testimonials heading'],
        'faq_heading'            => ['text', 'FAQ heading'],
        'blog_heading'           => ['text', 'Blog heading'],
    ]],
    'footer' => ['CTA & Footer', 'fa-solid fa-shoe-prints', [
        'cta_title'    => ['text', 'Call-to-action heading'],
        'cta_text'     => ['textarea', 'Call-to-action text'],
        'cta_btn_text' => ['text', 'Call-to-action button'],
        'footer_about' => ['textarea', 'Footer about text'],
        'copyright'    => ['text', 'Copyright line', '{year} is replaced with the current year.'],
    ]],
    'theme' => ['Brand Colors', 'fa-solid fa-palette', [
        'color_primary' => ['color', 'Primary color', 'Buttons, links and highlights.'],
        'color_accent'  => ['color', 'Accent color', 'Second color of the gradient.'],
        'color_dark'    => ['color', 'Dark background', 'Hero, footer and dark sections.'],
    ]],
    'seo' => ['SEO & Code', 'fa-solid fa-magnifying-glass', [
        'meta_title'       => ['text', 'Home page title (Google)'],
        'meta_description' => ['textarea', 'Meta description', 'About 150–160 characters.'],
        'meta_keywords'    => ['text', 'Keywords'],
        'og_image'         => ['image', 'Social share image', '1200×630 recommended.'],
        'ga_id'            => ['text', 'Google Analytics 4 ID', 'e.g. G-XXXXXXXXXX'],
        'head_code'        => ['code', 'Custom code in <head>', 'Search Console verification, Meta Pixel, etc. Only paste code from sources you trust.'],
        'footer_code'      => ['code', 'Custom code before </body>', 'Chat widgets, extra scripts.'],
    ]],
];

$tab = isset($tabs[$_GET['tab'] ?? '']) ? $_GET['tab'] : 'general';
$defaults = ['color_primary' => '#1f9d55', 'color_accent' => '#3ee089', 'color_dark' => '#0a1424'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tab = isset($tabs[$_POST['tab'] ?? '']) ? $_POST['tab'] : 'general';
    $ok = true;
    foreach ($tabs[$tab][2] as $key => $def) {
        $type = $def[0];
        if ($type === 'checkbox') {
            save_setting($key, isset($_POST[$key]) ? '1' : '0');
        } elseif ($type === 'image') {
            try {
                if ($file = upload_image($key)) {
                    save_setting($key, $file);
                } elseif (!empty($_POST[$key . '__remove'])) {
                    save_setting($key, '');
                }
            } catch (RuntimeException $ex) {
                flash('error', $def[1] . ': ' . $ex->getMessage());
                $ok = false;
            }
        } elseif ($type === 'color') {
            $v = trim((string) ($_POST[$key] ?? ''));
            save_setting($key, preg_match('~^#[0-9a-fA-F]{6}$~', $v) ? $v : ($defaults[$key] ?? ''));
        } else {
            save_setting($key, trim(str_replace("\r\n", "\n", (string) ($_POST[$key] ?? ''))));
        }
    }
    if (isset($_POST['reset_colors']) && $tab === 'theme') {
        foreach ($defaults as $k => $v) {
            save_setting($k, $v);
        }
    }
    if ($ok) {
        flash('success', $tabs[$tab][0] . ' saved.');
    }
    redirect('admin/settings.php?tab=' . $tab);
}

$s = settings(true);
admin_header('Site Settings', 'settings');
?>
<div class="tabs">
  <?php foreach ($tabs as $k => [$label, $icon]): ?>
    <a href="?tab=<?= e($k) ?>" class="<?= $k === $tab ? 'on' : '' ?>"><i class="<?= e($icon) ?>"></i> <?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<form method="post" enctype="multipart/form-data" class="card settings-form">
  <?= csrf_field() ?>
  <input type="hidden" name="tab" value="<?= e($tab) ?>">
  <div class="settings-grid">
  <?php foreach ($tabs[$tab][2] as $key => $def):
      [$type, $label] = $def;
      $help = $def[2] ?? '';
      $v = $s[$key] ?? '';
      $wide = in_array($type, ['textarea', 'lines', 'code', 'image'], true) ? ' wide' : '';
      ?>
    <div class="field<?= $wide ?><?= $type === 'checkbox' ? ' field-checkbox' : '' ?>">
      <?php if ($type !== 'checkbox'): ?><label for="s_<?= e($key) ?>"><?= e($label) ?></label><?php endif; ?>
      <?php switch ($type):
          case 'textarea': ?>
            <textarea id="s_<?= e($key) ?>" name="<?= e($key) ?>" rows="4"><?= e($v) ?></textarea>
          <?php break; case 'lines': ?>
            <textarea id="s_<?= e($key) ?>" name="<?= e($key) ?>" rows="6" class="mono"><?= e($v) ?></textarea>
          <?php break; case 'code': ?>
            <textarea id="s_<?= e($key) ?>" name="<?= e($key) ?>" rows="6" class="mono" spellcheck="false"><?= e($v) ?></textarea>
          <?php break; case 'image': ?>
            <div class="img-field">
              <div class="img-preview<?= str_contains($key, 'light') ? ' dark' : '' ?>"><?= $v ? '<img src="' . e(media($v)) . '" alt="">' : '<i class="fa-regular fa-image"></i>' ?></div>
              <div>
                <input type="file" id="s_<?= e($key) ?>" name="<?= e($key) ?>" accept="image/*">
                <?php if ($v): ?><label class="check small"><input type="checkbox" name="<?= e($key) ?>__remove" value="1"> Remove</label><?php endif; ?>
              </div>
            </div>
          <?php break; case 'checkbox': ?>
            <label class="switch"><input type="checkbox" name="<?= e($key) ?>" value="1" <?= ($v === '' || $v === '1') ? 'checked' : '' ?>><span></span> <?= e($label) ?></label>
          <?php break; case 'color': ?>
            <div class="color-field">
              <input type="color" id="s_<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($v ?: ($defaults[$key] ?? '#000000')) ?>">
              <code><?= e($v ?: ($defaults[$key] ?? '')) ?></code>
            </div>
          <?php break; case 'select': ?>
            <select id="s_<?= e($key) ?>" name="<?= e($key) ?>">
              <?php foreach ($def[3] as $ov => $ol): ?><option value="<?= e($ov) ?>" <?= $v === $ov ? 'selected' : '' ?>><?= e($ol) ?></option><?php endforeach; ?>
            </select>
          <?php break; case 'email': ?>
            <input type="email" id="s_<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($v) ?>">
          <?php break; case 'url': ?>
            <input type="url" id="s_<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($v) ?>" placeholder="https://">
          <?php break; default: ?>
            <input type="text" id="s_<?= e($key) ?>" name="<?= e($key) ?>" value="<?= e($v) ?>">
      <?php endswitch; ?>
      <?php if ($help): ?><small class="help"><?= e($help) ?></small><?php endif; ?>
    </div>
  <?php endforeach; ?>
  </div>
  <div class="form-buttons">
    <button class="btn btn-primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Save <?= e($tabs[$tab][0]) ?></button>
    <?php if ($tab === 'theme'): ?><button class="btn btn-light" type="submit" name="reset_colors" value="1">Reset to brand defaults</button><?php endif; ?>
  </div>
</form>
<?php
admin_footer();
