import 'dart:async';
import 'dart:math' as math;
import 'dart:ui' as ui;

import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:intl/intl.dart';
import 'package:qr_flutter/qr_flutter.dart';
import 'package:timezone/timezone.dart' as tz;
import 'package:video_player/video_player.dart';

import '../../core/constants/app_colors.dart';
import '../../core/utils/formatters.dart';
import '../../core/utils/json.dart';
import '../../models/layout.dart';
import 'render_context.dart';
import 'web_embed.dart';

// =============================================================================
// Shared helpers
// =============================================================================

/// Resolve a font family through Google Fonts, falling back to the platform font.
TextStyle fontStyle(String? family, TextStyle base) {
  if (family == null || family.isEmpty) return base;
  try {
    return GoogleFonts.getFont(family, textStyle: base);
  } catch (_) {
    return base.copyWith(fontFamily: family);
  }
}

FontWeight weightOf(dynamic v) {
  final w = asInt(v, 400).clamp(100, 900);
  return FontWeight.values[((w ~/ 100) - 1).clamp(0, 8)];
}

TextAlign alignOf(dynamic v) => switch (v) {
      'left' || 'start' => TextAlign.left,
      'right' || 'end' => TextAlign.right,
      'justify' => TextAlign.justify,
      _ => TextAlign.center,
    };

Alignment boxAlign(String? h, String? v) {
  final x = switch (h) { 'left' => -1.0, 'right' => 1.0, _ => 0.0 };
  final y = switch (v) { 'top' => -1.0, 'bottom' => 1.0, _ => 0.0 };
  return Alignment(x, y);
}

BoxFit fitOf(dynamic v) => switch (v) {
      'contain' => BoxFit.contain,
      'fill' => BoxFit.fill,
      'fit_width' => BoxFit.fitWidth,
      'fit_height' => BoxFit.fitHeight,
      'none' => BoxFit.none,
      _ => BoxFit.cover,
    };

Color colorOr(dynamic v, Color fallback) => parseHexColor(v) ?? fallback;

TextStyle textStyleFrom(Map<String, dynamic> c, {double size = 40, FontWeight? weight, Color color = Colors.white}) {
  final effect = c['text_effect'];
  final eColor = colorOr(c['effect_color'], const Color(0x66000000));
  final base = TextStyle(
    fontSize: asDouble(c['font_size'], size),
    fontWeight: c.containsKey('font_weight') ? weightOf(c['font_weight']) : (weight ?? FontWeight.w400),
    fontStyle: asBool(c['italic']) ? FontStyle.italic : FontStyle.normal,
    decoration: asBool(c['underline']) ? TextDecoration.underline : null,
    decorationColor: colorOr(c['color'], color),
    color: colorOr(c['color'], color),
    letterSpacing: asDouble(c['letter_spacing']),
    height: c['line_height'] == null ? null : asDouble(c['line_height'], 1.2),
    shadows: switch (effect) {
      'shadow' => [Shadow(color: eColor, blurRadius: asDouble(c['effect_blur'], 12), offset: Offset(0, asDouble(c['effect_offset_y'], 4)))],
      'glow' => [Shadow(color: eColor, blurRadius: 24), Shadow(color: eColor, blurRadius: 8)],
      'outline' => [
          for (final o in const [Offset(-2, -2), Offset(2, -2), Offset(-2, 2), Offset(2, 2)]) Shadow(color: eColor, offset: o)
        ],
      _ => null,
    },
  );
  return fontStyle(c['font_family']?.toString(), base);
}

/// Placeholder shown in the editor where content is missing.
class EditorPlaceholder extends StatelessWidget {
  const EditorPlaceholder({super.key, required this.icon, required this.label});
  final IconData icon;
  final String label;
  @override
  Widget build(BuildContext context) {
    final scope = RenderScope.of(context);
    if (scope.mode == RenderMode.player) return const SizedBox.shrink();
    return Container(
      decoration: BoxDecoration(
        color: const Color(0x33858B93),
        border: Border.all(color: const Color(0x88858B93), width: 3),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Center(
        child: FittedBox(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(mainAxisSize: MainAxisSize.min, children: [
              Icon(icon, size: 96, color: const Color(0xCCFFFFFF)),
              const SizedBox(height: 8),
              Text(label, style: const TextStyle(color: Color(0xCCFFFFFF), fontSize: 36, fontWeight: FontWeight.w600)),
            ]),
          ),
        ),
      ),
    );
  }
}

/// Entry animation (fade / slide / zoom) played once when the widget appears on a live screen.
class EntryAnimation extends StatefulWidget {
  const EntryAnimation({super.key, required this.type, required this.child, this.durationMs = 700});
  final String? type;
  final int durationMs;
  final Widget child;
  @override
  State<EntryAnimation> createState() => _EntryAnimationState();
}

class _EntryAnimationState extends State<EntryAnimation> with SingleTickerProviderStateMixin {
  late final AnimationController c = AnimationController(vsync: this, duration: Duration(milliseconds: widget.durationMs.clamp(100, 5000)));

  @override
  void initState() {
    super.initState();
    c.forward();
  }

  @override
  void dispose() {
    c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final t = CurvedAnimation(parent: c, curve: Curves.easeOutCubic);
    return switch (widget.type) {
      'fade_in' => FadeTransition(opacity: t, child: widget.child),
      'slide_up' => SlideTransition(position: Tween(begin: const Offset(0, 0.25), end: Offset.zero).animate(t), child: FadeTransition(opacity: t, child: widget.child)),
      'slide_left' => SlideTransition(position: Tween(begin: const Offset(0.25, 0), end: Offset.zero).animate(t), child: FadeTransition(opacity: t, child: widget.child)),
      'zoom_in' => ScaleTransition(scale: Tween(begin: 0.8, end: 1.0).animate(t), child: FadeTransition(opacity: t, child: widget.child)),
      'typewriter' => FadeTransition(opacity: t, child: widget.child),
      _ => widget.child,
    };
  }
}

/// Rebuilds on a fixed interval (clocks, timers, rotators).
class Ticking extends StatefulWidget {
  const Ticking({super.key, required this.interval, required this.builder});
  final Duration interval;
  final WidgetBuilder builder;
  @override
  State<Ticking> createState() => _TickingState();
}

class _TickingState extends State<Ticking> {
  Timer? _t;
  @override
  void initState() {
    super.initState();
    _t = Timer.periodic(widget.interval, (_) {
      if (mounted) setState(() {});
    });
  }

