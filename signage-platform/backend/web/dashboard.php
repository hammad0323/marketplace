<?php
require __DIR__ . '/_bootstrap.php';
require __DIR__ . '/_layout.php';
$u = web_require_login();
web_page_start('Home', 'home', $u);
?>
<div class="page-head"><div><h1>Hi, <?= h(explode(' ', $u['full_name'])[0]) ?> 👋</h1><div class="muted">Here is what is happening with your displays.</div></div>
  <a class="btn primary" href="screens.php?new=1"><span class="ms">add</span>New screen</a></div>
<div class="stats" id="stats"></div>
<div class="row" style="align-items:flex-start;gap:14px">
  <div class="card grow" style="min-width:280px"><div class="row" style="justify-content:space-between;margin-bottom:12px"><h2>Recent screens</h2><a href="screens.php">View all</a></div><div class="grid" id="recent"></div></div>
  <div class="card" style="flex:0 1 360px;min-width:280px"><h2 style="margin-bottom:12px">Plan &amp; usage</h2><div id="usage"></div>
    <h2 style="margin:18px 0 10px">Recent activity</h2><div id="activity" class="small"></div></div>
</div>
<script>
(async () => {
  const { api, el, icon, timeAgo, bytes, mb, lim, thumb, toast } = App;
  try {
    const d = await api('users/dashboard.php');
    const s = d.stats;
    const stat = (i, label, v, sub) => el('div', { class: 'card stat' }, icon(i), el('b', {}, v), el('div', { class: 'muted small' }, label), sub ? el('div', { class: 'small', style: { color: 'var(--teal)' } }, sub) : null);
    document.getElementById('stats').append(
      stat('dashboard', 'Screens', s.total_screens, s.published_screens + ' published · ' + s.draft_screens + ' draft'),
      stat('playlist_play', 'Playlists', s.total_playlists, s.active_playlists + ' live'),
      stat('tv', 'Connected displays', s.total_devices, s.online_devices + ' online'),
      stat('cloud', 'Storage used', bytes(d.usage.storage_used_bytes), 'of ' + mb(d.limits.max_storage_mb)));
    const rec = document.getElementById('recent');
    if (!d.recent_screens.length) rec.append(el('div', { class: 'empty', style: { gridColumn: '1/-1' } }, icon('dashboard_customize'), el('p', {}, 'No screens yet.'), el('a', { class: 'btn primary', href: 'screens.php?new=1' }, 'Create your first screen')));
    for (const sc of d.recent_screens) {
      const th = el('div', { class: 'thumb' });
      rec.append(el('a', { class: 'card hover tile', href: 'editor.php?screen_id=' + sc.screen_id, style: { color: 'inherit' } }, th, el('div', { class: 'name' }, sc.screen_name),
        el('div', { class: 'row small muted', style: { justifyContent: 'space-between' } }, el('span', { class: 'badge ' + sc.status }, sc.status), timeAgo(sc.updated_at))));
      thumb(th, sc.layout, sc.media);
    }
    const L = d.limits, U = d.usage;
    const bar = (label, used, max) => { const pct = max < 0 ? 0 : Math.min(100, (used / Math.max(1, max)) * 100);
      return el('div', { style: { marginBottom: '12px' } }, el('div', { class: 'row small', style: { justifyContent: 'space-between' } }, el('span', {}, label), el('b', {}, used + ' / ' + lim(max))),
        el('div', { style: { height: '8px', background: 'var(--surface2)', borderRadius: '6px', marginTop: '5px', overflow: 'hidden' } }, el('div', { style: { width: pct + '%', height: '100%', background: pct > 90 ? 'var(--danger)' : 'var(--teal)' } }))); };
    document.getElementById('usage').append(el('div', { class: 'badge active', style: { marginBottom: '12px' } }, L.plan_name || 'Plan'),
      bar('Screens', U.screens_used, L.max_screens), bar('Playlists', U.playlists_used, L.max_playlists), bar('Displays', U.devices_used, L.max_devices),
      bar('Storage (MB)', Math.round(U.storage_used_bytes / 1048576), L.max_storage_mb));
    const act = document.getElementById('activity');
    if (!d.recent_activity.length) act.append(el('div', { class: 'muted' }, 'No activity yet.'));
    d.recent_activity.forEach((a) => act.append(el('div', { style: { padding: '7px 0', borderBottom: '1px solid var(--border)' } }, el('div', {}, a.description || a.action), el('div', { class: 'muted' }, timeAgo(a.created_at)))));
  } catch (e) { toast(e.message, true); }
})();
</script>
<?php web_page_end();
