import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/constants/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../../core/utils/json.dart';
import '../../core/widgets/common.dart';
import '../../models/misc.dart';
import '../../models/screen.dart';
import '../../models/template.dart';
import '../../providers/providers.dart';
import '../devices/devices_screens.dart' show DeviceCard;
import '../widgets_library/layout_view.dart';
import '../widgets_library/render_context.dart';
import 'admin_users.dart';

final _adminDashProvider = FutureProvider.autoDispose((ref) => ref.watch(adminRepoProvider).dashboard());

class AdminDashboardScreen extends ConsumerWidget {
  const AdminDashboardScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final d = ref.watch(_adminDashProvider);
    final me = ref.watch(sessionProvider).value?.user;
    final t = Theme.of(context).textTheme;
    return Scaffold(
      body: SafeArea(
        child: RefreshIndicator(
          onRefresh: () async => ref.invalidate(_adminDashProvider),
          child: d.when(
            loading: () => const LoadingView(),
            error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(_adminDashProvider)),
            data: (data) {
              final s = asMap(data['stats']);
              final w = MediaQuery.of(context).size.width;
              final stat = [
                ('Total users', '${s['total_users']}', Icons.group_rounded, AppColors.teal, '/admin/users'),
                ('Active users', '${s['active_users']}', Icons.verified_user_outlined, AppColors.success, '/admin/users'),
                ('Inactive / suspended', '${s['inactive_users']}', Icons.person_off_outlined, AppColors.danger, '/admin/users'),
                ('Screens created', '${s['total_screens']}', Icons.dashboard_rounded, const Color(0xFF1E88E5), '/admin/content'),
                ('Published screens', '${s['published_screens']}', Icons.cloud_done_outlined, AppColors.success, '/admin/content'),
                ('Draft screens', '${s['draft_screens']}', Icons.edit_note_rounded, AppColors.warning, '/admin/content'),
                ('Playlists', '${s['total_playlists']}', Icons.playlist_play_rounded, const Color(0xFF5E35B1), '/admin/content'),
                ('Connected displays', '${s['connected_devices']}', Icons.tv_rounded, AppColors.success, '/admin/devices'),
                ('Offline displays', '${s['offline_devices']}', Icons.wifi_off_rounded, AppColors.danger, '/admin/devices'),
                ('Media storage', formatBytes(asInt(s['storage_used_bytes'])), Icons.cloud_outlined, const Color(0xFF1E88E5), '/admin/media'),
              ];
              return ListView(padding: const EdgeInsets.all(16), children: [
                Row(children: [
                  Expanded(child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Text('Admin dashboard', style: t.bodyMedium?.copyWith(color: AppColors.mediumGrey)),
                    Text('Hello, ${me?.firstName ?? ''}', style: t.headlineMedium),
                  ])),
                  IconButton.filledTonal(tooltip: 'Create user', onPressed: () => context.push('/admin/users/new'), icon: const Icon(Icons.person_add_alt_1_rounded)),
                ]),
                const SizedBox(height: 16),
                GridView(
                  gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(crossAxisCount: w > 900 ? 5 : (w > 600 ? 3 : 2), mainAxisSpacing: 12, crossAxisSpacing: 12, mainAxisExtent: 152),
                  shrinkWrap: true,
                  physics: const NeverScrollableScrollPhysics(),
                  children: [for (final x in stat) StatCard(label: x.$1, value: x.$2, icon: x.$3, color: x.$4, onTap: () => context.go(x.$5))],
                ),
                const SizedBox(height: 16),
                const SectionHeader('Users per plan'),
                AppCard(child: Column(children: [
                  for (final p in asList(data['plans']).map(asMap))
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 6),
                      child: Row(children: [
                        SizedBox(width: 110, child: Text(asString(p['plan_name']))),
                        Expanded(child: ClipRRect(borderRadius: BorderRadius.circular(6), child: LinearProgressIndicator(
                          minHeight: 10,
                          value: asInt(s['total_users']) == 0 ? 0 : asInt(p['users']) / (asInt(s['total_users']) + 1),
                        ))),
                        SizedBox(width: 36, child: Text('${p['users']}', textAlign: TextAlign.end, style: const TextStyle(fontWeight: FontWeight.w700))),
                      ]),
                    ),
                ])),
                const SizedBox(height: 16),
                SectionHeader('Offline displays', action: 'All devices', onAction: () => context.push('/admin/devices')),
                AppCard(padding: const EdgeInsets.symmetric(vertical: 4), child: Column(children: [
                  if (asList(data['offline_device_list']).isEmpty) const Padding(padding: EdgeInsets.all(14), child: Text('All displays are online 🎉')),
                  for (final dv in asList(data['offline_device_list']).map(asMap))
                    ListTile(dense: true, leading: const Icon(Icons.wifi_off_rounded, color: AppColors.danger), title: Text(asString(dv['device_name'])), subtitle: Text('${dv['owner_name']} · last seen ${timeAgo(asDate(dv['last_heartbeat']))}')),
                ])),
                const SizedBox(height: 16),
                SectionHeader('System activity', action: 'View all', onAction: () => context.push('/admin/activity')),
                AppCard(padding: const EdgeInsets.symmetric(vertical: 4), child: Column(children: [
                  for (final a in asList(data['recent_activity']).map(asMap).take(10))
                    ListTile(dense: true, title: Text(asString(a['description'], asString(a['action']))), subtitle: Text('${a['full_name'] ?? 'System'} · ${timeAgo(asDate(a['created_at']))}')),
                ])),
              ]);
            },
          ),
        ),
      ),
    );
  }
}

