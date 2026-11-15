// Android / iOS / desktop storage for VideoCache: complete files under app support storage.
import 'dart:io';
import 'dart:isolate';

import 'package:crypto/crypto.dart';
import 'package:dio/dio.dart';
import 'package:path_provider/path_provider.dart';
import 'package:video_player/video_player.dart';

/// Videos above this size are streamed instead of cached.
const int maxCacheableBytes = 2048 * 1048576;

/// Total size kept on the device; least recently used videos are removed beyond this.
const int _maxTotalBytes = 2048 * 1048576;

final Dio _dio = Dio(BaseOptions(connectTimeout: const Duration(seconds: 20), receiveTimeout: const Duration(minutes: 30)));
Directory? _dir;

Future<Directory> _cacheDir() async {
  if (_dir != null) return _dir!;
  final d = Directory('${(await getApplicationSupportDirectory()).path}/video_cache');
  if (!await d.exists()) await d.create(recursive: true);
  return _dir = d;
}

Future<bool> fileExists(String path) async {
  final f = File(path);
  return await f.exists() && await f.length() > 0;
}

Future<String?> findLocal(String key, int expectedSize) async {
  final f = File('${(await _cacheDir()).path}/$key.mp4');
  if (!await f.exists()) return null;
  final len = await f.length();
  if (len == 0 || (expectedSize > 0 && len != expectedSize)) {
    await f.delete().catchError((_) => f);
    return null;
  }
  await f.setLastModified(DateTime.now()).catchError((_) {});   // LRU: mark as recently used
  return f.path;
}

Future<String?> download(String key, String url, int expectedSize, String? checksum) async {
  final dir = await _cacheDir();
  final part = File('${dir.path}/$key.part');
  final dest = File('${dir.path}/$key.mp4');
  await _dio.download(url, part.path, deleteOnError: true);
  final len = await part.length();
  if (expectedSize > 0 && len != expectedSize) {
    await part.delete();
    throw Exception('incomplete video download ($len of $expectedSize bytes)');
  }
  if (checksum != null && checksum.length == 64) {
    final path = part.path;
    // Hash off the UI thread so playback never stutters while verifying.
    final digest = await Isolate.run(() async => (await sha256.bind(File(path).openRead()).first).toString());
    if (digest != checksum) {
      await part.delete();
      throw Exception('video checksum mismatch');
    }
  }
  await part.rename(dest.path);
  await _prune(dir, keep: dest.path);
  return dest.path;
}

VideoPlayerController controllerFor(String location) => VideoPlayerController.file(File(location));

Future<void> _prune(Directory dir, {required String keep}) async {
  final files = <File>[];
  await for (final e in dir.list()) {
    if (e is File && e.path.endsWith('.mp4')) files.add(e);
  }
  var total = 0;
  final stats = <File, FileStat>{};
  for (final f in files) {
    final s = await f.stat();
    stats[f] = s;
    total += s.size;
  }
  if (total <= _maxTotalBytes) return;
  files.sort((a, b) => stats[a]!.modified.compareTo(stats[b]!.modified));   // oldest first
  for (final f in files) {
    if (total <= _maxTotalBytes) break;
    if (f.path == keep) continue;
    total -= stats[f]!.size;
    await f.delete().catchError((_) => f);
  }
}
