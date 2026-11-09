import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:share_plus/share_plus.dart';

import '../../core/constants/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../../core/utils/json.dart';
import '../../core/widgets/common.dart';
import '../../models/misc.dart';
import '../../models/user.dart';
import '../../models/template.dart';
import '../../providers/providers.dart';
import '../widgets_library/layout_view.dart';
import '../widgets_library/render_context.dart';

final adminUsersProvider = FutureProvider.autoDispose.family<List<Map<String, dynamic>>, (String?, String?)>(
    (ref, a) async => (await ref.watch(adminRepoProvider).users(q: a.$1, status: a.$2)).items);
final adminUserProvider = FutureProvider.autoDispose.family<Map<String, dynamic>, int>((ref, id) => ref.watch(adminRepoProvider).userDetails(id));
final adminPlansProvider = FutureProvider.autoDispose<List<Plan>>((ref) => ref.watch(adminRepoProvider).plans());
final _allTemplatesProvider = FutureProvider.autoDispose((ref) => ref.watch(adminRepoProvider).templates());
final adminPermissionsProvider = FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) => ref.watch(adminRepoProvider).permissions());

class AdminUsersScreen extends ConsumerStatefulWidget {
  const AdminUsersScreen({super.key});
  @override
  ConsumerState<AdminUsersScreen> createState() => _AdminUsersScreenState();
}

class _AdminUsersScreenState extends ConsumerState<AdminUsersScreen> {
  String? q, status;
  @override
  Widget build(BuildContext context) {
    final users = ref.watch(adminUsersProvider((q, status)));
    return Scaffold(
      appBar: AppBar(title: const Text('Users')),
      floatingActionButton: FloatingActionButton.extended(onPressed: () => context.push('/admin/users/new'), icon: const Icon(Icons.person_add_alt_1_rounded), label: const Text('Create user')),
      body: Column(children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 4, 16, 8),
          child: TextField(
            decoration: const InputDecoration(hintText: 'Search name, email or business', prefixIcon: Icon(Icons.search), isDense: true),
            onSubmitted: (v) => setState(() => q = v.trim().isEmpty ? null : v.trim()),
          ),
        ),
        SizedBox(
          height: 42,
          child: ListView(scrollDirection: Axis.horizontal, padding: const EdgeInsets.symmetric(horizontal: 16), children: [
            for (final s in const [(null, 'All'), ('active', 'Active'), ('suspended', 'Suspended'), ('inactive', 'Inactive')])
              Padding(padding: const EdgeInsets.only(right: 8), child: FilterChip(showCheckmark: false, label: Text(s.$2), selected: status == s.$1, onSelected: (_) => setState(() => status = s.$1))),
          ]),
        ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: () async => ref.invalidate(adminUsersProvider),
            child: users.when(
              loading: () => const LoadingView(),
              error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(adminUsersProvider)),
              data: (list) => list.isEmpty
                  ? const EmptyState(icon: Icons.person_search_outlined, title: 'No users found')
                  : ListView.separated(
                      padding: const EdgeInsets.fromLTRB(16, 8, 16, 100),
                      itemCount: list.length,
                      separatorBuilder: (_, _) => const SizedBox(height: 10),
                      itemBuilder: (_, i) => _UserTile(u: list[i]),
                    ),
            ),
          ),
        ),
      ]),
    );
  }
}

class _UserTile extends StatelessWidget {
  const _UserTile({required this.u});
  final Map<String, dynamic> u;
  @override
  Widget build(BuildContext context) {
    final user = AppUser.fromJson(u);
    final l = UserLimits.fromJson(asMap(u['limits']));
    final usage = UserUsage.fromJson(asMap(u['usage']));
    return AppCard(
      onTap: () => context.push('/admin/users/${user.userId}'),
      child: Row(children: [
        CircleAvatar(backgroundColor: user.isAdmin ? const Color(0xFF5E35B1) : AppColors.teal, child: Text(user.initials, style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700))),
        const SizedBox(width: 12),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(user.fullName, style: const TextStyle(fontWeight: FontWeight.w700), maxLines: 1, overflow: TextOverflow.ellipsis),
            Text(user.email, style: const TextStyle(color: AppColors.mediumGrey, fontSize: 12.5), maxLines: 1, overflow: TextOverflow.ellipsis),
            const SizedBox(height: 4),
            Text('${l.planName ?? '—'} · screens ${usage.screensUsed}/${formatLimit(l.maxScreens)} · displays ${usage.devicesUsed}/${formatLimit(l.maxDevices)}', style: const TextStyle(fontSize: 12)),
          ]),
        ),
        StatusChip(user.isAdmin ? 'admin' : user.status, color: user.isAdmin ? const Color(0xFF5E35B1) : null),
      ]),
    );
  }
}

