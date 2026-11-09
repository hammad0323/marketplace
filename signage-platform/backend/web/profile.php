<?php
require __DIR__ . '/_bootstrap.php';
require __DIR__ . '/_layout.php';
$u = web_require_login();
web_page_start('Profile', 'profile', $u);
?>
<div class="page-head"><h1>Profile</h1></div>
<div class="row" style="align-items:flex-start;gap:14px">
  <div class="card grow" style="min-width:280px"><h2 style="margin-bottom:12px">Your details</h2>
    <label>Full name<input id="name"></label><label>Phone<input id="phone"></label><label>Business name<input id="biz"></label>
    <label>Time zone<input id="tz" placeholder="e.g. Asia/Dubai"></label>
    <div class="muted small" id="email" style="margin-bottom:12px"></div>
    <button class="btn primary" id="saveP">Save details</button></div>
  <div style="flex:0 1 380px;min-width:280px;display:grid;gap:14px">
    <div class="card"><h2 style="margin-bottom:12px">Change password</h2>
      <label>Current password<input type="password" id="cur" autocomplete="current-password"></label>
      <label>New password (min. 8 characters)<input type="password" id="new" autocomplete="new-password"></label>
      <button class="btn" id="saveW">Change password</button></div>
    <div class="card"><h2 style="margin-bottom:12px">Plan</h2><div id="plan" class="small"></div></div>
    <div class="card"><h2 style="margin-bottom:12px">Signed-in devices</h2><div id="sessions" class="small"></div></div>
  </div>
</div>
<script>
(async () => {
  const { api, el, icon, busy, timeAgo, mb, lim, toast } = App;
  const $ = (id) => document.getElementById(id);
  try {
    const p = await api('auth/profile.php');
    const user = p.user || p;
    $('name').value = user.full_name || ''; $('phone').value = user.phone || ''; $('biz').value = user.business_name || ''; $('tz').value = user.timezone || '';
    $('email').textContent = 'Email: ' + user.email + ' (ask your administrator to change it)';
    const L = p.limits || {};
    $('plan').append(el('div', { class: 'badge active', style: { marginBottom: '8px' } }, L.plan_name || '—'),
      el('div', {}, `Screens: ${lim(L.max_screens)} · Playlists: ${lim(L.max_playlists)} · Displays: ${lim(L.max_devices)}`), el('div', {}, `Storage: ${mb(L.max_storage_mb)} · Video upload: ${L.can_upload_video ? 'yes' : 'no'} · Premium templates: ${L.can_use_premium_templates ? 'yes' : 'no'}`));
  } catch (e) { toast(e.message, true); }
  $('saveP').onclick = () => busy(() => api('auth/update-profile.php', { full_name: $('name').value.trim(), phone: $('phone').value.trim(), business_name: $('biz').value.trim(), timezone: $('tz').value.trim() || null }), 'Saved');
  $('saveW').onclick = async () => { if (await busy(() => api('auth/change-password.php', { current_password: $('cur').value, new_password: $('new').value }), 'Password changed')) { $('cur').value = ''; $('new').value = ''; } };
  async function sessions() {
    try {
      const list = await api('auth/sessions.php');
      $('sessions').replaceChildren(...(list.sessions || list).map((s) => el('div', { class: 'row', style: { padding: '6px 0', borderBottom: '1px solid var(--border)', flexWrap: 'nowrap' } },
        el('div', { class: 'grow' }, el('div', {}, (s.device_label || 'Session') + (s.is_current ? ' (this browser)' : '')), el('div', { class: 'muted' }, 'last used ' + timeAgo(s.last_used_at || s.created_at))),
        !s.is_current ? el('button', { class: 'btn sm danger', onclick: async () => { if (await busy(() => api('auth/sessions.php', { action: 'revoke', token_id: s.token_id }), 'Signed out')) sessions(); } }, 'Sign out') : null)));
    } catch (e) { $('sessions').textContent = e.message; }
  }
  sessions();
})();
</script>
<?php web_page_end();