class AdminPlansScreen extends ConsumerWidget {
  const AdminPlansScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final plans = ref.watch(adminPlansProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('Plans')),
      floatingActionButton: FloatingActionButton.extended(heroTag: null, onPressed: () => _edit(context, ref, null), icon: const Icon(Icons.add), label: const Text('New plan')),
      body: plans.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(error: e),
        data: (list) => ListView(padding: const EdgeInsets.fromLTRB(16, 8, 16, 100), children: [
          for (final p in list)
            Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: AppCard(
                onTap: () => _edit(context, ref, p),
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Row(children: [
                    Text(p.planName, style: Theme.of(context).textTheme.titleMedium),
                    const SizedBox(width: 8),
                    if (p.isDefault) const StatusChip('default', color: AppColors.teal),
                    if (p.status != 'active') const StatusChip('inactive'),
                    const Spacer(),
                    Text(p.priceMonthly == 0 ? 'Free / custom' : '${p.currency} ${p.priceMonthly.toStringAsFixed(0)}/mo', style: const TextStyle(fontWeight: FontWeight.w700)),
                  ]),
                  if (p.description != null) Text(p.description!, style: const TextStyle(color: AppColors.mediumGrey)),
                  const SizedBox(height: 8),
                  Wrap(spacing: 6, runSpacing: 6, children: [
                    Chip(label: Text('${formatLimit(p.maxScreens)} screens')),
                    Chip(label: Text('${formatLimit(p.maxPlaylists)} playlists')),
                    Chip(label: Text('${formatLimit(p.maxDevices)} displays')),
                    Chip(label: Text(formatMb(p.maxStorageMb))),
                    if (asBool(p.features['video_upload'])) const Chip(label: Text('Video')),
                    if (asBool(p.features['premium_templates'])) const Chip(label: Text('Premium templates')),
                  ]),
                  Text('${p.userCount} user(s)', style: const TextStyle(color: AppColors.mediumGrey, fontSize: 12)),
                ]),
              ),
            ),
        ]),
      ),
    );
  }

  Future<void> _edit(BuildContext context, WidgetRef ref, Plan? p) async {
    final name = TextEditingController(text: p?.planName);
    final desc = TextEditingController(text: p?.description);
    final price = TextEditingController(text: p?.priceMonthly.toStringAsFixed(0) ?? '0');
    final vals = {'max_screens': p?.maxScreens ?? 3, 'max_playlists': p?.maxPlaylists ?? 3, 'max_devices': p?.maxDevices ?? 2, 'max_storage_mb': p?.maxStorageMb ?? 1024, 'max_upload_mb': p?.maxUploadMb ?? 50};
    final f = {'video_upload': asBool(p?.features['video_upload']), 'premium_templates': asBool(p?.features['premium_templates']), 'remote_commands': asBool(p?.features['remote_commands'], true), 'analytics': asBool(p?.features['analytics']), 'emergency': asBool(p?.features['emergency'], true)};
    var allWidgets = (p == null) || asList(p.features['allowed_widgets']).contains('*');
    var isDefault = p?.isDefault ?? false;
    var active = (p?.status ?? 'active') == 'active';
    final ok = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (c) => StatefulBuilder(
        builder: (c, set) => FractionallySizedBox(
          heightFactor: 0.9,
          child: ListView(padding: const EdgeInsets.fromLTRB(20, 0, 20, 20), children: [
            Row(children: [
              Expanded(child: Text(p == null ? 'New plan' : 'Edit ${p.planName}', style: Theme.of(c).textTheme.titleLarge)),
              if (p != null)
                IconButton(icon: const Icon(Icons.delete_outline, color: AppColors.danger), onPressed: () async {
                  try {
                    await ref.read(adminRepoProvider).deletePlan(p.planId);
                    if (c.mounted) Navigator.pop(c, true);
                  } catch (e) {
                    if (c.mounted) showToast(c, e.toString(), error: true);
                  }
                }),
              FilledButton(onPressed: () => Navigator.pop(c, true), child: const Text('Save')),
            ]),
            TextField(controller: name, decoration: const InputDecoration(labelText: 'Plan name')),
            const SizedBox(height: 10),
            TextField(controller: desc, decoration: const InputDecoration(labelText: 'Description')),
            const SizedBox(height: 10),
            TextField(controller: price, keyboardType: TextInputType.number, decoration: const InputDecoration(labelText: 'Monthly price (USD)')),
            const SizedBox(height: 6),
            const Text('Limits (-1 = unlimited)', style: TextStyle(color: AppColors.mediumGrey)),
            for (final k in vals.keys)
              Padding(
                padding: const EdgeInsets.only(top: 8),
                child: TextFormField(initialValue: '${vals[k]}', keyboardType: TextInputType.number, decoration: InputDecoration(labelText: k.replaceAll('_', ' ')), onChanged: (v) => vals[k] = int.tryParse(v) ?? vals[k]!),
              ),
            const SizedBox(height: 8),
            for (final k in f.keys) SwitchListTile(contentPadding: EdgeInsets.zero, title: Text(k.replaceAll('_', ' ')), value: f[k]!, onChanged: (v) => set(() => f[k] = v)),
            SwitchListTile(contentPadding: EdgeInsets.zero, title: const Text('All widgets available'), subtitle: const Text('Off = basic widgets only'), value: allWidgets, onChanged: (v) => set(() => allWidgets = v)),
            SwitchListTile(contentPadding: EdgeInsets.zero, title: const Text('Default plan for new users'), value: isDefault, onChanged: (v) => set(() => isDefault = v)),
            SwitchListTile(contentPadding: EdgeInsets.zero, title: const Text('Active'), value: active, onChanged: (v) => set(() => active = v)),
          ]),
        ),
      ),
    );
    if (ok != true || !context.mounted) return;
    await runWithProgress(context, () => ref.read(adminRepoProvider).savePlan({
          if (p != null) 'plan_id': p.planId, 'plan_name': name.text, 'description': desc.text, 'price_monthly': double.tryParse(price.text) ?? 0, ...vals,
          'features': {...f, 'allowed_widgets': allWidgets ? ['*'] : ['image', 'image_carousel', 'heading', 'text', 'ticker', 'clock', 'qr_code', 'shape', 'logo', 'background_image', 'contact_info', 'opening_hours', 'announcement']},
          'is_default': isDefault, 'status': active ? 'active' : 'inactive',
        }), success: 'Plan saved');
    ref.invalidate(adminPlansProvider);
  }
}

