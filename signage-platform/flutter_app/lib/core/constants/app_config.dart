/// Build-time configuration. Override with --dart-define, e.g.
///   flutter run --dart-define=API_BASE_URL=https://signage.example.com
class AppConfig {
  AppConfig._();

  /// Base URL of the PHP backend (the folder that contains /api).
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8080', // Android emulator → host machine
  );

  static const String appName = String.fromEnvironment('APP_NAME', defaultValue: 'Signal Signage');
  static const String playerVersion = '1.0.0';
  static const Duration connectTimeout = Duration(seconds: 15);
  static const Duration receiveTimeout = Duration(seconds: 60);
  static const Duration autosaveDelay = Duration(seconds: 4);
}