  @override
  void dispose() {
    _t?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => widget.builder(context);
}

/// Current time in a zone ('device'/null = local; or IANA name).
DateTime nowIn(String? zone, String? fallbackZone) {
  final z = (zone == null || zone == 'device' || zone.isEmpty) ? fallbackZone : zone;
  if (z == null || z.isEmpty) return DateTime.now();
  try {
    final t = tz.TZDateTime.now(tz.getLocation(z));
    return DateTime(t.year, t.month, t.day, t.hour, t.minute, t.second);
  } catch (_) {
    return DateTime.now();
  }
}

/// Panel decoration used by list-style widgets.
BoxDecoration panelDecoration(Map<String, dynamic> c) => BoxDecoration(
      color: parseHexColor(c['background_color']),
      borderRadius: BorderRadius.circular(asDouble(c['border_radius'])),
    );

// =============================================================================
// Dispatcher
// =============================================================================

/// Builds the visual for one widget, filling its box (sizes in design pixels).
Widget buildWidgetContent(BuildContext context, WidgetItem w) {
  final c = w.configuration;
  try {
    final Widget child = switch (w.widgetType) {
      'image' || 'logo' => ImageWidgetView(config: c, logo: w.widgetType == 'logo'),
      'background_image' => BackgroundImageWidgetView(config: c),
      'image_carousel' => CarouselWidgetView(config: c),
      'video' => VideoWidgetView(config: c),
      'heading' => TextWidgetView(config: c, heading: true),
      'text' => TextWidgetView(config: c),
      'ticker' => TickerWidgetView(config: c),
      'clock' => ClockWidgetView(config: c),
      'world_clock' => WorldClockView(config: c),
      'countdown' => CountdownView(config: c),
      'countup' => CountupView(config: c),
      'calendar' => CalendarView(config: c),
      'daily_schedule' => DailyScheduleView(config: c),
      'weather' => WeatherView(config: c),
      'rss_feed' => RssFeedView(config: c),
      'live_data' => LiveDataView(config: c),
      'qr_code' => QrWidgetView(config: c),
      'announcement' => AnnouncementView(config: c),
      'quote' => QuoteView(config: c),
      'daily_message' => DailyMessageView(config: c),
      'emergency' => EmergencyView(config: c),
      'menu_board' => MenuBoardView(config: c),
      'price_list' => PriceListView(config: c),
      'product_showcase' => ProductShowcaseView(config: c),
      'contact_info' => ContactInfoView(config: c),
      'opening_hours' => OpeningHoursView(config: c),
      'location_info' => LocationInfoView(config: c),
      'shape' => ShapeView(config: c),
      'webpage' => WebEmbedView(url: asString(c['url']), html: null, refreshMinutes: asInt(c['refresh_minutes'])),
      'custom_html' => WebEmbedView(url: null, html: '<style>${asString(c['css'])}</style>${asString(c['html'])}', allowScripts: asBool(c['allow_scripts'])),
      _ => EditorPlaceholder(icon: Icons.extension_outlined, label: '${w.widgetType} (not supported in this app version)'),
    };
    final anim = c['animation']?.toString();
    final scope = RenderScope.of(context);
    if (scope.animate && anim != null && anim != 'none') {
      return EntryAnimation(type: anim, durationMs: asInt(c['animation_duration_ms'], 700), child: child);
    }
    return child;
  } catch (e) {
    RenderScope.of(context).onError?.call(w.widgetType, e);
    return const SizedBox.shrink();
  }
}

// =============================================================================
// Media widgets
// =============================================================================

class ImageWidgetView extends StatelessWidget {
  const ImageWidgetView({super.key, required this.config, this.logo = false});
  final Map<String, dynamic> config;
  final bool logo;
  @override
  Widget build(BuildContext context) {
    final scope = RenderScope.of(context);
    final id = asIntOrNull(config['media_id']);
    final provider = scope.media.image(id, thumb: scope.mode == RenderMode.thumbnail);
    if (provider == null) {
      return EditorPlaceholder(icon: logo ? Icons.verified_outlined : Icons.image_outlined, label: asString(config['placeholder'], logo ? 'Logo' : 'Image'));
    }
    final radius = asDouble(config['border_radius']);
    final bw = asDouble(config['border_width']);
    Widget img = Image(
      image: provider,
      fit: fitOf(config['fit'] ?? (logo ? 'contain' : 'cover')),
      alignment: boxAlign(config['alignment']?.toString(), null),
      width: double.infinity,
      height: double.infinity,
      gaplessPlayback: true,
      errorBuilder: (_, e, _) {
        scope.onError?.call('image', e);
        return const SizedBox.shrink();
      },
    );
    final raw = img;
    img = Container(
      decoration: BoxDecoration(
        color: parseHexColor(config['background_color']),
        borderRadius: BorderRadius.circular(radius),
        border: bw > 0 ? Border.all(color: colorOr(config['border_color'], Colors.white), width: bw) : null,
      ),
      clipBehavior: radius > 0 ? Clip.antiAlias : Clip.none,
      child: img,
    );
    // Drop shadow that follows the image's transparency (cut-out PNGs get a dish-shaped shadow).
    final sc = parseHexColor(config['shadow_color']);
    if (sc != null && scope.mode != RenderMode.thumbnail) {
      final blur = asDouble(config['shadow_blur'], 30);
      img = Stack(fit: StackFit.expand, clipBehavior: Clip.none, children: [
        Transform.translate(
          offset: Offset(asDouble(config['shadow_offset_x']), asDouble(config['shadow_offset_y'], 18)),
          child: ImageFiltered(
            imageFilter: ui.ImageFilter.blur(sigmaX: blur / 2, sigmaY: blur / 2, tileMode: TileMode.decal),
            child: ColorFiltered(
              colorFilter: ColorFilter.mode(sc, BlendMode.srcIn),
              child: radius > 0 ? ClipRRect(borderRadius: BorderRadius.circular(radius), child: raw) : raw,
            ),
          ),
        ),
        img,
      ]);
    }
    return Opacity(opacity: asDouble(config['opacity'], 1).clamp(0, 1), child: img);
  }
}

class BackgroundImageWidgetView extends StatelessWidget {
  const BackgroundImageWidgetView({super.key, required this.config});
  final Map<String, dynamic> config;
  @override
  Widget build(BuildContext context) {
    final scope = RenderScope.of(context);
    final p = scope.media.image(asIntOrNull(config['media_id']), thumb: scope.mode == RenderMode.thumbnail);
    final overlay = colorOr(config['overlay_color'], Colors.black).withValues(alpha: asDouble(config['overlay_opacity']).clamp(0, 1));
    final blur = asDouble(config['blur']);
    return Stack(fit: StackFit.expand, children: [
      if (p == null)
        const EditorPlaceholder(icon: Icons.wallpaper, label: 'Background image')
      else
        ImageFiltered(
          imageFilter: ui.ImageFilter.blur(sigmaX: blur, sigmaY: blur),
          child: Image(image: p, fit: fitOf(config['fit']), errorBuilder: (_, _, _) => const SizedBox.shrink()),
        ),
      ColoredBox(color: overlay),
    ]);
  }
}

/// Rotates through images inside one widget (not to be confused with a playlist of screens).
class CarouselWidgetView extends StatefulWidget {
  const CarouselWidgetView({super.key, required this.config});
  final Map<String, dynamic> config;
  @override
  State<CarouselWidgetView> createState() => _CarouselWidgetViewState();
}

class _CarouselWidgetViewState extends State<CarouselWidgetView> {
  int index = 0;
  Timer? timer;

  List<Map<String, dynamic>> get items => asList(widget.config['items']).map(asMap).where((m) => m['media_id'] != null).toList();

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    _schedule();
  }

  void _schedule() {
    timer?.cancel();
    final list = items;
    if (!RenderScope.of(context).live || list.length < 2) return;
    final d = asInt(list[index % list.length]['duration'], asInt(widget.config['default_duration'], 5)).clamp(1, 3600);
    timer = Timer(Duration(seconds: d) + Duration(milliseconds: asInt(widget.config['pause_ms'])), () {
      if (!mounted) return;
      final loop = asBool(widget.config['loop'], true);
      if (!loop && index >= list.length - 1) return;
      setState(() => index = (index + 1) % list.length);
      _schedule();
    });
  }

  @override
  void dispose() {
    timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final list = items;
    if (list.isEmpty) return const EditorPlaceholder(icon: Icons.view_carousel_outlined, label: 'Add images');
    final scope = RenderScope.of(context);
    final cur = list[index % list.length];
    final p = scope.media.image(asIntOrNull(cur['media_id']), thumb: scope.mode == RenderMode.thumbnail);
    final radius = asDouble(widget.config['border_radius']);
    final child = KeyedSubtree(
      key: ValueKey('${cur['media_id']}_$index'),
      child: p == null
          ? const SizedBox.expand()
          : Image(image: p, fit: fitOf(cur['fit'] ?? widget.config['fit']), width: double.infinity, height: double.infinity, errorBuilder: (_, _, _) => const SizedBox.shrink()),
    );
    return ClipRRect(
      borderRadius: BorderRadius.circular(radius),
      child: AnimatedSwitcher(
        duration: Duration(milliseconds: asInt(widget.config['transition_ms'], 800).clamp(0, 5000)),
        transitionBuilder: (c, a) => transitionFor(widget.config['transition']?.toString() ?? 'fade', c, a),
        layoutBuilder: (cur, prev) => Stack(fit: StackFit.expand, children: [...prev, ?cur]),
        child: child,
      ),
    );
  }
}

/// Shared transition builder (carousel widget + playlist player).
Widget transitionFor(String type, Widget child, Animation<double> a) {
  final t = CurvedAnimation(parent: a, curve: Curves.easeInOutCubic);
  return switch (type) {
    'slide' || 'slide_left' => SlideTransition(position: Tween(begin: const Offset(1, 0), end: Offset.zero).animate(t), child: child),
    'slide_right' => SlideTransition(position: Tween(begin: const Offset(-1, 0), end: Offset.zero).animate(t), child: child),
    'slide_up' => SlideTransition(position: Tween(begin: const Offset(0, 1), end: Offset.zero).animate(t), child: child),
    'slide_down' => SlideTransition(position: Tween(begin: const Offset(0, -1), end: Offset.zero).animate(t), child: child),
    'zoom' => ScaleTransition(scale: Tween(begin: 1.15, end: 1.0).animate(t), child: FadeTransition(opacity: t, child: child)),
    'flip' => AnimatedBuilder(
        animation: t,
        child: child,
        builder: (_, c) => Transform(
          alignment: Alignment.center,
          transform: Matrix4.identity()
            ..setEntry(3, 2, 0.001)
            ..rotateY((1 - t.value) * math.pi / 2),
          child: c,
        ),
      ),
    'none' => child,
    _ => FadeTransition(opacity: t, child: child),
  };
}

class VideoWidgetView extends StatefulWidget {
  const VideoWidgetView({super.key, required this.config, this.mediaIdOverride});
  final Map<String, dynamic> config;
  final int? mediaIdOverride;
  @override
  State<VideoWidgetView> createState() => _VideoWidgetViewState();
}

class _VideoWidgetViewState extends State<VideoWidgetView> {
  VideoPlayerController? ctrl;
  int index = 0;
  bool failed = false;
  bool _started = false;

  List<int> get ids {
    if (widget.mediaIdOverride != null) return [widget.mediaIdOverride!];
    final list = asList(widget.config['items']).map((e) => asIntOrNull(asMap(e)['media_id'])).whereType<int>().toList();
    final single = asIntOrNull(widget.config['media_id']);
    if (single != null && !list.contains(single)) list.insert(0, single);
    return list;
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (!_started && RenderScope.of(context).live) {
      _started = true;
      _load();
    }
  }

  Future<void> _load() async {
    final list = ids;
    if (list.isEmpty) return;
    final scope = RenderScope.of(context);
    final old = ctrl;
    final c = scope.media.video(list[index % list.length]);
    if (c == null) return;
    try {
      await c.initialize();
      if (!mounted) {
        c.dispose();
        return;
      }
      final muted = asBool(widget.config['muted'], true);
      await c.setVolume(muted ? 0 : 1);
      final start = asInt(widget.config['start_sec']);
      if (start > 0) await c.seekTo(Duration(seconds: start));
      final single = list.length == 1;
      await c.setLooping(single && asBool(widget.config['loop'], true) && widget.config['end_sec'] == null);
      c.addListener(() => _onTick(c));
      if (asBool(widget.config['autoplay'], true)) await c.play();
      setState(() {
        ctrl = c;
        failed = false;
      });
      old?.dispose();
    } catch (e) {
      scope.onError?.call('video', e);
      c.dispose();
      if (mounted) setState(() => failed = true);
      // Skip to next video after a short delay if there are several.
      if (ids.length > 1) Future.delayed(const Duration(seconds: 2), _next);
    }
  }

  void _onTick(VideoPlayerController c) {
    if (c != ctrl || !mounted) return;
    final v = c.value;
    final endSec = asIntOrNull(widget.config['end_sec']);
    final reachedEnd = (endSec != null && v.position.inSeconds >= endSec) ||
        (!v.isLooping && v.duration > Duration.zero && v.position >= v.duration - const Duration(milliseconds: 200));
    if (reachedEnd && !_advancing) {
      _advancing = true;
      _next().whenComplete(() => _advancing = false);
    }
  }