class AdminUserDetailScreen extends ConsumerWidget {
  const AdminUserDetailScreen({super.key, required this.userId});
  final int userId;
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final d = ref.watch(adminUserProvider(userId));
    final repo = ref.read(adminRepoProvider);
    void reload() { ref.invalidate(adminUserProvider(userId)); ref.invalidate(adminUsersProvider); }
    return Scaffold(
      appBar: AppBar(title: const Text('User'), actions: [
        IconButton(icon: const Icon(Icons.edit_outlined), onPressed: () => context.push('/admin/users/$userId/edit')),
      ]),
      body: d.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(error: e, onRetry: reload),
        data: (u) {
          final user = AppUser.fromJson(u);
          final l = UserLimits.fromJson(asMap(u['limits']));
          final usage = UserUsage.fromJson(asMap(u['usage']));
          final t = Theme.of(context).textTheme;
          return ListView(padding: const EdgeInsets.all(16), children: [
            AppCard(
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Row(children: [
                  CircleAvatar(radius: 26, backgroundColor: AppColors.teal, child: Text(user.initials, style: const TextStyle(color: Colors.white, fontSize: 18))),
                  const SizedBox(width: 12),
                  Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text(user.fullName, style: t.titleMedium),
                    Text(user.email, style: const TextStyle(color: AppColors.mediumGrey)),
                    if (user.phone != null) Text(user.phone!),
                  ])),
                  StatusChip(user.status),
                ]),
                const SizedBox(height: 10),
                Text('Created ${formatDateTime(user.createdAt)} · last login ${timeAgo(user.lastLoginAt)}${user.accountExpiresAt != null ? ' · expires ${formatDateTime(user.accountExpiresAt)}' : ''}',
                    style: t.bodySmall?.copyWith(color: AppColors.mediumGrey)),
                const SizedBox(height: 12),
                Wrap(spacing: 8, runSpacing: 8, children: [
                  if (user.status != 'active')
                    FilledButton.tonalIcon(icon: const Icon(Icons.check_circle_outline), label: const Text('Activate'), onPressed: () async {
                      final m = await runWithProgress(context, () => repo.setStatus(userId, 'active'));
                      if (m != null && context.mounted) showToast(context, m);
                      reload();
                    })
                  else
                    OutlinedButton.icon(icon: const Icon(Icons.block), label: const Text('Suspend'), onPressed: () async {
                      if (!await confirmDialog(context, title: 'Suspend ${user.fullName}?', message: 'They are signed out and cannot sign in. Their displays keep playing.', confirm: 'Suspend', destructive: true) || !context.mounted) return;
                      final m = await runWithProgress(context, () => repo.setStatus(userId, 'suspended'));
                      if (m != null && context.mounted) showToast(context, m);
                      reload();
                    }),
                  OutlinedButton.icon(icon: const Icon(Icons.lock_reset), label: const Text('Reset password'), onPressed: () async {
                    final r = await runWithProgress(context, () => repo.updateUser({'user_id': userId, 'reset_password': true}));
                    if (r != null && context.mounted) showCredentials(context, asMap(r['credentials']));
                  }),
                  OutlinedButton.icon(
                    style: OutlinedButton.styleFrom(foregroundColor: AppColors.danger),
                    icon: const Icon(Icons.delete_outline), label: const Text('Delete'),
                    onPressed: () async {
                      if (!await confirmDialog(context, title: 'Delete ${user.fullName}?', message: 'Their screens, playlists and media are removed and their displays disconnected.', confirm: 'Delete', destructive: true) || !context.mounted) return;
                      await runWithProgress(context, () => repo.deleteUser(userId), success: 'User deleted');
                      ref.invalidate(adminUsersProvider);
                      if (context.mounted) context.pop();
                    },
                  ),
                ]),
              ]),
            ),
            const SizedBox(height: 14),
            SectionHeader('Plan: ${l.planName ?? '—'}', action: 'Edit limits', onAction: () => context.push('/admin/users/$userId/edit')),
            AppCard(child: Column(children: [
              UsageBar(label: 'Screens', used: usage.screensUsed, limit: l.maxScreens),
              const SizedBox(height: 10),
              UsageBar(label: 'Playlists', used: usage.playlistsUsed, limit: l.maxPlaylists),
              const SizedBox(height: 10),
              UsageBar(label: 'Displays', used: usage.devicesUsed, limit: l.maxDevices),
              const SizedBox(height: 10),
              UsageBar(label: 'Storage', used: usage.storageUsedBytes / 1048576, limit: l.maxStorageMb, format: (n) => formatMb(n.round())),
            ])),
            const SizedBox(height: 14),
            SectionHeader('Screens (${asList(u['screens']).length})'),
            AppCard(padding: const EdgeInsets.symmetric(vertical: 4), child: Column(children: [
              if (asList(u['screens']).isEmpty) const Padding(padding: EdgeInsets.all(14), child: Text('No screens')),
              for (final s in asList(u['screens']).map(asMap))
                ListTile(dense: true, leading: Icon(orientationIcon(asString(s['orientation']))), title: Text(asString(s['screen_name'])), trailing: StatusChip(asString(s['status']))),
            ])),
            const SizedBox(height: 14),
            SectionHeader('Displays (${asList(u['devices']).length})'),
            AppCard(padding: const EdgeInsets.symmetric(vertical: 4), child: Column(children: [
              if (asList(u['devices']).isEmpty) const Padding(padding: EdgeInsets.all(14), child: Text('No displays')),
              for (final dv in asList(u['devices']).map(asMap))
                ListTile(dense: true, leading: const Icon(Icons.tv_rounded), title: Text(asString(dv['device_name'])), subtitle: Text('${dv['device_code']} · last seen ${timeAgo(asDate(dv['last_heartbeat']))}')),
            ])),
            const SizedBox(height: 14),
            const SectionHeader('Activity'),
            AppCard(padding: const EdgeInsets.symmetric(vertical: 4), child: Column(children: [
              for (final a in asList(u['activity']).map(asMap).take(15))
                ListTile(dense: true, title: Text(asString(a['description'], asString(a['action']))), subtitle: Text('${a['action']} · ${formatDateTime(asDate(a['created_at']))}')),
            ])),
          ]);
        },
      ),
    );
  }
}

