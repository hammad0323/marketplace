<?php
require __DIR__ . '/../_bootstrap.php';
require __DIR__ . '/../_layout.php';
$u = web_require_login(true);
$uid = (int)($_GET['user_id'] ?? 0);
web_page_start($uid ? 'Edit user' : 'New user', 'admin-users', $u);
?>
<div class="page-head"><div class="row"><a class="btn icon sm" href="users.php"><span class="ms">arrow_back</span></a><h1 id="title"><?= $uid ? 'Edit user' : 'New user' ?></h1></div>
  <button class="btn primary" id="save"><span class="ms">save</span><?= $uid ? 'Save changes' : 'Create user' ?></button></div>
<div class="row" style="align-items:flex-start;gap:14px">
  <div style="flex:1 1 420px;min-width:300px;display:grid;gap:14px">
    <div class="card"><h2 style="margin-bottom:12px">Account</h2>
      <div class="geom"><label>Full name<input id="full_name" required></label><label>Email<input id="email" type="email" required></label>
      <label>Phone<input id="phone"></label><label>Business name<input id="business_name" placeholder="e.g. Green Bowl Café"></label>
      <label>Role<select id="role"><option value="user">Customer</option><option value="super_admin">Super admin</option></select></label>
      <label>Status<select id="status"><option>active</option><option>inactive</option><option>suspended</option></select></label>
      <label>Time zone<input id="timezone" placeholder="e.g. Asia/Dubai"></label><label>Account expires<input id="account_expires_at" type="date"></label></div>
      <div id="pwBox"><label>Password (leave empty to generate a secure one)<input id="password" type="text" autocomplete="new-password"></label></div>
      <div id="resetBox" style="display:none"><button class="btn sm" id="reset"><span class="ms">key</span>Reset password</button></div>
    </div>
    <div class="card"><h2>Templates this customer can use</h2>
      <p class="muted small">Give a restaurant only restaurant & menu templates, a clinic only healthcare, and so on. Templates the customer saves themselves are always available.</p>
      <div class="row" style="gap:6px;margin-bottom:12px"><button class="chip" data-mode="all">All templates</button><button class="chip" data-mode="selected">Only selected</button></div>
      <div id="tplBox"><div class="wgroup">Whole categories</div><div class="row" id="cats" style="gap:6px"></div>
        <div class="wgroup">…or individual templates</div><div class="grid" id="tpls" style="grid-template-columns:repeat(auto-fill,minmax(150px,1fr));max-height:460px;overflow:auto;padding:4px"></div></div>
      <div class="small muted" id="tplSummary" style="margin-top:8px"></div>
    </div>
  </div>
  <div style="flex:0 1 400px;min-width:290px;display:grid;gap:14px">
    <div class="card"><h2 style="margin-bottom:12px">Plan &amp; limits</h2>
      <label>Plan<select id="plan_id"></select></label>
      <p class="muted small">Leave a limit empty to use the plan's value. −1 = unlimited.</p>
      <div class="geom"><label>Max screens<input id="max_screens" type="number" min="-1"></label><label>Max playlists<input id="max_playlists" type="number" min="-1"></label>
      <label>Max displays<input id="max_devices" type="number" min="-1"></label><label>Storage (MB)<input id="max_storage_mb" type="number" min="-1"></label>
      <label>Max upload (MB)<input id="max_upload_mb" type="number" min="-1"></label></div>
      <label>Video upload<select id="can_upload_video"><option value="">From plan</option><option value="1">Allowed</option><option value="0">Not allowed</option></select></label>
      <label>Premium templates<select id="can_use_premium_templates"><option value="">From plan</option><option value="1">Allowed</option><option value="0">Not allowed</option></select></label>
      <div class="small muted" id="effective"></div>
    </div>
    <div class="card"><h2 style="margin-bottom:8px">Widgets</h2>
      <label style="flex-direction:row;align-items:center;gap:8px;color:var(--text)"><input type="checkbox" id="widgetsInherit" checked> Use the plan's widget list</label>
      <div id="widgets" class="row" style="gap:6px"></div></div>
    <div class="card"><h2 style="margin-bottom:8px">Permissions</h2><p class="muted small" style="margin-top:0">Override what this user may do. “Role” follows the default for customers.</p><div id="perms" class="small"></div></div>
    <div class="card" id="usageCard" style="display:none"><h2 style="margin-bottom:8px">Usage</h2><div id="usage" class="small"></div></div>
  </div>
