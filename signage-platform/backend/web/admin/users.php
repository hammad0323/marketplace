<?php
require __DIR__ . '/../_bootstrap.php';
require __DIR__ . '/../_layout.php';
$u = web_require_login(true);
web_page_start('Users', 'admin-users', $u);
?>
<div class="page-head"><div><h1>Users</h1><div class="muted">Create customer accounts, set limits and choose which templates each customer sees.</div></div><a class="btn primary" href="user.php"><span class="ms">person_add</span>New user</a></div>
<div class="row" style="margin-bottom:12px"><input id="q" placeholder="Search name, email, business…" style="max-width:320px"><select id="status" style="max-width:170px"><option value="">All statuses</option><option>active</option><option>inactive</option><option>suspended</option></select></div>
<div class="card" style="padding:0;overflow:auto"><table class="table" id="tbl"><thead><tr><th>User</th><th>Plan</th><th>Screens</th><th>Displays</th><th>Storage</th><th>Status</th><th></th></tr></thead><tbody></tbody></table></div>
<script>
(() => {
  const { api, el, icon, toast, confirmBox, busy, bytes, lim } = App;
  const tb = document.querySelector('#tbl tbody');
  async function load() {
    tb.innerHTML = '<tr><td colspan="7" class="muted">Loading…</td></tr>';
    try {
      const list = await App.get('admin/users.php', { q: document.getElementById('q').value.trim(), status: document.getElementById('status').value, per_page: 100 });
      tb.replaceChildren(...list.map((x) => el('tr', {},
        el('td', {}, el('a', { href: 'user.php?user_id=' + x.user_id, style: { fontWeight: '700' } }, x.full_name), el('div', { class: 'small muted' }, x.email + (x.business_name ? ' · ' + x.business_name : '')), x.role_key === 'super_admin' ? el('span', { class: 'badge active' }, 'admin') : null),
        el('td', {}, (x.plan || {}).plan_name || '—'), el('td', {}, x.usage.screens_used + ' / ' + lim(x.limits.max_screens)), el('td', {}, x.usage.devices_used + ' / ' + lim(x.limits.max_devices)),
        el('td', {}, bytes(x.usage.storage_used_bytes)), el('td', {}, el('span', { class: 'badge ' + x.status }, x.status)),
        el('td', { style: { whiteSpace: 'nowrap' } }, el('a', { class: 'btn icon sm', href: 'user.php?user_id=' + x.user_id, title: 'Edit' }, icon('edit')),
          el('button', { class: 'btn icon sm', title: x.status === 'active' ? 'Suspend' : 'Activate', onclick: async () => { if (await busy(() => api('admin/update-user-status.php', { user_id: x.user_id, status: x.status === 'active' ? 'suspended' : 'active' }), 'Status changed')) load(); } }, icon(x.status === 'active' ? 'block' : 'check_circle')),
          el('button', { class: 'btn icon sm danger', title: 'Delete', onclick: async () => { if (await confirmBox('Delete ' + x.full_name + '?', 'Signs them out everywhere and disconnects their displays.', 'Delete user', true) && await busy(() => api('admin/delete-user.php', { user_id: x.user_id }), 'User deleted')) load(); } }, icon('delete'))))));
      if (!list.length) tb.innerHTML = '<tr><td colspan="7" class="muted">No users found.</td></tr>';
    } catch (e) { toast(e.message, true); }
  }
  let t; document.getElementById('q').oninput = () => { clearTimeout(t); t = setTimeout(load, 300); };
  document.getElementById('status').onchange = load;
  load();
})();
</script>
<?php web_page_end();
