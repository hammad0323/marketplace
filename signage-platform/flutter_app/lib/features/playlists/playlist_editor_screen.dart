import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/constants/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../../core/widgets/common.dart';
import '../../models/playlist.dart';
import '../../models/screen.dart';
import '../../providers/providers.dart';
import '../scheduling/schedule_editor.dart';
import '../widgets_library/layout_view.dart';
import '../widgets_library/render_context.dart';

const transitions = {
  'fade': ('Fade', Icons.gradient), 'slide_left': ('Slide ←', Icons.arrow_back), 'slide_right': ('Slide →', Icons.arrow_forward),
  'slide_up': ('Slide ↑', Icons.arrow_upward), 'slide_down': ('Slide ↓', Icons.arrow_downward), 'zoom': ('Zoom', Icons.zoom_in),
  'flip': ('Flip', Icons.flip), 'none': ('Cut', Icons.content_cut),
};

/// Playlist builder: pick screens → order → durations → transition → loop → schedule → preview → publish.
class PlaylistEditorScreen extends ConsumerStatefulWidget {
  const PlaylistEditorScreen({super.key, this.playlistId, this.initialScreenIds = const []});
  final int? playlistId;
  final List<int> initialScreenIds;
  @override
  ConsumerState<PlaylistEditorScreen> createState() => _PlaylistEditorScreenState();
}

class _PlaylistEditorScreenState extends ConsumerState<PlaylistEditorScreen> {
  int? playlistId;
  final name = TextEditingController(text: 'New playlist');
  List<PlaylistItem> items = [];
  List<PlaylistSchedule> schedules = [];
  Map<int, ScreenModel> screens = {};
  String transition = 'fade';
  double transitionMs = 800;
  bool loop = true, shuffle = false;
  int priority = 0;
  String status = 'draft';
  bool loading = true, saving = false;
  List<Map<String, dynamic>> devices = [];

  @override
  void initState() {
    super.initState();
    playlistId = widget.playlistId;
    _load();
  }

  Future<void> _load() async {
    try {
      final all = (await ref.read(screensRepoProvider).list()).items;
      screens = {for (final s in all) s.screenId: s};
      if (playlistId != null) {
        final p = await ref.read(playlistsRepoProvider).details(playlistId!);
        name.text = p.playlistName;
        items = p.items;
        schedules = p.schedules;
        transition = p.transitionType;
        transitionMs = p.transitionDuration.toDouble();
        loop = p.loopEnabled;
        shuffle = p.shuffle;
        priority = p.priority;
        status = p.status;
        devices = p.devices;
      } else {
        items = [
          for (final id in widget.initialScreenIds)
            if (screens[id] != null) PlaylistItem(screenId: id, screenName: screens[id]!.screenName, orientation: screens[id]!.orientation, screenStatus: screens[id]!.status, effectiveDuration: screens[id]!.defaultDuration),
        ];
      }
    } catch (e) {
      if (mounted) showToast(context, e.toString(), error: true);
    }
    if (mounted) setState(() => loading = false);
  }

  int get total => items.where((i) => i.isEnabled).fold(0, (a, i) => a + (i.duration ?? i.effectiveDuration));

