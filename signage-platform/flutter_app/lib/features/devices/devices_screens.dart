import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/constants/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../../core/utils/json.dart';
import '../../core/widgets/common.dart';
import '../../models/device.dart';
import '../../providers/providers.dart';

class DevicesScreen extends ConsumerWidget {
  const DevicesScreen({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final list = ref.watch(devicesProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('Devices'), actions: [
        IconButton(tooltip: 'Locations', icon: const Icon(Icons.location_city_outlined), onPressed: () => context.push('/locations')),
        IconButton(tooltip: 'Emergency broadcast', icon: const Icon(Icons.warning_amber_rounded, color: AppColors.danger), onPressed: () => context.push('/emergency')),
      ]),
      floatingActionButton: FloatingActionButton.extended(onPressed: () => context.push('/devices/add'), icon: const Icon(Icons.add_to_queue_rounded), label: const Text('Add device')),
      body: RefreshIndicator(
        onRefresh: () async => ref.invalidate(devicesProvider),
        child: list.when(
          loading: () => const LoadingView(),
          error: (e, _) => ErrorView(error: e, onRetry: () => ref.invalidate(devicesProvider)),
          data: (items) {
            if (items.isEmpty) {
              return ListView(children: [
                const SizedBox(height: 60),
                EmptyState(icon: Icons.tv_rounded, title: 'No managed displays', message: 'Quickest way: open Signal Signage on your TV, tap "Show Display" and enter the code of a screen or playlist ("Show on a TV").\n\nFor remote control, schedules and monitoring, pair the TV instead: choose "Managed display" on the TV and enter its pairing code here.', action: 'Pair a TV', onAction: () => context.push('/devices/add')),
              ]);
            }
            final online = items.where((d) => d.isOnline).length;
            return ListView(padding: const EdgeInsets.fromLTRB(16, 8, 16, 100), children: [
              Row(children: [
                Expanded(child: StatCard(label: 'Online', value: '$online', icon: Icons.wifi_rounded, color: AppColors.success)),
                const SizedBox(width: 12),
                Expanded(child: StatCard(label: 'Offline', value: '${items.length - online}', icon: Icons.wifi_off_rounded, color: AppColors.danger)),
              ]),
              const SizedBox(height: 12),
              for (final d in items) Padding(padding: const EdgeInsets.only(bottom: 10), child: DeviceCard(d: d)),
            ]);
          },
        ),
      ),
    );
  }
}

class DeviceCard extends StatelessWidget {
  const DeviceCard({super.key, required this.d, this.admin = false});
  final Device d;
  final bool admin;
  @override
  Widget build(BuildContext context) {
    final t = Theme.of(context).textTheme;
    final c = d.isOnline ? AppColors.success : AppColors.danger;
    return AppCard(
      onTap: admin ? null : () => context.push('/devices/${d.deviceId}'),
      child: Row(children: [
        Stack(children: [
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(color: Theme.of(context).colorScheme.primaryContainer, borderRadius: BorderRadius.circular(14)),
            child: Icon(d.orientation == 'portrait' ? Icons.stay_current_portrait : Icons.tv_rounded, color: AppColors.teal),
          ),
          Positioned(right: 2, bottom: 2, child: Container(width: 12, height: 12, decoration: BoxDecoration(color: c, shape: BoxShape.circle, border: Border.all(color: Colors.white, width: 2)))),
        ]),
        const SizedBox(width: 12),
        Expanded(
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(d.deviceName, style: t.titleSmall?.copyWith(fontWeight: FontWeight.w700)),
            Text([d.typeLabel, if (d.resolution != null) d.resolution, if (d.locationName != null) d.locationName, if (admin && d.ownerName != null) d.ownerName].join(' · '),
                style: t.bodySmall?.copyWith(color: AppColors.mediumGrey), maxLines: 1, overflow: TextOverflow.ellipsis),
            const SizedBox(height: 4),
            Text(
              d.isOnline
                  ? (d.currentScreenName != null ? '▶ ${d.currentPlaylistName ?? ''} · ${d.currentScreenName}' : (d.assignments.isEmpty ? 'Online · nothing assigned' : 'Online · ${d.playbackState}'))
                  : 'Offline · last seen ${timeAgo(d.lastHeartbeat)}',
              style: t.bodySmall?.copyWith(color: c, fontWeight: FontWeight.w600), maxLines: 1, overflow: TextOverflow.ellipsis,
            ),
          ]),
        ),
        if (!admin) const Icon(Icons.chevron_right_rounded, color: AppColors.mediumGrey),
      ]),
    );
  }
}

/// Workflow C: enter the code shown on the TV, name it, pick content.
class AddDeviceScreen extends ConsumerStatefulWidget {
  const AddDeviceScreen({super.key});
  @override
  ConsumerState<AddDeviceScreen> createState() => _AddDeviceScreenState();
}

