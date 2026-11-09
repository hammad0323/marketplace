<?php
require __DIR__ . '/_bootstrap.php';
require __DIR__ . '/_layout.php';
$u = web_require_login();
web_page_start('Displays', 'displays', $u);
?>
<div class="page-head"><div><h1>Displays</h1><div class="muted">Two ways to put content on a TV.</div></div></div>
<div class="row" style="align-items:stretch;gap:14px;margin-bottom:18px">
  <div class="card grow" style="min-width:280px"><h2><span class="ms" style="color:var(--teal)">pin</span> Display codes — easiest</h2>
    <p class="muted">Every screen and playlist has a short code. On the TV open the app and tap <b>Show Display</b>, or open the web player in the TV's browser, and enter the code. No account needed on the TV, and it keeps playing offline once downloaded (app).</p>
    <div class="row"><a class="btn" href="<?= h(web_url('display.php')) ?>" target="_blank"><span class="ms">open_in_new</span>Open web player</a><span class="small muted" style="word-break:break-all"><?= h(web_url('display.php')) ?></span></div>
    <div id="codes" style="margin-top:14px"></div></div>
  <div class="card" style="flex:0 1 380px;min-width:280px"><h2><span class="ms" style="color:var(--teal)">link</span> Managed displays</h2>
    <p class="muted">For remote control, schedules and monitoring: open the app on the TV, choose <b>Pair this display</b> and enter the 6-character pairing code shown there.</p>
    <label>Pairing code<input id="pcode" maxlength="6" style="text-transform:uppercase;letter-spacing:4px;font-size:20px"></label>
    <label>Display name<input id="pname" placeholder="e.g. Lobby TV"></label>
    <button class="btn primary block" id="pair"><span class="ms">link</span>Pair display</button></div>