/// Shows generated credentials once, with copy + share.
void showCredentials(BuildContext context, Map<String, dynamic> c) {
  if (c.isEmpty) return;
  final text = asString(c['share_text']);
  showDialog(
    context: context,
    builder: (d) => AlertDialog(
      title: const Text('Login details'),
      content: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
        const Text('Share these with the user now — the password is not shown again.'),
        const SizedBox(height: 12),
        SelectableText('Email: ${c['email']}\nPassword: ${c['password']}', style: const TextStyle(fontFamily: 'monospace', fontWeight: FontWeight.w600)),
      ]),
      actions: [
        TextButton.icon(onPressed: () { Clipboard.setData(ClipboardData(text: text)); showToast(context, 'Copied'); }, icon: const Icon(Icons.copy), label: const Text('Copy')),
        TextButton.icon(onPressed: () => SharePlus.instance.share(ShareParams(text: text, subject: 'Your signage account')), icon: const Icon(Icons.share), label: const Text('Share')),
        FilledButton(onPressed: () => Navigator.pop(d), child: const Text('Done')),
      ],
    ),
  );
}

/// Workflow D: create / edit a user with plan, limits and permissions.
class AdminUserFormScreen extends ConsumerStatefulWidget {
  const AdminUserFormScreen({super.key, this.userId});
  final int? userId;
  @override
  ConsumerState<AdminUserFormScreen> createState() => _AdminUserFormScreenState();
}

