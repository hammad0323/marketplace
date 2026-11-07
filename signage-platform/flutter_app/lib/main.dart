import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:timezone/data/latest_all.dart' as tzdata;

import 'core/constants/app_config.dart';
import 'core/theme/app_theme.dart';
import 'features/display/code_display_service.dart';
import 'providers/providers.dart';
import 'routes/app_router.dart';

/// Management app (phones & tablets). The TV player has its own entry point: lib/main_player.dart
Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  tzdata.initializeTimeZones();
  // A device left in display mode (e.g. a TV) goes straight back to it after a restart.
  final (mode, code) = await DisplayModePrefs.read();
  final start = mode == 'managed' ? '/display/managed' : (mode == 'code' && code != null ? '/display/play/$code' : '/home');
  runApp(ProviderScope(
    retry: (_, _) => null,
    overrides: [initialLocationProvider.overrideWithValue(start)],
    child: const SignageApp(),
  ));
}

class SignageApp extends ConsumerWidget {
  const SignageApp({super.key});
  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final router = ref.watch(routerProvider);
    return MaterialApp.router(
      title: AppConfig.appName,
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light(),
      darkTheme: AppTheme.dark(),
      themeMode: ref.watch(themeModeProvider),
      routerConfig: router,
    );
  }
}
