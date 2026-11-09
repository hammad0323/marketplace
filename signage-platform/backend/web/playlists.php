<?php
require __DIR__ . '/_bootstrap.php';
require __DIR__ . '/_layout.php';
$u = web_require_login();
web_page_start('Playlists', 'playlists', $u);
?>
<div class="page-head"><div><h1>Playlists</h1><div class="muted">Rotate several complete screens on one TV — with durations, transitions and schedules.</div></div>
  <a class="btn primary" href="playlist.php"><span class="ms">add</span>New playlist</a></div>
<div class="grid" id="grid"></div>
<script>
(async () => {
  const { api, el, icon, toast, confirmBox, busy, timeAgo, displayCode } = App;
  const grid = document.getElementById('grid');
  const fmt = (s) => s >= 60 ? Math.floor(s / 60) + ' min ' + (s % 60 ? (s % 60) + ' s' : '') : s + ' s';
  async function load() {
    grid.innerHTML = '<div class="muted">Loading…</div>';
    try {
      const list = await App.get('playlists/list.php', { per_page: 100, with_items: 0 });
      grid.replaceChildren();
      if (!list.length) grid.append(el('div', { class: 'empty', style: { gridColumn: '1/-1' } }, icon('playlist_play'), el('h3', {}, 'No playlists yet'), el('p', {}, 'A playlist shows several screens one after another on the same display.'), el('a', { class: 'btn primary', href: 'playlist.php' }, icon('add'), 'Create playlist')));
      for (const p of list) grid.append(el('div', { class: 'card tile hover' },
        el('a', { href: 'playlist.php?playlist_id=' + p.playlist_id, style: { color: 'inherit' } },
          el('div', { class: 'row', style: { justifyContent: 'space-between', flexWrap: 'nowrap' } }, el('span', { class: 'ms', style: { background: 'var(--lteal)', color: 'var(--teal)', borderRadius: '12px', padding: '10px' } }, 'playlist_play'), el('span', { class: 'badge ' + p.status }, p.has_unpublished_changes && p.status === 'published' ? 'changes' : p.status)),
          el('div', { class: 'name', style: { marginTop: '10px', fontSize: '16px' } }, p.playlist_name),
          el('div', { class: 'small muted' }, `${p.item_count} screens · ${fmt(p.total_duration)} loop`),
          el('div', { class: 'small muted' }, `${p.device_count} displays · ${p.schedule_count} schedules · ${timeAgo(p.updated_at)}`)),
        el('div', { class: 'actions' },
          el('a', { class: 'btn sm', href: 'playlist.php?playlist_id=' + p.playlist_id, title: 'Edit' }, icon('edit')),
          el('button', { class: 'btn sm', title: 'Display code', onclick: () => displayCode('playlist', p.playlist_id, p.playlist_name) }, icon('tv')),
          el('button', { class: 'btn sm', title: 'Duplicate', onclick: async () => { if (await busy(() => api('playlists/duplicate.php', { playlist_id: p.playlist_id }), 'Duplicated')) load(); } }, icon('content_copy')),
          el('button', { class: 'btn sm danger', title: 'Delete', onclick: async () => { if (await confirmBox('Delete “' + p.playlist_name + '”?', 'Displays using it will stop playing it.', 'Delete', true) && await busy(() => api('playlists/delete.php', { playlist_id: p.playlist_id }), 'Deleted')) load(); } }, icon('delete')))));
    } catch (e) { grid.replaceChildren(el('div', { class: 'alert error' }, e.message)); }
  }
  load();
})();
</script>
<?php web_page_end();