  bool _advancing = false;

  Future<void> _next() async {
    if (!mounted) return;
    final list = ids;
    if (list.length == 1) {
      if (asBool(widget.config['loop'], true)) {
        await ctrl?.seekTo(Duration(seconds: asInt(widget.config['start_sec'])));
        await ctrl?.play();
      }
      return;
    }
    index = (index + 1) % list.length;
    await _load();
  }

  @override
  void dispose() {
    ctrl?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final scope = RenderScope.of(context);
    if (ids.isEmpty) return const EditorPlaceholder(icon: Icons.smart_display_outlined, label: 'Add a video');
    if (!scope.live) {
      final name = scope.media.info(ids.first)?.originalName ?? 'Video';
      return Container(
        color: Colors.black,
        child: Center(
          child: FittedBox(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                const Icon(Icons.play_circle_fill_rounded, size: 120, color: Colors.white70),
                Text(name, style: const TextStyle(color: Colors.white70, fontSize: 32)),
              ]),
            ),
          ),
        ),
      );
    }
    final c = ctrl;
    if (failed || c == null || !c.value.isInitialized) return const ColoredBox(color: Colors.black);
    final radius = asDouble(widget.config['border_radius']);
    return ClipRRect(
      borderRadius: BorderRadius.circular(radius),
      child: ColoredBox(
        color: Colors.black,
        child: SizedBox.expand(
          child: FittedBox(
            fit: fitOf(widget.config['fit']),
            clipBehavior: Clip.hardEdge,
            child: SizedBox(width: c.value.size.width, height: c.value.size.height, child: VideoPlayer(c)),
          ),
        ),
      ),
    );
  }
}

// =============================================================================
// Text widgets
// =============================================================================

class TextWidgetView extends StatelessWidget {
  const TextWidgetView({super.key, required this.config, this.heading = false});
  final Map<String, dynamic> config;
  final bool heading;
  @override
  Widget build(BuildContext context) {
    final style = textStyleFrom(config, size: heading ? 96 : 40, weight: heading ? FontWeight.w700 : FontWeight.w400);
    final align = alignOf(config['align'] ?? (heading ? 'center' : 'left'));
    final rtl = config['text_direction'] == 'rtl';
    final spans = asList(config['spans']);
    final Widget text = spans.isNotEmpty
        ? Text.rich(
            TextSpan(children: [
              for (final s in spans.map(asMap))
                TextSpan(
                  text: asString(s['text']),
                  style: style.copyWith(
                    fontWeight: asBool(s['bold']) ? FontWeight.w700 : null,
                    fontStyle: asBool(s['italic']) ? FontStyle.italic : null,
                    decoration: asBool(s['underline']) ? TextDecoration.underline : null,
                    color: parseHexColor(s['color']),
                  ),
                ),
            ]),
            textAlign: align,
            textDirection: rtl ? ui.TextDirection.rtl : null,
          )
        : Text(asString(config['text']), style: style, textAlign: align, textDirection: rtl ? ui.TextDirection.rtl : null);
    final vAlign = config['vertical_align']?.toString() ?? (heading ? 'center' : 'top');
    final hAlign = switch (align) { TextAlign.left => 'left', TextAlign.right => 'right', _ => 'center' };
    return Opacity(
      opacity: asDouble(config['opacity'], 1).clamp(0, 1),
      child: Container(
        decoration: panelDecoration(config),
        padding: EdgeInsets.all(asDouble(config['padding'])),
        alignment: boxAlign(hAlign, vAlign),
        child: FittedBox(fit: BoxFit.scaleDown, alignment: boxAlign(hAlign, vAlign), child: ConstrainedBox(constraints: const BoxConstraints(maxWidth: 100000), child: _WrapToBox(child: text))),
      ),
    );
  }
}

/// Lets text wrap at the box width while still scaling down if it overflows vertically.
class _WrapToBox extends StatelessWidget {
  const _WrapToBox({required this.child});
  final Widget child;
  @override
  Widget build(BuildContext context) => LayoutBuilder(builder: (context, _) => child);
}

class TickerWidgetView extends StatefulWidget {
  const TickerWidgetView({super.key, required this.config});
  final Map<String, dynamic> config;
  @override
  State<TickerWidgetView> createState() => _TickerWidgetViewState();
}

class _TickerWidgetViewState extends State<TickerWidgetView> with SingleTickerProviderStateMixin {
  late final AnimationController ctrl = AnimationController(vsync: this);
  double contentExtent = 1;

  @override
  void dispose() {
    ctrl.dispose();
    super.dispose();
  }

  String get joined {
    final msgs = asList(widget.config['messages']).map((e) => e.toString()).where((s) => s.isNotEmpty).toList();
    final sep = asString(widget.config['separator'], '•');
    return msgs.join('     $sep     ');
  }

  @override
  Widget build(BuildContext context) {
    final c = widget.config;
    final style = textStyleFrom(c, size: 42, weight: FontWeight.w600);
    final vertical = c['direction'] == 'up' || c['direction'] == 'down';
    final speed = asDouble(c['speed'], 120).clamp(5, 2000);
    final gap = asDouble(c['gap'], 80);
    final text = joined.isEmpty ? ' ' : joined;
    return Container(
      color: parseHexColor(c['background_color']),
      child: LayoutBuilder(builder: (context, box) {
        final scope = RenderScope.of(context);
        if (!vertical) {
          final tp = TextPainter(text: TextSpan(text: text, style: style), textDirection: ui.TextDirection.ltr, maxLines: 1)..layout();
          final extent = tp.width + gap;
          if (scope.live && (contentExtent != extent || !ctrl.isAnimating)) {
            contentExtent = extent;
            ctrl.duration = Duration(milliseconds: (extent / speed * 1000).round().clamp(500, 3600000));
            ctrl.repeat();
          }
          final reverse = c['direction'] == 'right';
          // Identical copies side by side, shifted by exactly one copy's share of the row.
          // Uses the real laid-out width, so it stays seamless even if fonts load late.
          final copies = math.max(2, (box.maxWidth / extent).ceil() + 1);
          return ClipRect(
            child: OverflowBox(
              maxWidth: double.infinity,
              alignment: Alignment.centerLeft,
              child: AnimatedBuilder(
                animation: ctrl,
                builder: (_, child) {
                  final v = scope.live ? ctrl.value : 0.0;
                  return FractionalTranslation(translation: Offset(-(reverse ? 1 - v : v) / copies, 0), child: child);
                },
                child: Row(mainAxisSize: MainAxisSize.min, children: [
                  for (var i = 0; i < copies; i++) ...[
                    Text(text, style: style, maxLines: 1, softWrap: false),
                    SizedBox(width: gap),
                  ],
                ]),
              ),
            ),
          );
        }
        // Vertical: messages scroll upward one after another.
        final msgs = asList(c['messages']).map((e) => e.toString()).toList();
        final itemH = box.maxHeight;
        final extent = itemH * math.max(1, msgs.length);
        if (scope.live && !ctrl.isAnimating) {
          ctrl.duration = Duration(milliseconds: (extent / speed * 1000).round().clamp(500, 3600000));
          ctrl.repeat();
        }
        return ClipRect(
          child: AnimatedBuilder(
            animation: ctrl,
            builder: (_, _) {
              final off = (scope.live ? ctrl.value : 0) * extent;
              return Stack(children: [
                for (var i = 0; i < msgs.length * 2; i++)
                  Positioned(
                    left: 0, right: 0, height: itemH,
                    top: c['direction'] == 'down' ? (i * itemH + off - extent) : (i * itemH - off),
                    child: Center(child: Text(msgs.isEmpty ? '' : msgs[i % msgs.length], style: style, maxLines: 1, overflow: TextOverflow.ellipsis)),
                  ),
              ]);
            },
          ),
        );
      }),
    );
  }
}

class AnnouncementView extends StatelessWidget {
  const AnnouncementView({super.key, required this.config});
  final Map<String, dynamic> config;
  @override
  Widget build(BuildContext context) {
    final items = asList(config['items']).map(asMap).toList();
    final accent = colorOr(config['accent_color'], AppColors.teal);
    final titleStyle = fontStyle(config['font_family']?.toString(), TextStyle(color: colorOr(config['title_color'], Colors.white), fontSize: 56, fontWeight: FontWeight.w700));
    final bodyStyle = fontStyle(config['font_family']?.toString(), TextStyle(color: colorOr(config['color'], const Color(0xFFE5F5F3)), fontSize: 34, height: 1.35));
    return Container(
      decoration: panelDecoration(config),
      padding: const EdgeInsets.all(36),
      child: FittedBox(
        fit: BoxFit.scaleDown,
        alignment: Alignment.topLeft,
        child: SizedBox(
          width: 900,
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
            Row(children: [
              Icon(Icons.campaign_rounded, color: accent, size: 60),
              const SizedBox(width: 16),
              Expanded(child: Text(asString(config['title'], 'Announcements'), style: titleStyle)),
            ]),
            const SizedBox(height: 24),
            for (final it in items)
              Padding(
                padding: const EdgeInsets.only(bottom: 22),
                child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Container(width: 8, height: 70, margin: const EdgeInsets.only(right: 20, top: 4), decoration: BoxDecoration(color: accent, borderRadius: BorderRadius.circular(4))),
                  Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(asString(it['title']), style: bodyStyle.copyWith(fontWeight: FontWeight.w700, fontSize: 40, color: titleStyle.color)),
                      if (asString(it['body']).isNotEmpty) Text(asString(it['body']), style: bodyStyle),
                    ]),
                  ),
                ]),
              ),
          ]),
        ),
      ),
    );
  }
}

