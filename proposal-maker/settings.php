<?php
require __DIR__ . '/includes/bootstrap.php';
require_login();

$tab = $_GET['tab'] ?? 'profile';
if (!in_array($tab, ['profile', 'brand', 'email'], true)) $tab = 'profile';
$co = company();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $form = post('form');
    try {
        if ($form === 'profile') {
            $upd = [];
            foreach (['company_name', 'tagline', 'phone', 'whatsapp', 'email', 'website', 'address', 'city', 'country', 'postal_code', 'facebook', 'instagram', 'linkedin', 'youtube', 'twitter'] as $f) {
                $upd[$f] = mb_substr(post($f), 0, 250);
            }
            if ($upd['company_name'] === '') throw new RuntimeException('Company name is required.');
            if ($upd['email'] !== '' && !filter_var($upd['email'], FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Company email is not valid.');
            foreach (['logo', 'logo_light'] as $lf) {
                if (!empty($_FILES[$lf]['name'])) $upd[$lf] = handle_image_upload($_FILES[$lf], 'logos', 900);
                if (post('remove_' . $lf) === '1') $upd[$lf] = null;
            }
            $upd['updated_at'] = date('Y-m-d H:i:s');
            db_update('company_settings', $upd, 'id = 1');
            flash('success', 'Company profile saved.');
        } elseif ($form === 'brand') {
            $upd = [];
            foreach (['primary_color', 'secondary_color', 'accent_color', 'heading_color', 'text_color', 'background_color', 'button_color'] as $f) {
                $upd[$f] = clean_color(post($f), $co[$f]);
            }
            $upd['default_font'] = valid_font(post('default_font'), 'Inter');
            $upd['heading_font'] = valid_font(post('heading_font'), 'Montserrat');
            $upd['default_template'] = pm_template((int) post('default_template'))['id'];
            $upd['default_currency'] = isset(currencies()[post('default_currency')]) ? post('default_currency') : 'USD';
            $upd['proposal_prefix'] = preg_replace('/[^A-Za-z0-9\-]/', '', post('proposal_prefix')) ?: 'PROP';
            $upd['default_validity_days'] = max(1, min(365, (int) post('default_validity_days')));
            $upd['updated_at'] = date('Y-m-d H:i:s');
            db_update('company_settings', $upd, 'id = 1');
            flash('success', 'Brand settings saved.');
        } elseif ($form === 'preset_add') {
            $name = post('preset_name');
            if ($name === '') throw new RuntimeException('Preset name is required.');
            $colors = [];
            foreach (color_keys() as $k => $l) $colors[$k] = clean_color(post('p_' . $k), '#000000');
            db_insert('brand_presets', ['name' => $name, 'colors' => jencode($colors), 'font_heading' => valid_font(post('p_font_heading'), 'Montserrat'), 'font_body' => valid_font(post('p_font_body'), 'Inter'), 'created_at' => date('Y-m-d H:i:s')]);
            flash('success', 'Preset “' . $name . '” saved.');
        } elseif ($form === 'preset_delete') {
            db_query('DELETE FROM brand_presets WHERE id = ?', [(int) post('preset_id')]);
            flash('success', 'Preset deleted.');
        } elseif ($form === 'preset_default') {
            $pr = db_row('SELECT * FROM brand_presets WHERE id = ?', [(int) post('preset_id')]);
            if ($pr) {
                db_query('UPDATE brand_presets SET is_default = (id = ?)', [(int) $pr['id']]);
                $c = jdecode($pr['colors']);
                $map = ['primary' => 'primary_color', 'secondary' => 'secondary_color', 'accent' => 'accent_color', 'heading' => 'heading_color', 'text' => 'text_color', 'background' => 'background_color', 'button' => 'button_color'];
                $upd = [];
                foreach ($map as $k => $col) if (is_hex_color($c[$k] ?? '')) $upd[$col] = $c[$k];
                if ($upd) db_update('company_settings', $upd, 'id = 1');
                flash('success', 'Preset applied as your brand colours.');
            }
        } elseif ($form === 'email') {
            $upd = [
                'smtp_enabled' => post('smtp_enabled') === '1' ? 1 : 0,
                'smtp_host' => post('smtp_host'), 'smtp_port' => (int) post('smtp_port') ?: 587, 'smtp_user' => post('smtp_user'),
                'smtp_secure' => in_array(post('smtp_secure'), ['tls', 'ssl', 'none'], true) ? post('smtp_secure') : 'tls',
                'smtp_from_email' => post('smtp_from_email'), 'smtp_from_name' => post('smtp_from_name'),
                'email_subject' => post('email_subject'), 'email_body' => (string) ($_POST['email_body'] ?? ''), 'notify_email' => post('notify_email'),
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            if (post('smtp_pass') !== '') $upd['smtp_pass'] = post('smtp_pass');
            db_update('company_settings', $upd, 'id = 1');
            flash('success', 'Email settings saved.');
            if (post('send_test') === '1') {
                require_once APP_ROOT . '/includes/mailer.php';
                company(true);
                $to = post('test_to') ?: (current_user()['email']);
                pm_send_mail($to, 'Test email from Proposal Maker', pm_email_html("This is a test email.\n\nIf you can read this, your email settings are working."));
                flash('success', 'Test email sent to ' . $to . '.');
            }
        }
    } catch (Throwable $e) {
        flash('danger', $e->getMessage());
    }
    redirect('settings.php?tab=' . $tab);
}

$presets = db_all('SELECT * FROM brand_presets ORDER BY is_default DESC, name');
$pageTitle = ['profile' => 'Company Profile', 'brand' => 'Brand & Colors', 'email' => 'Email / SMTP'][$tab];
$activeNav = ['profile' => 'settings', 'brand' => 'brand', 'email' => 'email'][$tab];
include __DIR__ . '/layouts/header.php';
$val = fn($k) => e($co[$k] ?? '');
function color_input(string $name, string $label, string $value): string
{
    return '<div class="color-field"><input type="color" value="' . e($value) . '"><span class="lbl">' . e($label) . '</span><input type="text" name="' . e($name) . '" value="' . e(strtoupper($value)) . '"></div>';
}
?>
<div class="page-head"><div><h1>Settings</h1><p>Configure your company details once — they are used on every proposal.</p></div></div>
<ul class="nav nav-tabs-x mb-4">
  <li class="nav-item"><a class="nav-link <?= $tab === 'profile' ? 'active' : '' ?>" href="?tab=profile"><i class="bi bi-building"></i> Company Profile</a></li>
  <li class="nav-item"><a class="nav-link <?= $tab === 'brand' ? 'active' : '' ?>" href="?tab=brand"><i class="bi bi-droplet-half"></i> Brand & Colors</a></li>
  <li class="nav-item"><a class="nav-link <?= $tab === 'email' ? 'active' : '' ?>" href="?tab=email"><i class="bi bi-envelope-at"></i> Email / SMTP</a></li>
</ul>

<?php if ($tab === 'profile'): ?>
<form method="post" enctype="multipart/form-data">
  <?= csrf_field() ?><input type="hidden" name="form" value="profile">
  <div class="row g-3">
    <div class="col-lg-4">
      <div class="card-x mb-3"><div class="card-head"><h6>Logo</h6></div><div class="card-body">
        <label class="form-label">Main logo <span class="text-muted small">(for light backgrounds)</span></label>
        <label class="logo-drop d-block mb-2"><?php if ($co['logo']): ?><img src="<?= e(media_src($co['logo'])) ?>" alt="" id="logoPrev"><?php else: ?><span class="text-muted small" id="logoPrevTxt"><i class="bi bi-cloud-upload fs-3 d-block"></i>Click to upload (PNG, SVG, JPG)</span><?php endif; ?>
          <input type="file" name="logo" accept="image/*" class="d-none" onchange="previewLogo(this)"></label>
        <?php if ($co['logo']): ?><div class="form-check small mb-3"><input class="form-check-input" type="checkbox" name="remove_logo" value="1" id="rl"><label class="form-check-label" for="rl">Remove logo</label></div><?php endif; ?>
        <label class="form-label mt-2">Light logo <span class="text-muted small">(for dark templates — optional)</span></label>
        <label class="logo-drop dark d-block mb-2"><?php if ($co['logo_light']): ?><img src="<?= e(media_src($co['logo_light'])) ?>" alt=""><?php else: ?><span class="text-white-50 small"><i class="bi bi-cloud-upload fs-3 d-block"></i>Click to upload</span><?php endif; ?>
          <input type="file" name="logo_light" accept="image/*" class="d-none" onchange="previewLogo(this)"></label>
        <?php if ($co['logo_light']): ?><div class="form-check small"><input class="form-check-input" type="checkbox" name="remove_logo_light" value="1" id="rll"><label class="form-check-label" for="rll">Remove light logo</label></div><?php endif; ?>
      </div></div>
    </div>
    <div class="col-lg-8">
      <div class="card-x mb-3"><div class="card-head"><h6>Company information</h6></div><div class="card-body"><div class="row g-3">
        <div class="col-md-6"><label class="form-label">Company name *</label><input class="form-control" name="company_name" value="<?= $val('company_name') ?>" required></div>
        <div class="col-md-6"><label class="form-label">Tagline</label><input class="form-control" name="tagline" value="<?= $val('tagline') ?>"></div>
        <div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone" value="<?= $val('phone') ?>"></div>
        <div class="col-md-6"><label class="form-label">WhatsApp</label><input class="form-control" name="whatsapp" value="<?= $val('whatsapp') ?>"></div>
        <div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= $val('email') ?>"></div>
        <div class="col-md-6"><label class="form-label">Website</label><input class="form-control" name="website" value="<?= $val('website') ?>" placeholder="www.yourcompany.com"></div>
        <div class="col-12"><label class="form-label">Address</label><input class="form-control" name="address" value="<?= $val('address') ?>"></div>
        <div class="col-md-4"><label class="form-label">City</label><input class="form-control" name="city" value="<?= $val('city') ?>"></div>
        <div class="col-md-4"><label class="form-label">Country</label><input class="form-control" name="country" value="<?= $val('country') ?>"></div>
        <div class="col-md-4"><label class="form-label">Postal code</label><input class="form-control" name="postal_code" value="<?= $val('postal_code') ?>"></div>
      </div></div></div>
      <div class="card-x mb-3"><div class="card-head"><h6>Social media</h6></div><div class="card-body"><div class="row g-3">
        <?php foreach (['facebook' => 'Facebook', 'instagram' => 'Instagram', 'linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'twitter' => 'X / Twitter'] as $f => $l): ?>
          <div class="col-md-6"><label class="form-label"><i class="bi bi-<?= $f === 'twitter' ? 'twitter-x' : $f ?>"></i> <?= $l ?></label><input class="form-control" name="<?= $f ?>" value="<?= $val($f) ?>" placeholder="https://"></div>
        <?php endforeach; ?>
      </div></div></div>
      <div class="text-end"><button class="btn btn-brand btn-lg px-4"><i class="bi bi-check2"></i> Save profile</button></div>
    </div>
  </div>
</form>

<?php elseif ($tab === 'brand'): ?>
<div class="row g-3">
  <div class="col-lg-7">
    <form method="post" class="card-x mb-3">
      <?= csrf_field() ?><input type="hidden" name="form" value="brand">
      <div class="card-head"><h6>Brand colours & defaults</h6><span class="ms-auto small text-muted">Applied when you choose “Apply my brand colours”</span></div>
      <div class="card-body">
        <div class="row g-2 mb-3">
          <?php foreach (['primary_color' => 'Primary', 'secondary_color' => 'Secondary', 'accent_color' => 'Accent', 'heading_color' => 'Heading', 'text_color' => 'Text', 'background_color' => 'Background', 'button_color' => 'Button'] as $f => $l): ?>
            <div class="col-md-6"><?= color_input($f, $l, $co[$f]) ?></div>
          <?php endforeach; ?>
        </div>
        <div class="row g-3">
          <div class="col-md-6"><label class="form-label">Heading font</label><select class="form-select" name="heading_font"><?php foreach (font_list() as $f => $x): ?><option <?= $co['heading_font'] === $f ? 'selected' : '' ?>><?= e($f) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-6"><label class="form-label">Default (body) font</label><select class="form-select" name="default_font"><?php foreach (font_list() as $f => $x): ?><option <?= $co['default_font'] === $f ? 'selected' : '' ?>><?= e($f) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-6"><label class="form-label">Default template</label><select class="form-select" name="default_template"><?php foreach (pm_templates() as $id => $t): ?><option value="<?= $id ?>" <?= (int) $co['default_template'] === $id ? 'selected' : '' ?>><?= sprintf('%02d', $id) ?> — <?= e($t['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-6"><label class="form-label">Default currency</label><select class="form-select" name="default_currency"><?php foreach (currencies() as $c => $x): ?><option value="<?= $c ?>" <?= $co['default_currency'] === $c ? 'selected' : '' ?>><?= $c ?> — <?= e($x['name']) ?></option><?php endforeach; ?></select></div>
          <div class="col-md-6"><label class="form-label">Proposal number prefix</label><input class="form-control" name="proposal_prefix" value="<?= $val('proposal_prefix') ?>"><div class="form-text">Next: <?= e(pm_next_number()) ?></div></div>
          <div class="col-md-6"><label class="form-label">Default validity (days)</label><input type="number" class="form-control" name="default_validity_days" value="<?= (int) $co['default_validity_days'] ?>"></div>
        </div>
      </div>
      <div class="card-body border-top text-end"><button class="btn btn-brand"><i class="bi bi-check2"></i> Save brand settings</button></div>
    </form>
  </div>
  <div class="col-lg-5">
    <div class="card-x mb-3"><div class="card-head"><h6>Colour presets</h6></div><div class="card-body">
      <div class="row g-2">
        <?php foreach ($presets as $pr): $c = jdecode($pr['colors']); ?>
          <div class="col-sm-6"><div class="preset-card">
            <div class="d-flex align-items-center"><b class="text-dark small"><?= e($pr['name']) ?></b><?php if ($pr['is_default']): ?><span class="chip ms-auto">Default</span><?php endif; ?></div>
            <div class="swatches"><?php foreach (['primary', 'secondary', 'accent', 'heading', 'background'] as $k): ?><span style="background:<?= e($c[$k] ?? '#fff') ?>"></span><?php endforeach; ?></div>
            <div class="d-flex gap-1">
              <form method="post"><?= csrf_field() ?><input type="hidden" name="form" value="preset_default"><input type="hidden" name="preset_id" value="<?= (int) $pr['id'] ?>"><button class="btn btn-sm btn-light border">Use as brand</button></form>
              <form method="post" onsubmit="return confirm('Delete this preset?')"><?= csrf_field() ?><input type="hidden" name="form" value="preset_delete"><input type="hidden" name="preset_id" value="<?= (int) $pr['id'] ?>"><button class="btn btn-sm btn-light border text-danger"><i class="bi bi-trash3"></i></button></form>
            </div>
          </div></div>
        <?php endforeach; ?>
      </div>
    </div></div>
    <form method="post" class="card-x">
      <?= csrf_field() ?><input type="hidden" name="form" value="preset_add">
      <div class="card-head"><h6>New preset</h6></div>
      <div class="card-body">
        <input class="form-control mb-2" name="preset_name" placeholder="Preset name, e.g. Purple Startup" required>
        <div class="row g-2">
          <?php $tc = pm_template(1)['colors']; foreach (color_keys() as $k => $l): ?>
            <div class="col-6"><?= color_input('p_' . $k, $l, $tc[$k]) ?></div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="card-body border-top text-end"><button class="btn btn-brand btn-sm"><i class="bi bi-plus-lg"></i> Save preset</button></div>
    </form>
  </div>
</div>

<?php else: ?>
<form method="post">
  <?= csrf_field() ?><input type="hidden" name="form" value="email">
  <div class="row g-3">
    <div class="col-lg-6">
      <div class="card-x"><div class="card-head"><h6>SMTP server</h6>
        <div class="form-check form-switch ms-auto m-0"><input class="form-check-input" type="checkbox" name="smtp_enabled" value="1" id="smtpOn" <?= $co['smtp_enabled'] ? 'checked' : '' ?>><label class="form-check-label small fw-semibold" for="smtpOn">Use SMTP</label></div></div>
        <div class="card-body"><div class="row g-3">
          <div class="col-8"><label class="form-label">Host</label><input class="form-control" name="smtp_host" value="<?= $val('smtp_host') ?>" placeholder="smtp.hostinger.com"></div>
          <div class="col-4"><label class="form-label">Port</label><input class="form-control" name="smtp_port" value="<?= $val('smtp_port') ?>"></div>
          <div class="col-6"><label class="form-label">Username</label><input class="form-control" name="smtp_user" value="<?= $val('smtp_user') ?>" autocomplete="off"></div>
          <div class="col-6"><label class="form-label">Password</label><input class="form-control" type="password" name="smtp_pass" placeholder="<?= $co['smtp_pass'] ? '•••••••• (unchanged)' : '' ?>" autocomplete="new-password"></div>
          <div class="col-6"><label class="form-label">Encryption</label><select class="form-select" name="smtp_secure"><?php foreach (['tls' => 'TLS (587)', 'ssl' => 'SSL (465)', 'none' => 'None'] as $k => $l): ?><option value="<?= $k ?>" <?= $co['smtp_secure'] === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select></div>
          <div class="col-6"><label class="form-label">Notify me at</label><input class="form-control" name="notify_email" value="<?= $val('notify_email') ?>" placeholder="When clients accept/reject"></div>
          <div class="col-6"><label class="form-label">From email</label><input class="form-control" name="smtp_from_email" value="<?= $val('smtp_from_email') ?>"></div>
          <div class="col-6"><label class="form-label">From name</label><input class="form-control" name="smtp_from_name" value="<?= $val('smtp_from_name') ?>"></div>
          <div class="col-12"><div class="input-group"><input class="form-control" name="test_to" placeholder="Send a test email to…" value="<?= e(current_user()['email']) ?>"><button class="btn btn-light border" name="send_test" value="1"><i class="bi bi-send"></i> Save & send test</button></div></div>
        </div></div>
      </div>
    </div>
    <div class="col-lg-6">
      <div class="card-x"><div class="card-head"><h6>Default proposal email</h6></div><div class="card-body">
        <div class="mb-3"><label class="form-label">Subject</label><input class="form-control" name="email_subject" value="<?= $val('email_subject') ?>"></div>
        <div class="mb-2"><label class="form-label">Message</label><textarea class="form-control" name="email_body" rows="10"><?= $val('email_body') ?></textarea></div>
        <div class="small text-muted">Variables: <?php foreach (['client_name', 'client_company', 'project_name', 'proposal_title', 'proposal_number', 'expiry_date', 'total_amount', 'company_name', 'proposal_link'] as $v): ?><span class="var-chip">{{<?= $v ?>}}</span><?php endforeach; ?></div>
      </div></div>
    </div>
  </div>
  <div class="text-end mt-3"><button class="btn btn-brand btn-lg px-4"><i class="bi bi-check2"></i> Save email settings</button></div>
</form>
<?php endif; ?>
<?php
$extraScripts = '<script>function previewLogo(inp){ if(!inp.files[0]) return; var u = URL.createObjectURL(inp.files[0]); var $l = $(inp).closest(".logo-drop"); $l.find("img,span").remove(); $l.prepend("<img src=\'" + u + "\'>"); }</script>';
include __DIR__ . '/layouts/footer.php';
