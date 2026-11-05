import 'dart:math' as math;
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:video_player/video_player.dart';

import '../../core/utils/formatters.dart';
import '../../core/utils/json.dart';
import '../../models/layout.dart';
import 'render_context.dart';
import 'widget_renderers.dart';

/// Pre-built background presets (background_type = 'preset').
const Map<String, List<String>> backgroundPresets = {
  'teal_night': ['#006B66', '#17191C'],
  'ocean': ['#008C86', '#1E88E5'],
  'sunset': ['#FB8C00', '#D81B60'],
  'charcoal': ['#2A2D31', '#17191C'],
  'mint': ['#E5F5F3', '#FFFFFF'],
  'forest': ['#1B5E20', '#004D40'],
  'royal': ['#311B92', '#006B66'],
  'gold': ['#3E2723', '#C49A2C'],
};

/// Full-canvas background: colour, gradient, image, video or preset, with
/// opacity, blur, overlay, fit and position.
class BackgroundView extends StatelessWidget {
  const BackgroundView({super.key, required this.bg});
  final BackgroundSpec bg;

  @override
  Widget build(BuildContext context) {
    final scope = RenderScope.of(context);
    final c = bg.config;
    final opacity = asDouble(c['opacity'], 1).clamp(0.0, 1.0);
    final blur = asDouble(c['blur']);
    final overlayOpacity = asDouble(c['overlay_opacity']).clamp(0.0, 1.0);
    Widget base;
    switch (bg.type) {
      case 'gradient':
      case 'preset':
        final g = asMap(c['gradient']);
        var colors = asList(g['colors']).map(parseHexColor).whereType<Color>().toList();
        if (bg.type == 'preset') colors = (backgroundPresets[bg.value] ?? backgroundPresets['teal_night']!).map((h) => parseHexColor(h)!).toList();
        if (colors.length < 2) colors = [...colors, ...[const Color(0xFF006B66), const Color(0xFF17191C)]].take(2).toList();
        final stops = asList(g['stops']).map((e) => asDouble(e)).toList();
        final angle = asDouble(g['angle'], 135) * math.pi / 180;
        base = DecoratedBox(
          decoration: BoxDecoration(
            gradient: g['type'] == 'radial'
                ? RadialGradient(colors: colors, stops: stops.length == colors.length ? stops : null, radius: 1.0)
                : LinearGradient(
                    begin: Alignment(-math.cos(angle), -math.sin(angle)),
                    end: Alignment(math.cos(angle), math.sin(angle)),
                    colors: colors,
                    stops: stops.length == colors.length ? stops : null,
                  ),
          ),
        );
        break;
      case 'image':
        final p = scope.media.image(bg.mediaId ?? int.tryParse(bg.value ?? ''), thumb: scope.mode == RenderMode.thumbnail);
        base = p == null
            ? const ColoredBox(color: Color(0xFF17191C))
            : Image(image: p, fit: fitOf(c['fit']), alignment: _pos(c['position']), width: double.infinity, height: double.infinity, errorBuilder: (_, _, _) => const ColoredBox(color: Color(0xFF17191C)));
        break;
      case 'video':
        base = scope.live
            ? _BackgroundVideo(mediaId: bg.mediaId ?? int.tryParse(bg.value ?? ''), fit: fitOf(c['fit']))
            : const ColoredBox(color: Colors.black, child: Center(child: Icon(Icons.movie_outlined, color: Colors.white30, size: 160)));
        break;
      default:
        base = ColoredBox(color: parseHexColor(bg.value) ?? const Color(0xFF17191C));
    }
    if (blur > 0) base = ImageFiltered(imageFilter: ui.ImageFilter.blur(sigmaX: blur, sigmaY: blur, tileMode: TileMode.clamp), child: base);
    return Stack(fit: StackFit.expand, children: [
      const ColoredBox(color: Colors.black),
      Opacity(opacity: opacity, child: base),
      if (overlayOpacity > 0) ColoredBox(color: (parseHexColor(c['overlay_color']) ?? Colors.black).withValues(alpha: overlayOpacity)),
    ]);
  }

