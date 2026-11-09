<?php
require __DIR__ . '/../_bootstrap.php';
require __DIR__ . '/../_layout.php';
$u = web_require_login(true);
web_page_start('Plans', 'admin-plans', $u);
?>
<div class="page-head"><div><h1>Plans</h1><div class="muted">Default limits for customers. Per-user overrides are set on each user.</div></div><button class="btn primary" id="new"><span class="ms">add</span>New plan</button></div>
<div class="grid" id="grid" style="grid-template-columns:repeat(auto-fill,minmax(260px,1fr))"></div>
<script>
(async () => {
  const { api, el, icon, toast, modal, confirmBox, busy, lim, mb } = App;
  const grid = document.getElementById('grid');
  async function load() {
    const plans = await api('admin/plans.php');
    grid.replaceChildren(...plans.map((p) => { const f = p.features_json || {};
      return el('div', { class: 'card' }, el('div', { class: 'row', style: { justifyContent: 'space-between' } }, el('h2', {}, p.plan_name), el('span', { class: 'badge ' + p.status }, p.is_default == 1 ? 'default' : p.status)),
        el('div', { style: { fontSize: '26px', fontWeight: '800', margin: '8px 0' } }, p.currency + ' ' + Number(p.price_monthly).toFixed(2), el('span', { class: 'small muted' }, ' / month')),
        el('div', { class: 'small' }, `${lim(p.max_screens)} screens · ${lim(p.max_playlists)} playlists · ${lim(p.max_devices)} displays`), el('div', { class: 'small' }, `${mb(p.max_storage_mb)} storage · ${lim(p.max_upload_mb)} MB uploads`),
        el('div', { class: 'small muted', style: { margin: '6px 0' } }, [f.video_upload && 'video', f.premium_templates && 'premium templates', f.remote_commands && 'remote control', f.emergency && 'emergency alerts'].filter(Boolean).join(' · ') || '—'),
        el('div', { class: 'small muted' }, p.user_count + ' users'),
        el('div', { class: 'row', style: { marginTop: '10px' } }, el('button', { class: 'btn sm', onclick: () => edit(p) }, icon('edit'), 'Edit'),
          p.is_default != 1 ? el('button', { class: 'btn sm danger', onclick: async () => { if (await confirmBox('Delete ' + p.plan_name + '?', '', 'Delete', true) && await busy(() => api('admin/plans.php', { action: 'delete', plan_id: p.plan_id }), 'Deleted')) load(); } }, icon('delete')) : null)); }));
  }
  function edit(p = {}) {
    const f = p.features_json || { video_upload: true, premium_templates: false, remote_commands: true, emergency: true, allowed_widgets: ['*'] };
    const i = (k, label, type = 'number', v = p[k]) => { const x = el('input', { type, value: v ?? '' }); x.dataset.k = k; return el('label', {}, label, x); };
    const cb = (k, label) => { const x = el('input', { type: 'checkbox', checked: !!f[k] }); x.dataset.f = k; return el('label', { style: { flexDirection: 'row', alignItems: 'center', gap: '8px', color: 'var(--text)' } }, x, label); };
    const body = el('div', {}, i('plan_name', 'Name', 'text'), i('description', 'Description', 'text'),
      el('div', { class: 'geom' }, i('price_monthly', 'Price / month'), i('price_yearly', 'Price / year'), i('currency', 'Currency', 'text', p.currency || 'USD'), i('sort_order', 'Sort order'),
        i('max_screens', 'Max screens (−1 = ∞)'), i('max_playlists', 'Max playlists'), i('max_devices', 'Max displays'), i('max_storage_mb', 'Storage MB'), i('max_upload_mb', 'Max upload MB')),
      cb('video_upload', 'Video upload'), cb('premium_templates', 'Premium templates'), cb('remote_commands', 'Remote display control'), cb('emergency', 'Emergency alerts'), cb('analytics', 'Reports'), cb('locations', 'Multiple locations'), cb('custom_branding', 'Custom branding'),
      (() => { const x = el('input', { type: 'checkbox', checked: p.is_default == 1 }); x.dataset.k2 = 'is_default'; return el('label', { style: { flexDirection: 'row', alignItems: 'center', gap: '8px', color: 'var(--text)' } }, x, 'Default plan for new users'); })());
    modal(p.plan_id ? 'Edit plan' : 'New plan', body, { actions: [{ label: 'Cancel', onClick: (c) => c() }, { label: 'Save', cls: 'primary', onClick: async (c) => {
      const out = { action: 'save', plan_id: p.plan_id, features: { ...f } };
      body.querySelectorAll('[data-k]').forEach((x) => (out[x.dataset.k] = x.type === 'number' ? (x.value === '' ? null : Number(x.value)) : x.value));
      body.querySelectorAll('[data-f]').forEach((x) => (out.features[x.dataset.f] = x.checked));
      out.is_default = body.querySelector('[data-k2]').checked;
      if (await busy(() => api('admin/plans.php', out), 'Plan saved')) { c(); load(); }
    } }] });
  }
  document.getElementById('new').onclick = () => edit({ max_screens: 5, max_playlists: 3, max_devices: 3, max_storage_mb: 1024, max_upload_mb: 50, price_monthly: 0, price_yearly: 0, status: 'active' });
  load().catch((e) => toast(e.message, true));
})();
</script>
<?php web_page_end();
