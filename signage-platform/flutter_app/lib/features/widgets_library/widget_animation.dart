import 'dart:async';
import 'dart:math' as math;
import 'dart:ui' as ui;

import 'package:flutter/material.dart';

import '../../core/utils/json.dart';
import '../../models/layout.dart';

/// Widget animations (shared contract with the web renderer `backend/web/assets/render.js`).
///
/// Configuration keys, valid on every widget type:
///   animation               entrance effect (see [entranceEffects])
///   animation_duration_ms   entrance length (default 800)
///   animation_delay_ms      wait before the entrance (stagger elements like a video)
///   animation_replay_seconds  0 = play once; N = exit + enter again every N seconds (looping scene)
///   loop_animation          continuous effect (see [loopEffects])
///   loop_duration_ms        length of one loop cycle (0 = the effect's default)
///   loop_intensity          0.2 … 3 (1 = normal)
///
/// Both entrance and loop effects are pure functions of progress, so the app,
/// the TV player and the web player draw identical frames.

const entranceEffects = <String, String>{
  'none': 'None', 'fade_in': 'Fade in', 'slide_up': 'Slide up', 'slide_down': 'Slide down', 'slide_left': 'Slide in from right',
  'slide_right': 'Slide in from left', 'zoom_in': 'Zoom in', 'zoom_out': 'Zoom out', 'pop': 'Pop', 'bounce_in': 'Drop & bounce',
  'flip_x': 'Flip (vertical)', 'flip_y': 'Flip (horizontal)', 'rotate_in': 'Spin in', 'blur_in': 'Blur in', 'wipe_right': 'Wipe right',
  'wipe_up': 'Wipe up', 'typewriter': 'Typewriter',
};

const loopEffects = <String, String>{
  'none': 'None', 'pulse': 'Pulse', 'breathe': 'Breathe', 'float': 'Float', 'bounce': 'Bounce', 'shake': 'Shake', 'wobble': 'Wobble',
  'swing': 'Swing (hanging)', 'sway': 'Sway (plant)', 'spin': 'Spin', 'heartbeat': 'Heartbeat', 'flash': 'Flash', 'tada': 'Tada',
  'jello': 'Jello', 'rubber_band': 'Rubber band', 'ken_burns': 'Slow zoom (Ken Burns)', 'glow': 'Glow', 'shine': 'Shine sweep',
  'color_cycle': 'Colour cycle',
};

/// Default length of one loop cycle in ms.
const loopDefaultMs = <String, int>{
  'pulse': 1600, 'breathe': 4000, 'float': 3000, 'bounce': 1400, 'shake': 2500, 'wobble': 2000, 'swing': 2400, 'sway': 3500, 'spin': 6000,
  'heartbeat': 1500, 'flash': 1600, 'tada': 3000, 'jello': 2500, 'rubber_band': 2500, 'ken_burns': 16000, 'glow': 2400, 'shine': 3500,
  'color_cycle': 8000,
};

/// One frame of an animation. Translations are fractions of the widget's own size.
class AnimFrame {
  double opacity = 1, tx = 0, ty = 0, scale = 1, sx = 1, sy = 1, rotate = 0, rotX = 0, rotY = 0, skewX = 0, blur = 0;
  double clipX = 1, clipY = 1, brightness = 1, hue = 0, shine = -1;
  Alignment origin = Alignment.center;
  bool get identity =>
      opacity >= 0.999 && tx == 0 && ty == 0 && scale == 1 && sx == 1 && sy == 1 && rotate == 0 && rotX == 0 && rotY == 0 && skewX == 0 && blur <= 0.01 &&
      clipX >= 1 && clipY >= 1 && brightness == 1 && hue == 0 && shine < 0;
}

