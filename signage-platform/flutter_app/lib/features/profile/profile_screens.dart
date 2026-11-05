import 'package:dio/dio.dart' show MultipartFile;
import 'package:file_picker/file_picker.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/constants/app_colors.dart';
import '../../core/constants/app_config.dart';
import '../../core/utils/formatters.dart';
import '../../core/utils/json.dart';
import '../../core/widgets/color_picker.dart';
import '../../core/widgets/common.dart';
import '../../models/misc.dart';
import '../../models/user.dart';
import '../../providers/providers.dart';

class ProfileScreen extends ConsumerWidget {
  const ProfileScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final s = ref.watch(sessionProvider).value;
    if (s == null) return const SizedBox.shrink();
    final mode = ref.watch(themeModeProvider);
    final t = Theme.of(context).textTheme;
    return Scaffold(
      appBar: AppBar(title: const Text('Profile')),
      body: RefreshIndicator(
        onRefresh: () => ref.read(sessionProvider.notifier).refresh(),
        child: ListView(padding: const EdgeInsets.all(16), children: [
          AppCard(
            child: Row(children: [
              CircleAvatar(
                radius: 32,
                backgroundColor: AppColors.teal,
                backgroundImage: s.user.profileImageUrl != null ? NetworkImage(s.user.profileImageUrl!) : null,
                child: s.user.profileImageUrl == null ? Text(s.user.initials, style: const TextStyle(color: Colors.white, fontSize: 22, fontWeight: FontWeight.w700)) : null,
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text(s.user.fullName, style: t.titleMedium),
                  Text(s.user.email, style: t.bodySmall?.copyWith(color: AppColors.mediumGrey)),
                  if (s.user.businessName != null) Text(s.user.businessName!, style: t.bodySmall),
                  const SizedBox(height: 6),
                  Row(children: [
                    StatusChip(s.limits.planName ?? 'Plan', color: AppColors.teal),
                    if (s.user.isAdmin) ...[const SizedBox(width: 6), const StatusChip('Super admin', color: Color(0xFF5E35B1))],
                  ]),
                ]),
              ),
              IconButton(icon: const Icon(Icons.edit_outlined), onPressed: () => context.push('/profile/edit')),
            ]),
          ),
          if (s.user.mustChangePassword)
            Padding(
              padding: const EdgeInsets.only(top: 12),
              child: AppCard(
                color: AppColors.warning.withValues(alpha: 0.12),
                onTap: () => context.push('/profile/password'),
                child: const Row(children: [Icon(Icons.key_rounded, color: AppColors.warning), SizedBox(width: 10), Expanded(child: Text('Please change the temporary password your administrator gave you.'))]),
              ),
            ),
          const SizedBox(height: 14),
          const SectionHeader('Plan & limits'),
          AppCard(
            child: Column(children: [
              UsageBar(label: 'Screens', used: s.usage.screensUsed, limit: s.limits.maxScreens),
              const SizedBox(height: 12),
              UsageBar(label: 'Playlists', used: s.usage.playlistsUsed, limit: s.limits.maxPlaylists),
              const SizedBox(height: 12),
              UsageBar(label: 'Displays', used: s.usage.devicesUsed, limit: s.limits.maxDevices),
              const SizedBox(height: 12),
              UsageBar(label: 'Storage', used: s.usage.storageUsedBytes / 1048576, limit: s.limits.maxStorageMb, format: (n) => formatMb(n.round())),
              const SizedBox(height: 12),
              Row(children: [
                Icon(s.limits.canUploadVideo ? Icons.check_circle : Icons.cancel, size: 18, color: s.limits.canUploadVideo ? AppColors.success : AppColors.mediumGrey),
                const Text(' Video uploads   '),
                Icon(s.limits.canUsePremiumTemplates ? Icons.check_circle : Icons.cancel, size: 18, color: s.limits.canUsePremiumTemplates ? AppColors.success : AppColors.mediumGrey),
                const Text(' Premium templates'),
              ]),
            ]),
          ),
          const SizedBox(height: 14),
          const SectionHeader('Workspace'),
          AppCard(
            padding: const EdgeInsets.symmetric(vertical: 4),
            child: Column(children: [
              _tile(Icons.photo_library_outlined, 'Media library', () => context.push('/media')),
              _tile(Icons.event_note_outlined, 'Schedules', () => context.push('/schedules')),
              _tile(Icons.location_city_outlined, 'Locations', () => context.push('/locations')),
              _tile(Icons.insights_outlined, 'Reports', () => context.push('/reports')),
              _tile(Icons.warning_amber_rounded, 'Emergency broadcast', () => context.push('/emergency'), color: AppColors.danger),
              _tile(Icons.notifications_none_rounded, 'Notifications', () => context.push('/notifications')),
              if (s.user.isAdmin) _tile(Icons.admin_panel_settings_outlined, 'Admin panel', () => context.go('/admin')),
            ]),
          ),
          const SizedBox(height: 14),
          const SectionHeader('Appearance'),
          SegmentedButton<ThemeMode>(
            segments: const [
              ButtonSegment(value: ThemeMode.light, icon: Icon(Icons.light_mode_outlined), label: Text('Light')),
              ButtonSegment(value: ThemeMode.dark, icon: Icon(Icons.dark_mode_outlined), label: Text('Dark')),
              ButtonSegment(value: ThemeMode.system, icon: Icon(Icons.brightness_auto_outlined), label: Text('System')),
            ],
            selected: {mode},
            onSelectionChanged: (v) {
              ref.read(themeModeProvider.notifier).set(v.first);
              ref.read(authRepoProvider).saveSettings({'theme': v.first.name}).ignore();
            },
          ),
          const SizedBox(height: 14),
          const SectionHeader('Security'),
          AppCard(
            padding: const EdgeInsets.symmetric(vertical: 4),
            child: Column(children: [
              _tile(Icons.lock_reset_rounded, 'Change password', () => context.push('/profile/password')),
              _tile(Icons.logout_rounded, 'Sign out', () async {
                if (await confirmDialog(context, title: 'Sign out?', confirm: 'Sign out')) ref.read(sessionProvider.notifier).logout();
              }, color: AppColors.danger),
            ]),
          ),
          const SizedBox(height: 20),
          Center(child: Text('${AppConfig.appName} · API ${AppConfig.apiBaseUrl}', style: t.labelSmall?.copyWith(color: AppColors.mediumGrey))),
        ]),
      ),
    );
  }

  Widget _tile(IconData i, String label, VoidCallback onTap, {Color? color}) =>
      ListTile(leading: Icon(i, color: color), title: Text(label, style: TextStyle(color: color)), trailing: const Icon(Icons.chevron_right_rounded), onTap: onTap);
}

