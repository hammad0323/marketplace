<?php
require __DIR__ . '/../config/config.php';
require_admin_page();

$settingsRes = mysqli_query(db(), 'SELECT setting_key, setting_value FROM site_settings');
$settings = [];
while ($row = mysqli_fetch_assoc($settingsRes)) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$cmsRes = mysqli_query(db(), "SELECT * FROM cms_pages WHERE slug IN ('about','privacy-policy','terms-conditions')");
$cmsPages = [];
while ($row = mysqli_fetch_assoc($cmsRes)) {
    $cmsPages[$row['slug']] = $row;
}

$faqs = mysqli_query(db(), 'SELECT * FROM faqs ORDER BY sort_order');

$sitemapPath = dirname(__DIR__) . '/sitemap.xml';
$sitemapExists = is_file($sitemapPath);
$sitemapGeneratedAt = $sitemapExists ? date('M j, Y g:i A', filemtime($sitemapPath)) : null;
$sitemapUrlCount = $sitemapExists ? substr_count(file_get_contents($sitemapPath), '<url>') : 0;

$pageTitle = 'Site Settings';
$heading = 'Site Settings';
$extraScripts = '<script src="/assets/js/admin-settings.js"></script>';
require __DIR__ . '/includes/header.php';
?>
<div class="tabs-row">
    <button class="tab-btn active" data-tab="general">General</button>
    <button class="tab-btn" data-tab="email">Email (SMTP)</button>
    <button class="tab-btn" data-tab="cms">Pages (About / Privacy / Terms)</button>
    <button class="tab-btn" data-tab="faqs">FAQs</button>
    <button class="tab-btn" data-tab="seo">SEO &amp; Sitemap</button>
</div>

