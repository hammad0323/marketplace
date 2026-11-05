import 'package:flutter_test/flutter_test.dart';
import 'package:signage_app/features/signage_player/scheduler.dart';
import 'package:timezone/data/latest_all.dart' as tzdata;

Map<String, dynamic> sch(int id, String start, String end, {List<int>? days, int priority = 10, String type = 'recurring', String? tz, String? sd, String? ed}) => {
      'schedule_id': id, 'schedule_name': 'S$id', 'schedule_type': type, 'start_time': start, 'end_time': end,
      'days_of_week': days, 'priority': priority, 'timezone': tz, 'start_date': sd, 'end_date': ed, 'status': 'active',
    };

void main() {
  setUpAll(tzdata.initializeTimeZones);
  const dubai = 'Asia/Dubai';
  // Monday 9 Nov 2026
  DateTime utc(int h, [int m = 0]) => DateTime.utc(2026, 11, 9, h, m);

  final store = PlaylistCandidate(playlistId: 1, priority: 0, isDefault: true, schedules: [
    sch(1, '08:00:00', '12:00:00'), sch(2, '12:00:00', '17:00:00'),
  ]);
  final evening = PlaylistCandidate(playlistId: 2, priority: 0, isDefault: false, schedules: [
    sch(3, '17:00:00', '23:00:00', priority: 20),
    sch(4, '00:00:00', '23:59:59', type: 'special_event', priority: 50, sd: '2026-11-27', ed: '2026-11-27'),
  ]);

  test('morning slot in device time zone', () {
    final d = resolvePlaylist([store, evening], utc(5), dubai); // 09:00 Dubai
    expect(d.playlistId, 1);
    expect(d.scheduleId, 1);
  });

  test('evening slot wins at 19:00', () {
    expect(resolvePlaylist([store, evening], utc(15), dubai).playlistId, 2);
  });

  test('falls back to default playlist when nothing matches', () {
    final d = resolvePlaylist([store, evening], utc(21), dubai); // 01:00 Dubai
    expect(d.playlistId, 1);
    expect(d.reason, 'Default playlist');
  });

  test('special event overrides everything on its date', () {
    final d = resolvePlaylist([store, evening], DateTime.utc(2026, 11, 27, 6), dubai);
    expect(d.playlistId, 2);
    expect(d.scheduleId, 4);
  });

  test('higher priority wins on overlap', () {
    final lunch = PlaylistCandidate(playlistId: 3, priority: 0, isDefault: false, schedules: [sch(5, '12:00:00', '14:00:00', days: [1, 2, 3, 4, 5], priority: 30)]);
    expect(resolvePlaylist([store, lunch], utc(9), dubai).playlistId, 3); // Mon 13:00
    expect(resolvePlaylist([store, lunch], DateTime.utc(2026, 11, 14, 9), dubai).playlistId, 1); // Sat 13:00 → weekdays only
  });

  test('overnight window crossing midnight respects the starting day', () {
    final night = PlaylistCandidate(playlistId: 9, priority: 0, isDefault: false, schedules: [sch(7, '22:00:00', '02:00:00', days: [5], priority: 40)]);
    // Fri 23:00 Dubai → active
    expect(resolvePlaylist([night], DateTime.utc(2026, 11, 13, 19), dubai).playlistId, 9);
    // Sat 01:00 Dubai → still Friday's window
    expect(resolvePlaylist([night], DateTime.utc(2026, 11, 13, 21), dubai).playlistId, 9);
    // Sun 01:00 → Saturday's window not scheduled; unscheduled? night has schedules → idle
    expect(resolvePlaylist([night], DateTime.utc(2026, 11, 14, 21), dubai).playlistId, isNull);
  });

  test('date range bounds', () {
    final promo = PlaylistCandidate(playlistId: 5, priority: 0, isDefault: false, schedules: [sch(8, '00:00:00', '23:59:59', sd: '2026-12-01', ed: '2026-12-31')]);
    expect(resolvePlaylist([promo], utc(8), dubai).playlistId, isNull);
    expect(resolvePlaylist([promo], DateTime.utc(2026, 12, 15, 8), dubai).playlistId, 5);
  });

  test('schedule-specific time zone overrides device zone', () {
    final ny = PlaylistCandidate(playlistId: 6, priority: 0, isDefault: false, schedules: [sch(9, '09:00:00', '10:00:00', tz: 'America/New_York')]);
    expect(resolvePlaylist([ny], DateTime.utc(2026, 11, 9, 14, 30), dubai).playlistId, 6); // 09:30 New York
  });

  test('unscheduled playlist plays when there is no default', () {
    final plain = PlaylistCandidate(playlistId: 7, priority: 0, isDefault: false, schedules: const []);
    expect(resolvePlaylist([plain], utc(3), dubai).playlistId, 7);
  });
}
