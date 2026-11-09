<?php
require __DIR__ . '/_bootstrap.php';
require __DIR__ . '/_layout.php';
$u = web_require_login();
$shared = !empty($_GET['shared']) && $u['role_key'] === ROLE_SUPER_ADMIN;
web_page_start($shared ? 'Shared media' : 'Media library', $shared ? 'admin-media' : 'media', $u);
?>
<div class="page-head"><div><h1><?= $shared ? 'Shared media' : 'Media library' ?></h1>
  <div class="muted" id="storage"><?= $shared ? 'Images here are available to every user — use them in templates (dish photos, backgrounds, logos).' : '&nbsp;' ?></div></div>
  <div class="row"><button class="btn" id="folderBtn" <?= $shared ? 'style="display:none"' : '' ?>><span class="ms">create_new_folder</span>New folder</button>
  <label class="btn primary" style="margin:0;color:#fff"><span class="ms">upload</span>Upload<input type="file" id="file" multiple accept="image/*,video/mp4,application/pdf" style="display:none"></label></div></div>
<div id="drop" class="card" style="border-style:dashed;text-align:center;padding:14px;margin-bottom:14px" class="muted"><span class="ms">cloud_upload</span> Drag &amp; drop files here — JPG, PNG, WebP, GIF, MP4, PDF</div>
<div id="progress"></div>
<div class="row" style="margin-bottom:12px">
  <input id="q" placeholder="Search files…" style="max-width:280px">
  <div class="row" id="types" style="gap:6px"></div>
