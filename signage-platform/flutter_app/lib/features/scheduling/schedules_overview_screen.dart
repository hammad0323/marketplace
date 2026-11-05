import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../core/constants/app_colors.dart';
import '../../core/widgets/common.dart';
import '../../models/playlist.dart';
import '../../providers/providers.dart';

final _allSchedulesProvider = FutureProvider.autoDispose<List<PlaylistSchedule>>((ref) => ref.watch(playlistsRepoProvider).allSchedules());

/// Day timeline of every time slot, so overlaps are easy to spot.
class SchedulesOverviewScreen extends ConsumerStatefulWidget {
  const SchedulesOverviewScreen({super.key});
  @override
  ConsumerState<SchedulesOverviewScreen> createState() => _SchedulesOverviewScreenState();
}

class _SchedulesOverviewScreenState extends ConsumerState<SchedulesOverviewScreen> {
  int day = DateTime.now().weekday;
  static const palette = [AppColors.teal, Color(0xFF5E35B1), Color(0xFFFB8C00), Color(0xFF1E88E5), Color(0xFFD81B60), Color(0xFF43A047)];

  double _h(String? t, double def) => t == null ? def : int.parse(t.substring(0, 2)) + int.parse(t.substring(3, 5)) / 60;

  @override
  Widget build(BuildContext context) {
    final data = ref.watch(_allSchedulesProvider);
    const names = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    return Scaffold(
      appBar: AppBar(title: const Text('Schedules')),
      body: data.when(
        loading: () => const LoadingView(),
        error: (e, _) => ErrorView(error: e),
        data: (all) {
          // The date of the selected weekday in the current week, so one-off and special events only show when they apply.
          final now = DateTime.now();
          final date = DateTime(now.year, now.month, now.day).add(Duration(days: day - now.weekday)).toIso8601String().substring(0, 10);
          final today = all.where((s) =>
              s.status == 'active' &&
              (s.daysOfWeek == null || s.daysOfWeek!.contains(day)) &&
              (s.startDate == null || s.startDate!.compareTo(date) <= 0) &&
              (s.endDate == null || s.endDate!.compareTo(date) >= 0)).toList()
            ..sort((a, b) => b.priority.compareTo(a.priority));
          final colors = <int, Color>{};
          for (final s in all) {
            colors.putIfAbsent(s.playlistId ?? 0, () => palette[colors.length % palette.length]);
          }
          return ListView(padding: const EdgeInsets.all(16), children: [
            SegmentedButton<int>(
              showSelectedIcon: false,
              segments: [for (var d = 1; d <= 7; d++) ButtonSegment(value: d, label: Text(names[d - 1], style: const TextStyle(fontSize: 12)))],
              selected: {day},
              onSelectionChanged: (v) => setState(() => day = v.first),
            ),
            const SizedBox(height: 16),
            if (today.isEmpty)
              EmptyState(icon: Icons.event_busy_outlined, title: 'No time slots on $date', message: 'Displays play their default playlist.')
            else
              AppCard(
                child: Column(children: [
                  SizedBox(
                    height: 24,
                    child: LayoutBuilder(builder: (_, box) => Stack(children: [
                          for (final h in [0, 6, 12, 18, 24])
                            Positioned(left: (box.maxWidth - 24) * h / 24, child: Text('${h.toString().padLeft(2, '0')}h', style: const TextStyle(fontSize: 11, color: AppColors.mediumGrey))),
                        ])),
                  ),
                  for (final s in today)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 6),
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text('${s.playlistName ?? ''} · ${s.scheduleName ?? ''} · P${s.priority}', style: const TextStyle(fontSize: 12, fontWeight: FontWeight.w600)),
                        const SizedBox(height: 4),
                        LayoutBuilder(builder: (_, box) {
                          final a = _h(s.startTime, 0), b = _h(s.endTime, 24);
                          final segs = b >= a ? [(a, b)] : [(a, 24.0), (0.0, b)];
                          return Container(
                            height: 18,
                            decoration: BoxDecoration(color: Theme.of(context).colorScheme.surfaceContainerHigh, borderRadius: BorderRadius.circular(6)),
                            child: Stack(children: [
                              for (final sg in segs)
                                Positioned(
                                  left: box.maxWidth * sg.$1 / 24,
                                  width: box.maxWidth * (sg.$2 - sg.$1) / 24,
                                  top: 0, bottom: 0,
                                  child: Container(decoration: BoxDecoration(color: colors[s.playlistId ?? 0], borderRadius: BorderRadius.circular(6))),
                                ),
                            ]),
                          );
                        }),
                      ]),
                    ),
                ]),
              ),
            const SizedBox(height: 16),
            const SectionHeader('All time slots'),
            for (final s in all)
              Card(
                margin: const EdgeInsets.only(bottom: 8),
                child: ListTile(
                  leading: CircleAvatar(backgroundColor: colors[s.playlistId ?? 0], radius: 8),
                  title: Text('${s.playlistName} — ${s.scheduleName ?? s.timeLabel}'),
                  subtitle: Text('${s.timeLabel} · ${s.daysLabel}${s.startDate != null ? ' · ${s.startDate}${s.endDate != null && s.endDate != s.startDate ? ' → ${s.endDate}' : ''}' : ''} · priority ${s.priority}${s.scheduleType == 'special_event' ? ' · special event' : ''}'),
                  trailing: const Icon(Icons.chevron_right),
                  onTap: () => context.push('/playlists/${s.playlistId}/edit'),
                ),
              ),
            const SizedBox(height: 8),
            const Text('When slots overlap, special events win, then the higher priority, then the playlist priority, then the shorter slot.',
                style: TextStyle(color: AppColors.mediumGrey, fontSize: 12)),
          ]);
        },
      ),
    );
  }
}
