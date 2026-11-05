import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:ui' as ui;

import 'package:crypto/crypto.dart';
import 'package:device_info_plus/device_info_plus.dart';
import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:path_provider/path_provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:uuid/uuid.dart';

import '../../core/constants/app_config.dart';
import '../../core/network/api_client.dart';
import '../../core/storage/secure_store.dart';
import '../../core/utils/json.dart';
import '../widgets_library/render_context.dart';
import 'playback_view.dart';
import 'scheduler.dart';

enum PlayerPhase { starting, pairing, playing, error }

/// The signage player engine. Owns registration, pairing, sync, the offline
/// cache, schedule resolution, heartbeats, remote commands and queued logs.
///
/// Reliability rules:
///  * The last good content is persisted and played while offline.
///  * New versions are only applied once all their media is downloaded and verified.
///  * Every network error is swallowed, logged and retried on the next cycle.
class PlayerService extends ChangeNotifier {
  PlayerService({String? baseUrl}) : api = ApiClient(baseUrl: baseUrl) {
    _api = ApiClient(baseUrl: baseUrl, tokenProvider: () => _token);
  }

  final ApiClient api;
  late final ApiClient _api;
  String? _token;

  PlayerPhase phase = PlayerPhase.starting;
  String statusText = 'Starting…';
  String? deviceUid, deviceCode, deviceName, pairingCode;
  String? deviceTimezone;
  String orientation = 'auto';
  DateTime? pairingExpires, lastSync;
  bool online = false, paused = false;
  DateTime? identifyUntil;
  int restartCounter = 0;

  /// playlist_id → publication document (current playable version).
  Map<int, Map<String, dynamic>> docs = {};
  List<Map<String, dynamic>> assignments = [];
  List<Map<String, dynamic>> emergency = [];
  String syncToken = '';
  int heartbeatSeconds = 60, syncSeconds = 120;

  /// media_id → local file path / signed URL.
  final Map<int, String> localPaths = {};
  final Map<int, String> urls = {};

  ScheduleDecision decision = const ScheduleDecision(null, 'Starting');
  int? currentScreenId;
  String? currentVersion;

  final List<Map<String, dynamic>> _logQueue = [];
  final List<Map<String, dynamic>> _playQueue = [];
  Timer? _heartbeat, _sync, _pairPoll, _schedule;
  bool _syncing = false;
  Directory? _dir;

  MediaSource get mediaSource => CachedFileMediaSource(localPaths: localPaths, urls: urls);
  Map<String, dynamic>? get activeDoc => decision.playlistId == null ? null : docs[decision.playlistId];
  bool get identifying => identifyUntil != null && DateTime.now().isBefore(identifyUntil!);

