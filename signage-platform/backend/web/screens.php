<?php
require __DIR__ . '/_bootstrap.php';
require __DIR__ . '/_layout.php';
$u = web_require_login();
web_page_start('My Screens', 'screens', $u);
?>
<div class="page-head"><div><h1>My Screens</h1><div class="muted" id="count">&nbsp;</div></div>
  <button class="btn primary" id="new"><span class="ms">add</span>New screen</button></div>
<div class="row" style="margin-bottom:14px">
  <input id="q" placeholder="Search screens…" style="max-width:320px">
  <div class="row" id="filters" style="gap:6px"></div>
</div>
<div class="grid" id="grid"></div>
<script>
(() => {
  const { api, el, icon, toast, modal, confirmBox, promptBox, busy, timeAgo, thumb, displayCode, S } = App;
  let status = '', q = '';
  const grid = document.getElementById('grid');
  const filters = document.getElementById('filters');
  [['', 'All'], ['published', 'Published'], ['draft', 'Drafts']].forEach(([k, l]) => filters.append(el('button', { class: 'chip' + (k === status ? ' on' : ''), 'data-k': k, onclick: (e) => { status = k; filters.querySelectorAll('.chip').forEach((c) => c.classList.toggle('on', c.dataset.k === k)); load(); } }, l)));
  let t; document.getElementById('q').addEventListener('input', (e) => { clearTimeout(t); t = setTimeout(() => { q = e.target.value.trim(); load(); }, 300); });

  async function load() {
    grid.innerHTML = '<div class="muted">Loading…</div>';
    try {
      const j = await App.get('screens/list.php', { q, status, per_page: 100, with_layout: 1 }, { full: true });
      const list = j.data;
      document.getElementById('count').textContent = (j.meta ? j.meta.total : list.length) + ' screens';
      grid.replaceChildren();
      if (!list.length) grid.append(el('div', { class: 'empty', style: { gridColumn: '1/-1' } }, icon('dashboard_customize'), el('h3', {}, q || status ? 'No matching screens' : 'No screens yet'), el('p', {}, 'Start from a ready-made template or a blank canvas.'), el('button', { class: 'btn primary', onclick: createScreen }, icon('add'), 'New screen')));
      list.forEach((s) => grid.append(card(s)));
    } catch (e) { grid.replaceChildren(el('div', { class: 'alert error' }, e.message)); }
  }
  function card(s) {
    const th = el('a', { class: 'thumb', href: 'editor.php?screen_id=' + s.screen_id });
    const more = () => {
      const m = modal(s.screen_name, el('div', { style: { display: 'grid', gap: '8px' } },
        el('a', { class: 'btn block', href: 'editor.php?screen_id=' + s.screen_id }, icon('edit'), 'Edit design'),
        el('button', { class: 'btn block', onclick: () => { m.close(); preview(s); } }, icon('play_arrow'), 'Preview'),
        el('button', { class: 'btn block', onclick: async () => { m.close(); if (await busy(() => api('screens/publish.php', { screen_id: s.screen_id }), 'Published')) load(); } }, icon('publish'), 'Publish'),
        el('button', { class: 'btn block', onclick: () => { m.close(); displayCode('screen', s.screen_id, s.screen_name); } }, icon('tv'), 'Display code (show on TV)'),
        el('button', { class: 'btn block', onclick: async () => { m.close(); const n = await promptBox('Rename screen', s.screen_name); if (n && await busy(() => api('screens/update.php', { screen_id: s.screen_id, screen_name: n }), 'Renamed')) load(); } }, icon('drive_file_rename_outline'), 'Rename'),
        el('button', { class: 'btn block', onclick: async () => { m.close(); if (await busy(() => api('screens/duplicate.php', { screen_id: s.screen_id }), 'Duplicated')) load(); } }, icon('content_copy'), 'Duplicate'),
        el('button', { class: 'btn block danger', onclick: async () => { m.close(); del(s); } }, icon('delete'), 'Delete screen')));
    };
    const c = el('div', { class: 'card tile hover' }, th,
      el('div', { class: 'row', style: { justifyContent: 'space-between', flexWrap: 'nowrap' } }, el('div', { class: 'name grow' }, s.screen_name), el('span', { class: 'badge ' + s.status }, s.has_unpublished_changes && s.status === 'published' ? 'changes' : s.status)),
      el('div', { class: 'small muted' }, s.orientation + ' · ' + timeAgo(s.updated_at)),
      el('div', { class: 'actions' },
        el('a', { class: 'btn sm', href: 'editor.php?screen_id=' + s.screen_id, title: 'Edit' }, icon('edit')),
        el('button', { class: 'btn sm', title: 'Preview', onclick: () => preview(s) }, icon('play_arrow')),
        el('button', { class: 'btn sm', title: 'Display code', onclick: () => displayCode('screen', s.screen_id, s.screen_name) }, icon('tv')),
        el('button', { class: 'btn sm danger', title: 'Delete', onclick: () => del(s) }, icon('delete')),
        el('button', { class: 'btn sm', title: 'More', onclick: more }, icon('more_horiz'))));
    thumb(th, s.layout, s.media);
    return c;
  }
  async function del(s) {
    if (!(await confirmBox('Delete “' + s.screen_name + '”?', 'It is removed from any playlists, and TVs showing it will stop. This cannot be undone.', 'Delete', true))) return;
    if (await busy(() => api('screens/delete.php', { screen_id: s.screen_id }), 'Screen deleted')) load();
  }
  function preview(s) {
    const ov = el('div', { class: 'preview-overlay' }); const host = el('div', { style: { position: 'absolute', inset: '0' } });
    const r = SignRender.mount(host, s.layout, { media: s.media, mode: 'preview' });
    const close = () => { r.destroy(); ov.remove(); };
    ov.append(host, el('button', { class: 'close', onclick: close }, icon('close')));
    document.body.append(ov);
    document.addEventListener('keydown', function k(e) { if (e.key === 'Escape') { close(); document.removeEventListener('keydown', k); } });
  }

  async function createScreen() {
    let cats = [], templates = [], cat = 0, chosen = null, orientation = 'landscape';
    const nameIn = el('input', { value: 'My screen ' + new Date().toLocaleDateString() });
    const chips = el('div', { class: 'row', style: { gap: '6px', margin: '10px 0' } });
    const grid2 = el('div', { class: 'grid', style: { maxHeight: '52vh', overflow: 'auto', padding: '4px' } }, el('div', { class: 'muted' }, 'Loading templates…'));
    const createBtn = el('button', { class: 'btn primary', onclick: go }, icon('add'), 'Create');
    const m = modal('New screen', el('div', {}, el('label', {}, 'Screen name', nameIn), el('div', { class: 'wgroup' }, 'Start from'), chips, grid2,
      el('div', { class: 'modal-foot' }, el('button', { class: 'btn', onclick: () => m.close() }, 'Cancel'), createBtn)), { wide: true });
    const blankCard = (o, icn, label) => el('div', { class: 'card hover tile' + (chosen === null && orientation === o ? ' selected' : ''), style: { cursor: 'pointer', position: 'relative' }, onclick: () => { chosen = null; orientation = o; draw(); } },
      el('div', { class: 'thumb', style: { display: 'flex', alignItems: 'center', justifyContent: 'center', background: 'var(--surface2)' } }, el('span', { class: 'ms', style: { fontSize: '44px', color: 'var(--teal)' } }, icn)),
      el('div', { class: 'name' }, 'Blank — ' + label), el('span', { class: 'check' }, icon('check')));
    function draw() {
      chips.replaceChildren(...[{ category_id: 0, category_name: 'All', template_count: templates.length }, ...cats].map((c) => el('button', { class: 'chip' + (cat === c.category_id ? ' on' : ''), onclick: () => { cat = c.category_id; draw(); } }, c.category_name + ' (' + (c.category_id ? c.template_count : templates.length) + ')')));
      grid2.replaceChildren();
      if (!cat) grid2.append(blankCard('landscape', 'crop_landscape', 'landscape'), blankCard('portrait', 'crop_portrait', 'portrait'), blankCard('square', 'crop_square', 'square'));
      for (const t of templates.filter((x) => !cat || x.category_id == cat)) {
        const th = el('div', { class: 'thumb' });
        grid2.append(el('div', { class: 'card hover tile' + (chosen && chosen.template_id === t.template_id ? ' selected' : ''), style: { cursor: t.is_locked ? 'not-allowed' : 'pointer', position: 'relative', opacity: t.is_locked ? '.6' : '1' },
          onclick: () => { if (t.is_locked) return toast('Premium template — not included in your plan.', true); chosen = t; draw(); } },
          th, t.is_premium ? el('span', { class: 'pro' }, 'PRO') : null, el('div', { class: 'name' }, t.template_name), el('div', { class: 'small muted' }, t.category_name + ' · ' + t.orientation), el('span', { class: 'check' }, icon('check'))));
        thumb(th, t.template_json, t.media);
      }
    }
    async function go() {
      const name = nameIn.value.trim(); if (!name) return toast('Enter a name', true);
      createBtn.disabled = true;
      const body = chosen ? { screen_name: name, template_id: chosen.template_id } : { screen_name: name, orientation };
      const s = await busy(() => api('screens/create.php', body));
      createBtn.disabled = false;
      if (s) location.href = 'editor.php?screen_id=' + s.screen_id;
    }
    try {
      [cats, templates] = await Promise.all([api('templates/categories.php'), api('templates/list.php')]);
      draw();
    } catch (e) { grid2.replaceChildren(el('div', { class: 'alert error' }, e.message)); }
  }
  document.getElementById('new').onclick = createScreen;
  load();
  if (new URLSearchParams(location.search).get('new')) createScreen();
})();
</script>
<?php web_page_end();