final _adminTemplatesProvider = FutureProvider.autoDispose((ref) => ref.watch(adminRepoProvider).templates());
final _adminAllScreensProvider = FutureProvider.autoDispose<List<ScreenModel>>((ref) async {
  final r = await ref.watch(apiClientProvider).get('screens/list.php', query: {'all': true, 'per_page': 100, 'with_layout': true});
  return asList(r.data).map((e) => ScreenModel.fromJson(asMap(e))).toList();
});
final _adminAllPlaylistsProvider = FutureProvider.autoDispose<List<Map<String, dynamic>>>((ref) async {
  final r = await ref.watch(apiClientProvider).get('playlists/list.php', query: {'all': true, 'per_page': 100, 'with_items': false});
  return asList(r.data).map(asMap).toList();
});

class AdminContentScreen extends ConsumerWidget {
  const AdminContentScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return DefaultTabController(
      length: 3,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Content'),
          actions: [TextButton.icon(onPressed: () => context.push('/admin/widgets'), icon: const Icon(Icons.widgets_outlined), label: const Text('Widgets'))],
          bottom: const TabBar(tabs: [Tab(text: 'Templates'), Tab(text: 'All screens'), Tab(text: 'All playlists')]),
        ),
        body: TabBarView(children: [
          ref.watch(_adminTemplatesProvider).when(
                loading: () => const LoadingView(),
                error: (e, _) => ErrorView(error: e),
                data: (list) => GridView.builder(
                  padding: const EdgeInsets.all(12),
                  gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(maxCrossAxisExtent: 240, mainAxisExtent: 210, mainAxisSpacing: 10, crossAxisSpacing: 10),
                  itemCount: list.length,
                  itemBuilder: (_, i) {
                    final t = ScreenTemplate.fromJson(list[i]);
                    final active = asString(list[i]['status']) == 'active';
                    return AppCard(
                      padding: const EdgeInsets.all(8),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Expanded(child: Opacity(opacity: active ? 1 : 0.4, child: LayoutThumbnail(layout: t.layout, media: NetworkMediaSource(t.media)))),
                        const SizedBox(height: 6),
                        Text(t.templateName, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700)),
                        Row(children: [
                          Expanded(child: Text('${list[i]['category_name']} · used ${list[i]['usage_count']}×', style: const TextStyle(fontSize: 11, color: AppColors.mediumGrey), maxLines: 1)),
                          Switch(value: active, onChanged: (_) async { await ref.read(adminRepoProvider).templateAction('toggle', t.templateId); ref.invalidate(_adminTemplatesProvider); }),
                        ]),
                      ]),
                    );
                  },
                ),
              ),
          ref.watch(_adminAllScreensProvider).when(
                loading: () => const LoadingView(),
                error: (e, _) => ErrorView(error: e),
                data: (list) => ListView(padding: const EdgeInsets.all(12), children: [
                  for (final s in list)
                    Card(
                      margin: const EdgeInsets.only(bottom: 8),
                      child: ListTile(
                        leading: SizedBox(width: 72, child: s.layout == null ? null : LayoutThumbnail(layout: s.layout!, media: NetworkMediaSource(s.media), radius: 6)),
                        title: Text(s.screenName),
                        subtitle: Text('${s.ownerName ?? ''} · ${orientationLabel(s.orientation)} · ${timeAgo(s.updatedAt)}'),
                        trailing: StatusChip(s.status),
                      ),
                    ),
                ]),
              ),
          ref.watch(_adminAllPlaylistsProvider).when(
                loading: () => const LoadingView(),
                error: (e, _) => ErrorView(error: e),
                data: (list) => ListView(padding: const EdgeInsets.all(12), children: [
                  for (final p in list)
                    Card(
                      margin: const EdgeInsets.only(bottom: 8),
                      child: ListTile(
                        leading: const Icon(Icons.view_carousel_rounded, color: AppColors.teal),
                        title: Text(asString(p['playlist_name'])),
                        subtitle: Text('User #${p['user_id']} · ${p['item_count']} screens · ${p['device_count']} display(s)'),
                        trailing: StatusChip(asString(p['status'])),
                      ),
                    ),
                ]),
              ),
        ]),
      ),
    );
  }
}

