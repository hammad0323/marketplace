import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'package:crypto/crypto.dart';
import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:path_provider/path_provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../../core/network/api_client.dart';
import '../../core/utils/json.dart';
import '../widgets_library/render_context.dart';

/// Plays the content behind a display code (Show Display mode).
///
///  * No account or pairing: the code is the only thing the device needs.
///  * The last good content and its media are cached, so playback continues
///    offline and after a restart.
///  * Checks for changes every minute; downloads only when the content hash
///    changes, and switches only after every media file is downloaded and verified.
class CodeDisplayService extends ChangeNotifier {
  CodeDisplayService(this.code, {String? baseUrl}) : _api = ApiClient(baseUrl: baseUrl);

  final String code;
  final ApiClient _api;

  Map<String, dynamic>? doc;
  String contentHash = '';
  String? name, kind;
  List<Map<String, dynamic>> emergency = [];
  bool loading = true, online = false;
  String? errorMessage, errorCode;
  DateTime? lastCheck;
  final Map<int, String> localPaths = {};
  final Map<int, String> urls = {};
  Timer? _timer;
  Directory? _dir;
  bool _busy = false, _disposed = false;

  MediaSource get media => CachedFileMediaSource(localPaths: localPaths, urls: urls);

  Future<void> start() async {
    if (!kIsWeb) {
      _dir = Directory('${(await getApplicationSupportDirectory()).path}/display_cache/$code');
      await Directory('${_dir!.path}/media').create(recursive: true);
    }
    await _load();
    if (doc != null) {
      loading = false;
      _notify();
    }
    await refresh();
    _timer = Timer.periodic(const Duration(seconds: 60), (_) => refresh());
  }

  Future<void> refresh() async {
    if (_busy) return;
    _busy = true;
    try {
      final r = await _api.get('display/content.php', query: {'code': code, 'have': contentHash});
      final d = asMap(r.data);
      online = true;
      errorMessage = null;
      errorCode = null;
      name = asString(d['name']);
      kind = asString(d['kind']);
      emergency = asList(d['emergency']).map(asMap).toList();
      final newDoc = d['doc'] == null ? null : asMap(d['doc']);
      if (newDoc != null) {
        final ok = await _download(newDoc);
        if (ok || doc == null) {
          doc = newDoc;
          contentHash = asString(d['content_hash']);
          await _save();
          _cleanup();
        }
      }
    } on ApiException catch (e) {
      if (e.isNetwork || (e.statusCode ?? 500) >= 500 || e.statusCode == 429) {
        online = false; // keep playing what we have
      } else {
        online = true;
        errorMessage = e.message;
        errorCode = e.code;
        // Code removed or account inactive → stop showing the old content.
        if (e.statusCode == 404 || e.statusCode == 403) {
          doc = null;
          contentHash = '';
          await _save();
        }
      }
    } catch (_) {
      online = false;
    } finally {
      lastCheck = DateTime.now();
      loading = false;
      _busy = false;
      _notify();
    }
  }

  Future<bool> _download(Map<String, dynamic> d) async {
    var allOk = true;
    for (final m in asList(d['media']).map(asMap)) {
      final id = asInt(m['media_id']);
      final url = m['url']?.toString();
      if (url != null) urls[id] = url;
      if (_dir == null || url == null) continue; // web streams media directly
      final sum = asString(m['checksum_sha256']);
      final ext = switch (asString(m['mime_type'])) { 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'video/mp4' => 'mp4', _ => 'jpg' };
      final path = '${_dir!.path}/media/${id}_${sum.isEmpty ? 'x' : sum.substring(0, 16)}.$ext';
      if (await File(path).exists()) {
        localPaths[id] = path;
        continue;
      }
      final tmp = File('$path.part');
      try {
        await Dio().download(url, tmp.path, options: Options(receiveTimeout: const Duration(minutes: 30)));
        if (sum.isNotEmpty && (await sha256.bind(tmp.openRead()).first).toString() != sum) throw Exception('checksum mismatch');
        await tmp.rename(path);
        localPaths[id] = path;
      } catch (_) {
        allOk = false;
        if (await tmp.exists()) await tmp.delete();
      }
    }
    return allOk;
  }

  void _cleanup() {
    if (_dir == null || doc == null) return;
    final keep = asList(doc!['media']).map((m) => localPaths[asInt(asMap(m)['media_id'])]).whereType<String>().toSet();
    try {
      for (final f in Directory('${_dir!.path}/media').listSync()) {
        if (f is File && !keep.contains(f.path) && !f.path.endsWith('.part')) f.deleteSync();
      }
    } catch (_) {}
  }

  Future<void> _save() async {
    final s = jsonEncode({
      'doc': doc, 'hash': contentHash, 'name': name, 'kind': kind,
      'local_paths': localPaths.map((k, v) => MapEntry('$k', v)), 'urls': urls.map((k, v) => MapEntry('$k', v)),
    });
    if (_dir != null) {
      final f = File('${_dir!.path}/state.json');
      await File('${f.path}.tmp').writeAsString(s, flush: true);
      await File('${f.path}.tmp').rename(f.path);
    } else {
      (await SharedPreferences.getInstance()).setString('display_state_$code', s);
    }
  }

  Future<void> _load() async {
    try {
      String? raw;
      if (_dir != null) {
        final f = File('${_dir!.path}/state.json');
        if (await f.exists()) raw = await f.readAsString();
      } else {
        raw = (await SharedPreferences.getInstance()).getString('display_state_$code');
      }
      if (raw == null) return;
      final s = asMap(jsonDecode(raw));
      doc = s['doc'] == null ? null : asMap(s['doc']);
      contentHash = asString(s['hash']);
      name = s['name']?.toString();
      kind = s['kind']?.toString();
      asMap(s['local_paths']).forEach((k, v) {
        if (_dir == null || File('$v').existsSync()) localPaths[int.parse(k)] = '$v';
      });
      asMap(s['urls']).forEach((k, v) => urls[int.parse(k)] = '$v');
    } catch (_) {}
  }

  void _notify() {
    if (!_disposed) notifyListeners();
  }

  @override
  void dispose() {
    _disposed = true;
    _timer?.cancel();
    super.dispose();
  }
}

/// Remembers which display mode this device runs in, so it restarts straight
/// into it after a reboot. Values: null (normal app), 'code', 'managed'.
class DisplayModePrefs {
  static const _mode = 'display_mode', _code = 'display_code';
  static Future<(String?, String?)> read() async {
    final p = await SharedPreferences.getInstance();
    return (p.getString(_mode), p.getString(_code));
  }

  static Future<void> setCode(String code) async {
    final p = await SharedPreferences.getInstance();
    await p.setString(_mode, 'code');
    await p.setString(_code, code);
  }

  static Future<void> setManaged() async => (await SharedPreferences.getInstance()).setString(_mode, 'managed');

  static Future<void> clear() async {
    final p = await SharedPreferences.getInstance();
    await p.remove(_mode);
    await p.remove(_code);
  }
}
