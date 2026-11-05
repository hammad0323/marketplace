import '../core/utils/json.dart';

class AppNotification {
  AppNotification({required this.id, required this.title, required this.message, required this.type, this.link, this.isRead = false, this.createdAt});
  final int id;
  final String title, message, type;
  final String? link;
  final bool isRead;
  final DateTime? createdAt;
  factory AppNotification.fromJson(Map<String, dynamic> j) => AppNotification(
      id: asInt(j['notification_id']), title: asString(j['title']), message: asString(j['message']), type: asString(j['type'], 'info'),
      link: j['link']?.toString(), isRead: asBool(j['is_read']), createdAt: asDate(j['created_at']));
}

class ActivityEntry {
  ActivityEntry({required this.action, this.description, this.createdAt, this.userName});
  final String action;
  final String? description, userName;
  final DateTime? createdAt;
  factory ActivityEntry.fromJson(Map<String, dynamic> j) => ActivityEntry(
      action: asString(j['action']), description: j['description']?.toString(), createdAt: asDate(j['created_at']),
      userName: j['full_name']?.toString());
}

class Plan {
  Plan({
    required this.planId, required this.planName, required this.maxScreens, required this.maxPlaylists, required this.maxDevices,
    required this.maxStorageMb, this.maxUploadMb = 50, this.priceMonthly = 0, this.priceYearly = 0, this.currency = 'USD', this.description,
    this.features = const {}, this.isDefault = false, this.status = 'active', this.userCount = 0, this.sortOrder = 0,
  });
  final int planId, maxScreens, maxPlaylists, maxDevices, maxStorageMb, maxUploadMb, userCount, sortOrder;
  final String planName, currency, status;
  final String? description;
  final double priceMonthly, priceYearly;
  final Map<String, dynamic> features;
  final bool isDefault;
  factory Plan.fromJson(Map<String, dynamic> j) => Plan(
      planId: asInt(j['plan_id']), planName: asString(j['plan_name']), maxScreens: asInt(j['max_screens']), maxPlaylists: asInt(j['max_playlists']),
      maxDevices: asInt(j['max_devices']), maxStorageMb: asInt(j['max_storage_mb']), maxUploadMb: asInt(j['max_upload_mb'], 50),
      priceMonthly: asDouble(j['price_monthly']), priceYearly: asDouble(j['price_yearly']), currency: asString(j['currency'], 'USD'),
      description: j['description']?.toString(), features: asMap(j['features_json']), isDefault: asBool(j['is_default']),
      status: asString(j['status'], 'active'), userCount: asInt(j['user_count']), sortOrder: asInt(j['sort_order']));
}

class EmergencyMessage {
  EmergencyMessage({required this.id, required this.title, this.body, required this.status, this.startsAt, this.endsAt});
  final int id;
  final String title, status;
  final String? body;
  final DateTime? startsAt, endsAt;
  factory EmergencyMessage.fromJson(Map<String, dynamic> j) => EmergencyMessage(
      id: asInt(j['message_id']), title: asString(j['title']), body: j['body']?.toString(), status: asString(j['status']),
      startsAt: asDate(j['starts_at']), endsAt: asDate(j['ends_at']));
}