double _easeOut(double p) => 1 - math.pow(1 - p, 3).toDouble();
double _backOut(double p) { const c1 = 1.70158, c3 = c1 + 1; return 1 + c3 * math.pow(p - 1, 3) + c1 * math.pow(p - 1, 2); }
double _bounceOut(double x) {
  const n1 = 7.5625, d1 = 2.75;
  if (x < 1 / d1) return n1 * x * x;
  if (x < 2 / d1) { x -= 1.5 / d1; return n1 * x * x + 0.75; }
  if (x < 2.5 / d1) { x -= 2.25 / d1; return n1 * x * x + 0.9375; }
  x -= 2.625 / d1;
  return n1 * x * x + 0.984375;
}
double _wave(double t) => 0.5 - 0.5 * math.cos(2 * math.pi * t); // 0 → 1 → 0
double _g(double t, double c, double w) => math.exp(-math.pow((t - c) / w, 2));

/// Entrance state at linear progress p (0 = hidden start, 1 = final position).
AnimFrame entranceFrame(String name, double p, {int chars = 20}) {
  final f = AnimFrame();
  p = p.clamp(0.0, 1.0);
  final e = _easeOut(p);
  final fadeFast = math.min(1.0, p * 2.5);
  switch (name) {
    case 'fade_in': f.opacity = e;
    case 'slide_up': f.ty = 0.6 * (1 - e); f.opacity = fadeFast;
    case 'slide_down': f.ty = -0.6 * (1 - e); f.opacity = fadeFast;
    case 'slide_left': f.tx = 0.6 * (1 - e); f.opacity = fadeFast;
    case 'slide_right': f.tx = -0.6 * (1 - e); f.opacity = fadeFast;
    case 'zoom_in': f.scale = 0.4 + 0.6 * e; f.opacity = e;
    case 'zoom_out': f.scale = 1.6 - 0.6 * e; f.opacity = e;
    case 'pop': f.scale = 0.2 + 0.8 * _backOut(p); f.opacity = math.min(1.0, p * 3);
    case 'bounce_in': f.ty = -1.2 * (1 - _bounceOut(p)); f.opacity = math.min(1.0, p * 4);
    case 'flip_x': f.rotX = 90 * (1 - e); f.opacity = fadeFast;
    case 'flip_y': f.rotY = 90 * (1 - e); f.opacity = fadeFast;
    case 'rotate_in': f.rotate = -180 * (1 - e); f.scale = 0.3 + 0.7 * e; f.opacity = e;
    case 'blur_in': f.blur = 20 * (1 - e); f.opacity = e;
    case 'wipe_right': f.clipX = e;
    case 'wipe_up': f.clipY = e;
    case 'typewriter': final n = chars.clamp(1, 200); f.clipX = (p * n).floor() / n;
  }
  return f;
}

/// Loop state at cycle position t (0…1) with intensity k.
AnimFrame loopFrame(String name, double t, double k) {
  final f = AnimFrame();
  t = t - t.floorToDouble();
  final s2 = math.sin(2 * math.pi * t);
  switch (name) {
    case 'pulse': f.scale = 1 + 0.06 * k * _wave(t);
    case 'breathe': f.scale = 1 + 0.04 * k * _wave(t); f.opacity = (1 - 0.25 * k * _wave(t)).clamp(0.1, 1.0);
    case 'float': f.ty = -0.04 * k * _wave(t);
    case 'bounce':
      final h = t < 0.6 ? 4 * (t / 0.6) * (1 - t / 0.6) : 0.0;
      f.ty = -0.12 * k * h;
      final land = _g(t, 0.62, 0.04);
      f.sx = 1 + 0.06 * k * land; f.sy = 1 - 0.06 * k * land; f.origin = Alignment.bottomCenter;
    case 'shake': f.tx = t < 0.4 ? 0.03 * k * math.sin(2 * math.pi * t * 10) : 0;
    case 'wobble': f.rotate = 6 * k * s2;
    case 'swing': f.rotate = 10 * k * s2; f.origin = Alignment.topCenter;
    case 'sway': f.rotate = 4 * k * s2; f.origin = Alignment.bottomCenter;
    case 'spin': f.rotate = 360 * t;
    case 'heartbeat': f.scale = 1 + 0.1 * k * (_g(t, 0.12, 0.05) + 0.7 * _g(t, 0.32, 0.05));
    case 'flash': f.opacity = 1 - math.min(0.9, 0.7 * k) * _wave(t);
    case 'tada':
      if (t < 0.4) { final u = t / 0.4; f.scale = 1 + 0.08 * k * math.sin(math.pi * u); f.rotate = 3 * k * math.sin(2 * math.pi * u * 3) * math.sin(math.pi * u); }
    case 'jello': f.skewX = t < 0.5 ? 8 * k * math.sin(2 * math.pi * t * 4) * (1 - t * 2) : 0;
    case 'rubber_band':
      if (t < 0.5) { final d = 0.15 * k * math.sin(2 * math.pi * t * 3) * (1 - t * 2); f.sx = 1 + d; f.sy = 1 - d; }
    case 'ken_burns': f.scale = 1 + 0.12 * k * _wave(t); f.tx = 0.03 * k * s2;
    case 'glow': f.brightness = 1 + 0.35 * k * _wave(t);
    case 'shine': f.shine = t < 0.45 ? -0.3 + 1.6 * (t / 0.45) : -1;
    case 'color_cycle': f.hue = 360 * t;
  }
  return f;
}

