import 'package:timezone/timezone.dart' as tz;

import '../../core/utils/json.dart';

/// Dart port of the server schedule resolver (docs/ARCHITECTURE.md §4 and
/// backend/includes/schedule.php). Pure function → unit tested.
class ScheduleDecision {
  const ScheduleDecision(this.playlistId, this.reason, [this.scheduleId]);
  final int? playlistId;
  final String reason;
  final int? scheduleId;
}

class PlaylistCandidate {
  PlaylistCandidate({required this.playlistId, required this.priority, required this.isDefault, required this.schedules});
  final int playlistId;
  final int priority;
  final bool isDefault;
  final List<Map<String, dynamic>> schedules;
}

int effectivePriority(Map<String, dynamic> s) {
  var p = asInt(s['priority'], 10);
  if (s['schedule_type'] == 'special_event') p += 1000;
  if (s['schedule_type'] == 'one_time') p += 500;
  return p;
}

/// Local wall-clock time for [zone] (null/empty → device local time).
DateTime localTime(DateTime utcNow, String? zone) {
  if (zone == null || zone.isEmpty) return utcNow.toLocal();
  try {
    final t = tz.TZDateTime.from(utcNow, tz.getLocation(zone));
    return DateTime(t.year, t.month, t.day, t.hour, t.minute, t.second);
  } catch (_) {
    return utcNow.toLocal();
  }
}

String _two(int v) => v.toString().padLeft(2, '0');
int _secs(String t) {
  final p = t.split(':').map(int.parse).toList();
  return p[0] * 3600 + p[1] * 60 + (p.length > 2 ? p[2] : 0);
}

/// Returns the active window length in minutes, or null when inactive.
int? scheduleActive(Map<String, dynamic> s, DateTime utcNow, String? deviceTz) {
  if ((s['status'] ?? 'active') != 'active') return null;
  final zone = (s['timezone'] as String?)?.isNotEmpty == true ? s['timezone'] as String : deviceTz;
  final local = localTime(utcNow, zone);
  final date = '${local.year}-${_two(local.month)}-${_two(local.day)}';
  final sd = s['start_date']?.toString(), ed = s['end_date']?.toString();
  if (sd != null && sd.isNotEmpty && date.compareTo(sd) < 0) return null;
  if (ed != null && ed.isNotEmpty && date.compareTo(ed) > 0) return null;
  final daysRaw = s['days_of_week'] ?? s['days_of_week_json'];
  final days = daysRaw is List ? daysRaw.map((e) => asInt(e)).toList() : null;
  final start = _secs(asString(s['start_time'], '00:00:00'));
  final end = _secs(asString(s['end_time'], '23:59:59'));
  final now = local.hour * 3600 + local.minute * 60 + local.second;
  final dow = local.weekday;
  if (end >= start) {
    if (now < start || now > end) return null;
    if (days != null && days.isNotEmpty && !days.contains(dow)) return null;
    return (end - start) ~/ 60;
  }
  int dayCheck;
  if (now >= start) {
    dayCheck = dow;
  } else if (now <= end) {
    dayCheck = dow == 1 ? 7 : dow - 1;
  } else {
    return null;
  }
  if (days != null && days.isNotEmpty && !days.contains(dayCheck)) return null;
  return (86400 - start + end) ~/ 60;
}

ScheduleDecision resolvePlaylist(List<PlaylistCandidate> candidates, DateTime utcNow, String? deviceTz) {
  List<int>? bestKey;
  ScheduleDecision? best;
  bool greater(List<int> a, List<int> b) {
    for (var i = 0; i < a.length; i++) {
      if (a[i] != b[i]) return a[i] > b[i];
    }
    return false;
  }

  for (final c in candidates) {
    for (final s in c.schedules) {
      final len = scheduleActive(s, utcNow, deviceTz);
      if (len == null) continue;
      final key = [effectivePriority(s), c.priority, -len, -asInt(s['schedule_id'])];
      if (bestKey == null || greater(key, bestKey)) {
        bestKey = key;
        best = ScheduleDecision(c.playlistId, 'Scheduled: ${s['schedule_name'] ?? 'schedule #${s['schedule_id']}'}', asInt(s['schedule_id']));
      }
    }
  }
  if (best != null) return best;
  for (final c in candidates) {
    if (c.isDefault) return ScheduleDecision(c.playlistId, 'Default playlist');
  }
  for (final c in candidates) {
    if (c.schedules.isEmpty) return ScheduleDecision(c.playlistId, 'Unscheduled playlist');
  }
  return const ScheduleDecision(null, 'Nothing scheduled — idle screen');
}