  Alignment _pos(dynamic p) => switch (p) {
        'top' => Alignment.topCenter,
        'bottom' => Alignment.bottomCenter,
        'left' => Alignment.centerLeft,
        'right' => Alignment.centerRight,
        _ => Alignment.center,
      };
}

class _BackgroundVideo extends StatefulWidget {
  const _BackgroundVideo({required this.mediaId, required this.fit});
  final int? mediaId;
  final BoxFit fit;
  @override
  State<_BackgroundVideo> createState() => _BackgroundVideoState();
}

class _BackgroundVideoState extends State<_BackgroundVideo> {
  VideoPlayerController? c;
  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (c != null) return;
    final ctrl = RenderScope.of(context).media.video(widget.mediaId);
    if (ctrl == null) return;
    c = ctrl;
    ctrl.initialize().then((_) async {
      await ctrl.setLooping(true);
      await ctrl.setVolume(0);
      await ctrl.play();
      if (mounted) setState(() {});
    }).catchError((Object e) {
      if (mounted) RenderScope.of(context).onError?.call('background_video', e);
    });
  }

  @override
  void dispose() {
    c?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final v = c;
    if (v == null || !v.value.isInitialized) return const ColoredBox(color: Colors.black);
    return FittedBox(fit: widget.fit, clipBehavior: Clip.hardEdge, child: SizedBox(width: v.value.size.width, height: v.value.size.height, child: VideoPlayer(v)));
  }
}

/// Positions one widget inside a design-resolution canvas.
Widget positionedWidget(BuildContext context, WidgetItem w, Size canvas, {Widget? child}) {
  return Positioned(
    left: w.positionX * canvas.width,
    top: w.positionY * canvas.height,
    width: math.max(1, w.width * canvas.width),
    height: math.max(1, w.height * canvas.height),
    child: Transform.rotate(
      angle: w.rotation * math.pi / 180,
      child: child ?? buildWidgetContent(context, w),
    ),
  );
}

/// Renders a complete screen layout, preserving its aspect ratio
/// (letter-boxed) inside whatever space it is given.
class LayoutView extends StatelessWidget {
  const LayoutView({super.key, required this.layout, required this.media, this.mode = RenderMode.preview, this.timezone, this.onError, this.letterboxColor = Colors.black});
  final LayoutDoc layout;
  final MediaSource media;
  final RenderMode mode;
  final String? timezone;
  final void Function(String, Object)? onError;
  final Color letterboxColor;

  @override
  Widget build(BuildContext context) {
    final size = Size(layout.width.toDouble(), layout.height.toDouble());
    return RenderScope(
      mode: mode,
      media: media,
      timezone: timezone,
      onError: onError,
      child: ColoredBox(
        color: letterboxColor,
        child: Center(
          child: AspectRatio(
            aspectRatio: layout.aspect,
            child: FittedBox(
              fit: BoxFit.contain,
              child: SizedBox(
                width: size.width,
                height: size.height,
                child: ClipRect(
                  child: Builder(
                    builder: (context) => Stack(clipBehavior: Clip.hardEdge, children: [
                      Positioned.fill(child: BackgroundView(bg: layout.background)),
                      for (final w in layout.sorted.where((w) => w.isVisible)) positionedWidget(context, w, size),
                    ]),
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

/// Non-interactive thumbnail used on cards.
class LayoutThumbnail extends StatelessWidget {
  const LayoutThumbnail({super.key, required this.layout, required this.media, this.radius = 12});
  final LayoutDoc layout;
  final MediaSource media;
  final double radius;
  @override
  Widget build(BuildContext context) => ClipRRect(
        borderRadius: BorderRadius.circular(radius),
        child: IgnorePointer(
          child: RepaintBoundary(
            child: LayoutView(layout: layout, media: media, mode: RenderMode.thumbnail, letterboxColor: const Color(0xFF101214)),
          ),
        ),
      );
}