class EditProfileScreen extends ConsumerStatefulWidget {
  const EditProfileScreen({super.key});
  @override
  ConsumerState<EditProfileScreen> createState() => _EditProfileScreenState();
}

class _EditProfileScreenState extends ConsumerState<EditProfileScreen> {
  late final AppUser u = ref.read(sessionProvider).value!.user;
  late final name = TextEditingController(text: u.fullName);
  late final phone = TextEditingController(text: u.phone);
  late final biz = TextEditingController(text: u.businessName);
  late String tz = u.timezone;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Edit profile')),
      body: ListView(padding: const EdgeInsets.all(20), children: [
        Center(
          child: OutlinedButton.icon(
            icon: const Icon(Icons.photo_camera_outlined),
            label: const Text('Change photo'),
            onPressed: () async {
              final files = await FilePicker.pickFiles(type: FileType.image);
              if (files.isEmpty || !context.mounted) return;
              final f = files.first;
              final mf = (!kIsWeb && f.path != null) ? await MultipartFile.fromFile(f.path!, filename: f.name) : MultipartFile.fromBytes(await f.readAsBytes(), filename: f.name);
              if (!context.mounted) return;
              final r = await runWithProgress(context, () => ref.read(apiClientProvider).upload('auth/update-profile.php', file: mf), success: 'Photo updated');
              if (r != null) ref.read(sessionProvider.notifier).updateUser(AppUser.fromJson(asMap(asMap(r.data)['user'])));
            },
          ),
        ),
        const SizedBox(height: 16),
        TextField(controller: name, decoration: const InputDecoration(labelText: 'Full name')),
        const SizedBox(height: 12),
        TextField(controller: phone, keyboardType: TextInputType.phone, decoration: const InputDecoration(labelText: 'Phone')),
        const SizedBox(height: 12),
        TextField(controller: biz, decoration: const InputDecoration(labelText: 'Business name')),
        const SizedBox(height: 12),
        TextFormField(initialValue: tz, decoration: const InputDecoration(labelText: 'Time zone (e.g. Asia/Dubai)'), onChanged: (v) => tz = v.trim()),
        const SizedBox(height: 24),
        FilledButton(
          onPressed: () async {
            final r = await runWithProgress(context, () => ref.read(authRepoProvider).updateProfile({'full_name': name.text, 'phone': phone.text, 'business_name': biz.text, 'timezone': tz}), success: 'Profile updated');
            if (r != null) {
              ref.read(sessionProvider.notifier).updateUser(r);
              if (context.mounted) context.pop();
            }
          },
          child: const Text('Save'),
        ),
      ]),
    );
  }
}

