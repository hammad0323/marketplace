<?php
require __DIR__ . '/_bootstrap.php';
require __DIR__ . '/_layout.php';
$u = web_require_login();
$pid = (int)($_GET['playlist_id'] ?? 0);
web_page_start($pid ? 'Edit playlist' : 'New playlist', 'playlists', $u);
?>
<div class="page-head">
  <div class="row"><a class="btn icon sm" href="playlists.php"><span class="ms">arrow_back</span></a><h1 id="title"><?= $pid ? 'Edit playlist' : 'New playlist' ?></h1><span id="status"></span></div>
  <div class="row">
    <button class="btn" id="preview"><span class="ms">play_arrow</span>Preview</button>
    <button class="btn" id="code" style="display:none"><span class="ms">tv</span>Display code</button>
    <button class="btn" id="save"><span class="ms">save</span>Save</button>
    <button class="btn primary" id="publish"><span class="ms">publish</span>Save &amp; publish</button>
  </div>
</div>
<div class="row" style="align-items:flex-start;gap:14px">
  <div class="card grow" style="min-width:300px">
    <div class="row" style="justify-content:space-between;margin-bottom:10px"><h2>Screens in this playlist</h2><button class="btn sm primary" id="add"><span class="ms">add</span>Add screens</button></div>
    <p class="muted small" style="margin-top:0">Drag to reorder. Durations are in seconds; leave empty to use the screen's default.</p>
    <div id="items"></div>
  </div>
  <div style="flex:0 1 360px;min-width:280px;display:grid;gap:14px">
    <div class="card"><h2 style="margin-bottom:12px">Settings</h2>
      <label>Name<input id="name" value="New playlist"></label>
      <label>Transition<select id="transition"></select></label>
      <label>Transition length (ms)<input id="tdur" type="number" min="0" max="5000" step="100" value="800"></label>
      <label>Default duration per screen (s)<input id="ddur" type="number" min="1" max="86400" value="10"></label>
      <label style="flex-direction:row;align-items:center;gap:8px;color:var(--text)"><input type="checkbox" id="loop" checked> Loop continuously</label>
      <label style="flex-direction:row;align-items:center;gap:8px;color:var(--text)"><input type="checkbox" id="shuffle"> Shuffle order</label>
    </div>
    <div class="card" id="schedCard" style="display:none"><div class="row" style="justify-content:space-between;margin-bottom:10px"><h2>Schedules</h2><button class="btn sm" id="addSched"><span class="ms">add</span>Add</button></div>
      <p class="muted small" style="margin-top:0">Without a schedule the playlist plays all the time on displays it is assigned to.</p><div id="scheds"></div></div>
  </div>
