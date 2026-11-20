import 'package:intl/intl.dart';

final _money = NumberFormat.decimalPattern('en_US');

String money(num? v) => v == null ? '—' : 'Rs. ${_money.format(v.round())}';

String dateTime(String? s) {
  if (s == null || s.isEmpty) return '—';
  final d = DateTime.tryParse(s.replaceFirst(' ', 'T'));
  return d == null ? s : DateFormat('d MMM yyyy, h:mm a').format(d);
}

String date(String? s) {
  if (s == null || s.isEmpty) return '—';
  final d = DateTime.tryParse(s);
  return d == null ? s : DateFormat('d MMM yyyy').format(d);
}

String timeAgo(String? s) {
  final d = s == null ? null : DateTime.tryParse(s.replaceFirst(' ', 'T'));
  if (d == null) return '';
  final diff = DateTime.now().difference(d);
  if (diff.inMinutes < 1) return 'just now';
  if (diff.inMinutes < 60) return '${diff.inMinutes} min ago';
  if (diff.inHours < 24) return '${diff.inHours} h ago';
  return '${diff.inDays} d ago';
}

/// Parses "1-3, 5, 8" into page numbers within 1..max (order kept, no duplicates).
List<int> parseRanges(String input, int max) {
  final out = <int>[];
  for (final part in input.split(',')) {
    final m = RegExp(r'^\s*(\d+)\s*(?:-\s*(\d+))?\s*$').firstMatch(part);
    if (m == null) continue;
    final a = int.parse(m.group(1)!);
    final b = m.group(2) != null ? int.parse(m.group(2)!) : a;
    final step = a <= b ? 1 : -1;
    for (var k = a; step > 0 ? k <= b : k >= b; k += step) {
      if (k >= 1 && k <= max && !out.contains(k)) out.add(k);
    }
  }
  return out;
}

int asInt(dynamic v, [int fallback = 0]) => v is int ? v : (v is num ? v.toInt() : int.tryParse('$v') ?? fallback);
double? asDouble(dynamic v) => v == null ? null : (v is num ? v.toDouble() : double.tryParse('$v'));