</div>
<script>
(async () => {
  const { api, el, icon, toast, modal, confirmBox, busy, lim, mb, bytes, thumb } = App;
  const UID = <?= $uid ?: 'null' ?>;
  const $ = (id) => document.getElementById(id);
  let access = { mode: 'all', category_ids: [], template_ids: [] }, allowedWidgets = null, permOverrides = {};
  const [plans, cats, tpls, types, perms] = await Promise.all([api('admin/plans.php'), api('templates/categories.php'), api('admin/templates.php'), api('admin/widget-types.php').catch(() => []), api('admin/permissions.php')]);
  $('plan_id').append(...plans.map((p) => el('option', { value: p.plan_id }, p.plan_name + (p.is_default == 1 ? ' (default)' : ''))));
  const def = plans.find((p) => p.is_default == 1); if (def) $('plan_id').value = def.plan_id;

  function drawAccess() {
    document.querySelectorAll('[data-mode]').forEach((b) => b.classList.toggle('on', b.dataset.mode === access.mode));
    $('tplBox').style.display = access.mode === 'selected' ? '' : 'none';
    const cs = new Set(access.category_ids.map(Number)), ts = new Set(access.template_ids.map(Number));
    $('cats').replaceChildren(...cats.map((c) => el('button', { class: 'chip' + (cs.has(+c.category_id) ? ' on' : ''), onclick: () => { cs.has(+c.category_id) ? cs.delete(+c.category_id) : cs.add(+c.category_id); access.category_ids = [...cs]; drawAccess(); } }, c.category_name + ' (' + c.template_count + ')')));
    const box = $('tpls');
    if (!box.childElementCount) for (const t of tpls) {
      const th = el('div', { class: 'thumb' });
      const card = el('div', { class: 'card tile', 'data-t': t.template_id, 'data-c': t.category_id, style: { cursor: 'pointer', position: 'relative', padding: '6px' }, onclick: () => { const id = +t.template_id; const s = new Set(access.template_ids.map(Number)); s.has(id) ? s.delete(id) : s.add(id); access.template_ids = [...s]; drawAccess(); } },
        th, el('div', { class: 'name small' }, t.template_name), el('div', { class: 'small muted' }, t.category_name), el('span', { class: 'check' }, icon('check')));
      box.append(card); thumb(th, t.template_json, t.media);
    }
    box.querySelectorAll('[data-t]').forEach((c) => { const byCat = cs.has(+c.dataset.c); c.classList.toggle('selected', ts.has(+c.dataset.t) || byCat); c.style.opacity = byCat ? '.7' : '1'; c.title = byCat ? 'Included through its category' : ''; });
    const n = tpls.filter((t) => cs.has(+t.category_id) || ts.has(+t.template_id)).length;
    $('tplSummary').textContent = access.mode === 'all' ? 'This customer sees every active template.' : `This customer sees ${n} of ${tpls.length} templates.`;
  }
  document.querySelectorAll('[data-mode]').forEach((b) => (b.onclick = () => { access.mode = b.dataset.mode; drawAccess(); }));

  function drawWidgets() {
    $('widgetsInherit').checked = allowedWidgets === null;
    $('widgets').style.display = allowedWidgets === null ? 'none' : '';
    const set = new Set(allowedWidgets || []);
    $('widgets').replaceChildren(...types.filter((t) => t.availability !== 'planned').map((t) => el('button', { class: 'chip' + (set.has(t.widget_key) || set.has('*') ? ' on' : ''), onclick: () => { if (set.has('*')) { set.clear(); types.forEach((x) => set.add(x.widget_key)); } set.has(t.widget_key) ? set.delete(t.widget_key) : set.add(t.widget_key); allowedWidgets = [...set]; drawWidgets(); } }, t.widget_name)));
  }
  $('widgetsInherit').onchange = (e) => { allowedWidgets = e.target.checked ? null : types.map((t) => t.widget_key); drawWidgets(); };

  function drawPerms() {
    let mod = '';
    $('perms').replaceChildren(...perms.flatMap((p) => {
      const out = [];
      if (p.module !== mod) { mod = p.module; out.push(el('div', { class: 'wgroup', style: { margin: '10px 0 4px' } }, mod)); }
      const v = permOverrides[p.permission_key];
      const sel = el('select', { style: { width: '120px', padding: '4px 6px' } }, el('option', { value: '' }, 'Role (' + (p.user_role_default == 1 ? 'yes' : 'no') + ')'), el('option', { value: '1', selected: v === true }, 'Allow'), el('option', { value: '0', selected: v === false }, 'Deny'));
      sel.onchange = () => { permOverrides[p.permission_key] = sel.value === '' ? null : sel.value === '1'; };
      out.push(el('div', { class: 'row', style: { padding: '3px 0', flexWrap: 'nowrap' } }, el('span', { class: 'grow' }, p.permission_name || p.permission_key), sel));
      return out;
    }));
  }

  if (UID) {
    const x = await App.get('admin/user-details.php', { user_id: UID });
    $('title').textContent = x.full_name;
    ['full_name', 'email', 'phone', 'business_name', 'timezone', 'status'].forEach((k) => ($(k).value = x[k] || ''));
    $('role').value = x.role_key || 'user';
    $('account_expires_at').value = (x.account_expires_at || '').slice(0, 10);
    if (x.plan) $('plan_id').value = x.plan.plan_id;
    const ov = x.limit_overrides || {};
    ['max_screens', 'max_playlists', 'max_devices', 'max_storage_mb', 'max_upload_mb'].forEach((k) => ($(k).value = ov[k] ?? ''));
    $('can_upload_video').value = ov.can_upload_video === null || ov.can_upload_video === undefined ? '' : String(+ov.can_upload_video);
    $('can_use_premium_templates').value = ov.can_use_premium_templates === null || ov.can_use_premium_templates === undefined ? '' : String(+ov.can_use_premium_templates);
    allowedWidgets = ov.allowed_widgets_json || null;
    access = x.template_access || access;
    (x.permission_overrides || []).forEach((p) => (permOverrides[p.permission_key] = !!+p.allowed));
    const L = x.limits, U = x.usage;
    $('effective').textContent = `In effect: ${lim(L.max_screens)} screens, ${lim(L.max_playlists)} playlists, ${lim(L.max_devices)} displays, ${mb(L.max_storage_mb)}.`;
    $('usageCard').style.display = '';
    $('usage').append(el('div', {}, `${U.screens_used} screens · ${U.playlists_used} playlists · ${U.devices_used} displays · ${bytes(U.storage_used_bytes)}`),
      ...(x.screens || []).slice(0, 8).map((s) => el('div', { class: 'muted' }, '• ' + s.screen_name + ' (' + s.status + ')')));
    $('pwBox').style.display = 'none'; $('resetBox').style.display = '';
    $('reset').onclick = async () => {
      if (!(await confirmBox('Reset password?', 'A new password is generated and the user is signed out everywhere.', 'Reset password', true))) return;
      const r = await busy(() => api('admin/update-user.php', { user_id: UID, reset_password: true }));
      if (r) showCredentials(r.credentials);
    };
  }
  drawAccess(); drawWidgets(); drawPerms();

  function showCredentials(c) {
    if (!c) return;
    const text = c.share_text || `Email: ${c.email}\nPassword: ${c.password}`;
    modal('Login details', el('div', {}, el('div', { class: 'alert warn' }, 'Copy these now — the password is shown only once.'), el('pre', { style: { background: 'var(--surface2)', padding: '12px', borderRadius: '12px', whiteSpace: 'pre-wrap' } }, text)),
      { actions: [{ label: 'Copy', cls: 'primary', onClick: () => { navigator.clipboard && navigator.clipboard.writeText(text); toast('Copied'); } }, { label: 'Done', onClick: (c2) => { c2(); if (!UID) location.href = 'users.php'; } }] });
  }
  $('save').onclick = async () => {
    if (access.mode === 'selected' && !access.category_ids.length && !access.template_ids.length) return toast('Select at least one template or category, or choose “All templates”.', true);
    const n = (k) => ($(k).value === '' ? null : parseInt($(k).value, 10));
    const b = (k) => ($(k).value === '' ? null : $(k).value === '1');
    const body = { full_name: $('full_name').value.trim(), email: $('email').value.trim(), phone: $('phone').value.trim() || null, business_name: $('business_name').value.trim() || null,
      role: $('role').value, status: $('status').value, timezone: $('timezone').value.trim() || null, account_expires_at: $('account_expires_at').value ? $('account_expires_at').value + ' 23:59:59' : null,
      plan_id: +$('plan_id').value, max_screens: n('max_screens'), max_playlists: n('max_playlists'), max_devices: n('max_devices'), max_storage_mb: n('max_storage_mb'), max_upload_mb: n('max_upload_mb'),
      can_upload_video: b('can_upload_video'), can_use_premium_templates: b('can_use_premium_templates'), allowed_widgets: allowedWidgets, permissions: permOverrides, template_access: access };
    if (!UID && $('password').value) body.password = $('password').value;
    if (UID) body.user_id = UID;
    const r = await busy(() => api(UID ? 'admin/update-user.php' : 'admin/create-user.php', body), UID ? 'Saved' : 'User created');
    if (r && !UID) showCredentials(r.credentials);
  };
})().catch((e) => App.toast(e.message, true));
</script>
<?php web_page_end();
