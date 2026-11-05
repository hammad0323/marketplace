import 'dart:io' show File;

import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:video_player/video_player.dart';

import '../../models/media.dart';

/// Where a layout is being rendered. Controls animation, video playback, placeholders.
enum RenderMode {
  /// Visual editor: placeholders for empty media, no entry animations, no video playback.
  editor,

  /// Small static thumbnails in lists.
  thumbnail,

  /// Full preview in the management app (behaves like the player, streaming media).
  preview,

  /// Signage player (cached local media).
  player,
}

/// Resolves media ids to displayable sources.
abstract class MediaSource {
  ImageProvider? image(int? mediaId, {bool thumb = false});
  VideoPlayerController? video(int? mediaId);
  MediaItem? info(int? mediaId);
}

/// Streams media from signed URLs (management app).
class NetworkMediaSource implements MediaSource {
  NetworkMediaSource(this.items);
  final Map<int, MediaItem> items;

  @override
  MediaItem? info(int? id) => id == null ? null : items[id];

  @override
  ImageProvider? image(int? id, {bool thumb = false}) {
    final m = info(id);
    final url = thumb ? (m?.thumbnailUrl ?? m?.url) : m?.url;
    if (url == null) return null;
    return CachedNetworkImageProvider(url, cacheKey: 'media_${id}_${thumb ? 't' : 'f'}_${m?.checksum ?? ''}');
  }

  @override
  VideoPlayerController? video(int? id) {
    final url = info(id)?.url;
    return url == null ? null : VideoPlayerController.networkUrl(Uri.parse(url));
  }

  NetworkMediaSource merge(Map<int, MediaItem> more) => NetworkMediaSource({...items, ...more});
}

/// Plays media from the player's local cache, falling back to the signed URL.
class CachedFileMediaSource implements MediaSource {
  CachedFileMediaSource({required this.localPaths, required this.urls, this.meta = const {}});
  final Map<int, String> localPaths;
  final Map<int, String> urls;
  final Map<int, MediaItem> meta;

  @override
  MediaItem? info(int? id) => id == null ? null : meta[id];

  @override
  ImageProvider? image(int? id, {bool thumb = false}) {
    if (id == null) return null;
    final p = localPaths[id];
    if (p != null && !kIsWeb) return FileImage(File(p));
    final u = urls[id];
    return u == null ? null : CachedNetworkImageProvider(u, cacheKey: 'media_$id');
  }

  @override
  VideoPlayerController? video(int? id) {
    if (id == null) return null;
    final p = localPaths[id];
    if (p != null && !kIsWeb) return VideoPlayerController.file(File(p));
    final u = urls[id];
    return u == null ? null : VideoPlayerController.networkUrl(Uri.parse(u));
  }
}

/// Inherited render settings for every widget renderer below a layout.
class RenderScope extends InheritedWidget {
  const RenderScope({super.key, required this.mode, required this.media, this.timezone, this.onError, required super.child});
  final RenderMode mode;
  final MediaSource media;

  /// IANA zone used by clocks set to 'device' (null = system local time).
  final String? timezone;
  final void Function(String widgetType, Object error)? onError;

  bool get live => mode == RenderMode.preview || mode == RenderMode.player;
  bool get animate => live;

  static RenderScope of(BuildContext context) {
    final s = context.dependOnInheritedWidgetOfExactType<RenderScope>();
    assert(s != null, 'RenderScope missing');
    return s!;
  }

  @override
  bool updateShouldNotify(RenderScope old) => old.mode != mode || old.media != media || old.timezone != timezone;
}
