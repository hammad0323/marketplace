import 'package:dio/dio.dart' show MultipartFile;

import '../core/network/api_client.dart';
import '../core/utils/json.dart';
import '../models/device.dart';
import '../models/layout.dart';
import '../models/media.dart';
import '../models/misc.dart';
import '../models/playlist.dart';
import '../models/screen.dart';
import '../models/template.dart';
import '../models/user.dart';

/// Page of results + meta.
class Paged<T> {
  Paged(this.items, this.total, this.page, this.totalPages, [this.extra = const {}]);
  final List<T> items;
  final int total, page, totalPages;
  final Map<String, dynamic> extra;
}

Paged<T> _paged<T>(ApiResponse r, T Function(Map<String, dynamic>) f) {
  final m = r.meta ?? {};
  return Paged(asList(r.data).map((e) => f(asMap(e))).toList(), asInt(m['total']), asInt(m['page'], 1), asInt(m['total_pages'], 1), r.raw);
}

class AuthRepository {
  AuthRepository(this.api);
  final ApiClient api;

  Future<Map<String, dynamic>> login(String email, String password, String deviceLabel) async =>
      asMap((await api.post('auth/login.php', {'email': email, 'password': password, 'device_label': deviceLabel})).data);
  Future<Map<String, dynamic>> profile() async => asMap((await api.get('auth/profile.php')).data);
  Future<void> logout() => api.post('auth/logout.php');
  Future<String> forgotPassword(String email) async => (await api.post('auth/forgot-password.php', {'email': email})).message;
  Future<String> resetPassword(String code, String password) async =>
      (await api.post('auth/reset-password.php', {'token': code, 'new_password': password})).message;
  Future<String> changePassword(String current, String next) async =>
      (await api.post('auth/change-password.php', {'current_password': current, 'new_password': next})).message;
  Future<AppUser> updateProfile(Map<String, dynamic> data) async =>
      AppUser.fromJson(asMap(asMap((await api.post('auth/update-profile.php', data)).data)['user']));
  Future<Map<String, dynamic>> dashboard() async => asMap((await api.get('users/dashboard.php')).data);
  Future<Map<String, dynamic>> saveSettings(Map<String, dynamic> s) async => asMap((await api.post('users/settings.php', {'settings': s})).data);
}

class ScreensRepository {
  ScreensRepository(this.api);
  final ApiClient api;

  Future<Paged<ScreenModel>> list({String? q, String? status, int? folderId, bool favorites = false, int page = 1, bool withLayout = true}) async =>
      _paged(await api.get('screens/list.php', query: {'q': q, 'status': status, 'folder_id': folderId, 'favorites': favorites ? true : null, 'page': page, 'per_page': 50, 'with_layout': withLayout}), ScreenModel.fromJson);
  Future<ScreenModel> details(int id) async => ScreenModel.fromJson(asMap((await api.get('screens/details.php', query: {'screen_id': id})).data));
  Future<ApiResponse> create({required String name, int? templateId, String? orientation, int? width, int? height, int? folderId}) =>
      api.post('screens/create.php', {'screen_name': name, 'template_id': templateId, 'orientation': orientation, 'width': width, 'height': height, 'folder_id': folderId});
  Future<ScreenModel> update(int id, Map<String, dynamic> data) async => ScreenModel.fromJson(asMap((await api.post('screens/update.php', {'screen_id': id, ...data})).data));
  Future<ScreenModel> saveLayout(int id, LayoutDoc layout, int layoutVersion, {bool publish = false, bool force = false, String? name}) async =>
      ScreenModel.fromJson(asMap((await api.post('screens/save-layout.php', {
        'screen_id': id, 'layout_version': layoutVersion, 'layout': layout.toJson(), 'publish': publish, 'force': force, 'screen_name': name,
      })).data));
  Future<String> publish(int id) async => (await api.post('screens/publish.php', {'screen_id': id})).message;
  Future<ScreenModel> duplicate(int id) async => ScreenModel.fromJson(asMap((await api.post('screens/duplicate.php', {'screen_id': id})).data));
  Future<void> delete(int id) => api.post('screens/delete.php', {'screen_id': id});
  Future<List<ScreenFolder>> folders() async => asList((await api.get('screens/folders.php')).data).map((e) => ScreenFolder.fromJson(asMap(e))).toList();
  Future<void> folderAction(String action, {int? folderId, String? name}) => api.post('screens/folders.php', {'action': action, 'folder_id': folderId, 'folder_name': name});
  Future<void> saveAsTemplate(int screenId, String name) => api.post('templates/save-as-template.php', {'screen_id': screenId, 'template_name': name});
}