</div>
<div class="row" id="folders" style="gap:6px;margin-bottom:12px"></div>
<div class="grid" id="grid" style="grid-template-columns:repeat(auto-fill,minmax(170px,1fr))"></div>
<script>
(() => {
  const { api, upload, el, icon, toast, modal, confirmBox, promptBox, busy, bytes, mb, timeAgo } = App;
  const SHARED = <?= $shared ? 'true' : 'false' ?>;
  let type = '', q = '', folder = null, folders = [];
  const $ = (id) => document.getElementById(id);
  [['', 'All'], ['image', 'Images'], ['video', 'Videos'], ['document', 'Documents']].forEach(([k, l]) => $('types').append(el('button', { class: 'chip' + (k === type ? ' on' : ''), 'data-k': k, onclick: () => { type = k; $('types').querySelectorAll('.chip').forEach((c) => c.classList.toggle('on', c.dataset.k === k)); load(); } }, l)));
  let t; $('q').addEventListener('input', (e) => { clearTimeout(t); t = setTimeout(() => { q = e.target.value.trim(); load(); }, 300); });

  async function load() {
    const grid = $('grid'); grid.innerHTML = '<div class="muted">Loading…</div>';
    try {
      const j = await App.get('media/list.php', { file_type: type, q, per_page: 100, include_global: SHARED ? 1 : 0, folder_id: SHARED ? null : folder }, { full: true });
      let list = j.data; folders = j.folders || [];
      if (SHARED) list = list.filter((m) => m.is_global);
      else $('storage').textContent = bytes(j.storage.used_bytes) + ' of ' + mb(j.storage.limit_mb) + ' used';
      drawFolders();
      grid.replaceChildren();
      if (!list.length) grid.append(el('div', { class: 'empty', style: { gridColumn: '1/-1' } }, icon('photo_library'), el('p', {}, 'No files here yet. Upload images or videos to use them on your screens.')));
      for (const m of list) {
        const preview = m.file_type === 'video'
          ? el('div', { class: 'sg-ph', style: { fontSize: '14px' } }, m.thumbnail_url ? el('img', { src: m.thumbnail_url, style: { position: 'absolute', inset: 0, width: '100%', height: '100%', objectFit: 'cover' } }) : null, el('span', { class: 'ms', style: { position: 'relative' } }, 'smart_display'))
          : m.thumbnail_url ? el('img', { src: m.thumbnail_url, loading: 'lazy', style: { width: '100%', height: '100%', objectFit: m.file_type === 'image' ? 'contain' : 'cover', background: 'repeating-conic-gradient(#ddd 0% 25%, #fff 0% 50%) 50%/16px 16px' } })
          : el('div', { class: 'sg-ph', style: { fontSize: '14px' } }, icon('description'));
        grid.append(el('div', { class: 'card tile' },
          el('div', { class: 'thumb', style: { aspectRatio: '1', cursor: 'pointer' }, onclick: () => details(m) }, preview),
          el('div', { class: 'name small', title: m.original_name }, (m.is_global && !SHARED ? '🌐 ' : '') + m.original_name),
          el('div', { class: 'small muted' }, bytes(m.file_size) + (m.width ? ` · ${m.width}×${m.height}` : '') + (m.usage_count ? ` · used ${m.usage_count}×` : '')),
          (m.user_id == App.S.user.user_id) ? el('div', { class: 'actions' },
            el('button', { class: 'btn sm', title: 'Rename', onclick: async () => { const n = await promptBox('Rename file', m.original_name); if (n && await busy(() => api('media/rename.php', { media_id: m.media_id, original_name: n }), 'Renamed')) load(); } }, icon('edit')),
            !SHARED ? el('button', { class: 'btn sm', title: 'Move to folder', onclick: () => move(m) }, icon('drive_file_move')) : null,
            el('button', { class: 'btn sm danger', title: 'Delete', onclick: () => del(m) }, icon('delete'))) : el('div', { class: 'small muted' }, 'Shared by admin')));
      }
    } catch (e) { grid.replaceChildren(el('div', { class: 'alert error' }, e.message)); }
  }
  function drawFolders() {
    if (SHARED) return;
    const box = $('folders');
    box.replaceChildren(el('button', { class: 'chip' + (folder === null ? ' on' : ''), onclick: () => { folder = null; load(); } }, icon('folder_open'), 'All files'),
      el('button', { class: 'chip' + (folder === 0 ? ' on' : ''), onclick: () => { folder = 0; load(); } }, 'Not in a folder'),
      ...folders.map((f) => el('span', { class: 'chip' + (folder === +f.folder_id ? ' on' : ''), onclick: (e) => { if (e.target.closest('button')) return; folder = +f.folder_id; load(); } }, icon('folder'), f.folder_name + ' (' + f.item_count + ')',
        el('button', { class: 'btn icon sm', style: { height: '22px', width: '22px', border: 0, background: 'none' }, title: 'Delete folder', onclick: async () => { if (await confirmBox('Delete folder “' + f.folder_name + '”?', 'Files inside are kept and moved out of the folder.', 'Delete folder', true) && await busy(() => api('media/delete-folder.php', { folder_id: f.folder_id }), 'Folder deleted')) { folder = null; load(); } } }, el('span', { class: 'ms', style: { fontSize: '16px' } }, 'close')))));
  }
  function details(m) {
    const body = el('div', {}, m.file_type === 'video' ? el('video', { src: m.url, controls: true, style: { width: '100%', borderRadius: '12px', background: '#000' } })
      : m.file_type === 'image' ? el('img', { src: m.url, style: { width: '100%', maxHeight: '60vh', objectFit: 'contain', borderRadius: '12px', background: 'repeating-conic-gradient(#ddd 0% 25%, #fff 0% 50%) 50%/16px 16px' } }) : el('a', { href: m.url, target: '_blank' }, 'Open file'),
      el('table', { class: 'table', style: { marginTop: '12px' } }, ...[['Name', m.original_name], ['Type', m.mime_type], ['Size', bytes(m.file_size)], ['Dimensions', m.width ? m.width + ' × ' + m.height : '—'], ['Uploaded', timeAgo(m.created_at)], ['Shared with all users', m.is_global ? 'Yes' : 'No']].map(([a, b]) => el('tr', {}, el('th', {}, a), el('td', {}, b)))));
    modal(m.original_name, body, { wide: true });
  }
  async function move(m) {
    const sel = el('select', {}, el('option', { value: 0 }, '— No folder —'), ...folders.map((f) => el('option', { value: f.folder_id, selected: f.folder_id == m.folder_id }, f.folder_name)));
    modal('Move to folder', el('label', {}, 'Folder', sel), { actions: [{ label: 'Cancel', onClick: (c) => c() }, { label: 'Move', cls: 'primary', onClick: async (c) => { if (await busy(() => api('media/move.php', { media_ids: [m.media_id], folder_id: +sel.value || null }), 'Moved')) { c(); load(); } } }] });
  }
  async function del(m) {
    if (!(await confirmBox('Delete “' + m.original_name + '”?', 'This cannot be undone.', 'Delete', true))) return;
    try { await api('media/delete.php', { media_id: m.media_id }); toast('Deleted'); load(); }
    catch (e) {
      if (e.code === 'IN_USE') { if (await confirmBox('File is in use', e.message + ' Delete anyway? Screens using it will show an empty space.', 'Delete anyway', true)) { await busy(() => api('media/delete.php', { media_id: m.media_id, force: true }), 'Deleted'); load(); } }
      else toast(e.message, true);
    }
  }
  async function uploadFiles(files) {
    const box = $('progress');
    for (const f of files) {
      const bar = el('div', { style: { height: '100%', width: '0%', background: 'var(--teal)', transition: 'width .2s' } });
      const row = el('div', { class: 'card', style: { padding: '10px', marginBottom: '8px' } }, el('div', { class: 'small' }, f.name), el('div', { style: { height: '6px', background: 'var(--surface2)', borderRadius: '4px', marginTop: '6px', overflow: 'hidden' } }, bar));
      box.append(row);
      try {
        await upload(f, { global: SHARED ? 1 : null, folder_id: !SHARED && folder ? folder : null }, (p) => (bar.style.width = Math.round(p * 100) + '%'));
        row.remove();
      } catch (e) { row.append(el('div', { class: 'small', style: { color: 'var(--danger)' } }, e.message)); setTimeout(() => row.remove(), 6000); }
    }
    load();
  }
  $('file').onchange = (e) => { uploadFiles([...e.target.files]); e.target.value = ''; };
  const drop = $('drop');
  ['dragover', 'dragenter'].forEach((ev) => drop.addEventListener(ev, (e) => { e.preventDefault(); drop.style.borderColor = 'var(--teal)'; }));
  ['dragleave', 'drop'].forEach((ev) => drop.addEventListener(ev, () => (drop.style.borderColor = '')));
  drop.addEventListener('drop', (e) => { e.preventDefault(); uploadFiles([...e.dataTransfer.files]); });
  $('folderBtn').onclick = async () => { const n = await promptBox('New folder', '', 'Folder name', 'Create'); if (n && await busy(() => api('media/create-folder.php', { folder_name: n }), 'Folder created')) load(); };
  load();
})();
</script>
<?php web_page_end();
