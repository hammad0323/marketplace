import '../core/utils/json.dart';

class PlaylistItem {
  PlaylistItem({
    this.itemId, required this.screenId, this.screenName = '', this.orientation = 'landscape', this.screenStatus = 'draft',
    this.duration, this.effectiveDuration = 10, this.transitionType = 'inherit', this.isEnabled = true, this.validFrom, this.validUntil,
  });
  int? itemId;
  int screenId;
  String screenName, orientation, screenStatus, transitionType;
  int? duration;
  int effectiveDuration;
  bool isEnabled;
  String? validFrom, validUntil;

  factory PlaylistItem.fromJson(Map<String, dynamic> j) => PlaylistItem(
        itemId: asIntOrNull(j['item_id']), screenId: asInt(j['screen_id']), screenName: asString(j['screen_name']),
        orientation: asString(j['orientation'], 'landscape'), screenStatus: asString(j['screen_status'], 'draft'),
        duration: asIntOrNull(j['duration']), effectiveDuration: asInt(j['effective_duration'], 10),
        transitionType: asString(j['transition_type'], 'inherit'), isEnabled: asBool(j['is_enabled'], true),
        validFrom: j['valid_from']?.toString(), validUntil: j['valid_until']?.toString(),
      );

  Map<String, dynamic> toJson() => {
        'screen_id': screenId, 'duration': duration, 'transition_type': transitionType, 'is_enabled': isEnabled,
        'valid_from': validFrom, 'valid_until': validUntil,
      };
}

class PlaylistSchedule {
  PlaylistSchedule({
    this.scheduleId, this.playlistId, this.scheduleName, this.scheduleType = 'recurring', this.startDate, this.endDate,
    this.startTime, this.endTime, this.daysOfWeek, this.timezone, this.priority = 10, this.status = 'active', this.playlistName,
  });
  int? scheduleId, playlistId;
  String? scheduleName, startDate, endDate, startTime, endTime, timezone, playlistName;
  String scheduleType, status;
  List<int>? daysOfWeek;
  int priority;

  factory PlaylistSchedule.fromJson(Map<String, dynamic> j) {
    final d = j['days_of_week_json'] ?? j['days_of_week'];
    return PlaylistSchedule(
      scheduleId: asIntOrNull(j['schedule_id']), playlistId: asIntOrNull(j['playlist_id']), scheduleName: j['schedule_name']?.toString(),
      scheduleType: asString(j['schedule_type'], 'recurring'), startDate: j['start_date']?.toString(), endDate: j['end_date']?.toString(),
      startTime: j['start_time']?.toString(), endTime: j['end_time']?.toString(),
      daysOfWeek: d is List ? d.map((e) => asInt(e)).toList() : null, timezone: j['timezone']?.toString(),
      priority: asInt(j['priority'], 10), status: asString(j['status'], 'active'), playlistName: j['playlist_name']?.toString(),
    );
  }

  Map<String, dynamic> toJson() => {
        'schedule_name': scheduleName, 'schedule_type': scheduleType, 'start_date': startDate, 'end_date': endDate,
        'start_time': startTime, 'end_time': endTime, 'days_of_week': daysOfWeek, 'timezone': timezone,
        'priority': priority, 'status': status,
      };

  String get timeLabel {
    String t(String? s) => s == null ? '' : s.substring(0, 5);
    if (startTime == null && endTime == null) return 'All day';
    return '${t(startTime ?? '00:00')} – ${t(endTime ?? '23:59')}';
  }

  String get daysLabel {
    if (daysOfWeek == null || daysOfWeek!.length == 7) return 'Every day';
    const n = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
    if ((daysOfWeek!..sort()).join() == '12345') return 'Weekdays';
    if (daysOfWeek!.join() == '67') return 'Weekends';
    return daysOfWeek!.map((d) => n[(d - 1).clamp(0, 6)]).join(', ');
  }
}

class Playlist {
  Playlist({
    required this.playlistId, required this.playlistName, this.description, this.loopEnabled = true, this.autoplay = true,
    this.shuffle = false, this.transitionType = 'fade', this.transitionDuration = 800, this.defaultDuration = 10, this.priority = 0,
    this.status = 'draft', this.publishedVersion, this.hasUnpublishedChanges = true, this.items = const [], this.schedules = const [],
    this.devices = const [], this.itemCount = 0, this.totalDuration = 0, this.deviceCount = 0, this.updatedAt,
  });
  final int playlistId;
  final String playlistName;
  final String? description;
  final bool loopEnabled, autoplay, shuffle, hasUnpublishedChanges;
  final String transitionType, status;
  final int transitionDuration, defaultDuration, priority, itemCount, totalDuration, deviceCount;
  final int? publishedVersion;
  final List<PlaylistItem> items;
  final List<PlaylistSchedule> schedules;
  final List<Map<String, dynamic>> devices;
  final DateTime? updatedAt;

  factory Playlist.fromJson(Map<String, dynamic> j) {
    final items = asList(j['items']).map((e) => PlaylistItem.fromJson(asMap(e))).toList();
    return Playlist(
      playlistId: asInt(j['playlist_id']), playlistName: asString(j['playlist_name']), description: j['description']?.toString(),
      loopEnabled: asBool(j['loop_enabled'], true), autoplay: asBool(j['autoplay'], true), shuffle: asBool(j['shuffle']),
      transitionType: asString(j['transition_type'], 'fade'), transitionDuration: asInt(j['transition_duration'], 800),
      defaultDuration: asInt(j['default_duration'], 10), priority: asInt(j['priority']), status: asString(j['status'], 'draft'),
      publishedVersion: asIntOrNull(j['published_version']), hasUnpublishedChanges: asBool(j['has_unpublished_changes'], true),
      items: items, schedules: asList(j['schedules']).map((e) => PlaylistSchedule.fromJson(asMap(e))).toList(),
      devices: asList(j['devices']).map((e) => asMap(e)).toList(),
      itemCount: asInt(j['item_count'], items.length),
      totalDuration: asInt(j['total_duration'], items.where((i) => i.isEnabled).fold(0, (a, i) => a + i.effectiveDuration)),
      deviceCount: asInt(j['device_count']), updatedAt: asDate(j['updated_at']),
    );
  }
}
