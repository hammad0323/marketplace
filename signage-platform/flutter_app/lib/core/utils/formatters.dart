import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

String formatBytes(num bytes) {
  if (bytes < 1024) return '${bytes.toInt()} B';
  const units = ['KB', 'MB', 'GB', 'TB'];
  double v = bytes / 1024;
  var i = 0;
  while (v >= 1024 && i < units.length - 1) {
    v /= 1024;
    i++;
  }
  return '${v.toStringAsFixed(v < 10 ? 1 : 0)} ${units[i]}';
}

String formatMb(int mb) => mb < 0 ? 'Unlimited' : (mb >= 1024 ? '${(mb / 1024).toStringAsFixed(mb % 1024 == 0 ? 0 : 1)} GB' : '$mb MB');

String formatLimit(int v) => v < 0 ? '∞' : '$v';

String timeAgo(DateTime? d) {
  if (d == null) return 'never';
  final diff = DateTime.now().difference(d);
  if (diff.inSeconds < 60) return 'just now';
  if (diff.inMinutes < 60) return '${diff.inMinutes} min ago';
  if (diff.inHours < 24) return '${diff.inHours} h ago';
  if (diff.inDays < 7) return '${diff.inDays} d ago';
  return DateFormat.yMMMd().format(d);
}

String formatDateTime(DateTime? d) => d == null ? '—' : DateFormat('d MMM yyyy, HH:mm').format(d);

String formatDuration(int seconds) {
  if (seconds < 60) return '${seconds}s';
  final m = seconds ~/ 60, s = seconds % 60;
  if (m < 60) return s == 0 ? '${m}m' : '${m}m ${s}s';
  return '${m ~/ 60}h ${m % 60}m';
}

/// "#RRGGBB" / "#AARRGGBB" / "#RRGGBBAA"(CSS) → Color. API uses CSS order (alpha last) for 8 digits.
Color? parseHexColor(dynamic v) {
  if (v is! String) return null;
  var s = v.trim().replaceFirst('#', '');
  if (s.length == 3) s = s.split('').map((c) => '$c$c').join();
  if (s.length == 6) return Color(int.parse('FF$s', radix: 16));
  if (s.length == 8) return Color(int.parse('${s.substring(6)}${s.substring(0, 6)}', radix: 16));
  return null;
}

String colorToHex(Color c, {bool withAlpha = false}) {
  String h(double x) => (x * 255).round().clamp(0, 255).toRadixString(16).padLeft(2, '0');
  final rgb = '${h(c.r)}${h(c.g)}${h(c.b)}'.toUpperCase();
  return withAlpha && c.a < 1 ? '#$rgb${h(c.a).toUpperCase()}' : '#$rgb';
}

String orientationLabel(String o) => switch (o) {
      'portrait' => 'Portrait 9:16',
      'square' => 'Square 1:1',
      'custom' => 'Custom',
      _ => 'Landscape 16:9',
    };

IconData orientationIcon(String o) => switch (o) {
      'portrait' => Icons.stay_current_portrait,
      'square' => Icons.crop_square,
      _ => Icons.stay_current_landscape,
    };