class AdminMoreScreen extends ConsumerWidget {
  const AdminMoreScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final mode = ref.watch(themeModeProvider);
    Widget tile(IconData i, String t, String sub, VoidCallback f, {Color? c}) => ListTile(leading: Icon(i, color: c), title: Text(t, style: TextStyle(color: c)), subtitle: Text(sub), trailing: const Icon(Icons.chevron_right), onTap: f);
    return Scaffold(
      appBar: AppBar(title: const Text('More')),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        AppCard(padding: const EdgeInsets.symmetric(vertical: 4), child: Column(children: [
          tile(Icons.tv_rounded, 'Display devices', 'Monitor every connected display', () => context.push('/admin/devices')),
          tile(Icons.history_rounded, 'System activity', 'Audit log of all actions', () => context.push('/admin/activity')),
          tile(Icons.widgets_outlined, 'Widget types', 'Availability and premium flags', () => context.push('/admin/widgets')),
          tile(Icons.perm_media_outlined, 'Media', 'All uploaded files · shared library', () => context.push('/admin/media')),
          tile(Icons.settings_outlined, 'Application settings', 'Branding, limits, timings, providers', () => context.push('/admin/settings')),
        ])),
        const SizedBox(height: 14),
        AppCard(padding: const EdgeInsets.symmetric(vertical: 4), child: Column(children: [
          tile(Icons.dashboard_customize_outlined, 'My workspace', 'Create screens and playlists as a user', () => context.go('/home')),
          ListTile(
            leading: const Icon(Icons.dark_mode_outlined),
            title: const Text('Dark mode'),
            trailing: Switch(value: mode == ThemeMode.dark, onChanged: (v) => ref.read(themeModeProvider.notifier).set(v ? ThemeMode.dark : ThemeMode.light)),
          ),
          tile(Icons.logout_rounded, 'Sign out', '', () => ref.read(sessionProvider.notifier).logout(), c: AppColors.danger),
        ])),
      ]),
    );
  }
}