</div>
<div class="card"><div class="row" style="justify-content:space-between;margin-bottom:10px"><h2>Managed displays</h2><button class="btn sm" id="refresh"><span class="ms">refresh</span>Refresh</button></div><div id="devices"></div></div>
<script>
(() => {
  const { api, el, icon, toast, modal, confirmBox, busy, timeAgo, displayCode } = App;
  const $ = (id) => document.getElementById(id);
  let playlists = [];
  async function loadCodes() {
    const [screens, pls] = await Promise.all([App.get('screens/list.php', { per_page: 100, with_layout: 0 }), App.get('playlists/list.php', { per_page: 100, with_items: 0 })]);
    playlists = pls;
    const row = (kind, id, name, status, i) => el('div', { class: 'row', style: { padding: '8px 0', borderBottom: '1px solid var(--border)', flexWrap: 'nowrap' } }, icon(i), el('span', { class: 'grow name' }, name), el('span', { class: 'badge ' + status }, status),
      el('button', { class: 'btn sm', onclick: () => displayCode(kind, id, name) }, icon('tv'), 'Code'));
    $('codes').replaceChildren(...pls.map((p) => row('playlist', p.playlist_id, p.playlist_name, p.status, 'playlist_play')), ...screens.map((s) => row('screen', s.screen_id, s.screen_name, s.status, 'dashboard')));
    if (!pls.length && !screens.length) $('codes').append(el('div', { class: 'muted' }, 'Create a screen first.'));
  }
  async function loadDevices() {
    const box = $('devices'); box.innerHTML = '<div class="muted">Loading…</div>';
    try {
      const list = await App.get('devices/list.php', { per_page: 100 });
      box.replaceChildren();
      if (!list.length) box.append(el('div', { class: 'empty' }, icon('tv'), el('p', {}, 'No managed displays yet. Display codes work without pairing.')));
      for (const d of list) {
        const caps = (d.capabilities && d.capabilities.commands) || [];
        box.append(el('div', { class: 'card', style: { marginBottom: '10px', padding: '12px' } },
          el('div', { class: 'row', style: { flexWrap: 'nowrap' } }, el('span', { class: 'ms', style: { background: 'var(--lteal)', color: 'var(--teal)', borderRadius: '12px', padding: '10px' } }, 'tv'),
            el('div', { class: 'grow' }, el('div', { style: { fontWeight: '700' } }, d.device_name || d.device_code), el('div', { class: 'small muted' }, [d.manufacturer, d.model, d.resolution, 'last seen ' + timeAgo(d.last_heartbeat)].filter(Boolean).join(' · '))),
            el('span', { class: 'badge ' + (d.is_online ? 'online' : 'offline') }, d.is_online ? 'online' : 'offline')),
          el('div', { class: 'small', style: { margin: '8px 0' } }, 'Playing: ', el('b', {}, d.current_screen_name || d.current_playlist_name || '—'), ' · Playlists: ', d.assignments.length ? d.assignments.map((a) => a.playlist_name + (a.is_default ? ' (default)' : '')).join(', ') : 'none'),
          el('div', { class: 'row', style: { gap: '6px' } },
            el('button', { class: 'btn sm', onclick: () => assign(d) }, icon('playlist_add'), 'Assign playlists'),
            el('button', { class: 'btn sm', onclick: async () => { const n = prompt('Display name', d.device_name || ''); if (n && await busy(() => api('devices/update.php', { device_id: d.device_id, device_name: n }), 'Renamed')) loadDevices(); } }, icon('edit'), 'Rename'),
            ...['sync', 'identify', 'restart'].filter((c) => caps.includes(c)).map((c) => el('button', { class: 'btn sm', onclick: () => busy(() => api('devices/command.php', { device_id: d.device_id, command: c }), 'Command sent: ' + c) }, icon({ sync: 'sync', identify: 'visibility', restart: 'restart_alt' }[c]), c)),
            el('button', { class: 'btn sm danger', onclick: async () => { if (await confirmBox('Remove “' + (d.device_name || d.device_code) + '”?', 'The display stops showing your content and must be paired again.', 'Remove', true) && await busy(() => api('devices/unpair.php', { device_id: d.device_id, remove: true }), 'Display removed')) loadDevices(); } }, icon('link_off'), 'Remove'))));
      }
    } catch (e) { box.replaceChildren(el('div', { class: 'alert error' }, e.message)); }
  }
  function assign(d) {
    const cur = new Set(d.assignments.map((a) => a.playlist_id)); let def = (d.assignments.find((a) => a.is_default) || {}).playlist_id || null;
    const body = el('div', {}, playlists.length ? null : el('p', { class: 'muted' }, 'No playlists yet — create one first.'),
      ...playlists.map((p) => { const c = el('input', { type: 'checkbox', checked: cur.has(p.playlist_id) }); c.onchange = () => (c.checked ? cur.add(p.playlist_id) : cur.delete(p.playlist_id));
        const r = el('input', { type: 'radio', name: 'def', checked: def === p.playlist_id }); r.onchange = () => { def = p.playlist_id; c.checked = true; cur.add(p.playlist_id); };
        return el('div', { class: 'row', style: { padding: '6px 0', flexWrap: 'nowrap' } }, c, el('span', { class: 'grow' }, p.playlist_name), el('span', { class: 'badge ' + p.status }, p.status), el('label', { style: { flexDirection: 'row', margin: 0, gap: '4px', alignItems: 'center' } }, r, 'default')); }));
    modal('Playlists on ' + (d.device_name || d.device_code), body, { actions: [{ label: 'Cancel', onClick: (c) => c() }, { label: 'Save', cls: 'primary', onClick: async (c) => { if (await busy(() => api('devices/assign-playlist.php', { device_id: d.device_id, playlist_ids: [...cur], default_playlist_id: def && cur.has(def) ? def : null }), 'Saved')) { c(); loadDevices(); } } }] });
  }
  $('pair').onclick = async () => {
    const code = $('pcode').value.trim().toUpperCase(); if (!code) return toast('Enter the pairing code shown on the TV', true);
    if (await busy(() => api('devices/pair.php', { pairing_code: code, device_name: $('pname').value.trim() || undefined }), 'Display paired')) { $('pcode').value = ''; $('pname').value = ''; loadDevices(); }
  };
  $('refresh').onclick = loadDevices;
  loadCodes().catch((e) => toast(e.message, true)); loadDevices();
})();
</script>
<?php web_page_end();