class QuoteView extends StatelessWidget {
  const QuoteView({super.key, required this.config});
  final Map<String, dynamic> config;
  @override
  Widget build(BuildContext context) {
    final quotes = asList(config['quotes']).map(asMap).toList();
    if (quotes.isEmpty) return const EditorPlaceholder(icon: Icons.format_quote, label: 'Add quotes');
    final rotate = asInt(config['rotate_seconds'], 15).clamp(2, 86400);
    return Ticking(
      interval: Duration(seconds: rotate),
      builder: (context) {
        final idx = config['mode'] == 'daily'
            ? DateTime.now().difference(DateTime(DateTime.now().year)).inDays % quotes.length
            : (DateTime.now().millisecondsSinceEpoch ~/ (rotate * 1000)) % quotes.length;
        final q = quotes[idx];
        final style = textStyleFrom(config, size: 56, weight: FontWeight.w500);
        return Container(
          padding: const EdgeInsets.all(24),
          child: FittedBox(
            fit: BoxFit.scaleDown,
            child: SizedBox(
              width: 1400,
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                Icon(Icons.format_quote_rounded, size: 90, color: colorOr(config['accent_color'], AppColors.teal)),
                Text('“${asString(q['text'])}”', style: style.copyWith(fontStyle: FontStyle.italic), textAlign: alignOf(config['align'])),
                if (asString(q['author']).isNotEmpty) ...[
                  const SizedBox(height: 18),
                  Text('— ${q['author']}', style: style.copyWith(fontSize: style.fontSize! * 0.55, fontStyle: FontStyle.normal, color: style.color?.withValues(alpha: 0.75))),
                ],
              ]),
            ),
          ),
        );
      },
    );
  }
}

class DailyMessageView extends StatelessWidget {
  const DailyMessageView({super.key, required this.config});
  final Map<String, dynamic> config;
  @override
  Widget build(BuildContext context) {
    final msgs = asList(config['messages']).map((e) => e.toString()).toList();
    if (msgs.isEmpty) return const EditorPlaceholder(icon: Icons.self_improvement, label: 'Add messages');
    final day = DateTime.now().difference(DateTime(2020)).inDays;
    return Center(
      child: FittedBox(
        fit: BoxFit.scaleDown,
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: SizedBox(width: 1200, child: Text(msgs[day % msgs.length], textAlign: alignOf(config['align']), style: textStyleFrom(config, size: 48, weight: FontWeight.w600))),
        ),
      ),
    );
  }
}

class EmergencyView extends StatelessWidget {
  const EmergencyView({super.key, required this.config});
  final Map<String, dynamic> config;
  @override
  Widget build(BuildContext context) => EmergencyBanner(
        title: asString(config['title'], 'Attention'),
        message: asString(config['message']),
        background: colorOr(config['background_color'], const Color(0xFFB00020)),
        color: colorOr(config['color'], Colors.white),
        flash: asBool(config['flash'], true) && RenderScope.of(context).live,
      );
}

/// Full-bleed alert (also used by the player for emergency broadcasts).
class EmergencyBanner extends StatefulWidget {
  const EmergencyBanner({super.key, required this.title, required this.message, required this.background, required this.color, this.flash = true});
  final String title, message;
  final Color background, color;
  final bool flash;
  @override
  State<EmergencyBanner> createState() => _EmergencyBannerState();
}

class _EmergencyBannerState extends State<EmergencyBanner> with SingleTickerProviderStateMixin {
  late final AnimationController c = AnimationController(vsync: this, duration: const Duration(milliseconds: 900));
  @override
  void initState() {
    super.initState();
    if (widget.flash) c.repeat(reverse: true);
  }

  @override
  void dispose() {
    c.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => AnimatedBuilder(
        animation: c,
        builder: (_, _) => Container(
          color: Color.lerp(widget.background, Colors.black, widget.flash ? c.value * 0.25 : 0),
          padding: const EdgeInsets.all(48),
          child: Center(
            child: FittedBox(
              fit: BoxFit.scaleDown,
              child: SizedBox(
                width: 1600,
                child: Column(mainAxisSize: MainAxisSize.min, children: [
                  Icon(Icons.warning_amber_rounded, color: widget.color, size: 160),
                  const SizedBox(height: 16),
                  Text(widget.title, textAlign: TextAlign.center, style: TextStyle(color: widget.color, fontSize: 120, fontWeight: FontWeight.w800)),
                  if (widget.message.isNotEmpty) ...[
                    const SizedBox(height: 24),
                    Text(widget.message, textAlign: TextAlign.center, style: TextStyle(color: widget.color, fontSize: 56, height: 1.3)),
                  ],
                ]),
              ),
            ),
          ),
        ),
      );
}

// =============================================================================
// Time widgets
// =============================================================================

String _timeFormat(Map<String, dynamic> c, {bool seconds = false}) {
  final h12 = c['format'] == '12h';
  final s = seconds || asBool(c['show_seconds']);
  return h12 ? (s ? 'h:mm:ss a' : 'h:mm a') : (s ? 'HH:mm:ss' : 'HH:mm');
}

class ClockWidgetView extends StatelessWidget {
  const ClockWidgetView({super.key, required this.config});
  final Map<String, dynamic> config;
  @override
  Widget build(BuildContext context) {
    final scope = RenderScope.of(context);
    return Ticking(
      interval: const Duration(seconds: 1),
      builder: (context) {
        final now = nowIn(config['timezone']?.toString(), scope.timezone);
        final color = colorOr(config['color'], Colors.white);
        final accent = colorOr(config['accent_color'], AppColors.teal);
        if (config['style'] == 'analog') {
          return Center(
            child: AspectRatio(
              aspectRatio: 1,
              child: CustomPaint(painter: AnalogClockPainter(now, color, accent, asBool(config['show_seconds'], true))),
            ),
          );
        }
        final timeStyle = textStyleFrom(config, size: 120, weight: FontWeight.w600);
        final dateStyle = timeStyle.copyWith(fontSize: (timeStyle.fontSize ?? 120) * 0.32, fontWeight: FontWeight.w500, color: color.withValues(alpha: 0.8));
        final align = config['align']?.toString() ?? 'center';
        final cross = align == 'left' ? CrossAxisAlignment.start : (align == 'right' ? CrossAxisAlignment.end : CrossAxisAlignment.center);
        String dateText;
        try {
          dateText = DateFormat(asString(config['date_format'], 'EEEE, d MMMM yyyy')).format(now);
        } catch (_) {
          dateText = DateFormat.yMMMMEEEEd().format(now);
        }
        return FittedBox(
          fit: BoxFit.scaleDown,
          alignment: boxAlign(align, 'center'),
          child: Column(crossAxisAlignment: cross, mainAxisSize: MainAxisSize.min, children: [
            Text(DateFormat(_timeFormat(config)).format(now), style: timeStyle),
            if (asBool(config['show_date'], true)) Text(dateText, style: dateStyle),
          ]),
        );
      },
    );
  }
}

class AnalogClockPainter extends CustomPainter {
  AnalogClockPainter(this.now, this.color, this.accent, this.seconds);
  final DateTime now;
  final Color color, accent;
  final bool seconds;

  @override
  void paint(Canvas canvas, Size size) {
    final r = size.shortestSide / 2;
    final c = Offset(size.width / 2, size.height / 2);
    canvas.drawCircle(c, r * 0.97, Paint()..color = color.withValues(alpha: 0.08));
    canvas.drawCircle(c, r * 0.97, Paint()
      ..color = color.withValues(alpha: 0.6)
      ..style = PaintingStyle.stroke
      ..strokeWidth = r * 0.025);
    for (var i = 0; i < 60; i++) {
      final a = i * math.pi / 30;
      final big = i % 5 == 0;
      final p1 = c + Offset(math.sin(a), -math.cos(a)) * r * (big ? 0.80 : 0.86);
      final p2 = c + Offset(math.sin(a), -math.cos(a)) * r * 0.91;
      canvas.drawLine(p1, p2, Paint()
        ..color = color.withValues(alpha: big ? 0.9 : 0.4)
        ..strokeWidth = r * (big ? 0.025 : 0.01)
        ..strokeCap = StrokeCap.round);
    }
    void hand(double angle, double len, double w, Color col) => canvas.drawLine(
        c, c + Offset(math.sin(angle), -math.cos(angle)) * r * len,
        Paint()
          ..color = col
          ..strokeWidth = r * w
          ..strokeCap = StrokeCap.round);
    final h = (now.hour % 12 + now.minute / 60) * math.pi / 6;
    final m = (now.minute + now.second / 60) * math.pi / 30;
    hand(h, 0.5, 0.05, color);
    hand(m, 0.75, 0.035, color);
    if (seconds) hand(now.second * math.pi / 30, 0.82, 0.012, accent);
    canvas.drawCircle(c, r * 0.04, Paint()..color = accent);
  }

  @override
  bool shouldRepaint(AnalogClockPainter old) => old.now != now || old.color != color;
}

class WorldClockView extends StatelessWidget {
  const WorldClockView({super.key, required this.config});
  final Map<String, dynamic> config;
  @override
  Widget build(BuildContext context) {
    final zones = asList(config['zones']).map(asMap).toList();
    final color = colorOr(config['color'], Colors.white);
    final accent = colorOr(config['accent_color'], AppColors.teal);
    return Ticking(
      interval: const Duration(seconds: 15),
      builder: (_) => FittedBox(
        fit: BoxFit.scaleDown,
        child: Row(mainAxisSize: MainAxisSize.min, children: [
          for (final z in zones)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 40),
              child: Column(mainAxisSize: MainAxisSize.min, children: [
                Text(asString(z['label']).toUpperCase(), style: fontStyle(config['font_family']?.toString(), TextStyle(color: accent, fontSize: 30, fontWeight: FontWeight.w700, letterSpacing: 3))),
                Text(DateFormat(_timeFormat(config)).format(nowIn(asString(z['timezone']), null)),
                    style: fontStyle(config['font_family']?.toString(), TextStyle(color: color, fontSize: 72, fontWeight: FontWeight.w600))),
              ]),
            ),
        ]),
      ),
    );
  }
}

