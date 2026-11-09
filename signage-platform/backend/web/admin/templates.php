<?php
require __DIR__ . '/../_bootstrap.php';
require __DIR__ . '/../_layout.php';
$u = web_require_login(true);
web_page_start('Templates', 'admin-templates', $u);
?>
<div class="page-head"><div><h1>Templates</h1><div class="muted">Design professional templates with backgrounds, photos, headings and shadows. Customers then just change the text and pictures.</div></div>
  <button class="btn primary" id="new"><span class="ms">add</span>New template</button></div>
<div class="row" style="margin-bottom:12px"><input id="q" placeholder="Search templates…" style="max-width:280px"><div class="row" id="cats" style="gap:6px"></div></div>
<div class="grid" id="grid"></div>
<script>
(async () => {
  const { api, el, icon, toast, modal, confirmBox, busy, thumb } = App;
  let list = [], cats = [], cat = 0, q = '';
  const grid = document.getElementById('grid');
  async function load() {
    try { [list, cats] = await Promise.all([api('admin/templates.php'), api('templates/categories.php')]); draw(); } catch (e) { toast(e.message, true); }
  }
  function draw() {
    const counts = {}; list.forEach((t) => (counts[t.category_id] = (counts[t.category_id] || 0) + 1));
    document.getElementById('cats').replaceChildren(el('button', { class: 'chip' + (!cat ? ' on' : ''), onclick: () => { cat = 0; draw(); } }, 'All (' + list.length + ')'),
      ...cats.filter((c) => counts[c.category_id]).map((c) => el('button', { class: 'chip' + (cat == c.category_id ? ' on' : ''), onclick: () => { cat = c.category_id; draw(); } }, c.category_name + ' (' + counts[c.category_id] + ')')));
    grid.replaceChildren();
    const shown = list.filter((t) => (!cat || t.category_id == cat) && (!q || (t.template_name + ' ' + (t.tags || '')).toLowerCase().includes(q)));
    if (!shown.length) grid.append(el('div', { class: 'empty', style: { gridColumn: '1/-1' } }, 'No templates.'));
    for (const t of shown) {
      const th = el('a', { class: 'thumb', href: '../editor.php?template_id=' + t.template_id });
      const locked = (t.template_json.widgets || []).filter((w) => w.is_locked).length;
      grid.append(el('div', { class: 'card tile hover', style: { position: 'relative', opacity: t.status === 'active' ? '1' : '.6' } }, th, t.is_premium == 1 ? el('span', { class: 'pro' }, 'PRO') : null,
        el('div', { class: 'row', style: { justifyContent: 'space-between', flexWrap: 'nowrap' } }, el('div', { class: 'name grow' }, t.template_name), el('span', { class: 'badge ' + t.status }, t.status)),
        el('div', { class: 'small muted' }, t.category_name + ' · ' + t.orientation + (locked ? ' · ' + locked + ' locked' : '')),
        el('div', { class: 'actions' },
          el('a', { class: 'btn sm', href: '../editor.php?template_id=' + t.template_id, title: 'Edit design' }, icon('edit')),
          el('button', { class: 'btn sm', title: 'Duplicate', onclick: () => duplicate(t) }, icon('content_copy')),
          el('button', { class: 'btn sm', title: t.status === 'active' ? 'Hide from users' : 'Show to users', onclick: async () => { if (await busy(() => api('admin/templates.php', { action: 'toggle', template_id: t.template_id }), 'Updated')) load(); } }, icon(t.status === 'active' ? 'visibility' : 'visibility_off')),
          el('button', { class: 'btn sm danger', title: 'Delete', onclick: async () => { if (await confirmBox('Delete “' + t.template_name + '”?', 'Screens already created from it are not affected.', 'Delete', true) && await busy(() => api('admin/templates.php', { action: 'delete', template_id: t.template_id }), 'Deleted')) load(); } }, icon('delete')))));
      thumb(th, t.template_json, t.media);
    }
  }
  async function duplicate(t) {
    const r = await busy(() => api('admin/templates.php', { action: 'save', category_id: t.category_id, template_name: t.template_name + ' (copy)', description: t.description, orientation: t.orientation, template_json: t.template_json, tags: t.tags, is_premium: t.is_premium == 1, status: 'inactive' }), 'Duplicated (hidden until you activate it)');
    if (r) load();
  }
  let tm; document.getElementById('q').oninput = (e) => { clearTimeout(tm); tm = setTimeout(() => { q = e.target.value.trim().toLowerCase(); draw(); }, 200); };
  document.getElementById('new').onclick = () => {
    const pick = (o, i, l) => el('a', { class: 'card hover', href: '../editor.php?template=new&orientation=' + o, style: { textAlign: 'center', color: 'inherit', flex: '1' } }, el('span', { class: 'ms', style: { fontSize: '48px', color: 'var(--teal)' } }, i), el('div', { style: { fontWeight: '700' } }, l));
    modal('New template', el('div', {}, el('p', { class: 'muted' }, 'Choose the screen shape. Then add a background, photos (shared media), headings, price badges and shadows.'),
      el('div', { class: 'row', style: { flexWrap: 'nowrap' } }, pick('landscape', 'crop_landscape', 'Landscape 1920×1080'), pick('portrait', 'crop_portrait', 'Portrait 1080×1920'), pick('square', 'crop_square', 'Square 1080×1080'))));
  };
  load();
})();
</script>
<?php web_page_end();
