<?php
require __DIR__ . '/../_bootstrap.php';
require __DIR__ . '/../_layout.php';
$u = web_require_login(true);
web_page_start('Settings', 'admin-settings', $u);
?>
<div class="page-head"><div><h1>Settings</h1><div class="muted">Platform-wide settings.</div></div><button class="btn primary" id="save"><span class="ms">save</span>Save</button></div>
<div class="card" id="form"></div>
<script>
(async () => {
  const { api, el, toast, busy } = App;
  const MASK = '••••••••';
  const box = document.getElementById('form');
  let rows = [];
  async function load() {
    rows = await api('settings/app.php');
    box.replaceChildren(...rows.map((r) => {
      let input;
      if (r.value_type === 'bool') input = el('select', {}, el('option', { value: 'true', selected: r.setting_value === 'true' }, 'Yes'), el('option', { value: 'false', selected: r.setting_value !== 'true' }, 'No'));
      else if (r.value_type === 'json' || String(r.setting_value).length > 80) input = el('textarea', { rows: 3 }, r.setting_value ?? '');
      else input = el('input', { type: r.value_type === 'int' ? 'number' : (r.setting_key.includes('color') ? 'text' : 'text'), value: r.setting_value ?? '' });
      input.dataset.key = r.setting_key; input.dataset.orig = r.setting_value ?? '';
      return el('label', {}, el('span', { style: { color: 'var(--text)' } }, r.setting_key.replace(/_/g, ' ')), input, r.description ? el('span', { class: 'small muted', style: { fontWeight: '400' } }, r.description) : null);
    }));
  }
  document.getElementById('save').onclick = async () => {
    const settings = {};
    box.querySelectorAll('[data-key]').forEach((x) => { if (x.value !== x.dataset.orig && x.value !== MASK) settings[x.dataset.key] = x.value; });
    if (!Object.keys(settings).length) return toast('Nothing changed');
    if (await busy(() => api('settings/app.php', { settings }), 'Settings saved')) load();
  };
  load().catch((e) => toast(e.message, true));
})();
</script>
<?php web_page_end();