/// Hue rotation colour matrix (degrees).
List<double> hueMatrix(double deg) {
  final a = deg * math.pi / 180, c = math.cos(a), s = math.sin(a);
  return [
    0.213 + c * 0.787 - s * 0.213, 0.715 - c * 0.715 - s * 0.715, 0.072 - c * 0.072 + s * 0.928, 0, 0,
    0.213 - c * 0.213 + s * 0.143, 0.715 + c * 0.285 + s * 0.140, 0.072 - c * 0.072 - s * 0.283, 0, 0,
    0.213 - c * 0.213 - s * 0.787, 0.715 - c * 0.715 + s * 0.715, 0.072 + c * 0.928 + s * 0.072, 0, 0,
    0, 0, 0, 1, 0,
  ];
}
List<double> brightnessMatrix(double b) => [b, 0, 0, 0, 0, 0, b, 0, 0, 0, 0, 0, b, 0, 0, 0, 0, 0, 1, 0];

/// Apply one frame to a child.
Widget applyFrame(AnimFrame f, Widget child) {
  if (f.identity) return child;
  var w = child;
  if (f.shine >= -0.5) {
    final c = f.shine;
    w = ShaderMask(
      blendMode: BlendMode.srcATop,
      shaderCallback: (r) => LinearGradient(
        begin: Alignment.topLeft, end: Alignment.bottomRight,
        colors: const [Color(0x00FFFFFF), Color(0x99FFFFFF), Color(0x00FFFFFF)],
        stops: [(c - 0.15).clamp(0.0, 1.0), c.clamp(0.0, 1.0), (c + 0.15).clamp(0.0, 1.0)],
      ).createShader(r),
      child: w,
    );
  }
  if (f.hue != 0) w = ColorFiltered(colorFilter: ColorFilter.matrix(hueMatrix(f.hue)), child: w);
  if (f.brightness != 1) w = ColorFiltered(colorFilter: ColorFilter.matrix(brightnessMatrix(f.brightness)), child: w);
  if (f.clipX < 1 || f.clipY < 1) w = ClipRect(clipper: _FractionClipper(f.clipX, f.clipY), child: w);
  if (f.blur > 0.01) w = ImageFiltered(imageFilter: ui.ImageFilter.blur(sigmaX: f.blur, sigmaY: f.blur, tileMode: TileMode.decal), child: w);
  final hasT = f.scale != 1 || f.sx != 1 || f.sy != 1 || f.rotate != 0 || f.rotX != 0 || f.rotY != 0 || f.skewX != 0;
  if (hasT) {
    final m = Matrix4.identity();
    if (f.rotX != 0 || f.rotY != 0) m.setEntry(3, 2, 0.0012);
    m
      ..rotateZ(f.rotate * math.pi / 180)
      ..rotateX(f.rotX * math.pi / 180)
      ..rotateY(f.rotY * math.pi / 180);
    if (f.skewX != 0) m.multiply(Matrix4.skewX(math.tan(f.skewX * math.pi / 180)));
    m.scaleByDouble(f.scale * f.sx, f.scale * f.sy, 1, 1);
    w = Transform(alignment: f.origin, transform: m, child: w);
  }
  if (f.tx != 0 || f.ty != 0) w = FractionalTranslation(translation: Offset(f.tx, f.ty), child: w);
  if (f.opacity < 0.999) w = Opacity(opacity: f.opacity.clamp(0.0, 1.0), child: w);
  return w;
}