class ChangePasswordScreen extends ConsumerStatefulWidget {
  const ChangePasswordScreen({super.key});
  @override
  ConsumerState<ChangePasswordScreen> createState() => _ChangePasswordScreenState();
}

class _ChangePasswordScreenState extends ConsumerState<ChangePasswordScreen> {
  final cur = TextEditingController(), next = TextEditingController(), again = TextEditingController();
  @override
  Widget build(BuildContext context) => Scaffold(
        appBar: AppBar(title: const Text('Change password')),
        body: ListView(padding: const EdgeInsets.all(20), children: [
          TextField(controller: cur, obscureText: true, decoration: const InputDecoration(labelText: 'Current password')),
          const SizedBox(height: 12),
          TextField(controller: next, obscureText: true, decoration: const InputDecoration(labelText: 'New password', helperText: 'At least 8 characters with letters and numbers')),
          const SizedBox(height: 12),
          TextField(controller: again, obscureText: true, decoration: const InputDecoration(labelText: 'Repeat new password')),
          const SizedBox(height: 24),
          FilledButton(
            onPressed: () async {
              if (next.text != again.text) return showToast(context, 'Passwords do not match', error: true);
              final r = await runWithProgress(context, () => ref.read(authRepoProvider).changePassword(cur.text, next.text));
              if (r != null && context.mounted) {
                showToast(context, r);
                ref.read(sessionProvider.notifier).refresh();
                context.pop();
              }
            },
            child: const Text('Update password'),
          ),
        ]),
      );
}

final _emergenciesProvider = FutureProvider.autoDispose<List<EmergencyMessage>>((ref) => ref.watch(miscRepoProvider).emergencies());

/// Emergency full-screen announcement on all (or selected) displays.
class EmergencyScreen extends ConsumerStatefulWidget {
  const EmergencyScreen({super.key});
  @override
  ConsumerState<EmergencyScreen> createState() => _EmergencyScreenState();
}

class _EmergencyScreenState extends ConsumerState<EmergencyScreen> {
  final title = TextEditingController(text: 'Attention please');
  final body = TextEditingController(text: 'Please follow staff instructions.');
  String bg = '#B00020';
  int? minutes = 30;
  final Set<int> devices = {};

