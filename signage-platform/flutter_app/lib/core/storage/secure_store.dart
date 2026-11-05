import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:shared_preferences/shared_preferences.dart';

/// Tokens go to the platform keystore; plain preferences to SharedPreferences.
class SecureStore {
  SecureStore._();
  static const _secure = FlutterSecureStorage();

  static Future<String?> read(String key) async {
    try {
      return await _secure.read(key: key);
    } catch (_) {
      final p = await SharedPreferences.getInstance();
      return p.getString('sec_$key');
    }
  }

  static Future<void> write(String key, String? value) async {
    try {
      if (value == null) {
        await _secure.delete(key: key);
      } else {
        await _secure.write(key: key, value: value);
      }
    } catch (_) {
      final p = await SharedPreferences.getInstance();
      if (value == null) {
        await p.remove('sec_$key');
      } else {
        await p.setString('sec_$key', value);
      }
    }
  }
}