class CountdownView extends StatelessWidget {
  const CountdownView({super.key, required this.config});
  final Map<String, dynamic> config;
  @override
  Widget build(BuildContext context) {
    final target = DateTime.tryParse(asString(config['target']));
    final style = textStyleFrom(config, size: 96, weight: FontWeight.w700);
    final accent = colorOr(config['accent_color'], AppColors.teal);
    return Ticking(
      interval: const Duration(seconds: 1),
      builder: (_) {
        if (target == null) return const EditorPlaceholder(icon: Icons.hourglass_bottom, label: 'Set a target date');
        final diff = target.difference(DateTime.now());
        if (diff.isNegative) {
          return Center(child: FittedBox(child: Text(asString(config['finished_text'], "It's time!"), style: style)));
        }
        final parts = <(String, int)>[
          if (asBool(config['show_days'], true)) ('DAYS', diff.inDays),
          ('HOURS', asBool(config['show_days'], true) ? diff.inHours % 24 : diff.inHours),
          ('MIN', diff.inMinutes % 60),
          if (asBool(config['show_seconds'], true)) ('SEC', diff.inSeconds % 60),
        ];
        return FittedBox(
          fit: BoxFit.scaleDown,
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            if (asString(config['label']).isNotEmpty)
              Text(asString(config['label']), style: style.copyWith(fontSize: (style.fontSize ?? 96) * 0.4, fontWeight: FontWeight.w500)),
            Row(mainAxisSize: MainAxisSize.min, children: [
              for (final p in parts)
                Container(
                  margin: const EdgeInsets.symmetric(horizontal: 14),
                  padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 16),
                  decoration: BoxDecoration(color: accent.withValues(alpha: 0.18), borderRadius: BorderRadius.circular(24)),
                  child: Column(children: [
                    Text(p.$2.toString().padLeft(2, '0'), style: style),
                    Text(p.$1, style: style.copyWith(fontSize: (style.fontSize ?? 96) * 0.25, letterSpacing: 4, color: accent)),
                  ]),
                ),
            ]),
          ]),
        );
      },
    );
  }
}

class CountupView extends StatelessWidget {
  const CountupView({super.key, required this.config});
  final Map<String, dynamic> config;
  @override
  Widget build(BuildContext context) {
    final start = DateTime.tryParse(asString(config['start']));
    final style = textStyleFrom(config, size: 96, weight: FontWeight.w700);
    return Ticking(
      interval: const Duration(seconds: 1),
      builder: (_) {
        if (start == null) return const EditorPlaceholder(icon: Icons.timer_outlined, label: 'Set a start date');
        final d = DateTime.now().difference(start);
        final value = switch (config['unit']) {
          'hours' => '${d.inHours}',
          'minutes' => '${d.inMinutes}',
          'hms' => '${d.inHours}:${(d.inMinutes % 60).toString().padLeft(2, '0')}:${(d.inSeconds % 60).toString().padLeft(2, '0')}',
          _ => '${d.inDays}',
        };
        return FittedBox(
          fit: BoxFit.scaleDown,
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Text(value, style: style),
            if (asString(config['label']).isNotEmpty) Text(asString(config['label']), style: style.copyWith(fontSize: (style.fontSize ?? 96) * 0.35, fontWeight: FontWeight.w500)),
          ]),
        );
      },
    );
  }
}

class CalendarView extends StatelessWidget {
  const CalendarView({super.key, required this.config});
  final Map<String, dynamic> config;
  @override
  Widget build(BuildContext context) {
    final now = DateTime.now();
    final first = DateTime(now.year, now.month, 1);
    final startDow = asInt(config['first_day_of_week'], 1);
    final offset = (first.weekday - startDow + 7) % 7;
    final days = DateUtils.getDaysInMonth(now.year, now.month);
    final color = colorOr(config['color'], Colors.white);
    final hi = colorOr(config['highlight_color'], AppColors.teal);
    final events = asList(config['events']).map(asMap).toList();
    final eventDays = events.map((e) => DateTime.tryParse(asString(e['date']))).whereType<DateTime>().where((d) => d.month == now.month && d.year == now.year).map((d) => d.day).toSet();
    final labels = List.generate(7, (i) => DateFormat.E().format(DateTime(2024, 1, 1 + ((startDow - 1 + i) % 7))).substring(0, 2));
    final ts = fontStyle(config['font_family']?.toString(), TextStyle(color: color, fontSize: 30));
    return Container(
      decoration: panelDecoration(config),
      padding: const EdgeInsets.all(28),
      child: FittedBox(
        fit: BoxFit.scaleDown,
        child: SizedBox(
          width: 700,
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Text(DateFormat.yMMMM().format(now), style: ts.copyWith(fontSize: 44, fontWeight: FontWeight.w700)),
            const SizedBox(height: 18),
            Row(children: [for (final l in labels) Expanded(child: Center(child: Text(l, style: ts.copyWith(color: color.withValues(alpha: 0.6), fontSize: 26))))]),
            const SizedBox(height: 8),
            for (var row = 0; row < ((offset + days) / 7).ceil(); row++)
              Row(children: [
                for (var col = 0; col < 7; col++)
                  Expanded(
                    child: Builder(builder: (_) {
                      final d = row * 7 + col - offset + 1;
                      if (d < 1 || d > days) return const SizedBox(height: 80);
                      final today = d == now.day;
                      return Container(
                        height: 80,
                        margin: const EdgeInsets.all(3),
                        decoration: BoxDecoration(color: today ? hi : null, borderRadius: BorderRadius.circular(16)),
                        child: Column(mainAxisAlignment: MainAxisAlignment.center, children: [
                          Text('$d', style: ts.copyWith(fontWeight: today ? FontWeight.w700 : FontWeight.w400)),
                          if (eventDays.contains(d)) Container(width: 8, height: 8, decoration: BoxDecoration(color: today ? Colors.white : hi, shape: BoxShape.circle)),
                        ]),
                      );
                    }),
                  ),
              ]),
          ]),
        ),
      ),
    );
  }
}

class DailyScheduleView extends StatelessWidget {
  const DailyScheduleView({super.key, required this.config});
  final Map<String, dynamic> config;
  @override
  Widget build(BuildContext context) {
    final scope = RenderScope.of(context);
    final items = asList(config['items']).map(asMap).toList();
    final color = colorOr(config['color'], Colors.white);
    final accent = colorOr(config['accent_color'], AppColors.teal);
    final ts = fontStyle(config['font_family']?.toString(), TextStyle(color: color, fontSize: 34));
    return Ticking(
      interval: const Duration(seconds: 30),
      builder: (_) {
        final now = nowIn(null, scope.timezone);
        final hm = '${now.hour.toString().padLeft(2, '0')}:${now.minute.toString().padLeft(2, '0')}';
        return Container(
          decoration: panelDecoration(config),
          padding: const EdgeInsets.all(32),
          child: FittedBox(
            fit: BoxFit.scaleDown,
            alignment: Alignment.topCenter,
            child: SizedBox(
              width: 900,
              child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
                if (asString(config['title']).isNotEmpty) ...[
                  Text(asString(config['title']), style: ts.copyWith(fontSize: 50, fontWeight: FontWeight.w700)),
                  const SizedBox(height: 22),
                ],
                for (final it in items)
                  Builder(builder: (_) {
                    final cur = asBool(config['highlight_current'], true) && hm.compareTo(asString(it['start'])) >= 0 && hm.compareTo(asString(it['end'], '99:99')) < 0;
                    return Container(
                      margin: const EdgeInsets.only(bottom: 14),
                      padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 18),
                      decoration: BoxDecoration(
                        color: cur ? accent : color.withValues(alpha: 0.06),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Row(children: [
                        SizedBox(
                          width: 230,
                          child: Text('${it['start'] ?? ''}${asString(it['end']).isNotEmpty ? ' – ${it['end']}' : ''}',
                              style: ts.copyWith(fontWeight: FontWeight.w700, color: cur ? Colors.white : accent)),
                        ),
                        Expanded(
                          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                            Text(asString(it['title']), style: ts.copyWith(fontWeight: FontWeight.w600, color: cur ? Colors.white : color)),
                            if (asString(it['subtitle']).isNotEmpty)
                              Text(asString(it['subtitle']), style: ts.copyWith(fontSize: 26, color: (cur ? Colors.white : color).withValues(alpha: 0.75))),
                          ]),
                        ),
                        if (cur) Text('NOW', style: ts.copyWith(fontSize: 24, fontWeight: FontWeight.w800, color: Colors.white, letterSpacing: 2)),
                      ]),
                    );
                  }),
              ]),
            ),
          ),
        );
      },
    );
  }
}

// =============================================================================
// Data widgets (network)
// =============================================================================

final Dio _http = Dio(BaseOptions(connectTimeout: const Duration(seconds: 10), receiveTimeout: const Duration(seconds: 20)));
final Map<String, (DateTime, dynamic)> _dataCache = {};

/// Fetch with in-memory cache; returns the last good value on failure (offline-friendly).
Future<dynamic> cachedFetch(String url, Duration maxAge, {ResponseType type = ResponseType.json}) async {
  final hit = _dataCache[url];
  if (hit != null && DateTime.now().difference(hit.$1) < maxAge) return hit.$2;
  try {
    final r = await _http.get(url, options: Options(responseType: type));
    _dataCache[url] = (DateTime.now(), r.data);
    return r.data;
  } catch (e) {
    if (hit != null) return hit.$2;
    rethrow;
  }
}