  @override
  Widget build(BuildContext context) {
    final list = ref.watch(_emergenciesProvider);
    final allDevices = ref.watch(devicesProvider).value ?? [];
    return Scaffold(
      appBar: AppBar(title: const Text('Emergency broadcast')),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        AppCard(
          color: AppColors.danger.withValues(alpha: 0.08),
          child: const Text('Shows a full-screen alert on your displays immediately, overriding all schedules until it ends or you cancel it.'),
        ),
        const SizedBox(height: 14),
        TextField(controller: title, decoration: const InputDecoration(labelText: 'Headline')),
        const SizedBox(height: 12),
        TextField(controller: body, minLines: 2, maxLines: 4, decoration: const InputDecoration(labelText: 'Message')),
        ColorField(label: 'Background', value: bg, allowNone: false, onChanged: (v) => setState(() => bg = v ?? bg)),
        DropdownButtonFormField<int?>(
          initialValue: minutes,
          decoration: const InputDecoration(labelText: 'Show for'),
          items: const [
            DropdownMenuItem(value: 15, child: Text('15 minutes')), DropdownMenuItem(value: 30, child: Text('30 minutes')),
            DropdownMenuItem(value: 60, child: Text('1 hour')), DropdownMenuItem(value: 240, child: Text('4 hours')),
            DropdownMenuItem(value: null, child: Text('Until I cancel it')),
          ],
          onChanged: (v) => setState(() => minutes = v),
        ),
        const SizedBox(height: 12),
        const Text('Displays', style: TextStyle(fontWeight: FontWeight.w600)),
        Wrap(spacing: 8, children: [
          FilterChip(label: const Text('All displays'), selected: devices.isEmpty, onSelected: (_) => setState(devices.clear)),
          for (final d in allDevices) FilterChip(label: Text(d.deviceName), selected: devices.contains(d.deviceId), onSelected: (v) => setState(() => v ? devices.add(d.deviceId) : devices.remove(d.deviceId))),
        ]),
        const SizedBox(height: 18),
        FilledButton.icon(
          style: FilledButton.styleFrom(backgroundColor: AppColors.danger),
          icon: const Icon(Icons.campaign_rounded),
          label: const Text('Broadcast now'),
          onPressed: () async {
            if (!await confirmDialog(context, title: 'Broadcast emergency message?', message: 'It appears on ${devices.isEmpty ? 'all your displays' : '${devices.length} display(s)'} right away.', confirm: 'Broadcast', destructive: true)) return;
            if (!context.mounted) return;
            final m = await runWithProgress(context, () => ref.read(miscRepoProvider).broadcast({'title': title.text, 'body': body.text, 'background': bg, 'duration_minutes': minutes, 'device_ids': devices.toList()}));
            if (m != null && context.mounted) showToast(context, m);
            ref.invalidate(_emergenciesProvider);
          },
        ),
        const SizedBox(height: 20),
        const SectionHeader('History'),
        ...?list.value?.map((e) => Card(
              margin: const EdgeInsets.only(bottom: 8),
              child: ListTile(
                leading: Icon(Icons.warning_amber_rounded, color: e.status == 'active' ? AppColors.danger : AppColors.mediumGrey),
                title: Text(e.title),
                subtitle: Text('${e.status} · ${formatDateTime(e.startsAt)}${e.endsAt != null ? ' → ${formatDateTime(e.endsAt)}' : ''}'),
                trailing: e.status == 'active'
                    ? TextButton(onPressed: () async {
                        final m = await runWithProgress(context, () => ref.read(miscRepoProvider).cancelEmergency(e.id));
                        if (m != null && context.mounted) showToast(context, m);
                        ref.invalidate(_emergenciesProvider);
                      }, child: const Text('Cancel'))
                    : null,
              ),
            )),
      ]),
    );
  }
}

class LocationsScreen extends ConsumerWidget {
  const LocationsScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final list = ref.watch(locationsProvider);
    Future<void> edit([Map<String, dynamic>? cur]) async {
      final name = TextEditingController(text: cur?['location_name']);
      final address = TextEditingController(text: cur?['address']);
      final city = TextEditingController(text: cur?['city']);
      final ok = await showDialog<bool>(context: context, builder: (c) => AlertDialog(
            title: Text(cur == null ? 'New location' : 'Edit location'),
            content: Column(mainAxisSize: MainAxisSize.min, children: [
              TextField(controller: name, decoration: const InputDecoration(labelText: 'Name')),
              const SizedBox(height: 8),
              TextField(controller: address, decoration: const InputDecoration(labelText: 'Address')),
              const SizedBox(height: 8),
              TextField(controller: city, decoration: const InputDecoration(labelText: 'City')),
            ]),
            actions: [TextButton(onPressed: () => Navigator.pop(c, false), child: const Text('Cancel')), FilledButton(onPressed: () => Navigator.pop(c, true), child: const Text('Save'))],
          ));
      if (ok != true || !context.mounted) return;
      await runWithProgress(context, () => ref.read(devicesRepoProvider).saveLocation({if (cur != null) 'location_id': cur['location_id'], 'location_name': name.text, 'address': address.text, 'city': city.text}), success: 'Saved');
      ref.invalidate(locationsProvider);
    }

