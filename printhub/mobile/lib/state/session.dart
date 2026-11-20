import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:material_ui/material_ui.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../api/api.dart';

/// App-wide state: the API client, the shop owner's sign-in and the anonymous customer session.
class Session extends ChangeNotifier {
  Session({Api? api, FlutterSecureStorage? storage}) : api = api ?? Api(), _secure = storage ?? const FlutterSecureStorage();

  final Api api;
  final FlutterSecureStorage _secure;
  Map<String, dynamic>? owner; // {user, shop, subscription}
  String? lastShopCode;
  bool ready = false;

  bool get isOwner => owner != null;
  Map<String, dynamic> get shop => (owner?['shop'] as Map<String, dynamic>?) ?? {};
  Map<String, dynamic> get user => (owner?['user'] as Map<String, dynamic>?) ?? {};
  Map<String, dynamic> get subscription => (owner?['subscription'] as Map<String, dynamic>?) ?? {};
  bool get mustChangePassword => user['must_change_password'] == true;
  bool get canWork => subscription['can_work'] == true;

  Future<void> init() async {
    final prefs = await SharedPreferences.getInstance();
    api.customerToken = prefs.getString('customer_token');
    lastShopCode = prefs.getString('last_shop');
    try {
      api.ownerToken = await _secure.read(key: 'owner_token');
    } catch (_) {
      api.ownerToken = null;
    }
    if (api.ownerToken != null) {
      try {
        owner = await api.get('/auth/me');
      } on ApiException catch (e) {
        if (e.unauthorized) await _clearOwner();
      }
    }
    ready = true;
    notifyListeners();
  }

  /// Customers never sign up: the device gets an anonymous session token on first use.
  Future<void> ensureCustomer() async {
    if (api.customerToken != null) return;
    final d = await api.post('/customer/session');
    if (d['token'] is String) {
      api.customerToken = d['token'] as String;
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString('customer_token', api.customerToken!);
    }
  }

  Future<void> rememberShop(String code) async {
    lastShopCode = code;
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString('last_shop', code);
    notifyListeners();
  }

  Future<void> login(String username, String password) async {
    final d = await api.post('/auth/login', {'username': username, 'password': password, 'device_name': 'PrintHub app'});
    api.ownerToken = d['token'] as String;
    await _secure.write(key: 'owner_token', value: api.ownerToken);
    owner = d;
    notifyListeners();
  }

  Future<void> refreshOwner() async {
    owner = await api.get('/auth/me');
    notifyListeners();
  }

  Future<void> logout() async {
    try {
      await api.post('/auth/logout');
    } catch (_) {}
    await _clearOwner();
  }

  /// Called when any owner request returns 401 (token expired or revoked).
  Future<void> signedOut() => _clearOwner();

  Future<void> _clearOwner() async {
    api.ownerToken = null;
    owner = null;
    try {
      await _secure.delete(key: 'owner_token');
    } catch (_) {}
    notifyListeners();
  }
}

/// Makes the [Session] available to the widget tree.
class SessionScope extends InheritedNotifier<Session> {
  const SessionScope({super.key, required Session session, required super.child}) : super(notifier: session);

  static Session of(BuildContext context) => context.dependOnInheritedWidgetOfExactType<SessionScope>()!.notifier!;
  static Session read(BuildContext context) => context.getInheritedWidgetOfExactType<SessionScope>()!.notifier!;
}
