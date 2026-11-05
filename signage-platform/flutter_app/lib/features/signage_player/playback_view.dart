import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/utils/json.dart';
import '../../models/layout.dart';
import '../widgets_library/layout_view.dart';
import '../widgets_library/render_context.dart';
import '../widgets_library/widget_renderers.dart';

/// One playable item resolved from a playlist publication.
class PlayItem {
  PlayItem({required this.itemId, required this.screenId, required this.screenVersion, required this.duration, required this.transition, required this.layout, this.validFrom, this.validUntil, this.expiresAt});
  final int itemId, screenId, screenVersion, duration;
  final String transition;
  final LayoutDoc layout;
  final DateTime? validFrom, validUntil, expiresAt;

  bool playableAt(DateTime now) =>
      (validFrom == null || !now.isBefore(validFrom!)) && (validUntil == null || now.isBefore(validUntil!)) && (expiresAt == null || now.isBefore(expiresAt!));

  static List<PlayItem> fromPublication(Map<String, dynamic> doc) {
    final defTransition = asString(asMap(doc['transition'])['type'], 'fade');
    return asList(doc['items']).map(asMap).map((it) {
      final screen = asMap(it['screen']);
      final t = asString(it['transition_type'], 'inherit');
      return PlayItem(
        itemId: asInt(it['item_id']),
        screenId: asInt(it['screen_id']),
        screenVersion: asInt(it['screen_version']),
        duration: asInt(it['duration'], asInt(screen['default_duration'], 10)).clamp(1, 86400),
        transition: t == 'inherit' ? defTransition : t,
        layout: LayoutDoc.fromJson(screen),
        validFrom: asDate(it['valid_from']),
        validUntil: asDate(it['valid_until']),
        expiresAt: asDate(screen['expires_at']),
      );
    }).toList();
  }
}

/// Plays a playlist publication: screen after screen with transitions, looping.
/// Used by the management preview and the TV player. Never shows an error
/// screen: empty/expired playlists fall back to [idle].
class PlaylistPlaybackView extends StatefulWidget {
  const PlaylistPlaybackView({
    super.key, required this.doc, required this.media, this.mode = RenderMode.player, this.timezone, this.paused = false,
    this.onItemStart, this.onItemEnd, this.onError, required this.idle, this.onFinished,
  });
  final Map<String, dynamic> doc;
  final MediaSource media;
  final RenderMode mode;
  final String? timezone;
  final bool paused;
  final void Function(PlayItem item)? onItemStart;
  final void Function(PlayItem item, Duration played, bool completed)? onItemEnd;
  final void Function(String widgetType, Object error)? onError;
  final VoidCallback? onFinished;
  final Widget idle;

  @override
  State<PlaylistPlaybackView> createState() => PlaylistPlaybackViewState();
}

class PlaylistPlaybackViewState extends State<PlaylistPlaybackView> {
  late List<PlayItem> items;
  int index = -1;
  int seq = 0;
  PlayItem? current;
  Timer? timer;
  DateTime? startedAt;
  Duration remaining = Duration.zero;

  @override
  void initState() {
    super.initState();
    items = PlayItem.fromPublication(widget.doc);
    if (asBool(widget.doc['shuffle'])) items.shuffle();
    _advance();
  }

  @override
  void didUpdateWidget(PlaylistPlaybackView oldWidget) {
    super.didUpdateWidget(oldWidget);
    final old = oldWidget;
    if (old.doc != widget.doc) {
      // New version: keep playing the current item, switch content at its end.
      items = PlayItem.fromPublication(widget.doc);
      if (asBool(widget.doc['shuffle'])) items.shuffle();
      index = -1;
      for (var i = 0; i < items.length; i++) {
        if (items[i].screenId == current?.screenId) index = i;
      }
    }
    if (old.paused != widget.paused) widget.paused ? _pause() : _resume();
  }

  void _pause() {
    if (startedAt == null) return;
    timer?.cancel();
    remaining -= DateTime.now().difference(startedAt!);
    startedAt = null;
  }

  void _resume() {
    if (startedAt != null || current == null) return;
    startedAt = DateTime.now();
    timer = Timer(remaining.isNegative ? Duration.zero : remaining, _advance);
  }

  void _advance() {
    final prev = current;
    if (prev != null && startedAt != null) {
      widget.onItemEnd?.call(prev, DateTime.now().difference(startedAt!), true);
    }
    final now = DateTime.now();
    final loop = asBool(widget.doc['loop_enabled'], true);
    PlayItem? next;
    for (var step = 1; step <= items.length; step++) {
      final i = index + step;
      if (!loop && i >= items.length) break;
      final cand = items[i % items.length];
      if (cand.playableAt(now)) {
        index = i % items.length;
        next = cand;
        break;
      }
    }
    if (next == null) {
      timer?.cancel();
      setState(() => current = null);
      widget.onFinished?.call();
      // Re-check later (content windows may open).
      timer = Timer(const Duration(seconds: 30), () { if (mounted) _advance(); });
      return;
    }
    seq++;
    setState(() => current = next);
    widget.onItemStart?.call(next);
    remaining = Duration(seconds: next.duration);
    startedAt = null;
    if (!widget.paused) _resume();
  }

  /// Skip to the next screen (remote control / preview controls).
  void skip() {
    timer?.cancel();
    _advance();
  }

  @override
  void dispose() {
    timer?.cancel();
    final c = current;
    if (c != null && startedAt != null) widget.onItemEnd?.call(c, DateTime.now().difference(startedAt!), false);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final c = current;
    final ms = asInt(asMap(widget.doc['transition'])['duration_ms'], 800).clamp(0, 10000);
    return AnimatedSwitcher(
      duration: Duration(milliseconds: ms),
      transitionBuilder: (child, a) => transitionFor(c?.transition ?? 'fade', child, a),
      layoutBuilder: (cur, prev) => Stack(fit: StackFit.expand, children: [...prev, ?cur]),
      child: c == null
          ? KeyedSubtree(key: const ValueKey('idle'), child: widget.idle)
          : KeyedSubtree(
              key: ValueKey('play_$seq'),
              child: LayoutView(layout: c.layout, media: widget.media, mode: widget.mode, timezone: widget.timezone, onError: widget.onError),
            ),
    );
  }
}