    return Scaffold(
      appBar: AppBar(title: const Text('Locations')),
      floatingActionButton: FloatingActionButton(onPressed: () => edit(), child: const Icon(Icons.add)),
      body: list.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(error: e),
        data: (items) => items.isEmpty
            ? const EmptyState(icon: Icons.location_city_outlined, title: 'No locations', message: 'Group displays by branch or site.')
            : ListView(padding: const EdgeInsets.all(16), children: [
                for (final l in items)
                  Card(
                    margin: const EdgeInsets.only(bottom: 8),
                    child: ListTile(
                      leading: const Icon(Icons.location_on_outlined, color: AppColors.teal),
                      title: Text(l.locationName),
                      subtitle: Text('${[l.address, l.city].whereType<String>().join(', ')} · ${l.deviceCount} display(s)'),
                      onTap: () => edit({'location_id': l.locationId, 'location_name': l.locationName, 'address': l.address, 'city': l.city}),
                      trailing: IconButton(icon: const Icon(Icons.delete_outline), onPressed: () async {
                        if (await confirmDialog(context, title: 'Delete ${l.locationName}?', destructive: true, confirm: 'Delete') && context.mounted) {
                          await runWithProgress(context, () => ref.read(devicesRepoProvider).deleteLocation(l.locationId));
                          ref.invalidate(locationsProvider);
                        }
                      }),
                    ),
                  ),
              ]),
      ),
    );
  }
}

final _uptimeProvider = FutureProvider.autoDispose((ref) => ref.watch(miscRepoProvider).uptime());
final _playbackProvider = FutureProvider.autoDispose((ref) {
  final now = DateTime.now();
  String d(DateTime x) => x.toIso8601String().substring(0, 10);
  return ref.watch(miscRepoProvider).playbackReport(d(now.subtract(const Duration(days: 7))), d(now));
});

class ReportsScreen extends ConsumerWidget {
  const ReportsScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final up = ref.watch(_uptimeProvider);
    final pb = ref.watch(_playbackProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('Reports · last 7 days')),
      body: ListView(padding: const EdgeInsets.all(16), children: [
        const SectionHeader('Display uptime'),
        up.when(
          loading: () => const LoadingView(),
          error: (e, _) => ErrorView(error: e),
          data: (rows) => AppCard(
            padding: const EdgeInsets.symmetric(vertical: 4),
            child: Column(children: [
              if (rows.isEmpty) const Padding(padding: EdgeInsets.all(16), child: Text('No displays yet')),
              for (final r in rows)
                ListTile(
                  leading: const Icon(Icons.tv_rounded, color: AppColors.teal),
                  title: Text(asString(r['device_name'])),
                  subtitle: Text('Last sync ${formatDateTime(asDate(r['last_sync_at']))}'),
                  trailing: Column(mainAxisAlignment: MainAxisAlignment.center, crossAxisAlignment: CrossAxisAlignment.end, children: [
                    Text('${asDouble(r['hours_played']).toStringAsFixed(1)} h played', style: const TextStyle(fontWeight: FontWeight.w700)),
                    Text('${asInt(r['errors'])} errors', style: TextStyle(color: asInt(r['errors']) > 0 ? AppColors.danger : AppColors.mediumGrey, fontSize: 12)),
                  ]),
                ),
            ]),
          ),
        ),
        const SizedBox(height: 14),
        const SectionHeader('Content played'),
        pb.when(
          loading: () => const LoadingView(),
          error: (e, _) => ErrorView(error: e),
          data: (d) {
            final rows = asList(d['rows']).map(asMap).toList();
            return AppCard(
              padding: const EdgeInsets.symmetric(vertical: 4),
              child: Column(children: [
                if (rows.isEmpty) const Padding(padding: EdgeInsets.all(16), child: Text('No playback recorded yet')),
                for (final r in rows.take(30))
                  ListTile(
                    dense: true,
                    title: Text('${r['screen_name'] ?? 'Screen'} · ${r['playlist_name'] ?? ''}'),
                    subtitle: Text(asString(r['device_name'])),
                    trailing: Text('${r['plays']} plays · ${formatDuration(asInt(r['seconds_played']))}'),
                  ),
              ]),
            );
          },
        ),
        const SizedBox(height: 10),
        Text('CSV export: ${AppConfig.apiBaseUrl}/api/reports/playback.php?format=csv (authenticated)', style: const TextStyle(color: AppColors.mediumGrey, fontSize: 12)),
      ]),
    );
  }
}
