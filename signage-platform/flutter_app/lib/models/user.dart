import '../core/utils/json.dart';

class AppUser {
  AppUser({
    required this.userId, required this.roleKey, required this.fullName, required this.email, this.phone,
    this.businessName, this.timezone = 'UTC', this.status = 'active', this.mustChangePassword = false,
    this.profileImageUrl, this.accountExpiresAt, this.lastLoginAt, this.createdAt,
  });
  final int userId;
  final String roleKey;
  final String fullName;
  final String email;
  final String? phone;
  final String? businessName;
  final String timezone;
  final String status;
  final bool mustChangePassword;
  final String? profileImageUrl;
  final DateTime? accountExpiresAt;
  final DateTime? lastLoginAt;
  final DateTime? createdAt;

  bool get isAdmin => roleKey == 'super_admin';
  String get firstName => fullName.split(' ').first;
  String get initials => fullName.trim().split(RegExp(r'\s+')).take(2).map((p) => p.isEmpty ? '' : p[0].toUpperCase()).join();

  factory AppUser.fromJson(Map<String, dynamic> j) => AppUser(
        userId: asInt(j['user_id']), roleKey: asString(j['role_key'], 'user'), fullName: asString(j['full_name']),
        email: asString(j['email']), phone: j['phone']?.toString(), businessName: j['business_name']?.toString(),
        timezone: asString(j['timezone'], 'UTC'), status: asString(j['status'], 'active'),
        mustChangePassword: asBool(j['must_change_password']), profileImageUrl: j['profile_image_url']?.toString(),
        accountExpiresAt: asDate(j['account_expires_at']), lastLoginAt: asDate(j['last_login_at']), createdAt: asDate(j['created_at']),
      );
}

class UserLimits {
  UserLimits({
    this.planId, this.planName, this.maxScreens = 0, this.maxPlaylists = 0, this.maxDevices = 0, this.maxStorageMb = 0,
    this.maxUploadMb = 10, this.allowedWidgets = const ['*'], this.canUploadVideo = false, this.canUsePremiumTemplates = false,
  });
  final int? planId;
  final String? planName;
  final int maxScreens, maxPlaylists, maxDevices, maxStorageMb, maxUploadMb;
  final List<String> allowedWidgets;
  final bool canUploadVideo, canUsePremiumTemplates;

  bool widgetAllowed(String key) => allowedWidgets.contains('*') || allowedWidgets.contains(key);

  factory UserLimits.fromJson(Map<String, dynamic> j) => UserLimits(
        planId: asIntOrNull(j['plan_id']), planName: j['plan_name']?.toString(),
        maxScreens: asInt(j['max_screens']), maxPlaylists: asInt(j['max_playlists']), maxDevices: asInt(j['max_devices']),
        maxStorageMb: asInt(j['max_storage_mb']), maxUploadMb: asInt(j['max_upload_mb'], 10),
        allowedWidgets: asList(j['allowed_widgets']).map((e) => e.toString()).toList(),
        canUploadVideo: asBool(j['can_upload_video']), canUsePremiumTemplates: asBool(j['can_use_premium_templates']),
      );
}

class UserUsage {
  UserUsage({this.screensUsed = 0, this.playlistsUsed = 0, this.devicesUsed = 0, this.storageUsedBytes = 0});
  final int screensUsed, playlistsUsed, devicesUsed, storageUsedBytes;
  factory UserUsage.fromJson(Map<String, dynamic> j) => UserUsage(
        screensUsed: asInt(j['screens_used']), playlistsUsed: asInt(j['playlists_used']),
        devicesUsed: asInt(j['devices_used']), storageUsedBytes: asInt(j['storage_used_bytes']),
      );
}

class Session {
  Session({required this.token, required this.user, required this.permissions, required this.limits, required this.usage});
  final String token;
  final AppUser user;
  final List<String> permissions;
  final UserLimits limits;
  final UserUsage usage;

  bool can(String p) => permissions.contains(p);

  Session copyWith({AppUser? user, List<String>? permissions, UserLimits? limits, UserUsage? usage}) =>
      Session(token: token, user: user ?? this.user, permissions: permissions ?? this.permissions, limits: limits ?? this.limits, usage: usage ?? this.usage);
}