  // ===================================================================== boot
  Future<void> start() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      deviceUid = prefs.getString('player_device_uid');
      if (deviceUid == null) {
        deviceUid = const Uuid().v4();
        await prefs.setString('player_device_uid', deviceUid!);
      }
      _token = await SecureStore.read('player_device_token');
      if (!kIsWeb) {
        _dir = Directory('${(await getApplicationSupportDirectory()).path}/signage_player');
        await Directory('${_dir!.path}/media').create(recursive: true);
      }
      await _loadState();
      if (docs.isNotEmpty) {
        // Offline-first: start playing cached content immediately.
        phase = PlayerPhase.playing;
        _resolve();
      }
      if (_token == null) await _register();
      await _afterAuth();
    } catch (e) {
      log('error', 'Startup failed: $e');
      if (docs.isEmpty) {
        phase = PlayerPhase.error;
        statusText = 'Cannot reach the server. Retrying…';
      }
      notifyListeners();
      Timer(const Duration(seconds: 20), start);
    }
  }

  Future<void> _afterAuth() async {
    try {
      final st = asMap((await _api.get('devices/pairing-status.php')).data);
      deviceCode = asString(st['device_code']);
      deviceName = asString(st['device_name']);
      online = true;
      if (asBool(st['is_paired'])) {
        phase = PlayerPhase.playing;
        await sync();
        _startTimers();
      } else {
        await _clearContent();
        await _beginPairing();
      }
    } on ApiException catch (e) {
      if (e.statusCode == 401) {
        // Token lost / device removed server-side → register again.
        _token = null;
        await _register();
        return _afterAuth();
      }
      online = false;
      if (docs.isNotEmpty) {
        _startTimers(); // keep playing cached content, keep retrying
      } else {
        rethrow;
      }
    }
    notifyListeners();
  }

  Future<Map<String, dynamic>> _deviceInfo() async {
    final view = ui.PlatformDispatcher.instance.views.first;
    final size = view.physicalSize;
    final info = <String, dynamic>{
      'device_uid': deviceUid,
      'app_version': AppConfig.playerVersion,
      'resolution': '${size.width.round()}x${size.height.round()}',
      'orientation': size.width >= size.height ? 'landscape' : 'portrait',
      'capabilities': {'commands': ['restart', 'pause', 'resume', 'sync', 'clear_cache', 'identify'], 'video': true, 'webview': !kIsWeb},
      'platform': kIsWeb ? 'web' : defaultTargetPlatform.name,
    };
    try {
      final di = DeviceInfoPlugin();
      if (!kIsWeb && defaultTargetPlatform == TargetPlatform.android) {
        final a = await di.androidInfo;
        final tv = a.systemFeatures.contains('android.software.leanback');
        info.addAll({
          'manufacturer': a.manufacturer, 'model': a.model, 'os_version': a.version.release,
          'device_type': tv ? (a.model.toLowerCase().contains('box') ? 'android_box' : 'android_tv') : (size.shortestSide / view.devicePixelRatio > 600 ? 'tablet' : 'phone'),
        });
      } else {
        info['device_type'] = 'other';
      }
    } catch (_) {}
    return info;
  }

  Future<void> _register() async {
    statusText = 'Registering this display…';
    notifyListeners();
    final r = asMap((await api.post('devices/register.php', await _deviceInfo())).data);
    _token = asString(r['device_token']);
    await SecureStore.write('player_device_token', _token);
    deviceCode = asString(r['device_code']);
    heartbeatSeconds = asInt(r['heartbeat_interval_seconds'], 60);
    syncSeconds = asInt(r['sync_interval_seconds'], 120);
  }

  // ================================================================== pairing
  Future<void> _beginPairing() async {
    phase = PlayerPhase.pairing;
    _stopTimers();
    try {
      final r = asMap((await _api.post('devices/generate-pairing-code.php')).data);
      pairingCode = asString(r['pairing_code']);
      pairingExpires = DateTime.now().add(Duration(seconds: asInt(r['expires_in'], 900)));
      deviceCode = asString(r['device_code'], deviceCode ?? '');
      statusText = 'Enter this code in the management app';
    } catch (e) {
      statusText = 'Waiting for network…';
    }
    notifyListeners();
    _pairPoll?.cancel();
    _pairPoll = Timer.periodic(const Duration(seconds: 5), (_) => _pollPairing());
  }

  Future<void> _pollPairing() async {
    try {
      if (pairingExpires == null || DateTime.now().isAfter(pairingExpires!.subtract(const Duration(seconds: 20)))) {
        await _beginPairing();
        return;
      }
      final st = asMap((await _api.get('devices/pairing-status.php')).data);
      online = true;
      if (asBool(st['is_paired'])) {
        _pairPoll?.cancel();
        deviceName = asString(st['device_name']);
        pairingCode = null;
        phase = PlayerPhase.playing;
        statusText = 'Connected! Downloading content…';
        notifyListeners();
        await sync();
        _startTimers();
      }
    } catch (_) {
      online = false;
      notifyListeners();
    }
  }

  // =================================================================== timers
  void _startTimers() {
    _stopTimers();
    _heartbeat = Timer.periodic(Duration(seconds: heartbeatSeconds.clamp(15, 3600)), (_) => heartbeat());
    _sync = Timer.periodic(Duration(seconds: syncSeconds.clamp(30, 3600)), (_) => sync());
    _schedule = Timer.periodic(const Duration(seconds: 20), (_) => _resolve());
    heartbeat();
  }

  void _stopTimers() {
    _heartbeat?.cancel();
    _sync?.cancel();
    _schedule?.cancel();
    _pairPoll?.cancel();
  }

  Future<void> heartbeat() async {
    try {
      final r = asMap((await _api.post('devices/heartbeat.php', {
        'sync_token': syncToken,
        'playback_state': paused ? 'paused' : (activeDoc == null ? 'idle' : 'playing'),
        'current_playlist_id': decision.playlistId,
        'current_screen_id': currentScreenId,
        'current_content_version': currentVersion,
        'app_version': AppConfig.playerVersion,
        ...await _storage(),
      })).data);
      online = true;
      if (!asBool(r['is_paired'], true)) {
        await _clearContent();
        await _beginPairing();
        return;
      }
      if (asBool(r['changed']) || asInt(r['pending_commands']) > 0) await sync();
      await _flushQueues();
    } on ApiException catch (e) {
      online = false;
      if (e.statusCode == 401) {
        _token = null;
        await SecureStore.write('player_device_token', null);
        _stopTimers();
        start();
      }
    } catch (_) {
      online = false;
    }
    notifyListeners();
  }

  Future<Map<String, dynamic>> _storage() async {
    if (kIsWeb || _dir == null) return {};
    try {
      final stat = await Process.run('df', ['-k', _dir!.path]);
      final line = stat.stdout.toString().trim().split('\n').last.split(RegExp(r'\s+'));
      return {'storage_total_mb': int.parse(line[1]) ~/ 1024, 'storage_free_mb': int.parse(line[3]) ~/ 1024};
    } catch (_) {
      return {};
    }
  }

  // ===================================================================== sync
  Future<void> sync() async {
    if (_syncing) return;
    _syncing = true;
    try {
      final have = docs.entries.map((e) => '${e.key}:${asInt(e.value['version'])}').join(',');
      final d = asMap((await _api.get('devices/sync.php', query: {'have': have})).data);
      online = true;
      final dev = asMap(d['device']);
      deviceName = asString(dev['device_name'], deviceName ?? '');
      deviceTimezone = dev['timezone']?.toString();
      orientation = asString(dev['orientation'], 'auto');
      if (!asBool(dev['is_paired'], true)) {
        await _clearContent();
        await _beginPairing();
        return;
      }
      final s = asMap(d['settings']);
      heartbeatSeconds = asInt(s['heartbeat_interval_seconds'], heartbeatSeconds);
      syncSeconds = asInt(s['sync_interval_seconds'], syncSeconds);
      assignments = asList(d['assignments']).map(asMap).toList();
      emergency = asList(d['emergency']).map(asMap).toList();

      // Download new versions; apply each only when all of its media is ready.
      for (final pl in asList(d['playlists']).map(asMap)) {
        final id = asInt(pl['playlist_id']);
        final ok = await _downloadMedia(pl);
        if (ok || !docs.containsKey(id)) {
          docs[id] = pl;
          log('sync', 'Playlist $id v${pl['version']} ready${ok ? '' : ' (some media pending)'}', {'playlist_id': id, 'version': pl['version']});
        } else {
          log('warning', 'Playlist $id v${pl['version']} download incomplete — keeping previous version');
        }
      }
      // Drop playlists that are no longer assigned.
      final assigned = assignments.map((a) => asInt(a['playlist_id'])).toSet();
      docs.removeWhere((k, _) => !assigned.contains(k));
      for (final a in assignments) {
        final id = asInt(a['playlist_id']);
        if (docs[id] != null && asInt(docs[id]!['version']) != asInt(a['version'])) {
          // Server says a different version is current but didn't send it (e.g. we skipped) → force next sync.
          syncToken = '';
        }
      }
      // Refresh signed URLs for media we keep.
      for (final doc in docs.values) {
        for (final m in asList(doc['media']).map(asMap)) {
          if (m['url'] != null) urls[asInt(m['media_id'])] = asString(m['url']);
        }
      }
      syncToken = asString(d['sync_token']);
      lastSync = DateTime.now();
      await _saveState();
      await _cleanupMedia();
      for (final c in asList(d['commands']).map(asMap)) {
        await _runCommand(c);
      }
      _resolve();
      if (phase != PlayerPhase.pairing) phase = PlayerPhase.playing;
    } on ApiException catch (e) {
      online = !e.isNetwork;
      if (e.statusCode == 401) {
        _token = null;
        start();
      }
    } catch (e) {
      online = false;
      log('error', 'Sync failed: $e');
    } finally {
      _syncing = false;
      notifyListeners();
    }
  }

  /// Downloads + verifies every media file of a publication. Returns true when all are present.
  Future<bool> _downloadMedia(Map<String, dynamic> doc) async {
    var allOk = true;
    for (final m in asList(doc['media']).map(asMap)) {
      final id = asInt(m['media_id']);
      final url = m['url']?.toString();
      if (url != null) urls[id] = url;
      if (kIsWeb || _dir == null || url == null) continue; // web streams directly
      final sum = asString(m['checksum_sha256']);
      final ext = _ext(asString(m['mime_type']));
      final path = '${_dir!.path}/media/${id}_${sum.isEmpty ? 'x' : sum.substring(0, 16)}.$ext';
      final f = File(path);
      if (await f.exists()) {
        localPaths[id] = path;
        continue;
      }
      final tmp = File('$path.part');
      try {
        await Dio().download(url, tmp.path, options: Options(receiveTimeout: const Duration(minutes: 30)));
        if (sum.isNotEmpty) {
          final digest = await sha256.bind(tmp.openRead()).first;
          if (digest.toString() != sum) throw Exception('checksum mismatch');
        }
        await tmp.rename(path);
        localPaths[id] = path;
      } catch (e) {
        allOk = false;
        if (await tmp.exists()) await tmp.delete();
        log('playback_error', 'Media $id download failed: $e', {'media_id': id});
      }
    }
    return allOk;
  }

  String _ext(String mime) => switch (mime) {
        'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'video/mp4' => 'mp4', 'application/pdf' => 'pdf', _ => 'jpg',
      };

  Future<void> _cleanupMedia() async {
    if (_dir == null) return;
    final keep = <String>{};
    for (final doc in docs.values) {
      for (final m in asList(doc['media']).map(asMap)) {
        final p = localPaths[asInt(m['media_id'])];
        if (p != null) keep.add(p);
      }
    }
    try {
      await for (final f in Directory('${_dir!.path}/media').list()) {
        if (f is File && !keep.contains(f.path) && !f.path.endsWith('.part')) await f.delete();
      }
    } catch (_) {}
    localPaths.removeWhere((_, p) => !keep.contains(p));
  }

  // ================================================================ schedule
  void _resolve() {
    final cands = assignments.where((a) => docs.containsKey(asInt(a['playlist_id']))).map((a) {
      final doc = docs[asInt(a['playlist_id'])]!;
      return PlaylistCandidate(
        playlistId: asInt(a['playlist_id']),
        priority: asInt(a['priority'], asInt(doc['priority'])),
        isDefault: asBool(a['is_default']),
        schedules: asList(doc['schedules']).map(asMap).toList(),
      );
    }).toList();
    if (cands.isEmpty && assignments.isEmpty && docs.isNotEmpty) {
      // Offline start before assignments are known: use cached docs.
      for (final e in docs.entries) {
        cands.add(PlaylistCandidate(playlistId: e.key, priority: asInt(e.value['priority']), isDefault: cands.isEmpty, schedules: asList(e.value['schedules']).map(asMap).toList()));
      }
    }
    final d = resolvePlaylist(cands, DateTime.now().toUtc(), deviceTimezone);
    if (d.playlistId != decision.playlistId) {
      log('info', 'Now playing: ${d.playlistId ?? 'idle'} (${d.reason})');
      decision = d;
      notifyListeners();
    } else {
      decision = d;
    }
    // Expire emergency messages locally even when offline.
    final now = DateTime.now();
    final before = emergency.length;
    emergency.removeWhere((e) => asDate(e['ends_at']) != null && asDate(e['ends_at'])!.isBefore(now));
    if (emergency.length != before) notifyListeners();
  }

  // ================================================================ commands
  Future<void> _runCommand(Map<String, dynamic> c) async {
    final cmd = asString(c['command']);
    var result = 'ok';
    var status = 'completed';
    try {
      switch (cmd) {
        case 'pause':
          paused = true;
        case 'resume':
          paused = false;
        case 'identify':
          identifyUntil = DateTime.now().add(const Duration(seconds: 20));
          Timer(const Duration(seconds: 21), notifyListeners);
        case 'sync':
          syncToken = '';
          Timer(const Duration(seconds: 1), sync);
        case 'clear_cache':
          await _clearContent(keepPairing: true);
          Timer(const Duration(seconds: 1), sync);
        case 'restart':
          restartCounter++;
          Timer(const Duration(seconds: 2), () { _stopTimers(); start(); });
        default:
          status = 'failed';
          result = 'Unsupported command';
      }
      log('command', 'Executed $cmd');
    } catch (e) {
      status = 'failed';
      result = e.toString();
    }
    try {
      await _api.post('devices/command-ack.php', {'command_id': c['command_id'], 'status': status, 'result_message': result});
    } catch (_) {}
    notifyListeners();
  }

  // ============================================================ logs / stats
  void log(String type, String message, [Map<String, dynamic>? context]) {
    debugPrint('[player:$type] $message');
    _logQueue.add({'log_type': type, 'message': message, 'context': context, 'logged_at': DateTime.now().toUtc().toIso8601String()});
    if (_logQueue.length > 5000) _logQueue.removeRange(0, _logQueue.length - 5000);
  }

  void onItemStart(PlayItem item) {
    currentScreenId = item.screenId;
    currentVersion = 'pl:${decision.playlistId}@v${asInt(activeDoc?['version'])}';
  }

  void onItemEnd(PlayItem item, Duration played, bool completed) {
    _playQueue.add({
      'playlist_id': decision.playlistId, 'screen_id': item.screenId, 'playlist_version': asInt(activeDoc?['version']),
      'screen_version': item.screenVersion, 'started_at': DateTime.now().toUtc().subtract(played).toIso8601String(),
      'duration_ms': played.inMilliseconds, 'completed': completed,
    });
    if (_playQueue.length > 5000) _playQueue.removeRange(0, _playQueue.length - 5000);
  }

  void onWidgetError(String type, Object e) => log('playback_error', 'Widget $type failed: $e', {'widget_type': type, 'screen_id': currentScreenId});

  Future<void> _flushQueues() async {
    try {
      while (_logQueue.isNotEmpty) {
        final batch = _logQueue.take(200).toList();
        await _api.post('devices/logs.php', {'logs': batch});
        _logQueue.removeRange(0, batch.length);
      }
      while (_playQueue.isNotEmpty) {
        final batch = _playQueue.take(500).toList();
        await _api.post('devices/playback.php', {'entries': batch});
        _playQueue.removeRange(0, batch.length);
      }
      await _saveState();
    } catch (_) {
      // keep queued; retried after the next heartbeat
    }
  }

  // =============================================================== persistence
  Future<void> _saveState() async {
    final state = jsonEncode({
      'docs': docs.map((k, v) => MapEntry('$k', v)), 'assignments': assignments, 'emergency': emergency,
      'local_paths': localPaths.map((k, v) => MapEntry('$k', v)), 'urls': urls.map((k, v) => MapEntry('$k', v)),
      'device_name': deviceName, 'device_code': deviceCode, 'device_timezone': deviceTimezone, 'orientation': orientation,
      'sync_token': syncToken, 'log_queue': _logQueue, 'play_queue': _playQueue,
    });
    if (_dir != null) {
      final f = File('${_dir!.path}/state.json');
      final tmp = File('${f.path}.tmp');
      await tmp.writeAsString(state, flush: true);
      await tmp.rename(f.path); // atomic replace
    } else {
      (await SharedPreferences.getInstance()).setString('player_state', state);
    }
  }

  Future<void> _loadState() async {
    String? raw;
    try {
      if (_dir != null) {
        final f = File('${_dir!.path}/state.json');
        if (await f.exists()) raw = await f.readAsString();
      } else {
        raw = (await SharedPreferences.getInstance()).getString('player_state');
      }
      if (raw == null) return;
      final s = asMap(jsonDecode(raw));
      docs = asMap(s['docs']).map((k, v) => MapEntry(int.parse(k), asMap(v)));
      assignments = asList(s['assignments']).map(asMap).toList();
      emergency = asList(s['emergency']).map(asMap).toList();
      asMap(s['local_paths']).forEach((k, v) {
        if (_dir == null || File(v.toString()).existsSync()) localPaths[int.parse(k)] = v.toString();
      });
      asMap(s['urls']).forEach((k, v) => urls[int.parse(k)] = v.toString());
      deviceName = s['device_name']?.toString();
      deviceCode = s['device_code']?.toString();
      deviceTimezone = s['device_timezone']?.toString();
      orientation = asString(s['orientation'], 'auto');
      syncToken = asString(s['sync_token']);
      _logQueue.addAll(asList(s['log_queue']).map(asMap));
      _playQueue.addAll(asList(s['play_queue']).map(asMap));
    } catch (e) {
      debugPrint('Corrupt player state ignored: $e');
    }
  }

  Future<void> _clearContent({bool keepPairing = false}) async {
    docs = {};
    assignments = [];
    emergency = [];
    localPaths.clear();
    syncToken = '';
    decision = const ScheduleDecision(null, 'Cleared');
    if (_dir != null) {
      try {
        await Directory('${_dir!.path}/media').delete(recursive: true);
        await Directory('${_dir!.path}/media').create(recursive: true);
      } catch (_) {}
    }
    await _saveState();
    notifyListeners();
  }

  /// Forget this device completely (new pairing). Used from the hidden info menu.
  Future<void> reset() async {
    _stopTimers();
    await _clearContent();
    await SecureStore.write('player_device_token', null);
    _token = null;
    phase = PlayerPhase.starting;
    notifyListeners();
    await start();
  }

  @override
  void dispose() {
    _stopTimers();
    super.dispose();
  }
}