</div>
<script>
(async () => {
  const { api, el, icon, toast, modal, confirmBox, busy, timeAgo, thumb, displayCode, S } = App;
  const TRANS = ['none', 'fade', 'slide_left', 'slide_right', 'slide_up', 'slide_down', 'zoom', 'flip'];
  let pid = <?= $pid ?: 'null' ?>, items = [], screens = [], pl = null;
  const $ = (id) => document.getElementById(id);
  $('transition').append(...TRANS.map((t) => el('option', { value: t }, t.replace('_', ' '))));
  $('transition').value = 'fade';
  const screenMap = () => Object.fromEntries(screens.map((s) => [s.screen_id, s]));

  function drawItems() {
    const box = $('items'); box.replaceChildren();
    if (!items.length) { box.append(el('div', { class: 'empty' }, icon('add_to_queue'), el('p', {}, 'Add screens to start.'))); return; }
    const map = screenMap();
    items.forEach((it, i) => {
      const s = map[it.screen_id] || {};
      const th = el('div', { class: 'thumb', style: { width: '120px', flex: 'none' } });
      const dur = el('input', { type: 'number', min: 1, max: 86400, value: it.duration ?? '', placeholder: String(s.default_duration || 10), style: { width: '90px' } });
      dur.onchange = () => { it.duration = dur.value === '' ? null : parseInt(dur.value, 10); };
      const tr = el('select', { style: { width: '130px' } }, el('option', { value: 'inherit' }, 'Playlist default'), ...TRANS.map((t) => el('option', { value: t, selected: it.transition_type === t }, t.replace('_', ' '))));
      tr.onchange = () => (it.transition_type = tr.value);
      const en = el('input', { type: 'checkbox', checked: it.is_enabled !== false }); en.onchange = () => (it.is_enabled = en.checked);
      const row = el('div', { class: 'card', draggable: 'true', style: { padding: '10px', marginBottom: '8px', display: 'flex', gap: '12px', alignItems: 'center', flexWrap: 'wrap', opacity: it.is_enabled === false ? '.55' : '1' } },
        el('span', { class: 'ms', style: { cursor: 'grab', color: 'var(--muted)' } }, 'drag_indicator'), el('b', {}, i + 1), th,
        el('div', { class: 'grow', style: { minWidth: '140px' } }, el('div', { style: { fontWeight: '700' } }, s.screen_name || 'Screen #' + it.screen_id),
          el('div', { class: 'small muted' }, (s.status || '') + (s.status === 'draft' ? ' — publish it or it will be skipped' : ''))),
        el('label', { style: { margin: 0 } }, 'Seconds', dur), el('label', { style: { margin: 0 } }, 'Transition', tr),
        el('label', { style: { margin: 0, flexDirection: 'row', alignItems: 'center', gap: '6px' } }, en, 'On'),
        el('div', { class: 'row', style: { gap: '4px' } }, el('button', { class: 'btn icon sm', disabled: i === 0, onclick: () => { items.splice(i - 1, 0, items.splice(i, 1)[0]); drawItems(); } }, icon('arrow_upward')),
          el('button', { class: 'btn icon sm', disabled: i === items.length - 1, onclick: () => { items.splice(i + 1, 0, items.splice(i, 1)[0]); drawItems(); } }, icon('arrow_downward')),
          el('button', { class: 'btn icon sm danger', onclick: () => { items.splice(i, 1); drawItems(); } }, icon('delete'))));
      row.addEventListener('dragstart', (e) => { e.dataTransfer.setData('text/plain', String(i)); row.style.opacity = '.4'; });
      row.addEventListener('dragend', () => (row.style.opacity = ''));
      row.addEventListener('dragover', (e) => e.preventDefault());
      row.addEventListener('drop', (e) => { e.preventDefault(); const from = parseInt(e.dataTransfer.getData('text/plain'), 10); if (from === i || isNaN(from)) return; items.splice(i, 0, items.splice(from, 1)[0]); drawItems(); });
      box.append(row);
      if (s.layout) thumb(th, s.layout, s.media);
    });
  }
  $('add').onclick = () => {
    const chosen = new Set();
    const grid = el('div', { class: 'grid', style: { maxHeight: '60vh', overflow: 'auto', padding: '4px' } });
    if (!screens.length) grid.append(el('div', { class: 'empty' }, 'You have no screens yet. ', el('a', { href: 'screens.php?new=1' }, 'Create one')));
    for (const s of screens) {
      const th = el('div', { class: 'thumb' });
      const c = el('div', { class: 'card hover tile', style: { cursor: 'pointer', position: 'relative' }, onclick: () => { chosen.has(s.screen_id) ? chosen.delete(s.screen_id) : chosen.add(s.screen_id); c.classList.toggle('selected'); } },
        th, el('div', { class: 'name' }, s.screen_name), el('span', { class: 'badge ' + s.status }, s.status), el('span', { class: 'check' }, icon('check')));
      grid.append(c); thumb(th, s.layout, s.media);
    }
    modal('Add screens', grid, { wide: true, actions: [{ label: 'Cancel', onClick: (c) => c() }, { label: 'Add selected', cls: 'primary', onClick: (c) => { chosen.forEach((id) => items.push({ screen_id: id, duration: null, transition_type: 'inherit', is_enabled: true })); drawItems(); c(); } }] });
  };
  function body(publish) {
    return { playlist_id: pid || undefined, playlist_name: $('name').value.trim() || 'Playlist', transition_type: $('transition').value, transition_duration: parseInt($('tdur').value, 10) || 0,
      default_duration: parseInt($('ddur').value, 10) || 10, loop_enabled: $('loop').checked, shuffle: $('shuffle').checked,
      items: items.map((it) => ({ screen_id: it.screen_id, duration: it.duration, transition_type: it.transition_type || 'inherit', is_enabled: it.is_enabled !== false, valid_from: it.valid_from || null, valid_until: it.valid_until || null })) };
  }
  async function save(publish) {
    try {
      const res = await api(pid ? 'playlists/update.php' : 'playlists/create.php', body(publish));
      const isNew = !pid; pid = res.playlist_id;
      if (publish) {
        const pr = await api('playlists/publish.php', { playlist_id: pid });
        toast('Published v' + pr.version + (pr.skipped_screens.length ? ' — skipped unpublished: ' + pr.skipped_screens.join(', ') : ''));
      } else toast('Saved');
      if (isNew) history.replaceState(null, '', 'playlist.php?playlist_id=' + pid);
      await loadPlaylist();
    } catch (e) { toast(e.message, true); }
  }
  $('save').onclick = () => save(false);
  $('publish').onclick = () => save(true);
  $('code').onclick = () => displayCode('playlist', pid, $('name').value);
  $('preview').onclick = async () => {
    if (!pid) return toast('Save the playlist first', true);
    await api(pid ? 'playlists/update.php' : 'playlists/create.php', body(false)).catch(() => null);
    let doc; try { doc = await App.get('playlists/preview.php', { playlist_id: pid, draft: 1 }); } catch (e) { return toast(e.message, true); }
    if (!doc.items.length) return toast('Add screens first', true);
    const ov = el('div', { class: 'preview-overlay' }), host = el('div', { style: { position: 'absolute', inset: '0' } });
    const info = el('div', { style: { position: 'absolute', right: '14px', top: '14px', zIndex: 5, color: '#fff', background: 'rgba(0,0,0,.55)', padding: '8px 12px', borderRadius: '10px', fontSize: '13px' } });
    const p = SignPlayer.start(host, doc, { mode: 'preview', onItem: (it, i, n) => (info.textContent = `${i + 1} / ${n} · ${it.screen.screen_name || ''} · ${it.duration}s`) });
    const close = () => { p.destroy(); ov.remove(); };
    ov.append(host, info, el('button', { class: 'close', onclick: close }, icon('close')), el('button', { class: 'close', style: { left: '66px' }, title: 'Next', onclick: () => p.next() }, icon('skip_next')));
    document.body.append(ov);
  };

  const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
  function drawScheds() {
    const box = $('scheds'); box.replaceChildren();
    const list = (pl && pl.schedules) || [];
    if (!list.length) box.append(el('div', { class: 'muted small' }, 'No schedules — always on.'));
    for (const s of list) box.append(el('div', { class: 'item-card' }, el('div', { class: 'bar' }, el('span', { class: 'grow' }, s.schedule_name || s.schedule_type),
      el('span', { class: 'badge ' + s.status }, s.status), el('button', { class: 'btn icon sm danger', onclick: async () => { if (await busy(() => api('schedules/delete.php', { schedule_id: s.schedule_id }), 'Schedule removed')) loadPlaylist(); } }, icon('delete'))),
      el('div', { class: 'small muted' }, [s.start_date && ('from ' + s.start_date), s.end_date && ('until ' + s.end_date), s.start_time && (s.start_time.slice(0, 5) + '–' + (s.end_time || '').slice(0, 5)),
        Array.isArray(s.days_of_week_json) && s.days_of_week_json.length ? s.days_of_week_json.map((d) => DAYS[d - 1]).join(' ') : 'every day'].filter(Boolean).join(' · '))));
  }
  $('addSched').onclick = () => {
    const f = { name: el('input', { placeholder: 'e.g. Breakfast menu' }), type: el('select', {}, el('option', { value: 'recurring' }, 'Repeat weekly'), el('option', { value: 'one_time' }, 'One time'), el('option', { value: 'special_event' }, 'Special event (higher priority)')),
      sd: el('input', { type: 'date' }), ed: el('input', { type: 'date' }), st: el('input', { type: 'time', value: '08:00' }), et: el('input', { type: 'time', value: '11:00' }), pr: el('input', { type: 'number', value: 0, min: 0, max: 100 }) };
    const days = DAYS.map((d, i) => { const c = el('input', { type: 'checkbox', checked: true, value: i + 1 }); return el('label', { style: { flexDirection: 'row', alignItems: 'center', gap: '4px', margin: 0 } }, c, d); });
    modal('Add schedule', el('div', {}, el('label', {}, 'Name', f.name), el('label', {}, 'Type', f.type),
      el('div', { class: 'geom' }, el('label', {}, 'Start date', f.sd), el('label', {}, 'End date', f.ed), el('label', {}, 'From', f.st), el('label', {}, 'To', f.et)),
      el('div', { class: 'row', style: { gap: '10px', marginBottom: '12px' } }, ...days), el('label', {}, 'Priority (higher wins)', f.pr)), { actions: [{ label: 'Cancel', onClick: (c) => c() }, { label: 'Add schedule', cls: 'primary', onClick: async (c) => {
      const r = await busy(() => api('schedules/create.php', { playlist_id: pid, schedule_name: f.name.value || null, schedule_type: f.type.value, start_date: f.sd.value || null, end_date: f.ed.value || null,
        start_time: f.st.value || null, end_time: f.et.value || null, days_of_week: days.map((l) => l.firstChild).filter((x) => x.checked).map((x) => +x.value), priority: +f.pr.value || 0 }), 'Schedule added');
      if (r) { c(); loadPlaylist(); }
    } }] });
  };
  async function loadPlaylist() {
    if (!pid) return;
    pl = await App.get('playlists/details.php', { playlist_id: pid });
    $('title').textContent = pl.playlist_name; $('name').value = pl.playlist_name; $('transition').value = pl.transition_type; $('tdur').value = pl.transition_duration;
    $('ddur').value = pl.default_duration; $('loop').checked = pl.loop_enabled; $('shuffle').checked = pl.shuffle;
    $('status').replaceChildren(el('span', { class: 'badge ' + pl.status }, pl.has_unpublished_changes && pl.status === 'published' ? 'unpublished changes' : pl.status));
    items = pl.items.map((i) => ({ ...i })); $('code').style.display = ''; $('schedCard').style.display = '';
    drawItems(); drawScheds();
  }
  try {
    screens = await App.get('screens/list.php', { per_page: 100, with_layout: 1 });
    await loadPlaylist(); drawItems();
  } catch (e) { toast(e.message, true); }
})();
</script>
<?php web_page_end();
