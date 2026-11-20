/// Build-time configuration. Point the app at your server with:
///   flutter build apk --dart-define=API_BASE=https://yourdomain.com
class AppConfig {
  static const String baseUrl = String.fromEnvironment('API_BASE', defaultValue: 'https://yourdomain.com');
  static const String appName = String.fromEnvironment('APP_NAME', defaultValue: 'PrintHub');

  static String get apiBase => '${baseUrl.replaceAll(RegExp(r'/+$'), '')}/api/v1';
  static Uri web(String path) => Uri.parse('${baseUrl.replaceAll(RegExp(r'/+$'), '')}/${path.replaceAll(RegExp(r'^/+'), '')}');
}