class TemplatesRepository {
  TemplatesRepository(this.api);
  final ApiClient api;
  Future<List<TemplateCategory>> categories() async => asList((await api.get('templates/categories.php')).data).map((e) => TemplateCategory.fromJson(asMap(e))).toList();
  Future<List<ScreenTemplate>> list({int? categoryId, String? orientation, String? q, bool favorites = false}) async =>
      asList((await api.get('templates/list.php', query: {'category_id': categoryId, 'orientation': orientation, 'q': q, 'favorites': favorites ? true : null})).data)
          .map((e) => ScreenTemplate.fromJson(asMap(e))).toList();
  Future<bool> toggleFavorite(int id) async => asBool(asMap((await api.post('templates/favorite.php', {'template_id': id})).data)['is_favorite']);
  Future<({List<WidgetTypeInfo> types, List<String> recent})> widgetTypes() async {
    final d = asMap((await api.get('widgets/types.php')).data);
    return (
      types: asList(d['types']).map((e) => WidgetTypeInfo.fromJson(asMap(e))).toList(),
      recent: asList(d['recent']).map((e) => e.toString()).toList(),
    );
  }
}

class MediaRepository {
  MediaRepository(this.api);
  final ApiClient api;
  Future<Paged<MediaItem>> list({String? type, int? folderId, String? q, int page = 1, bool includeGlobal = false}) async =>
      _paged(await api.get('media/list.php', query: {'file_type': type, 'folder_id': folderId, 'q': q, 'page': page, 'per_page': 60, 'include_global': includeGlobal}), MediaItem.fromJson);
  Future<MediaItem> upload(MultipartFile file, {int? folderId, bool asLogo = false, int? durationMs, void Function(double)? onProgress}) async =>
      MediaItem.fromJson(asMap((await api.upload('media/upload.php', file: file, fields: {
        'folder_id': ?folderId, if (asLogo) 'as_logo': 1, 'duration_ms': ?durationMs,
      }, onProgress: onProgress)).data));
  Future<void> delete(List<int> ids, {bool force = false}) => api.post('media/delete.php', {'media_ids': ids, 'force': force});
  Future<void> rename(int id, String name) => api.post('media/rename.php', {'media_id': id, 'original_name': name});
  Future<void> move(List<int> ids, int? folderId) => api.post('media/move.php', {'media_ids': ids, 'folder_id': folderId ?? 0});
  Future<void> createFolder(String name) => api.post('media/create-folder.php', {'folder_name': name});
  Future<void> renameFolder(int id, String name) => api.post('media/rename-folder.php', {'folder_id': id, 'folder_name': name});
  Future<void> deleteFolder(int id) => api.post('media/delete-folder.php', {'folder_id': id});
}

