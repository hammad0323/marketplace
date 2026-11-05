import '../core/utils/json.dart';

class DeviceAssignment {
  DeviceAssignment({required this.playlistId, required this.playlistName, required this.isDefault, this.status, this.publishedVersion});
  final int playlistId;
  final String playlistName;
  final bool isDefault;
  final String? status;
  final int? publishedVersion;
  factory DeviceAssignment.fromJson(Map<String, dynamic> j) => DeviceAssignment(
        playlistId: asInt(j['playlist_id']), playlistName: asString(j['playlist_name']), isDefault: asBool(j['is_default']),
        status: j['status']?.toString(), publishedVersion: asIntOrNull(j['published_version']),
      );
}

class Device {
  Device({
    required this.deviceId, required this.deviceName, required this.deviceCode, required this.deviceType, required this.status,
    this.resolution, this.orientation = 'auto', this.appVersion, this.model, this.manufacturer, this.osVersion, this.timezone,
    this.isOnline = false, this.lastHeartbeat, this.lastSyncAt, this.playbackState = 'idle', this.currentPlaylistName,
    this.currentScreenName, this.locationId, this.locationName, this.storageFreeMb, this.storageTotalMb,
    this.commands = const [], this.assignments = const [], this.recentLogs = const [], this.ownerName,
  });
  final int deviceId;
  final String deviceName, deviceCode, deviceType, status, orientation, playbackState;
  final String? resolution, appVersion, model, manufacturer, osVersion, timezone, currentPlaylistName, currentScreenName, locationName, ownerName;
  final bool isOnline;
  final DateTime? lastHeartbeat, lastSyncAt;
  final int? locationId, storageFreeMb, storageTotalMb;
  final List<String> commands;
  final List<DeviceAssignment> assignments;
  final List<Map<String, dynamic>> recentLogs;

  String get typeLabel => switch (deviceType) {
        'android_tv' => 'Android TV',
        'android_box' => 'Android TV box',
        'tablet' => 'Tablet',
        'phone' => 'Phone',
        _ => 'Display',
      };

  factory Device.fromJson(Map<String, dynamic> j) => Device(
        deviceId: asInt(j['device_id']), deviceName: asString(j['device_name']), deviceCode: asString(j['device_code']),
        deviceType: asString(j['device_type'], 'android_tv'), status: asString(j['status'], 'active'), resolution: j['resolution']?.toString(),
        orientation: asString(j['orientation'], 'auto'), appVersion: j['app_version']?.toString(), model: j['model']?.toString(),
        manufacturer: j['manufacturer']?.toString(), osVersion: j['os_version']?.toString(), timezone: j['timezone']?.toString(),
        isOnline: asBool(j['is_online']), lastHeartbeat: asDate(j['last_heartbeat']), lastSyncAt: asDate(j['last_sync_at']),
        playbackState: asString(j['playback_state'], 'idle'), currentPlaylistName: j['current_playlist_name']?.toString(),
        currentScreenName: j['current_screen_name']?.toString(), locationId: asIntOrNull(j['location_id']), locationName: j['location_name']?.toString(),
        storageFreeMb: asIntOrNull(j['storage_free_mb']), storageTotalMb: asIntOrNull(j['storage_total_mb']),
        commands: asList(asMap(j['capabilities'])['commands']).map((e) => e.toString()).toList(),
        assignments: asList(j['assignments']).map((e) => DeviceAssignment.fromJson(asMap(e))).toList(),
        recentLogs: asList(j['recent_logs']).map((e) => asMap(e)).toList(), ownerName: j['owner_name']?.toString(),
      );
}

class LocationModel {
  LocationModel({required this.locationId, required this.locationName, this.address, this.city, this.deviceCount = 0});
  final int locationId;
  final String locationName;
  final String? address, city;
  final int deviceCount;
  factory LocationModel.fromJson(Map<String, dynamic> j) => LocationModel(
      locationId: asInt(j['location_id']), locationName: asString(j['location_name']), address: j['address']?.toString(),
      city: j['city']?.toString(), deviceCount: asInt(j['device_count']));
}