class _AdminUserFormScreenState extends ConsumerState<AdminUserFormScreen> {
  final name = TextEditingController(), email = TextEditingController(), phone = TextEditingController(), biz = TextEditingController(), password = TextEditingController();
  String role = 'user', status = 'active';
  int? planId;
  DateTime? expires;
  bool sendEmail = true, loading = true;
  // overrides: null = inherit plan
  final Map<String, int?> limits = {'max_screens': null, 'max_playlists': null, 'max_devices': null, 'max_storage_mb': null};
  bool? video, premium;
  final Map<String, bool?> perms = {};
  // Template access: 'all' or only the selected categories / templates.
  String templateMode = 'all';
  final Set<int> tplCategories = {};
  final Set<int> tplIds = {};

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (widget.userId != null) {
      final u = await ref.read(adminRepoProvider).userDetails(widget.userId!);
      name.text = asString(u['full_name']);
      email.text = asString(u['email']);
      phone.text = asString(u['phone']);
      biz.text = asString(u['business_name']);
      role = asString(u['role_key'], 'user');
      status = asString(u['status'], 'active');
      planId = asIntOrNull(asMap(u['plan'])['plan_id']);
      expires = asDate(u['account_expires_at']);
      final ov = asMap(u['limit_overrides']);
      for (final k in limits.keys) {
        limits[k] = ov[k] == null ? null : asInt(ov[k]);
      }
      video = ov['can_upload_video'] == null ? null : asBool(ov['can_upload_video']);
      premium = ov['can_use_premium_templates'] == null ? null : asBool(ov['can_use_premium_templates']);
      for (final p in asList(u['permission_overrides']).map(asMap)) {
        perms[asString(p['permission_key'])] = asBool(p['allowed']);
      }
      final ta = asMap(u['template_access']);
      templateMode = asString(ta['mode'], 'all');
      tplCategories.addAll(asList(ta['category_ids']).map((e) => asInt(e)));
      tplIds.addAll(asList(ta['template_ids']).map((e) => asInt(e)));
    }
    if (mounted) setState(() => loading = false);
  }

  @override
  Widget build(BuildContext context) {
    if (loading) return const Scaffold(body: LoadingView());
    final plans = ref.watch(adminPlansProvider).value ?? [];
    final allPerms = ref.watch(adminPermissionsProvider).value ?? [];
    final plan = plans.where((p) => p.planId == planId).firstOrNull ?? plans.where((p) => p.isDefault).firstOrNull;
    final t = Theme.of(context).textTheme;
    final editing = widget.userId != null;
    return Scaffold(
      appBar: AppBar(title: Text(editing ? 'Edit user' : 'Create user')),
      bottomNavigationBar: SafeArea(child: Padding(padding: const EdgeInsets.fromLTRB(16, 8, 16, 12), child: FilledButton(onPressed: _save, child: Text(editing ? 'Save changes' : 'Create user')))),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        Text('Details', style: t.titleMedium),
        const SizedBox(height: 10),
        TextField(controller: name, decoration: const InputDecoration(labelText: 'Full name *')),
        const SizedBox(height: 10),
        TextField(controller: email, keyboardType: TextInputType.emailAddress, decoration: const InputDecoration(labelText: 'Email *')),
        const SizedBox(height: 10),
        TextField(controller: phone, keyboardType: TextInputType.phone, decoration: const InputDecoration(labelText: 'Phone')),
        const SizedBox(height: 10),
        TextField(controller: biz, decoration: const InputDecoration(labelText: 'Business name')),
        if (!editing) ...[
          const SizedBox(height: 10),
          TextField(controller: password, decoration: const InputDecoration(labelText: 'Password', helperText: 'Leave empty to generate a secure password')),
          SwitchListTile(contentPadding: EdgeInsets.zero, title: const Text('Email the login details'), value: sendEmail, onChanged: (v) => setState(() => sendEmail = v)),
        ],
        const SizedBox(height: 10),
        Row(children: [
          Expanded(child: DropdownButtonFormField<String>(initialValue: role, decoration: const InputDecoration(labelText: 'Role'), items: const [DropdownMenuItem(value: 'user', child: Text('User')), DropdownMenuItem(value: 'super_admin', child: Text('Super admin'))], onChanged: (v) => setState(() => role = v!))),
          const SizedBox(width: 10),
          Expanded(child: DropdownButtonFormField<String>(initialValue: status, decoration: const InputDecoration(labelText: 'Status'), items: const [DropdownMenuItem(value: 'active', child: Text('Active')), DropdownMenuItem(value: 'inactive', child: Text('Inactive')), DropdownMenuItem(value: 'suspended', child: Text('Suspended'))], onChanged: (v) => setState(() => status = v!))),
        ]),
        ListTile(
          contentPadding: EdgeInsets.zero,
          title: const Text('Account expiry'),
          subtitle: Text(expires == null ? 'Never expires' : formatDateTime(expires)),
          trailing: Row(mainAxisSize: MainAxisSize.min, children: [
            if (expires != null) IconButton(icon: const Icon(Icons.close), onPressed: () => setState(() => expires = null)),
            const Icon(Icons.event_outlined),
          ]),
          onTap: () async {
            final d = await showDatePicker(context: context, firstDate: DateTime.now(), lastDate: DateTime(2100), initialDate: expires ?? DateTime.now().add(const Duration(days: 365)));
            if (d != null) setState(() => expires = DateTime(d.year, d.month, d.day, 23, 59, 59));
          },
        ),
        const Divider(height: 32),
        Text('Plan & screen limits', style: t.titleMedium),
        const SizedBox(height: 10),
        DropdownButtonFormField<int>(
          initialValue: planId,
          decoration: const InputDecoration(labelText: 'Plan'),
          items: [for (final p in plans) DropdownMenuItem(value: p.planId, child: Text('${p.planName} — ${formatLimit(p.maxScreens)} screens, ${formatLimit(p.maxDevices)} displays'))],
          onChanged: (v) => setState(() => planId = v),
        ),
        const SizedBox(height: 6),
        const Text('Overrides apply to this user only. "Plan" uses the plan value.', style: TextStyle(color: AppColors.mediumGrey, fontSize: 12)),
        for (final e in const [('max_screens', 'Maximum screens'), ('max_playlists', 'Maximum playlists'), ('max_devices', 'Maximum displays'), ('max_storage_mb', 'Storage (MB)')])
          _limitRow(e.$1, e.$2, plan == null ? null : switch (e.$1) { 'max_screens' => plan.maxScreens, 'max_playlists' => plan.maxPlaylists, 'max_devices' => plan.maxDevices, _ => plan.maxStorageMb }),
        _triState('Video uploads', video, asBool(plan?.features['video_upload']), (v) => setState(() => video = v)),
        _triState('Premium templates', premium, asBool(plan?.features['premium_templates']), (v) => setState(() => premium = v)),
        const Divider(height: 32),
        Text('Templates this user can use', style: t.titleMedium),
        const SizedBox(height: 6),
        SegmentedButton<String>(
          showSelectedIcon: false,
          segments: const [ButtonSegment(value: 'all', label: Text('All templates')), ButtonSegment(value: 'selected', label: Text('Only selected'))],
          selected: {templateMode},
          onSelectionChanged: (v) => setState(() => templateMode = v.first),
        ),
        if (templateMode == 'selected') ...[
          const SizedBox(height: 10),
          const Text('Whole categories (e.g. Restaurant for a restaurant customer):', style: TextStyle(color: AppColors.mediumGrey, fontSize: 12)),
          const SizedBox(height: 6),
          Wrap(spacing: 6, runSpacing: 6, children: [
            for (final c in ref.watch(templateCategoriesProvider).value ?? [])
              FilterChip(
                label: Text(c.categoryName),
                selected: tplCategories.contains(c.categoryId),
                onSelected: (v) => setState(() => v ? tplCategories.add(c.categoryId) : tplCategories.remove(c.categoryId)),
              ),
          ]),
          const SizedBox(height: 10),
          const Text('…and/or individual templates:', style: TextStyle(color: AppColors.mediumGrey, fontSize: 12)),
          const SizedBox(height: 6),
          SizedBox(
            height: 150,
            child: ListView(scrollDirection: Axis.horizontal, children: [
              for (final tj in ref.watch(_allTemplatesProvider).value ?? <Map<String, dynamic>>[])
                Builder(builder: (_) {
                  final tpl = ScreenTemplate.fromJson(tj);
                  final on = tplIds.contains(tpl.templateId) || tplCategories.contains(tpl.categoryId);
                  return GestureDetector(
                    onTap: tplCategories.contains(tpl.categoryId) ? null : () => setState(() => on ? tplIds.remove(tpl.templateId) : tplIds.add(tpl.templateId)),
                    child: Container(
                      width: 150,
                      margin: const EdgeInsets.only(right: 8),
                      padding: const EdgeInsets.all(4),
                      decoration: BoxDecoration(borderRadius: BorderRadius.circular(12), border: Border.all(color: on ? AppColors.teal : Colors.transparent, width: 3)),
                      child: Column(children: [
                        Expanded(child: LayoutThumbnail(layout: tpl.layout, media: NetworkMediaSource(tpl.media), radius: 8)),
                        Text(tpl.templateName, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w600)),
                      ]),
                    ),
                  );
                }),
            ]),
          ),
          Text('${tplCategories.length} categories · ${tplIds.length} individual templates selected', style: const TextStyle(color: AppColors.mediumGrey, fontSize: 12)),
        ],
        const Divider(height: 32),
        Text('Permissions', style: t.titleMedium),
        const Text('Defaults come from the role. Tap to grant ✓ / revoke ✕ / reset.', style: TextStyle(color: AppColors.mediumGrey, fontSize: 12)),
        const SizedBox(height: 6),
        for (final p in allPerms.where((p) => !asString(p['permission_key']).startsWith('admin.')))
          ListTile(
            dense: true,
            contentPadding: EdgeInsets.zero,
            title: Text(asString(p['permission_name'])),
            subtitle: Text(asString(p['permission_key']), style: const TextStyle(fontSize: 11)),
            trailing: _permChip(asString(p['permission_key']), asBool(p['user_role_default'])),
          ),
      ]),
    );
  }

  Widget _limitRow(String key, String label, int? planValue) {
    final v = limits[key];
    return Padding(
      padding: const EdgeInsets.only(top: 10),
      child: Row(children: [
        Expanded(child: Text(label)),
        ChoiceChip(label: Text('Plan (${planValue == null ? '—' : formatLimit(planValue)})'), selected: v == null, onSelected: (_) => setState(() => limits[key] = null)),
        const SizedBox(width: 6),
        ChoiceChip(label: const Text('∞'), selected: v == -1, onSelected: (_) => setState(() => limits[key] = -1)),
        const SizedBox(width: 6),
        SizedBox(
          width: 76,
          child: TextFormField(
            key: ValueKey('$key-${v == null || v < 0}'),
            initialValue: v != null && v >= 0 ? '$v' : '',
            keyboardType: TextInputType.number,
            decoration: const InputDecoration(hintText: 'N', isDense: true),
            onChanged: (s) => limits[key] = int.tryParse(s),
          ),
        ),
      ]),
    );
  }

  Widget _triState(String label, bool? v, bool planDefault, ValueChanged<bool?> set) => Padding(
        padding: const EdgeInsets.only(top: 10),
        child: Row(children: [
          Expanded(child: Text(label)),
          SegmentedButton<int>(
            showSelectedIcon: false,
            segments: [ButtonSegment(value: 0, label: Text('Plan (${planDefault ? 'yes' : 'no'})')), const ButtonSegment(value: 1, label: Text('Allow')), const ButtonSegment(value: 2, label: Text('Block'))],
            selected: {v == null ? 0 : (v ? 1 : 2)},
            onSelectionChanged: (s) => set(s.first == 0 ? null : s.first == 1),
          ),
        ]),
      );

  Widget _permChip(String key, bool roleDefault) {
    final o = perms[key];
    final effective = o ?? (role == 'super_admin' ? true : roleDefault);
    return ActionChip(
      avatar: Icon(effective ? Icons.check : Icons.close, size: 16, color: effective ? AppColors.success : AppColors.danger),
      label: Text(o == null ? 'Role default' : (o ? 'Granted' : 'Revoked')),
      onPressed: () => setState(() => perms[key] = o == null ? !roleDefault : (o == !roleDefault ? roleDefault : null)),
    );
  }

  Future<void> _save() async {
    final repo = ref.read(adminRepoProvider);
    final body = <String, dynamic>{
      'full_name': name.text.trim(), 'email': email.text.trim(), 'phone': phone.text.trim(), 'business_name': biz.text.trim(),
      'role': role, 'status': status, 'account_expires_at': expires?.toUtc().toIso8601String(), 'plan_id': planId,
      ...limits, 'can_upload_video': video, 'can_use_premium_templates': premium,
      'permissions': perms.map((k, v) => MapEntry(k, v)),
      'template_access': {'mode': templateMode, 'category_ids': tplCategories.toList(), 'template_ids': tplIds.toList()},
    };
    if (templateMode == 'selected' && tplCategories.isEmpty && tplIds.isEmpty) {
      showToast(context, 'Select at least one template category or template', error: true);
      return;
    }
    if (widget.userId == null) {
      final r = await runWithProgress(context, () => repo.createUser({...body, if (password.text.isNotEmpty) 'password': password.text, 'send_email': sendEmail}), success: 'User created');
      if (r == null || !mounted) return;
      ref.invalidate(adminUsersProvider);
      final id = asInt(asMap(r['user'])['user_id']);
      context.pushReplacement('/admin/users/$id');
      showCredentials(context, asMap(r['credentials']));
    } else {
      final r = await runWithProgress(context, () async {
        final u = await repo.updateUser({'user_id': widget.userId, ...body..remove('status')});
        await repo.setStatus(widget.userId!, status);
        return u;
      }, success: 'User updated');
      if (r == null || !mounted) return;
      ref.invalidate(adminUsersProvider);
      ref.invalidate(adminUserProvider(widget.userId!));
      context.pop();
    }
  }
}