/// Periodically reloads data and keeps showing the last value if a refresh fails.
class AutoRefresh<T> extends StatefulWidget {
  const AutoRefresh({super.key, required this.load, required this.every, required this.builder, required this.widgetType});
  final Future<T> Function() load;
  final Duration every;
  final Widget Function(BuildContext, T? data, Object? error) builder;
  final String widgetType;
  @override
  State<AutoRefresh<T>> createState() => _AutoRefreshState<T>();
}

class _AutoRefreshState<T> extends State<AutoRefresh<T>> {
  T? data;
  Object? error;
  Timer? timer;

  @override
  void initState() {
    super.initState();
    _run();
    timer = Timer.periodic(widget.every, (_) => _run());
  }

  Future<void> _run() async {
    try {
      final d = await widget.load();
      if (mounted) setState(() { data = d; error = null; });
    } catch (e) {
      if (mounted) {
        RenderScope.of(context).onError?.call(widget.widgetType, e);
        setState(() => error = e);
      }
    }
  }

  @override
  void dispose() {
    timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => widget.builder(context, data, error);
}

IconData weatherIcon(int code) {
  if (code == 0) return Icons.wb_sunny_rounded;
  if (code <= 2) return Icons.wb_cloudy_outlined;
  if (code == 3) return Icons.cloud_rounded;
  if (code >= 45 && code <= 48) return Icons.foggy;
  if (code >= 51 && code <= 67) return Icons.grain_rounded;
  if (code >= 71 && code <= 77) return Icons.ac_unit_rounded;
  if (code >= 80 && code <= 82) return Icons.umbrella_rounded;
  if (code >= 95) return Icons.thunderstorm_rounded;
  return Icons.wb_cloudy_outlined;
}

String weatherLabel(int code) {
  if (code == 0) return 'Clear';
  if (code <= 2) return 'Partly cloudy';
  if (code == 3) return 'Overcast';
  if (code >= 45 && code <= 48) return 'Fog';
  if (code >= 51 && code <= 57) return 'Drizzle';
  if (code >= 61 && code <= 67) return 'Rain';
  if (code >= 71 && code <= 77) return 'Snow';
  if (code >= 80 && code <= 82) return 'Showers';
  if (code >= 95) return 'Thunderstorm';
  return 'Cloudy';
}

/// Weather via Open-Meteo (no API key required).
class WeatherView extends StatelessWidget {
  const WeatherView({super.key, required this.config});
  final Map<String, dynamic> config;

  String get url {
    final imperial = config['units'] == 'imperial';
    final days = asInt(config['forecast_days'], 3).clamp(1, 7);
    return 'https://api.open-meteo.com/v1/forecast?latitude=${asDouble(config['latitude'])}&longitude=${asDouble(config['longitude'])}'
        '&current=temperature_2m,relative_humidity_2m,wind_speed_10m,weather_code'
        '&daily=weather_code,temperature_2m_max,temperature_2m_min&timezone=auto&forecast_days=${days + 1}'
        '${imperial ? '&temperature_unit=fahrenheit&wind_speed_unit=mph' : ''}';
  }

  @override
  Widget build(BuildContext context) {
    final refresh = Duration(minutes: asInt(config['refresh_minutes'], 30).clamp(5, 1440));
    final color = colorOr(config['color'], Colors.white);
    final accent = colorOr(config['accent_color'], AppColors.teal);
    final ts = fontStyle(config['font_family']?.toString(), TextStyle(color: color, fontSize: 34));
    final unit = config['units'] == 'imperial' ? '°F' : '°C';
    return Container(
      decoration: panelDecoration(config),
      padding: const EdgeInsets.all(28),
      child: AutoRefresh<Map<String, dynamic>>(
        widgetType: 'weather',
        every: refresh,
        load: () async => asMap(await cachedFetch(url, refresh)),
        builder: (context, d, err) {
          if (d == null) {
            return Center(
              child: FittedBox(
                child: Column(mainAxisSize: MainAxisSize.min, children: [
                  Icon(err == null ? Icons.cloud_sync_outlined : Icons.cloud_off_outlined, color: color.withValues(alpha: 0.6), size: 90),
                  Text(err == null ? 'Loading weather…' : 'Weather unavailable', style: ts.copyWith(color: color.withValues(alpha: 0.7))),
                ]),
              ),
            );
          }
          final cur = asMap(d['current']);
          final daily = asMap(d['daily']);
          final code = asInt(cur['weather_code']);
          final days = asList(daily['time']);
          final n = math.min(asInt(config['forecast_days'], 3), days.length - 1);
          return FittedBox(
            fit: BoxFit.scaleDown,
            child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
              Text(asString(config['location_name']), style: ts.copyWith(fontSize: 38, fontWeight: FontWeight.w600)),
              Row(mainAxisSize: MainAxisSize.min, children: [
                Icon(weatherIcon(code), color: accent, size: 130),
                const SizedBox(width: 18),
                Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                  Text('${asDouble(cur['temperature_2m']).round()}$unit', style: ts.copyWith(fontSize: 110, fontWeight: FontWeight.w700, height: 1)),
                  Text(weatherLabel(code), style: ts.copyWith(color: color.withValues(alpha: 0.8))),
                ]),
              ]),
              if (asBool(config['show_humidity'], true) || asBool(config['show_wind'], true))
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: Row(mainAxisSize: MainAxisSize.min, children: [
                    if (asBool(config['show_humidity'], true)) ...[
                      Icon(Icons.water_drop_outlined, color: accent, size: 34),
                      Text(' ${asInt(cur['relative_humidity_2m'])}%   ', style: ts),
                    ],
                    if (asBool(config['show_wind'], true)) ...[
                      Icon(Icons.air, color: accent, size: 34),
                      Text(' ${asDouble(cur['wind_speed_10m']).round()} ${config['units'] == 'imperial' ? 'mph' : 'km/h'}', style: ts),
                    ],
                  ]),
                ),
              if (asBool(config['show_forecast'], true) && n > 0) ...[
                const SizedBox(height: 18),
                Row(mainAxisSize: MainAxisSize.min, children: [
                  for (var i = 1; i <= n; i++)
                    Padding(
                      padding: const EdgeInsets.only(right: 34),
                      child: Column(children: [
                        Text(DateFormat.E().format(DateTime.parse(asString(days[i]))), style: ts.copyWith(fontSize: 28, color: color.withValues(alpha: 0.7))),
                        Icon(weatherIcon(asInt(asList(daily['weather_code'])[i])), color: color, size: 50),
                        Text('${asDouble(asList(daily['temperature_2m_max'])[i]).round()}° / ${asDouble(asList(daily['temperature_2m_min'])[i]).round()}°', style: ts.copyWith(fontSize: 28)),
                      ]),
                    ),
                ]),
              ],
            ]),
          );
        },
      ),
    );
  }
}

/// Parses RSS 2.0 / Atom titles, descriptions and first image.
List<Map<String, String>> parseFeed(String xml) {
  String clean(String s) => s
      .replaceAll(RegExp(r'<!\[CDATA\[|\]\]>'), '')
      .replaceAll(RegExp(r'<[^>]+>'), '')
      .replaceAll('&amp;', '&')
      .replaceAll('&quot;', '"')
      .replaceAll('&#39;', "'")
      .replaceAll('&apos;', "'")
      .replaceAll('&lt;', '<')
      .replaceAll('&gt;', '>')
      .trim();
  final out = <Map<String, String>>[];
  final items = RegExp(r'<(item|entry)[\s>][\s\S]*?</\1>').allMatches(xml);
  for (final m in items) {
    final block = m.group(0)!;
    String tag(String t) => clean(RegExp('<$t[^>]*>([\\s\\S]*?)</$t>').firstMatch(block)?.group(1) ?? '');
    final img = RegExp(r'''(?:media:content|media:thumbnail|enclosure)[^>]*url=["']([^"']+)["']''').firstMatch(block)?.group(1) ?? '';
    out.add({'title': tag('title'), 'description': tag('description').isNotEmpty ? tag('description') : tag('summary'), 'image': img});
  }
  final source = clean(RegExp(r'<title[^>]*>([\s\S]*?)</title>').firstMatch(xml)?.group(1) ?? '');
  for (final o in out) {
    o['source'] = source;
  }
  return out;
}

class RssFeedView extends StatelessWidget {
  const RssFeedView({super.key, required this.config});
  final Map<String, dynamic> config;
  @override
  Widget build(BuildContext context) {
    final url = asString(config['url']);
    if (url.isEmpty) return const EditorPlaceholder(icon: Icons.rss_feed, label: 'Enter a feed URL');
    final refresh = Duration(minutes: asInt(config['refresh_minutes'], 15).clamp(5, 1440));
    final color = colorOr(config['color'], Colors.white);
    final accent = colorOr(config['accent_color'], AppColors.teal);
    final ts = fontStyle(config['font_family']?.toString(), TextStyle(color: color, fontSize: 40));
    return Container(
      decoration: panelDecoration(config),
      padding: const EdgeInsets.all(32),
      clipBehavior: Clip.antiAlias,
      child: AutoRefresh<List<Map<String, String>>>(
        widgetType: 'rss_feed',
        every: refresh,
        load: () async => parseFeed(asString(await cachedFetch(url, refresh, type: ResponseType.plain))).take(asInt(config['max_items'], 10)).toList(),
        builder: (context, items, err) {
          if (items == null || items.isEmpty) {
            return Center(
              child: Text(err != null ? (kIsWeb ? 'Feed preview unavailable in the browser (it will play on the display)' : 'Feed unavailable') : 'Loading headlines…',
                  textAlign: TextAlign.center, style: ts.copyWith(color: color.withValues(alpha: 0.7))),
            );
          }
          final rotate = asInt(config['rotate_seconds'], 8).clamp(2, 3600);
          return Ticking(
            interval: Duration(seconds: rotate),
            builder: (_) {
              final idx = (DateTime.now().millisecondsSinceEpoch ~/ (rotate * 1000)) % items.length;
              final it = items[idx];
              return Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Row(children: [
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                    decoration: BoxDecoration(color: accent, borderRadius: BorderRadius.circular(10)),
                    child: Text('NEWS', style: ts.copyWith(fontSize: 26, fontWeight: FontWeight.w800, letterSpacing: 3)),
                  ),
                  const SizedBox(width: 16),
                  if (asBool(config['show_source'], true)) Expanded(child: Text(it['source'] ?? '', style: ts.copyWith(fontSize: 26, color: color.withValues(alpha: 0.7)), overflow: TextOverflow.ellipsis)),
                  Text('${idx + 1}/${items.length}', style: ts.copyWith(fontSize: 24, color: color.withValues(alpha: 0.6))),
                ]),
                const SizedBox(height: 24),
                Expanded(
                  child: AnimatedSwitcher(
                    duration: const Duration(milliseconds: 600),
                    child: Column(
                      key: ValueKey(idx),
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(it['title'] ?? '', style: ts.copyWith(fontSize: 58, fontWeight: FontWeight.w700, height: 1.15), maxLines: 3, overflow: TextOverflow.ellipsis),
                        const SizedBox(height: 18),
                        Expanded(child: Text(it['description'] ?? '', style: ts.copyWith(color: color.withValues(alpha: 0.8), height: 1.35), overflow: TextOverflow.fade)),
                      ],
                    ),
                  ),
                ),
              ]);
            },
          );
        },
      ),
    );
  }
}