class _AddDeviceScreenState extends ConsumerState<AddDeviceScreen> {
  final code = TextEditingController();
  final name = TextEditingController(text: 'Lobby TV');
  int? playlistId, locationId;
  bool busy = false;

  @override
  Widget build(BuildContext context) {
    final playlists = ref.watch(playlistsProvider).value ?? [];
    final locations = ref.watch(locationsProvider).value ?? [];
    final t = Theme.of(context).textTheme;
    return Scaffold(
      appBar: AppBar(title: const Text('Connect a display')),
      body: ListView(padding: const EdgeInsets.all(20), children: [
        AppCard(
          color: Theme.of(context).colorScheme.primaryContainer,
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text('How it works', style: t.titleMedium),
            const SizedBox(height: 8),
            for (final s in const [
              '1. Open Signal Signage on your Android TV or TV box → Show Display → Managed display.',
              '2. The TV shows a 6-character pairing code.',
              '3. Type the code below and choose what to show.',
            ])
              Padding(padding: const EdgeInsets.only(bottom: 4), child: Text(s)),
          ]),
        ),
        const SizedBox(height: 20),
        TextField(
          controller: code,
          textCapitalization: TextCapitalization.characters,
          textAlign: TextAlign.center,
          maxLength: 7,
          inputFormatters: [FilteringTextInputFormatter.allow(RegExp(r'[A-Za-z0-9 ]')), TextInputFormatter.withFunction((o, n) => n.copyWith(text: n.text.toUpperCase()))],
          style: const TextStyle(fontSize: 32, letterSpacing: 10, fontWeight: FontWeight.w800),
          decoration: const InputDecoration(labelText: 'Pairing code', hintText: 'K7Q4ZP', counterText: ''),
        ),
        const SizedBox(height: 12),
        TextField(controller: name, decoration: const InputDecoration(labelText: 'Display name', prefixIcon: Icon(Icons.tv_rounded))),
        const SizedBox(height: 12),
        DropdownButtonFormField<int?>(
          initialValue: playlistId,
          decoration: const InputDecoration(labelText: 'Show this playlist (optional)', prefixIcon: Icon(Icons.playlist_play_rounded)),
          items: [const DropdownMenuItem(value: null, child: Text('Choose later')), for (final p in playlists) DropdownMenuItem(value: p.playlistId, child: Text(p.playlistName))],
          onChanged: (v) => setState(() => playlistId = v),
        ),
        const SizedBox(height: 12),
        DropdownButtonFormField<int?>(
          initialValue: locationId,
          decoration: const InputDecoration(labelText: 'Location (optional)', prefixIcon: Icon(Icons.location_on_outlined)),
          items: [const DropdownMenuItem(value: null, child: Text('None')), for (final l in locations) DropdownMenuItem(value: l.locationId, child: Text(l.locationName))],
          onChanged: (v) => setState(() => locationId = v),
        ),
        const SizedBox(height: 24),
        FilledButton.icon(
          onPressed: busy ? null : () async {
            setState(() => busy = true);
            try {
              final d = await ref.read(devicesRepoProvider).pair(code.text.replaceAll(' ', ''), name.text.trim(), locationId: locationId, playlistId: playlistId);
              invalidateContent(ref);
              if (!context.mounted) return;
              showToast(context, '${d.deviceName} connected');
              context.pushReplacement('/devices/${d.deviceId}');
            } catch (e) {
              if (context.mounted) showToast(context, e.toString(), error: true);
            } finally {
              if (mounted) setState(() => busy = false);
            }
          },
          icon: const Icon(Icons.link_rounded),
          label: const Text('Connect display'),
        ),
      ]),
    );
  }
}

final deviceDetailProvider = FutureProvider.autoDispose.family<Device, int>((ref, id) => ref.watch(devicesRepoProvider).details(id));

