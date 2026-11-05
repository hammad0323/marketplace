import 'package:flutter/material.dart';
import 'package:timezone/data/latest_all.dart' as tzdata;

import 'features/signage_player/player_app.dart';
import 'features/signage_player/player_service.dart';

/// Signage player entry point (Android TV / boxes / tablets).
///   flutter run -t lib/main_player.dart --dart-define=API_BASE_URL=https://signage.example.com
///   flutter build apk --flavor player -t lib/main_player.dart --dart-define=API_BASE_URL=...
void main() {
  WidgetsFlutterBinding.ensureInitialized();
  tzdata.initializeTimeZones();
  // A failing widget must never show a red error box on a public display.
  ErrorWidget.builder = (_) => const SizedBox.shrink();
  FlutterError.onError = (d) => debugPrint('Player render error: ${d.exceptionAsString()}');
  runApp(PlayerApp(service: PlayerService()));
}