/// Reads a value from a JSON endpoint using a simple $.a.b[0].c path.
dynamic jsonPath(dynamic data, String path) {
  var cur = data;
  final parts = path.replaceFirst(RegExp(r'^\$\.?'), '').split(RegExp(r'\.|\[|\]')).where((p) => p.isNotEmpty);
  for (final p in parts) {
    final i = int.tryParse(p);
    if (i != null && cur is List && i < cur.length) {
      cur = cur[i];
    } else if (cur is Map) {
      cur = cur[p];
    } else {
      return null;
    }
  }
  return cur;
}

class LiveDataView extends StatelessWidget {
  const LiveDataView({super.key, required this.config});
  final Map<String, dynamic> config;
  @override
  Widget build(BuildContext context) {
    final url = asString(config['url']);
    if (url.isEmpty) return const EditorPlaceholder(icon: Icons.monitor_heart_outlined, label: 'Enter a data URL');
    final every = Duration(seconds: asInt(config['refresh_seconds'], 60).clamp(10, 86400));
    final style = textStyleFrom(config, size: 96, weight: FontWeight.w700);
    return AutoRefresh<dynamic>(
      widgetType: 'live_data',
      every: every,
      load: () async => jsonPath(await cachedFetch(url, every), asString(config['json_path'], r'$.value')),
      builder: (_, v, err) => FittedBox(
        fit: BoxFit.scaleDown,
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          Text('${config['prefix'] ?? ''}${v ?? (err != null ? '—' : '…')}${config['suffix'] ?? ''}', style: style),
          if (asString(config['label']).isNotEmpty) Text(asString(config['label']), style: style.copyWith(fontSize: (style.fontSize ?? 96) * 0.35, fontWeight: FontWeight.w500)),
        ]),
      ),
    );
  }
}

class QrWidgetView extends StatelessWidget {
  const QrWidgetView({super.key, required this.config});
  final Map<String, dynamic> config;
  @override
  Widget build(BuildContext context) {
    final data = asString(config['data']);
    final label = asString(config['label']);
    return Column(children: [
      Expanded(
        child: Center(
          child: AspectRatio(
            aspectRatio: 1,
            child: Container(
              padding: EdgeInsets.all(asDouble(config['padding'], 16)),
              decoration: BoxDecoration(color: colorOr(config['background'], Colors.white), borderRadius: BorderRadius.circular(asDouble(config['border_radius'], 16))),
              child: data.isEmpty
                  ? const Icon(Icons.qr_code_2, size: 100)
                  : QrImageView(
                      data: data,
                      padding: EdgeInsets.zero,
                      errorCorrectionLevel: switch (config['error_correction']) { 'L' => QrErrorCorrectLevel.L, 'Q' => QrErrorCorrectLevel.Q, 'H' => QrErrorCorrectLevel.H, _ => QrErrorCorrectLevel.M },
                      eyeStyle: QrEyeStyle(eyeShape: QrEyeShape.square, color: colorOr(config['foreground'], AppColors.charcoal)),
                      dataModuleStyle: QrDataModuleStyle(dataModuleShape: QrDataModuleShape.square, color: colorOr(config['foreground'], AppColors.charcoal)),
                    ),
            ),
          ),
        ),
      ),
      if (label.isNotEmpty)
        Padding(
          padding: const EdgeInsets.only(top: 10),
          child: FittedBox(child: Text(label, style: TextStyle(color: colorOr(config['label_color'], Colors.white), fontSize: 30, fontWeight: FontWeight.w600))),
        ),
    ]);
  }
}

// =============================================================================
// Business widgets
// =============================================================================

class MenuBoardView extends StatelessWidget {
  const MenuBoardView({super.key, required this.config});
  final Map<String, dynamic> config;
  @override
  Widget build(BuildContext context) {
    final sections = asList(config['sections']).map(asMap).toList();
    final cols = asInt(config['columns'], 1).clamp(1, 3);
    final cur = asString(config['currency'], '\$');
    final f = config['font_family']?.toString();
    final titleS = fontStyle(f, TextStyle(color: colorOr(config['title_color'], Colors.white), fontSize: 64, fontWeight: FontWeight.w800));
    final sectionS = fontStyle(f, TextStyle(color: colorOr(config['section_color'], AppColors.teal), fontSize: 40, fontWeight: FontWeight.w700, letterSpacing: 2));
    final itemS = fontStyle(f, TextStyle(color: colorOr(config['item_color'], Colors.white), fontSize: 36, fontWeight: FontWeight.w600));
    final descS = fontStyle(f, TextStyle(color: colorOr(config['description_color'], const Color(0xFFB8BEC6)), fontSize: 26));
    final priceS = fontStyle(f, TextStyle(color: colorOr(config['price_color'], Colors.white), fontSize: 36, fontWeight: FontWeight.w700));

    Widget section(Map<String, dynamic> s) => Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          if (asString(s['name']).isNotEmpty) Padding(padding: const EdgeInsets.only(bottom: 14, top: 8), child: Text(asString(s['name']).toUpperCase(), style: sectionS)),
          for (final it in asList(s['items']).map(asMap))
            Padding(
              padding: const EdgeInsets.only(bottom: 18),
              child: Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
                Expanded(
                  child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                    Row(children: [
                      Flexible(child: Text(asString(it['name']), style: itemS)),
                      for (final t in asList(it['tags']))
                        Container(
                          margin: const EdgeInsets.only(left: 10),
                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 2),
                          decoration: BoxDecoration(border: Border.all(color: sectionS.color!), borderRadius: BorderRadius.circular(8)),
                          child: Text(t.toString(), style: descS.copyWith(color: sectionS.color, fontSize: 20, fontWeight: FontWeight.w700)),
                        ),
                    ]),
                    if (asString(it['description']).isNotEmpty) Text(asString(it['description']), style: descS),
                  ]),
                ),
                const SizedBox(width: 20),
                Text(asString(it['price']).isEmpty ? '' : '$cur${it['price']}', style: priceS),
              ]),
            ),
        ]);

    final perCol = (sections.length / cols).ceil();
    return Container(
      decoration: panelDecoration(config),
      padding: const EdgeInsets.all(28),
      child: FittedBox(
        fit: BoxFit.scaleDown,
        alignment: Alignment.topLeft,
        child: SizedBox(
          width: 760.0 * cols,
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
            if (asString(config['title']).isNotEmpty) Padding(padding: const EdgeInsets.only(bottom: 18), child: Text(asString(config['title']), style: titleS)),
            Row(crossAxisAlignment: CrossAxisAlignment.start, children: [
              for (var c = 0; c < cols; c++)
                Expanded(
                  child: Padding(
                    padding: EdgeInsets.only(right: c < cols - 1 ? 40 : 0),
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      for (final s in sections.skip(c * perCol).take(perCol)) section(s),
                    ]),
                  ),
                ),
            ]),
          ]),
        ),
      ),
    );
  }
}

class PriceListView extends StatelessWidget {
  const PriceListView({super.key, required this.config});
  final Map<String, dynamic> config;
  @override
  Widget build(BuildContext context) {
    final f = config['font_family']?.toString();
    final items = asList(config['items']).map(asMap).toList();
    final cur = asString(config['currency'], '\$');
    final itemS = fontStyle(f, TextStyle(color: colorOr(config['item_color'], Colors.white), fontSize: 40, fontWeight: FontWeight.w600));
    final priceS = fontStyle(f, TextStyle(color: colorOr(config['price_color'], AppColors.teal), fontSize: 44, fontWeight: FontWeight.w800));
    return Container(
      decoration: panelDecoration(config),
      padding: const EdgeInsets.all(36),
      child: FittedBox(
        fit: BoxFit.scaleDown,
        alignment: Alignment.topCenter,
        child: SizedBox(
          width: 900,
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
            if (asString(config['title']).isNotEmpty)
              Padding(padding: const EdgeInsets.only(bottom: 22), child: Text(asString(config['title']), style: itemS.copyWith(fontSize: 60, fontWeight: FontWeight.w800, color: colorOr(config['title_color'], Colors.white)))),
            for (final it in items)
              Container(
                padding: const EdgeInsets.symmetric(vertical: 20),
                decoration: BoxDecoration(border: Border(bottom: BorderSide(color: itemS.color!.withValues(alpha: 0.15), width: 2))),
                child: Row(crossAxisAlignment: CrossAxisAlignment.end, children: [
                  Expanded(
                    child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                      Text(asString(it['name']), style: itemS),
                      if (asString(it['note']).isNotEmpty) Text(asString(it['note']), style: itemS.copyWith(fontSize: 26, fontWeight: FontWeight.w400, color: itemS.color!.withValues(alpha: 0.65))),
                    ]),
                  ),
                  Text('$cur${asString(it['price'])}', style: priceS),
                ]),
              ),
          ]),
        ),
      ),
    );
  }
}

