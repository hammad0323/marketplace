import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:video_player/video_player.dart';

import 'video_cache_io.dart' if (dart.library.js_interop) 'video_cache_web.dart' as store;

/// What a renderer needs to play one video.
class VideoRef {
  const VideoRef({required this.mediaId, required this.url, this.localPath, this.size = 0, this.checksum});
  final int mediaId;

  /// Signed download URL (changes daily — never used as the cache key).
  final String? url;

  /// File already saved by the TV player / offline display, if any.
  final String? localPath;
  final int size;
  final String? checksum;

  /// Stable cache key: the media id plus its checksum (media files never change in place).
  String get key {
    final c = checksum ?? '';
    final tag = c.isNotEmpty ? c.substring(0, c.length < 16 ? c.length : 16) : '$size';
    return 'v${mediaId}_$tag';
  }
}

/// Smooth, uninterrupted video playback.
///
/// Every video is downloaded once, completely, and then played from the device:
///   * Android / iOS / desktop: saved under app storage (`video_cache/`), size + SHA-256 verified,
///     played with `VideoPlayerController.file`. Kept across restarts (LRU, 2 GB cap).
///   * Web: downloaded into memory and played from a `blob:` URL for the rest of the session.
/// While the first download runs the video streams, and the widget switches to the local copy
/// at the end of the current loop, so loops never re-buffer from the network.
class VideoCache {
  VideoCache._();
  static final VideoCache instance = VideoCache._();

  final Map<String, Future<String?>> _inflight = {};

  /// Location of a complete local copy, or null.
  Future<String?> local(VideoRef r) async {
    if (r.localPath != null && await store.fileExists(r.localPath!)) return r.localPath;
    return store.findLocal(r.key, r.size);
  }

  /// Downloads the video once (shared between widgets). Resolves to the local location, or null on failure.
  Future<String?> ensure(VideoRef r) {
    final existing = _inflight[r.key];
    if (existing != null) return existing;
    final f = () async {
      final have = await local(r);
      if (have != null) return have;
      final url = r.url;
      if (url == null || (r.size > 0 && r.size > store.maxCacheableBytes)) return null;
      try {
        return await store.download(r.key, url, r.size, r.checksum);
      } catch (e) {
        debugPrint('video cache: $e');
        return null;
      }
    }();
    _inflight[r.key] = f;
    f.whenComplete(() => _inflight.remove(r.key));
    return f;
  }

  /// A controller for [r]: local copy when available, otherwise the network stream while a
  /// background download starts. `isLocal` tells the caller whether to switch later.
  Future<(VideoPlayerController, bool isLocal)> open(VideoRef r) async {
    final loc = await local(r);
    if (loc != null) return (store.controllerFor(loc), true);
    final url = r.url;
    if (url == null) throw StateError('Video ${r.mediaId} is not available offline yet');
    unawaited(ensure(r));
    return (VideoPlayerController.networkUrl(Uri.parse(url)), false);
  }

  /// Completes with the local location once the download finished (null if it failed).
  Future<String?> whenCached(VideoRef r) => _inflight[r.key] ?? local(r);
}