  Future<bool> _save({bool publish = false}) async {
    if (items.isEmpty) {
      showToast(context, 'Add at least one screen', error: true);
      return false;
    }
    setState(() => saving = true);
    try {
      final (p, msg) = await ref.read(playlistsRepoProvider).save(
            playlistId: playlistId,
            settings: {
              'playlist_name': name.text.trim().isEmpty ? 'Untitled playlist' : name.text.trim(),
              'transition_type': transition, 'transition_duration': transitionMs.round(), 'loop_enabled': loop, 'shuffle': shuffle, 'priority': priority,
            },
            items: items,
          );
      playlistId = p.playlistId;
      items = p.items;
      status = p.status;
      if (publish) {
        final m = await ref.read(playlistsRepoProvider).publish(p.playlistId);
        status = 'published';
        if (mounted) showToast(context, m);
      } else if (mounted) {
        showToast(context, msg);
      }
      invalidateContent(ref);
      return true;
    } catch (e) {
      if (mounted) showToast(context, e.toString(), error: true);
      return false;
    } finally {
      if (mounted) setState(() => saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    if (loading) return const Scaffold(body: LoadingView());
    final t = Theme.of(context).textTheme;
    return Scaffold(
      appBar: AppBar(
        title: Text(playlistId == null ? 'Create Screen Carousel' : 'Edit playlist'),
        actions: [
          IconButton(
            tooltip: 'Preview',
            icon: const Icon(Icons.play_circle_outline_rounded),
            onPressed: () async {
              if (await _save() && context.mounted) context.push('/playlists/$playlistId/preview');
            },
          ),
        ],
      ),
      bottomNavigationBar: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
          child: Row(children: [
            Expanded(child: OutlinedButton(onPressed: saving ? null : () => _save(), child: const Text('Save draft'))),
            const SizedBox(width: 12),
            Expanded(
              flex: 2,
              child: FilledButton.icon(
                onPressed: saving ? null : () async {
                  if (await _save(publish: true) && mounted) _offerAssign();
                },
                icon: const Icon(Icons.cloud_upload_outlined),
                label: const Text('Save & publish'),
              ),
            ),
          ]),
        ),
      ),
      body: ListView(padding: const EdgeInsets.fromLTRB(16, 4, 16, 24), children: [
        TextField(controller: name, decoration: const InputDecoration(labelText: 'Playlist name', prefixIcon: Icon(Icons.drive_file_rename_outline))),
        const SizedBox(height: 16),
        Row(children: [
          Text('Screens', style: t.titleMedium),
          const SizedBox(width: 8),
          Text('${items.length} · ${formatDuration(total)} per loop', style: t.bodySmall?.copyWith(color: AppColors.mediumGrey)),
          const Spacer(),
          TextButton.icon(onPressed: _addScreens, icon: const Icon(Icons.add), label: const Text('Add')),
        ]),
        if (items.isEmpty)
          AppCard(child: EmptyState(icon: Icons.add_to_queue_rounded, title: 'Pick the screens to rotate', action: 'Select screens', onAction: _addScreens))
        else
          ReorderableListView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            buildDefaultDragHandles: false,
            itemCount: items.length,
            onReorderItem: (a, b) => setState(() {
              final x = items.removeAt(a);
              items.insert(b, x);
            }),
            itemBuilder: (_, i) => _itemTile(i),
          ),
        if (items.length > 1)
          Padding(
            padding: const EdgeInsets.only(top: 6, left: 4),
            child: Text(
              items.where((i) => i.isEnabled).map((i) => i.screenName).join(' → ') + (loop ? ' → repeat' : ''),
              style: t.bodySmall?.copyWith(color: AppColors.mediumGrey),
            ),
          ),
        const SizedBox(height: 20),
        Text('Transition', style: t.titleMedium),
        const SizedBox(height: 8),
        Wrap(spacing: 8, runSpacing: 8, children: [
          for (final e in transitions.entries)
            ChoiceChip(avatar: Icon(e.value.$2, size: 16), label: Text(e.value.$1), selected: transition == e.key, onSelected: (_) => setState(() => transition = e.key)),
        ]),
        Row(children: [
          const SizedBox(width: 130, child: Text('Transition speed')),
          Expanded(child: Slider(value: transitionMs.clamp(0, 3000), min: 0, max: 3000, divisions: 30, onChanged: (v) => setState(() => transitionMs = v))),
          Text('${(transitionMs / 1000).toStringAsFixed(1)} s'),
        ]),
        SwitchListTile(contentPadding: EdgeInsets.zero, title: const Text('Loop continuously'), subtitle: const Text('Start again after the last screen'), value: loop, onChanged: (v) => setState(() => loop = v)),
        SwitchListTile(contentPadding: EdgeInsets.zero, title: const Text('Shuffle order'), value: shuffle, onChanged: (v) => setState(() => shuffle = v)),
        Row(children: [
          const Expanded(child: Text('Priority (when several playlists are scheduled at once)')),
          IconButton(onPressed: () => setState(() => priority--), icon: const Icon(Icons.remove_circle_outline)),
          Text('$priority', style: const TextStyle(fontWeight: FontWeight.w700)),
          IconButton(onPressed: () => setState(() => priority++), icon: const Icon(Icons.add_circle_outline)),
        ]),
        const SizedBox(height: 16),
        Row(children: [
          Text('Schedule', style: t.titleMedium),
          const Spacer(),
          TextButton.icon(
            onPressed: () async {
              if (playlistId == null && !await _save()) return;
              if (!context.mounted) return;
              final s = await showScheduleEditor(context, ref, playlistId!, null);
              if (s != null) setState(() => schedules.add(s));
            },
            icon: const Icon(Icons.add),
            label: const Text('Add time slot'),
          ),
        ]),
        if (schedules.isEmpty)
          const Text('No schedule: plays whenever it is the display\'s default playlist.', style: TextStyle(color: AppColors.mediumGrey))
        else
          for (final s in schedules)
            Card(
              margin: const EdgeInsets.only(bottom: 8),
              child: ListTile(
                leading: Icon(s.scheduleType == 'special_event' ? Icons.celebration_outlined : Icons.schedule, color: AppColors.teal),
                title: Text(s.scheduleName ?? s.timeLabel),
                subtitle: Text('${s.timeLabel} · ${s.daysLabel}${s.startDate != null ? ' · from ${s.startDate}' : ''}${s.endDate != null ? ' to ${s.endDate}' : ''} · priority ${s.priority}'),
                trailing: Row(mainAxisSize: MainAxisSize.min, children: [
                  if (s.status != 'active') const StatusChip('inactive'),
                  IconButton(icon: const Icon(Icons.delete_outline), onPressed: () async {
                    await runWithProgress(context, () => ref.read(playlistsRepoProvider).deleteSchedule(s.scheduleId!));
                    setState(() => schedules.remove(s));
                  }),
                ]),
                onTap: () async {
                  final ns = await showScheduleEditor(context, ref, playlistId!, s);
                  if (ns != null) setState(() => schedules[schedules.indexOf(s)] = ns);
                },
              ),
            ),
        if (playlistId != null) ...[
          const SizedBox(height: 16),
          Row(children: [
            Text('Displays', style: t.titleMedium),
            const Spacer(),
            TextButton.icon(onPressed: _offerAssign, icon: const Icon(Icons.cast_rounded), label: const Text('Show on displays')),
          ]),
          if (devices.isEmpty)
            const Text('Not assigned to any display yet.', style: TextStyle(color: AppColors.mediumGrey))
          else
            Wrap(spacing: 8, children: [for (final d in devices) Chip(avatar: const Icon(Icons.tv, size: 16), label: Text('${d['device_name']}'))]),
        ],
      ]),
    );
  }

  Widget _itemTile(int i) {
    final it = items[i];
    final s = screens[it.screenId];
    final d = it.duration ?? it.effectiveDuration;
    return Card(
      key: ValueKey('${it.screenId}_${it.itemId}_$i'),
      margin: const EdgeInsets.only(bottom: 8),
      child: Padding(
        padding: const EdgeInsets.all(8),
        child: Row(children: [
          ReorderableDragStartListener(index: i, child: const Padding(padding: EdgeInsets.all(6), child: Icon(Icons.drag_indicator_rounded, color: AppColors.mediumGrey))),
          SizedBox(
            width: 84, height: 52,
            child: s?.layout == null ? const ColoredBox(color: AppColors.charcoal) : LayoutThumbnail(layout: s!.layout!, media: NetworkMediaSource(s.media), radius: 8),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Opacity(
              opacity: it.isEnabled ? 1 : 0.45,
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Text('${i + 1}. ${it.screenName}', maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.w700)),
                Row(children: [
                  ActionChip(
                    visualDensity: VisualDensity.compact,
                    avatar: const Icon(Icons.timer_outlined, size: 16),
                    label: Text(formatDuration(d)),
                    onPressed: () => _editDuration(it),
                  ),
                  const SizedBox(width: 6),
                  if (it.screenStatus != 'published') const StatusChip('draft'),
                ]),
              ]),
            ),
          ),
          PopupMenuButton<String>(
            onSelected: (a) => setState(() {
              if (a == 'remove') items.removeAt(i);
              if (a == 'toggle') it.isEnabled = !it.isEnabled;
              if (a.startsWith('t:')) it.transitionType = a.substring(2);
            }),
            itemBuilder: (_) => [
              PopupMenuItem(value: 'toggle', child: Text(it.isEnabled ? 'Skip this screen' : 'Include this screen')),
              const PopupMenuItem(value: 't:inherit', child: Text('Transition: playlist default')),
              for (final e in transitions.entries) PopupMenuItem(value: 't:${e.key}', child: Text('Transition: ${e.value.$1}${it.transitionType == e.key ? '  ✓' : ''}')),
              const PopupMenuItem(value: 'remove', child: Text('Remove', style: TextStyle(color: AppColors.danger))),
            ],
          ),
        ]),
      ),
    );
  }

  Future<void> _editDuration(PlaylistItem it) async {
    var v = (it.duration ?? it.effectiveDuration).toDouble();
    final r = await showDialog<double>(
      context: context,
      builder: (c) => StatefulBuilder(
        builder: (c, set) => AlertDialog(
          title: Text('Show "${it.screenName}" for'),
          content: Column(mainAxisSize: MainAxisSize.min, children: [
            Text(formatDuration(v.round()), style: Theme.of(c).textTheme.headlineMedium),
            Slider(value: v.clamp(3, 300), min: 3, max: 300, divisions: 297, onChanged: (x) => set(() => v = x)),
            Wrap(spacing: 6, children: [for (final p in [5, 10, 15, 30, 60]) ActionChip(label: Text('${p}s'), onPressed: () => set(() => v = p.toDouble()))]),
          ]),
          actions: [TextButton(onPressed: () => Navigator.pop(c), child: const Text('Cancel')), FilledButton(onPressed: () => Navigator.pop(c, v), child: const Text('Set'))],
        ),
      ),
    );
    if (r != null) setState(() => it.duration = r.round());
  }

  Future<void> _addScreens() async {
    final chosen = <int>{};
    final all = screens.values.toList();
    final r = await showModalBottomSheet<Set<int>>(
      context: context,
      isScrollControlled: true,
      builder: (c) => StatefulBuilder(
        builder: (c, set) => FractionallySizedBox(
          heightFactor: 0.85,
          child: Column(children: [
            ListTile(
              title: const Text('Select screens', style: TextStyle(fontWeight: FontWeight.w700)),
              subtitle: const Text('They are added in the order you tap them'),
              trailing: FilledButton(onPressed: () => Navigator.pop(c, chosen), child: Text('Add ${chosen.length}')),
            ),
            Expanded(
              child: all.isEmpty
                  ? EmptyState(icon: Icons.dashboard_outlined, title: 'You have no screens yet', action: 'Create a screen', onAction: () { Navigator.pop(c); context.push('/screens/new'); })
                  : GridView.builder(
                      padding: const EdgeInsets.all(12),
                      gridDelegate: const SliverGridDelegateWithMaxCrossAxisExtent(maxCrossAxisExtent: 220, mainAxisSpacing: 10, crossAxisSpacing: 10, childAspectRatio: 1.15),
                      itemCount: all.length,
                      itemBuilder: (_, i) {
                        final s = all[i];
                        final on = chosen.contains(s.screenId);
                        return InkWell(
                          onTap: () => set(() => on ? chosen.remove(s.screenId) : chosen.add(s.screenId)),
                          child: Container(
                            decoration: BoxDecoration(borderRadius: BorderRadius.circular(14), border: Border.all(color: on ? AppColors.teal : Colors.transparent, width: 3)),
                            padding: const EdgeInsets.all(4),
                            child: Column(children: [
                              Expanded(child: Stack(children: [
                                Positioned.fill(child: s.layout == null ? const ColoredBox(color: AppColors.charcoal) : LayoutThumbnail(layout: s.layout!, media: NetworkMediaSource(s.media))),
                                if (on) Positioned(top: 4, right: 4, child: CircleAvatar(radius: 12, backgroundColor: AppColors.teal, child: Text('${chosen.toList().indexOf(s.screenId) + 1}', style: const TextStyle(color: Colors.white, fontSize: 12)))),
                              ])),
                              const SizedBox(height: 4),
                              Text(s.screenName, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                            ]),
                          ),
                        );
                      },
                    ),
            ),
          ]),
        ),
      ),
    );
    if (r == null) return;
    setState(() {
      for (final id in r) {
        final s = screens[id]!;
        items.add(PlaylistItem(screenId: id, screenName: s.screenName, orientation: s.orientation, screenStatus: s.status, effectiveDuration: s.defaultDuration));
      }
    });
  }

  Future<void> _offerAssign() async {
    if (playlistId == null) return;
    final all = await ref.read(devicesRepoProvider).list();
    if (!mounted) return;
    if (all.items.isEmpty) {
      final go = await confirmDialog(context, title: 'Show it on a TV', message: 'You have no connected displays yet. Connect one now?', confirm: 'Connect a TV');
      if (go && mounted) context.push('/devices/add');
      return;
    }
    final chosen = <int>{for (final d in all.items) if (d.assignments.any((a) => a.playlistId == playlistId)) d.deviceId};
    final r = await showModalBottomSheet<Set<int>>(
      context: context,
      builder: (c) => StatefulBuilder(
        builder: (c, set) => SafeArea(
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            ListTile(title: const Text('Play on these displays', style: TextStyle(fontWeight: FontWeight.w700)), trailing: FilledButton(onPressed: () => Navigator.pop(c, chosen), child: const Text('Apply'))),
            for (final d in all.items)
              CheckboxListTile(
                value: chosen.contains(d.deviceId),
                secondary: Icon(Icons.tv_rounded, color: d.isOnline ? AppColors.success : AppColors.mediumGrey),
                title: Text(d.deviceName),
                subtitle: Text(d.assignments.isEmpty ? 'Nothing assigned' : 'Now: ${d.assignments.map((a) => a.playlistName).join(', ')}'),
                onChanged: (v) => set(() => v! ? chosen.add(d.deviceId) : chosen.remove(d.deviceId)),
              ),
          ]),
        ),
      ),
    );
    if (r == null || !mounted) return;
    final repo = ref.read(devicesRepoProvider);
    await runWithProgress(context, () async {
      for (final d in all.items) {
        final has = d.assignments.any((a) => a.playlistId == playlistId);
        final want = r.contains(d.deviceId);
        if (has == want) continue;
        final others = d.assignments.where((a) => a.playlistId != playlistId).toList();
        final ids = [if (want) playlistId!, ...others.map((a) => a.playlistId)];
        final def = want ? playlistId : (others.where((a) => a.isDefault).firstOrNull?.playlistId ?? others.firstOrNull?.playlistId);
        await repo.assign(d.deviceId, ids, def);
      }
      return true;
    }, success: 'Displays updated');
    final p = await ref.read(playlistsRepoProvider).details(playlistId!);
    if (mounted) setState(() => devices = p.devices);
    invalidateContent(ref);
  }
}
