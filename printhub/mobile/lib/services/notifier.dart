import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../state/session.dart';

/// Polls the notification centre while the app is open and shows a local notification for new items.
/// The in-app notification lists stay the reliable fallback; background push (FCM/APNs) can be added
/// with server credentials — see docs/DEPLOYMENT.md.
class Notifier {
  Notifier(this.session);
  final Session session;
  final _plugin = FlutterLocalNotificationsPlugin();
  Timer? _timer;
  bool _ready = false;
  final _listeners = <VoidCallback>[];

  Future<void> start() async {
    if (!kIsWeb) {
      try {
        await _plugin.initialize(settings: const InitializationSettings(android: AndroidInitializationSettings('@mipmap/ic_launcher'), iOS: DarwinInitializationSettings()));
        await _plugin.resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()?.requestNotificationsPermission();
        _ready = true;
      } catch (_) {
        _ready = false;
      }
    }
    _timer?.cancel();
    _timer = Timer.periodic(const Duration(seconds: 30), (_) => poll());
    unawaited(poll());
  }

  void addListener(VoidCallback l) => _listeners.add(l);
  void removeListener(VoidCallback l) => _listeners.remove(l);

  Future<void> poll() async {
    final owner = session.isOwner;
    if (!owner && session.api.customerToken == null) return;
    final key = owner ? 'notif_owner' : 'notif_customer';
    final prefs = await SharedPreferences.getInstance();
    final since = prefs.getInt(key) ?? 0;
    try {
      final d = await session.api.get(owner ? '/owner/notifications' : '/customer/notifications', {'since': since, 'limit': 10});
      final items = (d['items'] as List).cast<Map<String, dynamic>>();
      if (items.isEmpty) return;
      final maxId = items.map((n) => n['id'] as int).reduce((a, b) => a > b ? a : b);
      await prefs.setInt(key, maxId);
      if (since > 0 && _ready) {
        for (final n in items.take(3)) {
          await _plugin.show(
            id: n['id'] as int,
            title: '${n['title']}',
            body: n['body'] as String?,
            notificationDetails: const NotificationDetails(android: AndroidNotificationDetails('orders', 'Orders', importance: Importance.high, priority: Priority.high), iOS: DarwinNotificationDetails()),
          );
        }
      }
      for (final l in List.of(_listeners)) {
        l();
      }
    } catch (_) {
      // offline or signed out: try again on the next tick
    }
  }

  void stop() => _timer?.cancel();
}