<div class="settings-panel" id="panel-general">
    <div class="card" style="padding:28px;max-width:640px;" data-reveal>
        <form id="settings-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="form-group"><label class="form-label">Site Name</label><input type="text" class="form-control" name="site_name" value="<?= e($settings['site_name'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">Tagline</label><input type="text" class="form-control" name="site_tagline" value="<?= e($settings['site_tagline'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">Contact Email</label><input type="email" class="form-control" name="contact_email" value="<?= e($settings['contact_email'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">Contact Phone</label><input type="text" class="form-control" name="contact_phone" value="<?= e($settings['contact_phone'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">Contact Address</label><input type="text" class="form-control" name="contact_address" value="<?= e($settings['contact_address'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">Currency Symbol</label><input type="text" class="form-control" name="currency_symbol" value="<?= e($settings['currency_symbol'] ?? '$') ?>" style="max-width:100px;"></div>
            <label class="checkbox-row" style="margin-bottom:20px;"><input type="checkbox" name="maintenance_mode" value="1" <?= ($settings['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>> Maintenance mode</label>
            <button type="submit" class="btn btn-primary">Save Settings</button>
        </form>
    </div>
</div>

<div class="settings-panel" id="panel-email" style="display:none;">
    <div class="card" style="padding:28px;max-width:640px;" data-reveal>
        <p style="color:var(--color-text-muted);margin-bottom:20px;">
            Configure an SMTP server to send appointment, verification, and message emails to patients, doctors, and admins.
            Leave the host blank to fall back to the server's built-in <code>mail()</code> function (not recommended for production).
        </p>
        <form id="email-settings-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <label class="checkbox-row" style="margin-bottom:20px;"><input type="checkbox" name="email_notifications_enabled" value="1" <?= ($settings['email_notifications_enabled'] ?? '1') === '1' ? 'checked' : '' ?>> Send activity emails (appointments, verification, messages, contact form)</label>
            <div class="grid grid-2">
                <div class="form-group"><label class="form-label">SMTP Host</label><input type="text" class="form-control" name="smtp_host" placeholder="smtp.yourprovider.com" value="<?= e($settings['smtp_host'] ?? '') ?>"></div>
                <div class="form-group"><label class="form-label">SMTP Port</label><input type="number" class="form-control" name="smtp_port" placeholder="587" value="<?= e($settings['smtp_port'] ?? '587') ?>"></div>
            </div>
            <div class="grid grid-2">
                <div class="form-group"><label class="form-label">Username</label><input type="text" class="form-control" name="smtp_username" autocomplete="off" value="<?= e($settings['smtp_username'] ?? '') ?>"></div>
                <div class="form-group"><label class="form-label">Password</label><input type="password" class="form-control" name="smtp_password" autocomplete="new-password" value="<?= e($settings['smtp_password'] ?? '') ?>"></div>
            </div>
            <div class="grid grid-2">
                <div class="form-group">
                    <label class="form-label">Encryption</label>
                    <select class="form-control" name="smtp_encryption">
                        <?php foreach (['tls' => 'STARTTLS (port 587, recommended)', 'ssl' => 'SSL/TLS (port 465)', 'none' => 'None'] as $val => $label): ?>
                        <option value="<?= $val ?>" <?= ($settings['smtp_encryption'] ?? 'tls') === $val ? 'selected' : '' ?>><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label class="form-label">From Email</label><input type="email" class="form-control" name="smtp_from_email" placeholder="no-reply@yourdomain.com" value="<?= e($settings['smtp_from_email'] ?? '') ?>"></div>
            </div>
            <div class="form-group"><label class="form-label">From Name</label><input type="text" class="form-control" name="smtp_from_name" value="<?= e($settings['smtp_from_name'] ?? $settings['site_name'] ?? '') ?>"></div>
            <div style="display:flex;gap:10px;">
                <button type="submit" class="btn btn-primary">Save Email Settings</button>
                <button type="button" class="btn btn-outline" id="send-test-email-btn">Send Test Email</button>
            </div>
        </form>
    </div>
</div>

<div class="settings-panel" id="panel-cms" style="display:none;">
    <?php foreach (['about' => 'About Page', 'privacy-policy' => 'Privacy Policy', 'terms-conditions' => 'Terms & Conditions'] as $slug => $label): $page = $cmsPages[$slug] ?? ['title' => $label, 'content' => '', 'meta_title' => '', 'meta_description' => '']; ?>
    <div class="card" style="padding:28px;margin-bottom:20px;" data-reveal>
        <h4 style="margin-bottom:16px;"><?= $label ?></h4>
        <form class="cms-form" data-slug="<?= $slug ?>">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="slug" value="<?= $slug ?>">
            <div class="form-group"><label class="form-label">Page Title</label><input type="text" class="form-control" name="title" value="<?= e($page['title']) ?>"></div>
            <div class="form-group"><label class="form-label">Content (HTML allowed)</label><textarea class="form-control" name="content" rows="6"><?= e($page['content']) ?></textarea></div>
            <div class="grid grid-2">
                <div class="form-group"><label class="form-label">Meta Title</label><input type="text" class="form-control" name="meta_title" value="<?= e($page['meta_title']) ?>"></div>
                <div class="form-group"><label class="form-label">Meta Description</label><input type="text" class="form-control" name="meta_description" value="<?= e($page['meta_description']) ?>"></div>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Save Page</button>
        </form>
    </div>
    <?php endforeach; ?>
</div>

<div class="settings-panel" id="panel-faqs" style="display:none;">
    <div class="card" style="padding:28px;margin-bottom:20px;" data-reveal>
        <h4 style="margin-bottom:16px;">Add FAQ</h4>
        <form id="faq-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="form-group" data-field="question"><label class="form-label">Question</label><input type="text" class="form-control" name="question" required><div class="form-error"></div></div>
            <div class="form-group"><label class="form-label">Answer</label><textarea class="form-control" name="answer" rows="3" required></textarea></div>
            <button type="submit" class="btn btn-primary btn-sm">Add FAQ</button>
        </form>
    </div>
    <div id="faq-list">
        <?php while ($f = mysqli_fetch_assoc($faqs)): ?>
        <div class="card" style="padding:18px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:flex-start;gap:16px;" data-faq-id="<?= (int)$f['id'] ?>">
            <div><strong><?= e($f['question']) ?></strong><p style="color:var(--color-text-muted);margin-top:4px;font-size:14px;"><?= e($f['answer']) ?></p></div>
            <button type="button" class="btn-icon btn-delete-faq" style="width:32px;height:32px;flex-shrink:0;"><i class="ri-delete-bin-line"></i></button>
        </div>
        <?php endwhile; ?>
    </div>
</div>

<div class="settings-panel" id="panel-seo" style="display:none;">
    <div class="card" style="padding:28px;max-width:640px;" data-reveal>
        <h4 style="margin-bottom:8px;">On-page SEO</h4>
        <p style="color:var(--color-text-muted);font-size:13.5px;margin-bottom:20px;">
            Canonical URLs, Open Graph tags, and JSON-LD structured data are generated automatically on every public
            page — doctor profiles, blog posts, and store listings each get their own unique canonical URL. Meta title
            and meta description can be set per-item on the <a href="/admin/blog" style="color:var(--color-primary);font-weight:600;">Blog</a>
            editor, each doctor's <strong>My Store</strong> listings, and the CMS pages above; anything left blank is
            auto-generated from the title/excerpt/description of that item.
        </p>
        <div class="divider-fade" style="margin:20px 0;"></div>
        <h4 style="margin-bottom:8px;">AI Search (AEO / GEO)</h4>
        <p style="color:var(--color-text-muted);font-size:13.5px;margin-bottom:20px;">
            <code>robots.txt</code> explicitly allows the major AI crawlers (GPTBot, ClaudeBot, PerplexityBot,
            Google-Extended, and others) so this site can be indexed and cited by AI search/chat products. Doctor
            profiles, medicines, and the FAQ page also emit FAQPage structured data so AI answer engines can quote
            them directly. The button below also generates <code>/llms.txt</code> — a curated, plain-text index of the
            site aimed at AI agents, per the <a href="https://llmstxt.org" target="_blank" rel="noopener" style="color:var(--color-primary);font-weight:600;">llms.txt</a> convention.
        </p>
        <div class="divider-fade" style="margin:20px 0;"></div>
        <h4 style="margin-bottom:8px;">Sitemap</h4>
        <p style="color:var(--color-text-muted);font-size:13.5px;margin-bottom:16px;">
            <code>/sitemap.xml</code> is always available as a live, dynamically-generated page. Use the button below to
            also write it out as a real static file at the project root (and refresh <code>/llms.txt</code> alongside
            it) — useful for search-console verification or to serve it with zero PHP overhead.
        </p>
        <div id="sitemap-status" style="font-size:13px;color:var(--color-text-muted);margin-bottom:16px;">
            <?php if ($sitemapExists): ?>
                Static file last generated <strong><?= e($sitemapGeneratedAt) ?></strong> — <?= (int) $sitemapUrlCount ?> URLs.
                <a href="/sitemap.xml" target="_blank" style="color:var(--color-primary);font-weight:600;">View sitemap.xml</a>
            <?php else: ?>
                No static sitemap.xml has been generated yet. The dynamic <a href="/sitemap.php" target="_blank" style="color:var(--color-primary);font-weight:600;">/sitemap.php</a> is always live regardless.
            <?php endif; ?>
        </div>
        <input type="hidden" id="sitemap-csrf" value="<?= e(csrf_token()) ?>">
        <button type="button" class="btn btn-primary" id="generate-sitemap-btn">Generate Sitemap</button>
    </div>
</div>

<script>
document.querySelectorAll('.tabs-row .tab-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.tabs-row .tab-btn').forEach(function (b) { b.classList.remove('active'); });
        document.querySelectorAll('.settings-panel').forEach(function (p) { p.style.display = 'none'; });
        btn.classList.add('active');
        document.getElementById('panel-' + btn.getAttribute('data-tab')).style.display = 'block';
    });
});
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