class DeviceDetailScreen extends ConsumerWidget {
  const DeviceDetailScreen({super.key, required this.deviceId});
  final int deviceId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final dv = ref.watch(deviceDetailProvider(deviceId));
    final repo = ref.read(devicesRepoProvider);
    void reload() { ref.invalidate(deviceDetailProvider(deviceId)); ref.invalidate(devicesProvider); }
    return Scaffold(
      appBar: AppBar(title: Text(dv.value?.deviceName ?? 'Display'), actions: [
        IconButton(icon: const Icon(Icons.refresh), onPressed: reload),
      ]),
      body: dv.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(error: e, onRetry: reload),
        data: (d) {
          final t = Theme.of(context).textTheme;
          Future<void> cmd(String c) async {
            final m = await runWithProgress(context, () => repo.command(d.deviceId, c));
            if (m != null && context.mounted) showToast(context, m);
            reload();
          }
          final cmds = {
            'restart': (Icons.restart_alt_rounded, 'Restart player'), 'pause': (Icons.pause_rounded, 'Pause'), 'resume': (Icons.play_arrow_rounded, 'Resume'),
            'sync': (Icons.sync_rounded, 'Sync now'), 'identify': (Icons.highlight_rounded, 'Identify'), 'clear_cache': (Icons.cleaning_services_outlined, 'Clear cache'),
          };
          return RefreshIndicator(
            onRefresh: () async => reload(),
            child: ListView(padding: const EdgeInsets.all(16), children: [
              DeviceCard(d: d, admin: true),
              const SizedBox(height: 14),
              const SectionHeader('Content'),
              AppCard(
                child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
                  if (d.assignments.isEmpty) const Text('Nothing assigned yet — the display shows a clock and logo.'),
                  for (final a in d.assignments)
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      leading: Icon(a.isDefault ? Icons.star_rounded : Icons.playlist_play_rounded, color: AppColors.teal),
                      title: Text(a.playlistName),
                      subtitle: Text('${a.isDefault ? 'Default · ' : 'Scheduled · '}${a.publishedVersion == null ? 'not published yet' : 'v${a.publishedVersion}'}'),
                    ),
                  const SizedBox(height: 6),
                  FilledButton.tonalIcon(onPressed: () => _assign(context, ref, d), icon: const Icon(Icons.swap_horiz_rounded), label: Text(d.assignments.isEmpty ? 'Assign playlist' : 'Change playlists')),
                  TextButton.icon(
                    icon: const Icon(Icons.help_outline),
                    label: const Text('What is playing right now?'),
                    onPressed: () async {
                      final r = await runWithProgress(context, () => repo.resolve(d.deviceId));
                      if (r != null && context.mounted) showToast(context, '${r['playlist_name'] ?? 'Idle screen'} — ${r['reason']}');
                    },
                  ),
                ]),
              ),
              const SizedBox(height: 14),
              const SectionHeader('Remote control'),
              Wrap(spacing: 8, runSpacing: 8, children: [
                for (final e in cmds.entries)
                  if (d.commands.contains(e.key)) ActionChip(avatar: Icon(e.value.$1, size: 18), label: Text(e.value.$2), onPressed: () => cmd(e.key)),
              ]),
              if (!d.isOnline) const Padding(padding: EdgeInsets.only(top: 6), child: Text('Commands are queued until the display comes back online.', style: TextStyle(color: AppColors.mediumGrey, fontSize: 12))),
              const SizedBox(height: 14),
              const SectionHeader('Details'),
              AppCard(
                child: Column(children: [
                  for (final r in [
                    ('Device ID', d.deviceCode), ('Type', d.typeLabel), ('Model', [d.manufacturer, d.model].whereType<String>().join(' ')),
                    ('Resolution', d.resolution ?? '—'), ('Orientation', d.orientation), ('App version', d.appVersion ?? '—'), ('Android', d.osVersion ?? '—'),
                    ('Time zone', d.timezone ?? '—'), ('Last heartbeat', formatDateTime(d.lastHeartbeat)), ('Last sync', formatDateTime(d.lastSyncAt)),
                    ('Storage free', d.storageFreeMb == null ? '—' : '${formatMb(d.storageFreeMb!)} of ${formatMb(d.storageTotalMb ?? 0)}'),
                  ])
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 5),
                      child: Row(children: [
                        SizedBox(width: 130, child: Text(r.$1, style: t.bodySmall?.copyWith(color: AppColors.mediumGrey))),
                        Expanded(child: Text(r.$2, style: const TextStyle(fontWeight: FontWeight.w600))),
                      ]),
                    ),
                ]),
              ),
              const SizedBox(height: 14),
              const SectionHeader('Recent logs'),
              AppCard(
                padding: const EdgeInsets.symmetric(vertical: 4),
                child: d.recentLogs.isEmpty
                    ? const Padding(padding: EdgeInsets.all(16), child: Text('No logs yet'))
                    : Column(children: [
                        for (final l in d.recentLogs.take(15))
                          ListTile(
                            dense: true,
                            leading: Icon(
                              switch (asString(l['log_type'])) { 'error' || 'playback_error' || 'crash' => Icons.error_outline, 'warning' => Icons.warning_amber_rounded, 'sync' => Icons.sync, _ => Icons.info_outline },
                              color: StatusChip.colorFor(asString(l['log_type']) == 'info' ? 'active' : (asString(l['log_type']) == 'warning' ? 'pending' : 'error')),
                              size: 20,
                            ),
                            title: Text(asString(l['message'])),
                            subtitle: Text(formatDateTime(asDate(l['logged_at'] ?? l['created_at']))),
                          ),
                      ]),
              ),
              const SizedBox(height: 14),
              const SectionHeader('Settings'),
              AppCard(
                padding: const EdgeInsets.symmetric(vertical: 4),
                child: Column(children: [
                  ListTile(leading: const Icon(Icons.drive_file_rename_outline), title: const Text('Rename'), onTap: () async {
                    final n = await promptText(context, title: 'Display name', initial: d.deviceName);
                    if (n != null && n.isNotEmpty && context.mounted) { await runWithProgress(context, () => repo.update(d.deviceId, {'device_name': n})); reload(); }
                  }),
                  ListTile(leading: const Icon(Icons.screen_rotation_outlined), title: const Text('Orientation'), subtitle: Text(d.orientation), onTap: () async {
                    final o = await showModalBottomSheet<String>(context: context, builder: (c) => SafeArea(child: Column(mainAxisSize: MainAxisSize.min, children: [
                          for (final x in const ['auto', 'landscape', 'portrait']) ListTile(title: Text(x), onTap: () => Navigator.pop(c, x)),
                        ])));
                    if (o != null && context.mounted) { await runWithProgress(context, () => repo.update(d.deviceId, {'orientation': o})); reload(); }
                  }),
                  ListTile(leading: const Icon(Icons.link_off_rounded), title: const Text('Disconnect'), subtitle: const Text('The TV will show a new pairing code'), onTap: () async {
                    if (await confirmDialog(context, title: 'Disconnect ${d.deviceName}?', confirm: 'Disconnect', destructive: true) && context.mounted) {
                      await runWithProgress(context, () => repo.unpair(d.deviceId), success: 'Disconnected');
                      invalidateContent(ref);
                      if (context.mounted) context.pop();
                    }
                  }),
                  ListTile(leading: const Icon(Icons.delete_outline, color: AppColors.danger), title: const Text('Remove device', style: TextStyle(color: AppColors.danger)), onTap: () async {
                    if (await confirmDialog(context, title: 'Remove ${d.deviceName}?', message: 'It is disconnected and removed from your list.', confirm: 'Remove', destructive: true) && context.mounted) {
                      await runWithProgress(context, () => repo.unpair(d.deviceId, remove: true), success: 'Removed');
                      invalidateContent(ref);
                      if (context.mounted) context.pop();
                    }
                  }),
                ]),
              ),
            ]),
          );
        },
      ),
    );
  }

  Future<void> _assign(BuildContext context, WidgetRef ref, Device d) async {
    final playlists = await ref.read(playlistsRepoProvider).list();
    if (!context.mounted) return;
    final chosen = <int>{for (final a in d.assignments) a.playlistId};
    int? def = d.assignments.where((a) => a.isDefault).firstOrNull?.playlistId;
    final ok = await showModalBottomSheet<bool>(
      context: context,
      isScrollControlled: true,
      builder: (c) => StatefulBuilder(
        builder: (c, set) => SafeArea(
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            ListTile(
              title: const Text('Playlists on this display', style: TextStyle(fontWeight: FontWeight.w700)),
              subtitle: const Text('★ = default (plays when no schedule matches)'),
              trailing: FilledButton(onPressed: () => Navigator.pop(c, true), child: const Text('Save')),
            ),
            if (playlists.items.isEmpty) const Padding(padding: EdgeInsets.all(16), child: Text('Create a playlist first.')),
            for (final p in playlists.items)
              ListTile(
                leading: Checkbox(value: chosen.contains(p.playlistId), onChanged: (v) => set(() { v! ? chosen.add(p.playlistId) : chosen.remove(p.playlistId); if (!chosen.contains(def)) def = chosen.firstOrNull; })),
                title: Text(p.playlistName),
                subtitle: Text('${p.itemCount} screens · ${p.schedules.isEmpty ? 'no schedule' : '${p.schedules.length} time slot(s)'}${p.publishedVersion == null ? ' · not published' : ''}'),
                trailing: IconButton(
                  icon: Icon(def == p.playlistId ? Icons.star_rounded : Icons.star_outline_rounded, color: def == p.playlistId ? AppColors.warning : null),
                  onPressed: () => set(() { chosen.add(p.playlistId); def = p.playlistId; }),
                ),
              ),
          ]),
        ),
      ),
    );
    if (ok != true || !context.mounted) return;
    final r = await runWithProgress(context, () => ref.read(devicesRepoProvider).assign(d.deviceId, chosen.toList(), def));
    if (r != null && context.mounted) showToast(context, r.$2);
    ref.invalidate(deviceDetailProvider(d.deviceId));
    invalidateContent(ref);
  }
}