class _FractionClipper extends CustomClipper<Rect> {
  _FractionClipper(this.x, this.y);
  final double x, y;
  @override
  Rect getClip(Size s) => Rect.fromLTRB(0, s.height * (1 - y), s.width * x, s.height);
  @override
  bool shouldReclip(_FractionClipper o) => o.x != x || o.y != y;
}

/// Timeline maths shared with the web: where in the entrance (0…1) we are at `ms` into the cycle.
double entranceProgressAt(double ms, {required double delay, required double dur, double period = 0}) {
  if (ms < delay) return 0;
  if (ms < delay + dur) return (ms - delay) / dur;
  if (period <= 0 || ms < period - dur) return 1;
  return (1 - (ms - (period - dur)) / dur).clamp(0.0, 1.0);
}

/// Period of a replaying scene: at least long enough to enter, hold briefly and exit.
double replayPeriodMs(double replaySeconds, double delay, double dur) => math.max(replaySeconds * 1000, delay + dur * 2 + 500);

class AnimSpec {
  AnimSpec(Map<String, dynamic> c, {this.chars = 20})
      : entrance = asString(c['animation'], 'none'),
        dur = asDouble(c['animation_duration_ms'], 800).clamp(100, 10000).toDouble(),
        delay = asDouble(c['animation_delay_ms']).clamp(0, 60000).toDouble(),
        replay = asDouble(c['animation_replay_seconds']).clamp(0, 3600).toDouble(),
        loop = asString(c['loop_animation'], 'none'),
        loopMs = asDouble(c['loop_duration_ms']),
        intensity = asDouble(c['loop_intensity'], 1).clamp(0.1, 3).toDouble();
  final String entrance, loop;
  final double dur, delay, replay, intensity;
  final double loopMs;
  final int chars;
  bool get hasEntrance => entrance != 'none' && entranceEffects.containsKey(entrance);
  bool get hasLoop => loop != 'none' && loopEffects.containsKey(loop);
  bool get any => hasEntrance || hasLoop;
  double get loopPeriod => (loopMs > 0 ? loopMs : (loopDefaultMs[loop] ?? 2000)).clamp(200, 120000).toDouble();
}

/// Plays a widget's entrance (optionally replaying) and its continuous loop effect.
class WidgetAnimator extends StatefulWidget {
  const WidgetAnimator({super.key, required this.spec, required this.child});
  final AnimSpec spec;
  final Widget child;
  @override
  State<WidgetAnimator> createState() => _WidgetAnimatorState();
}

class _WidgetAnimatorState extends State<WidgetAnimator> with TickerProviderStateMixin {
  AnimationController? main, loop;
  Timer? loopStart;
  late double period;

  @override
  void initState() {
    super.initState();
    final s = widget.spec;
    if (s.hasEntrance) {
      period = s.replay > 0 ? replayPeriodMs(s.replay, s.delay, s.dur) : s.delay + s.dur;
      main = AnimationController(vsync: this, duration: Duration(milliseconds: period.round()));
      s.replay > 0 ? main!.repeat() : main!.forward();
    }
    if (s.hasLoop) {
      loop = AnimationController(vsync: this, duration: Duration(milliseconds: s.loopPeriod.round()));
      final wait = s.hasEntrance ? s.delay + s.dur : 0.0;
      loopStart = Timer(Duration(milliseconds: wait.round()), () { if (mounted) loop!.repeat(); });
    }
  }