class ProductShowcaseView extends StatelessWidget {
  const ProductShowcaseView({super.key, required this.config});
  final Map<String, dynamic> config;
  @override
  Widget build(BuildContext context) {
    final items = asList(config['items']).map(asMap).toList();
    if (items.isEmpty) return const EditorPlaceholder(icon: Icons.shopping_bag_outlined, label: 'Add products');
    final rotate = asInt(config['rotate_seconds'], 8).clamp(2, 3600);
    final scope = RenderScope.of(context);
    final color = colorOr(config['color'], Colors.white);
    final accent = colorOr(config['accent_color'], AppColors.teal);
    final ts = fontStyle(config['font_family']?.toString(), TextStyle(color: color, fontSize: 40));
    return Ticking(
      interval: Duration(seconds: rotate),
      builder: (_) {
        final idx = scope.live ? (DateTime.now().millisecondsSinceEpoch ~/ (rotate * 1000)) % items.length : 0;
        final it = items[idx];
        final img = scope.media.image(asIntOrNull(it['media_id']), thumb: scope.mode == RenderMode.thumbnail);
        return AnimatedSwitcher(
          duration: const Duration(milliseconds: 700),
          child: Container(
            key: ValueKey(idx),
            decoration: panelDecoration(config),
            clipBehavior: Clip.antiAlias,
            child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
              Expanded(
                flex: 3,
                child: Stack(fit: StackFit.expand, children: [
                  img == null
                      ? const EditorPlaceholder(icon: Icons.image_outlined, label: 'Product photo')
                      : Image(image: img, fit: BoxFit.cover, errorBuilder: (_, _, _) => const SizedBox.shrink()),
                  if (asString(it['badge']).isNotEmpty)
                    Positioned(
                      top: 24, left: 24,
                      child: Container(
                        padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 8),
                        decoration: BoxDecoration(color: accent, borderRadius: BorderRadius.circular(12)),
                        child: Text(asString(it['badge']), style: ts.copyWith(fontSize: 28, fontWeight: FontWeight.w800, color: Colors.white)),
                      ),
                    ),
                ]),
              ),
              Expanded(
                flex: 2,
                child: Padding(
                  padding: const EdgeInsets.all(32),
                  child: FittedBox(
                    fit: BoxFit.scaleDown,
                    alignment: Alignment.topLeft,
                    child: SizedBox(
                      width: 800,
                      child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
                        Text(asString(it['name']), style: ts.copyWith(fontSize: 60, fontWeight: FontWeight.w800)),
                        if (asString(it['description']).isNotEmpty) Text(asString(it['description']), style: ts.copyWith(color: color.withValues(alpha: 0.75))),
                        const SizedBox(height: 12),
                        Text('${asString(config['currency'], '\$')}${asString(it['price'])}', style: ts.copyWith(fontSize: 72, fontWeight: FontWeight.w800, color: accent)),
                      ]),
                    ),
                  ),
                ),
              ),
            ]),
          ),
        );
      },
    );
  }
}

class ContactInfoView extends StatelessWidget {
  const ContactInfoView({super.key, required this.config});
  final Map<String, dynamic> config;
  @override
  Widget build(BuildContext context) {
    final color = colorOr(config['color'], Colors.white);
    final accent = colorOr(config['accent_color'], AppColors.teal);
    final ts = fontStyle(config['font_family']?.toString(), TextStyle(color: color, fontSize: 38));
    final rows = [
      (Icons.phone_rounded, asString(config['phone'])),
      (Icons.mail_rounded, asString(config['email'])),
      (Icons.language_rounded, asString(config['website'])),
      (Icons.location_on_rounded, asString(config['address'])),
    ].where((r) => r.$2.isNotEmpty);
    return Container(
      decoration: panelDecoration(config),
      padding: const EdgeInsets.all(24),
      child: FittedBox(
        fit: BoxFit.scaleDown,
        alignment: Alignment.topLeft,
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
          if (asString(config['title']).isNotEmpty) Padding(padding: const EdgeInsets.only(bottom: 18), child: Text(asString(config['title']), style: ts.copyWith(fontSize: 50, fontWeight: FontWeight.w700))),
          for (final r in rows)
            Padding(
              padding: const EdgeInsets.only(bottom: 16),
              child: Row(mainAxisSize: MainAxisSize.min, children: [
                if (asBool(config['show_icons'], true)) ...[
                  Container(padding: const EdgeInsets.all(12), decoration: BoxDecoration(color: accent.withValues(alpha: 0.2), shape: BoxShape.circle), child: Icon(r.$1, color: accent, size: 36)),
                  const SizedBox(width: 18),
                ],
                Text(r.$2, style: ts),
              ]),
            ),
        ]),
      ),
    );
  }
}

class OpeningHoursView extends StatelessWidget {
  const OpeningHoursView({super.key, required this.config});
  final Map<String, dynamic> config;
  @override
  Widget build(BuildContext context) {
    final days = asList(config['days']).map(asMap).toList();
    final color = colorOr(config['color'], Colors.white);
    final accent = colorOr(config['accent_color'], AppColors.teal);
    final ts = fontStyle(config['font_family']?.toString(), TextStyle(color: color, fontSize: 36));
    final today = DateTime.now().weekday;
    const names = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    return Container(
      decoration: panelDecoration(config),
      padding: const EdgeInsets.all(30),
      child: FittedBox(
        fit: BoxFit.scaleDown,
        alignment: Alignment.topCenter,
        child: SizedBox(
          width: 640,
          child: Column(mainAxisSize: MainAxisSize.min, crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(asString(config['title'], 'Opening Hours'), style: ts.copyWith(fontSize: 48, fontWeight: FontWeight.w700)),
            const SizedBox(height: 18),
            for (final d in days)
              Builder(builder: (_) {
                final day = asInt(d['day'], 1).clamp(1, 7);
                final isToday = asBool(config['highlight_today'], true) && day == today;
                return Container(
                  padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 10),
                  margin: const EdgeInsets.only(bottom: 4),
                  decoration: BoxDecoration(color: isToday ? accent : null, borderRadius: BorderRadius.circular(14)),
                  child: Row(children: [
                    Expanded(child: Text(names[day - 1], style: ts.copyWith(fontWeight: isToday ? FontWeight.w700 : FontWeight.w400))),
                    Text(asBool(d['closed']) ? asString(config['closed_text'], 'Closed') : '${d['open']} – ${d['close']}',
                        style: ts.copyWith(fontWeight: FontWeight.w600, color: asBool(d['closed']) && !isToday ? color.withValues(alpha: 0.5) : null)),
                  ]),
                );
              }),
          ]),
        ),
      ),
    );
  }
}

class LocationInfoView extends StatelessWidget {
  const LocationInfoView({super.key, required this.config});
  final Map<String, dynamic> config;
  @override
  Widget build(BuildContext context) {
    final color = colorOr(config['color'], Colors.white);
    final accent = colorOr(config['accent_color'], AppColors.teal);
    final ts = fontStyle(config['font_family']?.toString(), TextStyle(color: color, fontSize: 38));
    return FittedBox(
      fit: BoxFit.scaleDown,
      alignment: Alignment.centerLeft,
      child: Row(mainAxisSize: MainAxisSize.min, children: [
        Icon(Icons.location_on_rounded, color: accent, size: 90),
        const SizedBox(width: 18),
        Column(crossAxisAlignment: CrossAxisAlignment.start, mainAxisSize: MainAxisSize.min, children: [
          if (asString(config['title']).isNotEmpty) Text(asString(config['title']), style: ts.copyWith(fontSize: 46, fontWeight: FontWeight.w700)),
          Text(asString(config['address']), style: ts),
          if (asString(config['directions']).isNotEmpty) Text(asString(config['directions']), style: ts.copyWith(fontSize: 28, color: color.withValues(alpha: 0.7))),
        ]),
      ]),
    );
  }
}

class ShapeView extends StatelessWidget {
  const ShapeView({super.key, required this.config});
  final Map<String, dynamic> config;
  @override
  Widget build(BuildContext context) {
    final shape = config['shape']?.toString() ?? 'rectangle';
    final g = asMap(config['gradient']);
    final colors = asList(g['colors']).map(parseHexColor).whereType<Color>().toList();
    final bw = asDouble(config['border_width']);
    if (shape == 'line') {
      return Center(child: Container(height: math.max(2, bw), color: colorOr(config['fill_color'], Colors.white)));
    }
    return Opacity(
      opacity: asDouble(config['opacity'], 1).clamp(0, 1),
      child: Container(
        decoration: BoxDecoration(
          color: colors.length >= 2 ? null : parseHexColor(config['fill_color']),
          gradient: colors.length >= 2 ? LinearGradient(colors: colors, transform: GradientRotation(asDouble(g['angle'], 135) * math.pi / 180)) : null,
          shape: shape == 'circle' ? BoxShape.circle : BoxShape.rectangle,
          borderRadius: shape == 'circle' ? null : BorderRadius.circular(asDouble(config['border_radius'])),
          border: bw > 0 ? Border.all(color: colorOr(config['border_color'], Colors.white), width: bw) : null,
          boxShadow: asBool(config['shadow'])
              ? [BoxShadow(color: colorOr(config['shadow_color'], const Color(0x55000000)), blurRadius: asDouble(config['shadow_blur'], 30),
                  offset: Offset(asDouble(config['shadow_offset_x']), asDouble(config['shadow_offset_y'], 12)))]
              : null,
        ),
      ),
    );
  }
}