final _adminDevicesProvider = FutureProvider.autoDispose((ref) => ref.watch(adminRepoProvider).devices());

class AdminDevicesScreen extends ConsumerWidget {
  const AdminDevicesScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) => Scaffold(
        appBar: AppBar(title: const Text('All displays')),
        body: RefreshIndicator(
          onRefresh: () async => ref.invalidate(_adminDevicesProvider),
          child: ref.watch(_adminDevicesProvider).when(
                loading: () => const LoadingView(),
                error: (e, _) => ErrorView(error: e),
                data: (p) => p.items.isEmpty
                    ? const EmptyState(icon: Icons.tv_off_rounded, title: 'No paired displays')
                    : ListView(padding: const EdgeInsets.all(16), children: [for (final d in p.items) Padding(padding: const EdgeInsets.only(bottom: 10), child: DeviceCard(d: d, admin: true))]),
              ),
        ),
      );
}

final _activityProvider = FutureProvider.autoDispose.family<List<ActivityEntry>, String?>((ref, a) async => (await ref.watch(adminRepoProvider).activity(action: a)).items);

class AdminActivityScreen extends ConsumerStatefulWidget {
  const AdminActivityScreen({super.key});
  @override
  ConsumerState<AdminActivityScreen> createState() => _AdminActivityScreenState();
}

class _AdminActivityScreenState extends ConsumerState<AdminActivityScreen> {
  String? filter;
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('System activity')),
        body: Column(children: [
          SizedBox(
            height: 46,
            child: ListView(scrollDirection: Axis.horizontal, padding: const EdgeInsets.symmetric(horizontal: 16), children: [
              for (final f in const [(null, 'All'), ('admin.', 'Admin'), ('auth.', 'Sign-ins'), ('screen.', 'Screens'), ('playlist.', 'Playlists'), ('device.', 'Devices'), ('media.', 'Media')])
                Padding(padding: const EdgeInsets.only(right: 8), child: FilterChip(showCheckmark: false, label: Text(f.$2), selected: filter == f.$1, onSelected: (_) => setState(() => filter = f.$1))),
            ]),
          ),
          Expanded(
            child: ref.watch(_activityProvider(filter)).when(
                  loading: () => const LoadingView(),
                  error: (e, _) => ErrorView(error: e),
                  data: (list) => ListView.separated(
                    itemCount: list.length,
                    separatorBuilder: (_, _) => const Divider(height: 1),
                    itemBuilder: (_, i) => ListTile(
                      title: Text(list[i].description ?? list[i].action),
                      subtitle: Text('${list[i].userName ?? 'System'} · ${list[i].action} · ${formatDateTime(list[i].createdAt)}'),
                    ),
                  ),
                ),
          ),
        ]),
      );
}

final _settingsProvider = FutureProvider.autoDispose((ref) => ref.watch(adminRepoProvider).settings());

class AdminSettingsScreen extends ConsumerStatefulWidget {
  const AdminSettingsScreen({super.key});
  @override
  ConsumerState<AdminSettingsScreen> createState() => _AdminSettingsScreenState();
}

class _AdminSettingsScreenState extends ConsumerState<AdminSettingsScreen> {
  final changes = <String, dynamic>{};
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Application settings'), actions: [
          TextButton(
            onPressed: changes.isEmpty ? null : () async {
              await runWithProgress(context, () => ref.read(adminRepoProvider).saveSettings(changes), success: 'Settings saved');
              changes.clear();
              ref.invalidate(_settingsProvider);
            },
            child: const Text('Save'),
          ),
        ]),
        body: ref.watch(_settingsProvider).when(
              loading: () => const LoadingView(),
              error: (e, _) => ErrorView(error: e),
              data: (list) => ListView(padding: const EdgeInsets.all(16), children: [
                for (final s in list)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: asString(s['value_type']) == 'bool'
                        ? SwitchListTile(
                            contentPadding: EdgeInsets.zero,
                            title: Text(asString(s['setting_key'])),
                            subtitle: Text(asString(s['description'])),
                            value: asBool(changes[s['setting_key']] ?? s['setting_value']),
                            onChanged: (v) => setState(() => changes[asString(s['setting_key'])] = v),
                          )
                        : TextFormField(
                            initialValue: asString(s['setting_value']),
                            keyboardType: asString(s['value_type']) == 'int' ? TextInputType.number : TextInputType.text,
                            decoration: InputDecoration(labelText: asString(s['setting_key']), helperText: asString(s['description'])),
                            onChanged: (v) => setState(() => changes[asString(s['setting_key'])] = v),
                          ),
                  ),
              ]),
            ),
      );
}

