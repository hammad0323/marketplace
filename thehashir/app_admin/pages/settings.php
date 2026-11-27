<?php
/** /manage/settings — website settings (saved in storage/settings.json; config.php holds the defaults). */
require_admin('settings');
$tab = in_array(str_param('tab', 12), array('general', 'homepage', 'seo', 'maps', 'workflow'), true) ? str_param('tab', 12) : 'general';
if (is_post()) {
    require_post_csrf();
    $s = array();
    if ($tab === 'general') {
        $s['site_name'] = mb_substr(post('site_name'), 0, 80, 'UTF-8') ?: SITE_NAME;
        $s['contact_email'] = filter_var(post('contact_email'), FILTER_VALIDATE_EMAIL) ? post('contact_email') : CONTACT_EMAIL;
        $s['contact_phone'] = mb_substr(post('contact_phone'), 0, 40, 'UTF-8');
        $s['footer_about'] = mb_substr(post('footer_about'), 0, 800, 'UTF-8');
        foreach (array('logo_url', 'favicon_url') as $k) {
            $s[$k] = post($k) === '' ? '' : (preg_match('#^(https://|/|images/)#', post($k)) ? mb_substr(post($k), 0, 300) : setting($k));
        }
        $social = array();
        foreach (array('facebook', 'instagram', 'youtube', 'tiktok', 'x', 'linkedin') as $k) {
            $u = safe_url(isset($_POST['social'][$k]) ? $_POST['social'][$k] : '');
            if ($u !== '') {
                $social[$k] = $u;
            }
        }
        $s['social'] = $social;
        $s['announcement'] = array('enabled' => post('ann_enabled') === '1', 'text' => mb_substr(post('ann_text'), 0, 240, 'UTF-8'),
            'link' => mb_substr(post('ann_link'), 0, 300), 'style' => in_array(post('ann_style'), array('info', 'warning', 'success'), true) ? post('ann_style') : 'info');
        $ticker = array();
        foreach (array_filter(array_map('trim', explode("\n", (string)post('ticker')))) as $line) {
            $parts = array_map('trim', explode('|', $line, 2));
            if ($parts[0] !== '') {
                $ticker[] = array(mb_substr($parts[0], 0, 120, 'UTF-8'), isset($parts[1]) && $parts[1] !== '' ? mb_substr($parts[1], 0, 300) : '/');
            }
        }
        $s['ticker'] = $ticker;
    } elseif ($tab === 'homepage') {
        $s['hero_title'] = mb_substr(post('hero_title'), 0, 120, 'UTF-8');
        $s['hero_subtitle'] = mb_substr(post('hero_subtitle'), 0, 240, 'UTF-8');
        $sections = array();
        foreach (array_keys(settings_defaults()['home_sections']) as $k) {
            $sections[$k] = !empty($_POST['sections'][$k]);
        }
        $s['home_sections'] = $sections;
        $s['featured_mosques'] = post_ids('featured_mosques');
        $s['featured_orgs'] = post_ids('featured_orgs');
        $slides = array();
        $titles = isset($_POST['slide_title']) ? (array)$_POST['slide_title'] : array();
        foreach ($titles as $i => $t) {
            $img = trim((string)$_POST['slide_image'][$i]);
            if ($img === '' && !empty($_FILES['slide_upload']['name'][$i])) {
                $_FILES['__slide'] = array('name' => $_FILES['slide_upload']['name'][$i], 'type' => $_FILES['slide_upload']['type'][$i], 'tmp_name' => $_FILES['slide_upload']['tmp_name'][$i], 'error' => $_FILES['slide_upload']['error'][$i], 'size' => $_FILES['slide_upload']['size'][$i]);
                require_once __DIR__ . '/../../includes/uploads.php';
                $up = upload_image('__slide', 'blogs');
                if ($up['ok'] && $up['file'] !== '') {
                    $img = 'images/blogs/' . $up['file'];
                } elseif (!$up['ok']) {
                    flash('danger', 'Slide image: ' . $up['error']);
                }
            }
            if ($img !== '' && empty($_POST['slide_remove'][$i])) {
                $slides[] = array('image' => mb_substr($img, 0, 300), 'title' => mb_substr(trim((string)$t), 0, 120, 'UTF-8'),
                    'button' => mb_substr(trim((string)$_POST['slide_button'][$i]), 0, 40, 'UTF-8'), 'link' => mb_substr(trim((string)$_POST['slide_link'][$i]), 0, 300),
                    'until' => valid_ymd(trim((string)$_POST['slide_until'][$i])) ? trim((string)$_POST['slide_until'][$i]) : '');
            }
        }
        $s['slides'] = $slides;
    } elseif ($tab === 'seo') {
        $s['seo_title'] = mb_substr(post('seo_title'), 0, 120, 'UTF-8');
        $s['seo_description'] = mb_substr(post('seo_description'), 0, 300, 'UTF-8');
        $s['seo_image'] = mb_substr(post('seo_image'), 0, 300);
        $s['ga4_id'] = preg_match('/^G-[A-Z0-9]{4,15}$/', post('ga4_id')) ? post('ga4_id') : '';
        $s['head_code_verification'] = preg_match('/^[A-Za-z0-9_\-]{0,100}$/', post('head_code_verification')) ? post('head_code_verification') : '';
    } elseif ($tab === 'maps') {
        $tiles = post('map_tiles');
        $s['map_tiles'] = preg_match('#^https://[^\s"\'<>]+\{z\}[^\s"\'<>]*$#', $tiles) ? $tiles : settings_defaults()['map_tiles'];
        $s['map_attribution'] = strip_tags(post('map_attribution'), '<a>') ?: settings_defaults()['map_attribution'];
        $s['map_center'] = array('lat' => is_numeric(post('center_lat')) ? (float)post('center_lat') : 43.6532, 'lng' => is_numeric(post('center_lng')) ? (float)post('center_lng') : -79.3832, 'zoom' => max(2, min(16, post_int('center_zoom', 9))));
    } elseif ($tab === 'workflow') {
        $s['approve_profile'] = post('approve_profile') === '1';
        $s['approve_details'] = post('approve_details') === '1';
    }
    settings_save($s);
    log_me('settings.update', 'settings', null, null, array('tab' => $tab, 'keys' => array_keys($s)));
    flash('success', 'Settings saved.');
    bo_redirect('admin', 'settings?tab=' . $tab);
}
$S = settings_all();
bo_start('admin', 'Settings');
$tabs = array('general' => 'General', 'homepage' => 'Homepage', 'seo' => 'SEO & analytics', 'maps' => 'Maps', 'workflow' => 'Approval workflow');
?>
<ul class="nav nav-pills bo-pills mb-4"><?php foreach ($tabs as $k => $l): ?><li class="nav-item"><a class="nav-link<?= $tab === $k ? ' active' : '' ?>" href="<?= e(ADMIN_SELF . '?tab=' . $k) ?>"><?= e($l) ?></a></li><?php endforeach; ?></ul>
<form method="post" enctype="multipart/form-data" style="max-width:980px"><?= csrf_field() ?>
<?php if ($tab === 'general'): ?>
  <div class="card bo-card mb-4"><div class="card-header"><h2 class="h6 m-0">Brand &amp; contact</h2></div><div class="card-body row g-3">
    <div class="col-md-6"><label class="form-label">Website name</label><input class="form-control" name="site_name" value="<?= e($S['site_name']) ?>"></div>
    <div class="col-md-6"><label class="form-label">Contact email (contact form goes here)</label><input class="form-control" type="email" name="contact_email" value="<?= e($S['contact_email']) ?>"></div>
    <div class="col-md-6"><label class="form-label">Phone (shown in the footer)</label><input class="form-control" name="contact_phone" value="<?= e($S['contact_phone']) ?>"></div>
    <div class="col-md-6"><label class="form-label">Logo URL <span class="text-muted small">(empty = images/logos/hashirLogo.png)</span></label><input class="form-control" name="logo_url" value="<?= e($S['logo_url']) ?>" placeholder="images/logos/hashirLogo.png"></div>
    <div class="col-md-6"><label class="form-label">Favicon URL <span class="text-muted small">(empty = images/logos/hashirLogosite.png)</span></label><input class="form-control" name="favicon_url" value="<?= e($S['favicon_url']) ?>"></div>
    <div class="col-12"><label class="form-label">Footer “about” text</label><textarea class="form-control" name="footer_about" rows="3"><?= e($S['footer_about']) ?></textarea></div>
  </div></div>
  <div class="card bo-card mb-4"><div class="card-header"><h2 class="h6 m-0">Social media</h2></div><div class="card-body row g-3">
    <?php foreach (array('facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'x' => 'X (Twitter)', 'linkedin' => 'LinkedIn') as $k => $l): ?>
    <div class="col-md-6"><label class="form-label small"><?= $l ?></label><input class="form-control" name="social[<?= $k ?>]" value="<?= e(isset($S['social'][$k]) ? $S['social'][$k] : '') ?>" placeholder="https://"></div>
    <?php endforeach; ?>
  </div></div>
  <div class="card bo-card mb-4"><div class="card-header"><h2 class="h6 m-0">Website-wide announcement bar</h2></div><div class="card-body row g-3">
    <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="ann_enabled" value="1" id="ane"<?= chk(!empty($S['announcement']['enabled'])) ?>><label class="form-check-label" for="ane">Show the announcement on every page</label></div></div>
    <div class="col-md-8"><label class="form-label small">Text</label><input class="form-control" name="ann_text" value="<?= e($S['announcement']['text']) ?>" maxlength="240"></div>
    <div class="col-md-4"><label class="form-label small">Style</label><select class="form-select" name="ann_style"><option value="info">Teal</option><option value="warning"<?= sel('warning', $S['announcement']['style']) ?>>Amber</option><option value="success"<?= sel('success', $S['announcement']['style']) ?>>Green</option></select></div>
    <div class="col-12"><label class="form-label small">Link (optional)</label><input class="form-control" name="ann_link" value="<?= e($S['announcement']['link']) ?>" placeholder="/events-catalog or https://…"></div>
  </div></div>
  <div class="card bo-card mb-4"><div class="card-header"><h2 class="h6 m-0">Top bar ticker</h2></div><div class="card-body">
    <label class="form-label small">One item per line: <code>Text | /link</code></label>
    <textarea class="form-control font-monospace small" name="ticker" rows="7"><?php foreach ((array)$S['ticker'] as $t) { echo e($t[0] . ' | ' . $t[1]) . "\n"; } ?></textarea>
  </div></div>
<?php elseif ($tab === 'homepage'): ?>
  <div class="card bo-card mb-4"><div class="card-header"><h2 class="h6 m-0">Hero</h2></div><div class="card-body row g-3">
    <div class="col-12"><label class="form-label">Title</label><input class="form-control" name="hero_title" value="<?= e($S['hero_title']) ?>"></div>
    <div class="col-12"><label class="form-label">Subtitle</label><input class="form-control" name="hero_subtitle" value="<?= e($S['hero_subtitle']) ?>"></div>
  </div></div>
  <div class="card bo-card mb-4"><div class="card-header"><h2 class="h6 m-0">Sections</h2></div><div class="card-body row g-2">
    <?php $labels = array('slider' => 'Slider', 'cities' => 'Browse mosques by city', 'featured_mosques' => 'Featured mosques', 'nearby' => 'Mosques near you', 'platform' => 'Platform introduction', 'programs' => 'Explore programs', 'services' => 'Explore services', 'events' => 'Upcoming events', 'organizations' => 'Mosques & organizations tiles', 'featured_orgs' => 'Featured organizations', 'blogs' => 'Latest blogs', 'newsletter' => 'Newsletter');
    foreach ($labels as $k => $l): ?>
    <div class="col-sm-6 col-lg-4"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="sections[<?= $k ?>]" value="1" id="sec<?= $k ?>"<?= chk(!empty($S['home_sections'][$k])) ?>><label class="form-check-label" for="sec<?= $k ?>"><?= e($l) ?></label></div></div>
    <?php endforeach; ?>
  </div></div>
  <div class="card bo-card mb-4"><div class="card-header"><h2 class="h6 m-0">Featured</h2></div><div class="card-body row g-3">
    <div class="col-12"><label class="form-label">Featured mosques <span class="text-muted small">(empty = mosques with cover images)</span></label>
      <select class="form-select" name="featured_mosques[]" multiple data-bo-venue data-role="1" data-placeholder="Search mosques…"><?php foreach (venues_by_ids_any($S['featured_mosques']) as $v): ?><option value="<?= (int)$v['venue_id'] ?>" selected><?= e(trim($v['venue_title'])) ?></option><?php endforeach; ?></select></div>
    <div class="col-12"><label class="form-label">Featured organizations</label>
      <select class="form-select" name="featured_orgs[]" multiple data-bo-venue data-role="org" data-placeholder="Search organizations…"><?php foreach (venues_by_ids_any($S['featured_orgs']) as $v): ?><option value="<?= (int)$v['venue_id'] ?>" selected><?= e(trim($v['venue_title'])) ?></option><?php endforeach; ?></select></div>
  </div></div>
  <div class="card bo-card mb-4"><div class="card-header d-flex"><h2 class="h6 m-0">Slider</h2><button type="button" class="btn btn-sm btn-outline-primary ms-auto" data-add-slide>Add slide</button></div><div class="card-body" data-slides>
    <?php $slides = (array)$S['slides']; $slides[] = array('image' => '', 'title' => '', 'button' => '', 'link' => '', 'until' => '');
    foreach ($slides as $i => $sl): ?>
    <div class="slide-row row g-2 align-items-end border-bottom pb-3 mb-3"<?= $sl['image'] === '' ? ' data-slide-template' : '' ?>>
      <div class="col-md-3"><?php if ($sl['image'] !== ''): ?><img src="<?= e(media($sl['image'])) ?>" alt="" class="img-fluid rounded mb-1" style="max-height:70px" onerror="this.remove()"><?php endif; ?><input class="form-control form-control-sm" name="slide_image[]" value="<?= e($sl['image']) ?>" placeholder="images/… or upload"><input class="form-control form-control-sm mt-1" type="file" name="slide_upload[]" accept="image/*"></div>
      <div class="col-md-3"><label class="form-label small">Title</label><input class="form-control form-control-sm" name="slide_title[]" value="<?= e($sl['title']) ?>"></div>
      <div class="col-md-2"><label class="form-label small">Button</label><input class="form-control form-control-sm" name="slide_button[]" value="<?= e($sl['button']) ?>"></div>
      <div class="col-md-2"><label class="form-label small">Link</label><input class="form-control form-control-sm" name="slide_link[]" value="<?= e($sl['link']) ?>"></div>
      <div class="col-md-2"><label class="form-label small">Show until</label><input class="form-control form-control-sm" type="date" name="slide_until[]" value="<?= e($sl['until']) ?>"><?php if ($sl['image'] !== ''): ?><div class="form-check small mt-1"><input class="form-check-input" type="checkbox" name="slide_remove[<?= $i ?>]" value="1" id="sr<?= $i ?>"><label class="form-check-label" for="sr<?= $i ?>">Remove</label></div><?php endif; ?></div>
    </div>
    <?php endforeach; ?>
  </div></div>
<?php elseif ($tab === 'seo'): ?>
  <div class="card bo-card mb-4"><div class="card-header"><h2 class="h6 m-0">Default SEO</h2></div><div class="card-body row g-3">
    <div class="col-12"><label class="form-label">Homepage title</label><input class="form-control" name="seo_title" value="<?= e($S['seo_title']) ?>" maxlength="120" data-count="60"></div>
    <div class="col-12"><label class="form-label">Default meta description</label><textarea class="form-control" name="seo_description" rows="2" maxlength="300" data-count="158"><?= e($S['seo_description']) ?></textarea></div>
    <div class="col-12"><label class="form-label">Default share image (Open Graph)</label><input class="form-control" name="seo_image" value="<?= e($S['seo_image']) ?>" placeholder="images/… or https://…"></div>
  </div></div>
  <div class="card bo-card mb-4"><div class="card-header"><h2 class="h6 m-0">Analytics &amp; verification</h2></div><div class="card-body row g-3">
    <div class="col-md-6"><label class="form-label">Google Analytics 4 measurement ID</label><input class="form-control" name="ga4_id" value="<?= e($S['ga4_id']) ?>" placeholder="G-XXXXXXXXXX"></div>
    <div class="col-md-6"><label class="form-label">Google Search Console verification code</label><input class="form-control" name="head_code_verification" value="<?= e($S['head_code_verification']) ?>" placeholder="content value of the meta tag"></div>
    <p class="small text-muted mb-0">The sitemap is generated automatically at <code><?= e(SITE_URL) ?>/sitemap.xml</code> from published, indexable records.</p>
  </div></div>
<?php elseif ($tab === 'maps'): ?>
  <div class="card bo-card mb-4"><div class="card-header"><h2 class="h6 m-0">Map tiles</h2></div><div class="card-body row g-3">
    <div class="col-12"><label class="form-label">Tile URL</label><input class="form-control font-monospace small" name="map_tiles" value="<?= e($S['map_tiles']) ?>"><div class="form-text">Default: OpenStreetMap (free, fair use). For heavy traffic use a provider such as MapTiler or Stadia with your key in the URL.</div></div>
    <div class="col-12"><label class="form-label">Attribution</label><input class="form-control" name="map_attribution" value="<?= e($S['map_attribution']) ?>"></div>
    <div class="col-4"><label class="form-label small">Default centre latitude</label><input class="form-control" name="center_lat" value="<?= e($S['map_center']['lat']) ?>"></div>
    <div class="col-4"><label class="form-label small">longitude</label><input class="form-control" name="center_lng" value="<?= e($S['map_center']['lng']) ?>"></div>
    <div class="col-4"><label class="form-label small">zoom</label><input class="form-control" type="number" min="2" max="16" name="center_zoom" value="<?= e($S['map_center']['zoom']) ?>"></div>
  </div></div>
<?php else: ?>
  <div class="card bo-card mb-4"><div class="card-header"><h2 class="h6 m-0">What needs approval before it's published?</h2></div><div class="card-body">
    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" name="approve_profile" value="1" id="ap1"<?= chk($S['approve_profile']) ?>><label class="form-check-label" for="ap1">Profile changes made in the portal</label></div>
    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" name="approve_details" value="1" id="ap2"<?= chk($S['approve_details']) ?>><label class="form-check-label" for="ap2">Programs, services and events added or edited in the portal</label></div>
    <p class="small text-muted mb-0">Prayer times, Jumu'ah and announcements from the portal are published immediately (they are time-sensitive). Everything is recorded in the activity log.</p>
  </div></div>
<?php endif; ?>
  <button class="btn btn-primary">Save settings</button>
</form>
<?php bo_end();