class PlaylistsRepository {
  PlaylistsRepository(this.api);
  final ApiClient api;
  Future<Paged<Playlist>> list({String? q}) async => _paged(await api.get('playlists/list.php', query: {'q': q, 'per_page': 100}), Playlist.fromJson);
  Future<Playlist> details(int id) async => Playlist.fromJson(asMap((await api.get('playlists/details.php', query: {'playlist_id': id})).data));
  Future<(Playlist, String)> save({int? playlistId, required Map<String, dynamic> settings, required List<PlaylistItem> items, bool publish = false}) async {
    final body = {...settings, 'items': items.map((i) => i.toJson()).toList(), 'publish': publish};
    final r = playlistId == null ? await api.post('playlists/create.php', body) : await api.post('playlists/update.php', {'playlist_id': playlistId, ...body});
    return (Playlist.fromJson(asMap(r.data)), r.message);
  }
  Future<String> publish(int id, {bool publishScreens = true}) async =>
      (await api.post('playlists/publish.php', {'playlist_id': id, 'publish_screens': publishScreens})).message;
  Future<Playlist> duplicate(int id) async => Playlist.fromJson(asMap((await api.post('playlists/duplicate.php', {'playlist_id': id})).data));
  Future<void> delete(int id) => api.post('playlists/delete.php', {'playlist_id': id});
  Future<Map<String, dynamic>> preview(int id) async => asMap((await api.get('playlists/preview.php', query: {'playlist_id': id, 'draft': true})).data);
  Future<PlaylistSchedule> saveSchedule(int playlistId, PlaylistSchedule s) async {
    final r = s.scheduleId == null
        ? await api.post('schedules/create.php', {'playlist_id': playlistId, ...s.toJson()})
        : await api.post('schedules/update.php', {'schedule_id': s.scheduleId, ...s.toJson()});
    return PlaylistSchedule.fromJson(asMap(r.data));
  }
  Future<void> deleteSchedule(int id) => api.post('schedules/delete.php', {'schedule_id': id});
  Future<List<PlaylistSchedule>> allSchedules() async => asList((await api.get('schedules/list.php')).data).map((e) => PlaylistSchedule.fromJson(asMap(e))).toList();
}

class DevicesRepository {
  DevicesRepository(this.api);
  final ApiClient api;
  Future<Paged<Device>> list({String? q}) async => _paged(await api.get('devices/list.php', query: {'q': q, 'per_page': 100}), Device.fromJson);
  Future<Device> details(int id) async => Device.fromJson(asMap((await api.get('devices/details.php', query: {'device_id': id})).data));
  Future<Device> pair(String code, String? name, {int? locationId, int? playlistId}) async =>
      Device.fromJson(asMap((await api.post('devices/pair.php', {'pairing_code': code, 'device_name': name, 'location_id': locationId, 'playlist_id': playlistId})).data));
  Future<Device> update(int id, Map<String, dynamic> data) async => Device.fromJson(asMap((await api.post('devices/update.php', {'device_id': id, ...data})).data));
  Future<(Device, String)> assign(int id, List<int> playlistIds, int? defaultId) async {
    final r = await api.post('devices/assign-playlist.php', {'device_id': id, 'playlist_ids': playlistIds, 'default_playlist_id': defaultId});
    return (Device.fromJson(asMap(r.data)), r.message);
  }
  Future<String> command(int id, String command) async => (await api.post('devices/command.php', {'device_id': id, 'command': command})).message;
  Future<void> unpair(int id, {bool remove = false}) => api.post('devices/unpair.php', {'device_id': id, 'remove': remove});
  Future<Map<String, dynamic>> resolve(int id) async => asMap((await api.get('schedules/resolve.php', query: {'device_id': id})).data);
  Future<List<LocationModel>> locations() async => asList((await api.get('locations/list.php')).data).map((e) => LocationModel.fromJson(asMap(e))).toList();
  Future<void> saveLocation(Map<String, dynamic> data) => api.post('locations/save.php', data);
  Future<void> deleteLocation(int id) => api.post('locations/delete.php', {'location_id': id});
}

class MiscRepository {
  MiscRepository(this.api);
  final ApiClient api;
  Future<Paged<AppNotification>> notifications() async => _paged(await api.get('notifications/list.php', query: {'per_page': 50}), AppNotification.fromJson);
  Future<void> markAllRead() => api.post('notifications/mark-read.php', {'all': true});
  Future<void> markRead(int id) => api.post('notifications/mark-read.php', {'notification_id': id});
  Future<List<EmergencyMessage>> emergencies() async => asList((await api.get('emergency/list.php')).data).map((e) => EmergencyMessage.fromJson(asMap(e))).toList();
  Future<String> broadcast(Map<String, dynamic> data) async => (await api.post('emergency/create.php', data)).message;
  Future<String> cancelEmergency(int id) async => (await api.post('emergency/cancel.php', {'message_id': id})).message;
  Future<List<Map<String, dynamic>>> uptime() async => asList((await api.get('reports/uptime.php')).data).map(asMap).toList();
  Future<Map<String, dynamic>> playbackReport(String from, String to) async => asMap((await api.get('reports/playback.php', query: {'from': from, 'to': to})).data);
}

