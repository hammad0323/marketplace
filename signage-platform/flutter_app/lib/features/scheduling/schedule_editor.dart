import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:timezone/timezone.dart' as tz;

import '../../core/constants/app_colors.dart';
import '../../core/widgets/common.dart';
import '../../models/playlist.dart';
import '../../providers/providers.dart';

/// Add / edit a time slot for a playlist. Returns the saved schedule.
Future<PlaylistSchedule?> showScheduleEditor(BuildContext context, WidgetRef ref, int playlistId, PlaylistSchedule? existing) {
  return showModalBottomSheet<PlaylistSchedule>(
    context: context,
    isScrollControlled: true,
    builder: (_) => FractionallySizedBox(heightFactor: 0.9, child: _ScheduleEditor(playlistId: playlistId, existing: existing)),
  );
}

class _ScheduleEditor extends ConsumerStatefulWidget {
  const _ScheduleEditor({required this.playlistId, this.existing});
  final int playlistId;
  final PlaylistSchedule? existing;
  @override
  ConsumerState<_ScheduleEditor> createState() => _ScheduleEditorState();
}

class _ScheduleEditorState extends ConsumerState<_ScheduleEditor> {
  late final s = widget.existing == null
      ? PlaylistSchedule(scheduleName: 'Morning promotion', startTime: '08:00:00', endTime: '12:00:00')
      : PlaylistSchedule.fromJson({...widget.existing!.toJson(), 'schedule_id': widget.existing!.scheduleId, 'days_of_week_json': widget.existing!.daysOfWeek});
  late final name = TextEditingController(text: s.scheduleName);
  bool allDay = false, saving = false;

  @override
  void initState() {
    super.initState();
    allDay = s.startTime == null && s.endTime == null;
  }

  TimeOfDay _t(String? v, int defH) => v == null ? TimeOfDay(hour: defH, minute: 0) : TimeOfDay(hour: int.parse(v.substring(0, 2)), minute: int.parse(v.substring(3, 5)));
  String _fmt(TimeOfDay t) => '${t.hour.toString().padLeft(2, '0')}:${t.minute.toString().padLeft(2, '0')}:00';
  String _d(DateTime d) => d.toIso8601String().substring(0, 10);