  @override
  void dispose() {
    loopStart?.cancel();
    main?.dispose();
    loop?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final s = widget.spec;
    return AnimatedBuilder(
      animation: Listenable.merge([?main, ?loop]),
      builder: (context, child) {
        var w = child!;
        if (loop != null) w = applyFrame(loopFrame(s.loop, loop!.value, s.intensity), w);
        if (main != null) {
          final p = entranceProgressAt(main!.value * period, delay: s.delay, dur: s.dur, period: s.replay > 0 ? period : 0);
          w = applyFrame(entranceFrame(s.entrance, p, chars: s.chars), w);
        }
        return w;
      },
      child: s.loop == 'ken_burns' ? ClipRect(child: widget.child) : widget.child,
    );
  }
}

/// Number of characters used by the typewriter effect.
int textLength(Map<String, dynamic> c) {
  final spans = asList(c['spans']);
  if (spans.isNotEmpty) return spans.map((s) => asString(asMap(s)['text']).length).fold(0, (a, b) => a + b);
  return asString(c['text'] ?? c['title'] ?? c['message'], '').length.clamp(1, 200);
}

// ---------------------------------------------------------------------------
// "Animate whole screen" presets
// ---------------------------------------------------------------------------

const screenAnimationStyles = <String, String>{
  'smooth': 'Smooth', 'energetic': 'Energetic', 'elegant': 'Elegant', 'none': 'Remove all animations',
};

const animationKeys = ['animation', 'animation_duration_ms', 'animation_delay_ms', 'animation_replay_seconds', 'loop_animation', 'loop_duration_ms', 'loop_intensity'];

/// Gives every widget a staggered entrance (top to bottom) and a fitting loop, so the
/// screen plays like a short video. `replaySeconds` > 0 restarts the scene in a loop.
void applyScreenAnimation(List<WidgetItem> widgets, String style, int replaySeconds) {
  if (style == 'none') {
    for (final w in widgets) {
      for (final k in animationKeys) { w.configuration.remove(k); }
    }
    return;
  }
  final (dur, stagger) = switch (style) { 'energetic' => (700, 180), 'elegant' => (1200, 400), _ => (850, 260) };
  final order = [...widgets.where((w) => w.isVisible)]..sort((a, b) {
      final dy = (a.positionY - b.positionY);
      return dy.abs() > 0.05 ? dy.sign.toInt() : (a.positionX - b.positionX).sign.toInt();
    });
  var i = 0;
  for (final w in order) {
    final c = w.configuration;
    final full = w.width >= 0.95 && w.height >= 0.95;
    final type = w.widgetType;
    final isText = type == 'heading' || type == 'text';
    final isImage = type == 'image' || type == 'logo' || type == 'background_image';
    final isRound = type == 'shape' && c['shape'] == 'circle';
    final small = w.width * w.height < 0.03;
    String entrance;
    String loop = 'none';
    if (full) {
      entrance = 'fade_in';
      if (isImage) loop = 'ken_burns';
    } else {
      entrance = switch (style) {
        'energetic' => type == 'heading' ? 'bounce_in' : isText ? 'slide_left' : (isImage || isRound || type == 'shape') ? 'pop' : 'zoom_in',
        'elegant' => type == 'heading' ? 'wipe_right' : isImage ? 'blur_in' : 'fade_in',
        _ => isText ? 'slide_up' : isImage ? 'zoom_in' : (isRound || type == 'shape') ? 'pop' : 'fade_in',
      };
      if (isRound) {
        loop = style == 'energetic' ? 'heartbeat' : (style == 'elegant' ? 'breathe' : 'pulse');
      } else if (isImage && small) {
        loop = 'sway';
      } else if (isImage) {
        loop = style == 'energetic' ? 'bounce' : 'float';
      }
    }
    c['animation'] = entrance;
    c['animation_duration_ms'] = full ? 1000 : dur;
    c['animation_delay_ms'] = full ? 0 : 150 + stagger * i++;
    c['animation_replay_seconds'] = replaySeconds;
    c['loop_animation'] = loop;
    c['loop_duration_ms'] = 0;
    c['loop_intensity'] = 1.0;
  }
}