class AdminRepository {
  AdminRepository(this.api);
  final ApiClient api;
  Future<Map<String, dynamic>> dashboard() async => asMap((await api.get('admin/dashboard.php')).data);
  Future<Paged<Map<String, dynamic>>> users({String? q, String? status, int page = 1}) async =>
      _paged(await api.get('admin/users.php', query: {'q': q, 'status': status, 'page': page, 'per_page': 50}), (m) => m);
  Future<Map<String, dynamic>> userDetails(int id) async => asMap((await api.get('admin/user-details.php', query: {'user_id': id})).data);
  Future<Map<String, dynamic>> createUser(Map<String, dynamic> data) async => asMap((await api.post('admin/create-user.php', data)).data);
  Future<Map<String, dynamic>> updateUser(Map<String, dynamic> data) async => asMap((await api.post('admin/update-user.php', data)).data);
  Future<void> updateLimits(Map<String, dynamic> data) => api.post('admin/update-user-limits.php', data);
  Future<String> setStatus(int id, String status) async => (await api.post('admin/update-user-status.php', {'user_id': id, 'status': status})).message;
  Future<void> deleteUser(int id) => api.post('admin/delete-user.php', {'user_id': id});
  Future<List<Plan>> plans() async => asList((await api.get('admin/plans.php')).data).map((e) => Plan.fromJson(asMap(e))).toList();
  Future<void> savePlan(Map<String, dynamic> data) => api.post('admin/plans.php', {'action': 'save', ...data});
  Future<void> deletePlan(int id) => api.post('admin/plans.php', {'action': 'delete', 'plan_id': id});
  Future<List<Map<String, dynamic>>> templates() async => asList((await api.get('admin/templates.php')).data).map(asMap).toList();
  Future<void> templateAction(String action, int id) => api.post('admin/templates.php', {'action': action, 'template_id': id});
  Future<List<WidgetTypeInfo>> widgetTypes() async => asList((await api.get('admin/widget-types.php')).data).map((e) => WidgetTypeInfo.fromJson(asMap(e))).toList();
  Future<void> updateWidgetType(Map<String, dynamic> data) => api.post('admin/widget-types.php', data);
  Future<Paged<Device>> devices({String? q}) async => _paged(await api.get('admin/devices.php', query: {'q': q, 'per_page': 100}), Device.fromJson);
  Future<Paged<MediaItem>> media({String? q}) async => _paged(await api.get('admin/media.php', query: {'q': q, 'per_page': 100}), MediaItem.fromJson);
  Future<void> mediaAction(int id, String action) => api.post('admin/media.php', {'media_id': id, 'action': action});
  Future<Paged<ActivityEntry>> activity({int page = 1, String? action}) async =>
      _paged(await api.get('admin/activity-logs.php', query: {'page': page, 'per_page': 50, 'action': action}), ActivityEntry.fromJson);
  Future<List<Map<String, dynamic>>> settings() async => asList((await api.get('settings/app.php')).data).map(asMap).toList();
  Future<void> saveSettings(Map<String, dynamic> s) => api.post('settings/app.php', {'settings': s});
  Future<List<Map<String, dynamic>>> permissions() async => asList((await api.get('admin/permissions.php')).data).map(asMap).toList();
}

/// Display codes: show a screen/playlist on any device by typing its code.
class DisplayCodesRepository {
  DisplayCodesRepository(this.api);
  final ApiClient api;
  Map<String, dynamic> _q(int? screenId, int? playlistId) => {'screen_id': ?screenId, 'playlist_id': ?playlistId};
  Future<Map<String, dynamic>> get({int? screenId, int? playlistId}) async => asMap((await api.get('display-codes/get.php', query: _q(screenId, playlistId))).data);
  Future<(Map<String, dynamic>, String)> set(String code, {int? screenId, int? playlistId}) async {
    final r = await api.post('display-codes/set.php', {..._q(screenId, playlistId), 'code': code});
    return (asMap(r.data), r.message);
  }
  Future<Map<String, dynamic>> regenerate({int? screenId, int? playlistId}) async => asMap((await api.post('display-codes/regenerate.php', _q(screenId, playlistId))).data);
}