final _adminWidgetTypesProvider = FutureProvider.autoDispose((ref) => ref.watch(adminRepoProvider).widgetTypes());

class AdminWidgetTypesScreen extends ConsumerWidget {
  const AdminWidgetTypesScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) => Scaffold(
        appBar: AppBar(title: const Text('Widget types')),
        body: ref.watch(_adminWidgetTypesProvider).when(
              loading: () => const LoadingView(),
              error: (e, _) => ErrorView(error: e),
              data: (list) => ListView(padding: const EdgeInsets.all(12), children: [
                const Padding(padding: EdgeInsets.all(4), child: Text('"Planned" widgets are hidden from users until they are implemented and tested.', style: TextStyle(color: AppColors.mediumGrey))),
                for (final w in list)
                  Card(
                    margin: const EdgeInsets.only(bottom: 8),
                    child: ListTile(
                      title: Text('${w.widgetName}  ·  ${w.widgetKey}'),
                      subtitle: Text('${w.category} · used ${w.usageCount}× · ${w.description ?? ''}'),
                      trailing: DropdownButton<String>(
                        value: w.availability,
                        items: const [DropdownMenuItem(value: 'stable', child: Text('Stable')), DropdownMenuItem(value: 'beta', child: Text('Beta')), DropdownMenuItem(value: 'planned', child: Text('Planned'))],
                        onChanged: (v) async {
                          await ref.read(adminRepoProvider).updateWidgetType({'widget_key': w.widgetKey, 'availability': v});
                          ref.invalidate(_adminWidgetTypesProvider);
                          ref.invalidate(widgetTypesProvider);
                        },
                      ),
                    ),
                  ),
              ]),
            ),
      );
}

final _adminMediaProvider = FutureProvider.autoDispose((ref) => ref.watch(adminRepoProvider).media());

class AdminMediaScreen extends ConsumerWidget {
  const AdminMediaScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) => Scaffold(
        appBar: AppBar(title: const Text('All media')),
        body: ref.watch(_adminMediaProvider).when(
              loading: () => const LoadingView(),
              error: (e, _) => ErrorView(error: e),
              data: (p) => GridView.builder(
                padding: const EdgeInsets.all(12),
                gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(maxCrossAxisExtent: 180, mainAxisExtent: 200, mainAxisSpacing: 10, crossAxisSpacing: 10),
                itemCount: p.items.length,
                itemBuilder: (_, i) {
                  final m = p.items[i];
                  return AppCard(
                    padding: const EdgeInsets.all(6),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                      Expanded(child: ClipRRect(borderRadius: BorderRadius.circular(10), child: m.thumbnailUrl != null && m.isImage ? CachedNetworkImage(imageUrl: m.thumbnailUrl!, fit: BoxFit.cover) : const ColoredBox(color: AppColors.charcoal, child: Icon(Icons.insert_drive_file, color: Colors.white54)))),
                      Text(m.originalName, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                      Row(children: [
                        Expanded(child: Text(formatBytes(m.fileSize), style: const TextStyle(fontSize: 11, color: AppColors.mediumGrey))),
                        PopupMenuButton<String>(
                          padding: EdgeInsets.zero,
                          onSelected: (a) async {
                            await runWithProgress(context, () => ref.read(adminRepoProvider).mediaAction(m.mediaId, a), success: 'Updated');
                            ref.invalidate(_adminMediaProvider);
                          },
                          itemBuilder: (_) => [
                            PopupMenuItem(value: m.isGlobal ? 'make_private' : 'make_global', child: Text(m.isGlobal ? 'Remove from shared library' : 'Share with all users')),
                            const PopupMenuItem(value: 'delete', child: Text('Delete')),
                          ],
                        ),
                      ]),
                    ]),
                  );
                },
              ),
            ),
      );
}
