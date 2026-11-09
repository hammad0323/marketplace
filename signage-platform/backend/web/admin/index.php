<?php
require __DIR__ . '/../_bootstrap.php';
require __DIR__ . '/../_layout.php';
$u = web_require_login(true);
web_page_start('Admin', 'admin', $u);
?>
<div class="page-head"><div><h1>Admin dashboard</h1><div class="muted">Everything across all customers.</div></div>
  <div class="row"><a class="btn" href="templates.php"><span class="ms">auto_awesome_mosaic</span>Templates</a><a class="btn primary" href="user.php"><span class="ms">person_add</span>New user</a></div></div>
<div class="stats" id="stats"></div>
<div class="row" style="align-items:flex-start;gap:14px">
  <div class="card grow" style="min-width:280px"><h2 style="margin-bottom:10px">Recent activity</h2><div id="act" class="small"></div></div>
  <div style="flex:0 1 380px;min-width:280px;display:grid;gap:14px">
    <div class="card"><h2 style="margin-bottom:10px">Users per plan</h2><div id="plans"></div></div>
    <div class="card"><h2 style="margin-bottom:10px">Newest users</h2><div id="users"></div></div>
    <div class="card"><h2 style="margin-bottom:10px">Offline displays</h2><div id="offline" class="small"></div></div>
  </div>
</div>
<script>
(async () => {
  const { api, el, icon, timeAgo, bytes, toast } = App;
  try {
    const d = await api('admin/dashboard.php'); const s = d.stats;
    const stat = (i, l, v, sub) => el('div', { class: 'card stat' }, icon(i), el('b', {}, v), el('div', { class: 'muted small' }, l), sub ? el('div', { class: 'small', style: { color: 'var(--teal)' } }, sub) : null);
    document.getElementById('stats').append(stat('group', 'Customers', s.total_users, s.active_users + ' active'), stat('dashboard', 'Screens', s.total_screens, s.published_screens + ' published'),
      stat('playlist_play', 'Playlists', s.total_playlists), stat('tv', 'Displays', s.total_devices, s.connected_devices + ' online · ' + s.offline_devices + ' offline'), stat('cloud', 'Storage', bytes(s.storage_used_bytes), s.media_files + ' files'));
    document.getElementById('act').append(...d.recent_activity.map((a) => el('div', { style: { padding: '7px 0', borderBottom: '1px solid var(--border)' } }, el('b', {}, a.full_name || 'System'), ' — ', a.description || a.action, el('div', { class: 'muted' }, timeAgo(a.created_at)))));
    document.getElementById('plans').append(...d.plans.map((p) => el('div', { class: 'row', style: { justifyContent: 'space-between', padding: '5px 0' } }, el('span', {}, p.plan_name), el('b', {}, p.users))));
    document.getElementById('users').append(...d.recent_users.map((x) => el('a', { href: 'user.php?user_id=' + x.user_id, class: 'row', style: { padding: '6px 0', color: 'inherit', flexWrap: 'nowrap' } }, el('div', { class: 'grow' }, el('div', {}, x.full_name), el('div', { class: 'small muted' }, x.email)), el('span', { class: 'badge ' + x.status }, x.status))));
    const off = d.offline_device_list || [];
    document.getElementById('offline').append(...(off.length ? off.map((x) => el('div', { style: { padding: '5px 0' } }, el('b', {}, x.device_name || 'Display'), ' · ', x.owner_name, el('div', { class: 'muted' }, 'last seen ' + timeAgo(x.last_heartbeat)))) : [el('div', { class: 'muted' }, 'All displays are online.')]));
  } catch (e) { toast(e.message, true); }
})();
</script>
<?php web_page_end();
