/// Lenient JSON helpers: the API returns ints/floats natively, DECIMALs as strings.
int asInt(dynamic v, [int fallback = 0]) {
  if (v is int) return v;
  if (v is num) return v.toInt();
  if (v is String) return int.tryParse(v) ?? double.tryParse(v)?.toInt() ?? fallback;
  if (v is bool) return v ? 1 : 0;
  return fallback;
}

int? asIntOrNull(dynamic v) => v == null ? null : asInt(v);

double asDouble(dynamic v, [double fallback = 0]) {
  if (v is num) return v.toDouble();
  if (v is String) return double.tryParse(v) ?? fallback;
  return fallback;
}

bool asBool(dynamic v, [bool fallback = false]) {
  if (v is bool) return v;
  if (v is num) return v != 0;
  if (v is String) return v == '1' || v.toLowerCase() == 'true';
  return fallback;
}

String asString(dynamic v, [String fallback = '']) => v == null ? fallback : v.toString();

Map<String, dynamic> asMap(dynamic v) {
  if (v is Map<String, dynamic>) return v;
  if (v is Map) return v.map((k, val) => MapEntry(k.toString(), val));
  return <String, dynamic>{};
}

List<dynamic> asList(dynamic v) => v is List ? v : const [];

DateTime? asDate(dynamic v) {
  if (v == null) return null;
  final s = v.toString();
  if (s.isEmpty) return null;
  // API timestamps are UTC "YYYY-MM-DD HH:MM:SS"
  return DateTime.tryParse(s.contains('T') ? s : '${s.replaceFirst(' ', 'T')}Z')?.toLocal();
}

/// Deep copy of JSON-like structures (maps/lists/primitives).
dynamic deepCopy(dynamic v) {
  if (v is Map) return v.map((k, val) => MapEntry(k.toString(), deepCopy(val)));
  if (v is List) return v.map(deepCopy).toList();
  return v;
}