  @override
  Widget build(BuildContext context) {
    const dayNames = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    final days = s.daysOfWeek ?? [1, 2, 3, 4, 5, 6, 7];
    final crosses = !allDay && s.startTime != null && s.endTime != null && s.endTime!.compareTo(s.startTime!) < 0;
    return Column(children: [
      Padding(
        padding: const EdgeInsets.fromLTRB(20, 0, 12, 0),
        child: Row(children: [
          Expanded(child: Text(widget.existing == null ? 'New time slot' : 'Edit time slot', style: Theme.of(context).textTheme.titleLarge)),
          FilledButton(
            style: FilledButton.styleFrom(minimumSize: const Size(0, 42)),
            onPressed: saving ? null : () async {
              setState(() => saving = true);
              s.scheduleName = name.text.trim().isEmpty ? null : name.text.trim();
              if (allDay) { s.startTime = null; s.endTime = null; }
              try {
                final r = await ref.read(playlistsRepoProvider).saveSchedule(widget.playlistId, s);
                if (context.mounted) Navigator.pop(context, r);
                invalidateContent(ref);
              } catch (e) {
                if (context.mounted) showToast(context, e.toString(), error: true);
                setState(() => saving = false);
              }
            },
            child: const Text('Save'),
          ),
        ]),
      ),
      Expanded(
        child: ListView(padding: const EdgeInsets.all(20), children: [
          TextField(controller: name, decoration: const InputDecoration(labelText: 'Name (e.g. Lunch specials)')),
          const SizedBox(height: 16),
          SegmentedButton<String>(
            showSelectedIcon: false,
            segments: const [
              ButtonSegment(value: 'recurring', label: Text('Repeats')),
              ButtonSegment(value: 'one_time', label: Text('One-off')),
              ButtonSegment(value: 'special_event', label: Text('Special event')),
            ],
            selected: {s.scheduleType},
            onSelectionChanged: (v) => setState(() => s.scheduleType = v.first),
          ),
          if (s.scheduleType == 'special_event')
            const Padding(padding: EdgeInsets.only(top: 6), child: Text('Special events override every regular schedule while active.', style: TextStyle(color: AppColors.mediumGrey, fontSize: 12))),
          const SizedBox(height: 16),
          SwitchListTile(contentPadding: EdgeInsets.zero, title: const Text('All day'), value: allDay, onChanged: (v) => setState(() => allDay = v)),
          if (!allDay)
            Row(children: [
              Expanded(child: _pick('Start', s.startTime?.substring(0, 5) ?? '08:00', () async {
                final t = await showTimePicker(context: context, initialTime: _t(s.startTime, 8));
                if (t != null) setState(() => s.startTime = _fmt(t));
              })),
              const SizedBox(width: 12),
              Expanded(child: _pick('End', s.endTime?.substring(0, 5) ?? '17:00', () async {
                final t = await showTimePicker(context: context, initialTime: _t(s.endTime, 17));
                if (t != null) setState(() => s.endTime = _fmt(t));
              })),
            ]),
          if (crosses) const Padding(padding: EdgeInsets.only(top: 6), child: Text('Runs overnight (ends the next day).', style: TextStyle(color: AppColors.mediumGrey, fontSize: 12))),
          const SizedBox(height: 16),
          const Text('Days', style: TextStyle(fontWeight: FontWeight.w600)),
          const SizedBox(height: 8),
          Wrap(spacing: 6, children: [
            for (var d = 1; d <= 7; d++)
              FilterChip(
                showCheckmark: false,
                label: Text(dayNames[d - 1]),
                selected: days.contains(d),
                onSelected: (on) => setState(() {
                  final l = [...days];
                  on ? l.add(d) : l.remove(d);
                  if (l.isEmpty) return;
                  l.sort();
                  s.daysOfWeek = l.length == 7 ? null : l;
                }),
              ),
          ]),
          Wrap(spacing: 8, children: [
            TextButton(onPressed: () => setState(() => s.daysOfWeek = null), child: const Text('Every day')),
            TextButton(onPressed: () => setState(() => s.daysOfWeek = [1, 2, 3, 4, 5]), child: const Text('Weekdays')),
            TextButton(onPressed: () => setState(() => s.daysOfWeek = [6, 7]), child: const Text('Weekends')),
          ]),
          const SizedBox(height: 12),
          Row(children: [
            Expanded(child: _pick('Start date', s.startDate ?? 'Any', () async {
              final d = await showDatePicker(context: context, firstDate: DateTime(2020), lastDate: DateTime(2100), initialDate: DateTime.tryParse(s.startDate ?? '') ?? DateTime.now());
              setState(() => s.startDate = d == null ? null : _d(d));
            })),
            const SizedBox(width: 12),
            Expanded(child: _pick('End date', s.endDate ?? 'No end', () async {
              final d = await showDatePicker(context: context, firstDate: DateTime(2020), lastDate: DateTime(2100), initialDate: DateTime.tryParse(s.endDate ?? '') ?? DateTime.now());
              setState(() => s.endDate = d == null ? null : _d(d));
            })),
          ]),
          const SizedBox(height: 16),
          Row(children: [
            const Expanded(child: Text('Priority (higher wins when slots overlap)')),
            IconButton(onPressed: () => setState(() => s.priority -= 5), icon: const Icon(Icons.remove_circle_outline)),
            Text('${s.priority}', style: const TextStyle(fontWeight: FontWeight.w700, fontSize: 16)),
            IconButton(onPressed: () => setState(() => s.priority += 5), icon: const Icon(Icons.add_circle_outline)),
          ]),
          const SizedBox(height: 8),
          DropdownButtonFormField<String?>(
            initialValue: s.timezone,
            isExpanded: true,
            decoration: const InputDecoration(labelText: 'Time zone'),
            items: [
              const DropdownMenuItem(value: null, child: Text("Display's own time zone")),
              for (final z in tz.timeZoneDatabase.locations.keys.where((k) => k.contains('/')).toList()..sort()) DropdownMenuItem(value: z, child: Text(z)),
            ],
            onChanged: (v) => setState(() => s.timezone = v),
          ),
          SwitchListTile(contentPadding: EdgeInsets.zero, title: const Text('Active'), value: s.status == 'active', onChanged: (v) => setState(() => s.status = v ? 'active' : 'inactive')),
        ]),
      ),
    ]);
  }

  Widget _pick(String label, String value, VoidCallback onTap) => InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: InputDecorator(decoration: InputDecoration(labelText: label), child: Text(value, style: const TextStyle(fontWeight: FontWeight.w600))),
      );
}
