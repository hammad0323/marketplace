import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../core/network/api_client.dart';
import '../core/storage/secure_store.dart';
import '../core/utils/json.dart';
import '../models/device.dart';
import '../models/misc.dart';
import '../models/playlist.dart';
import '../models/screen.dart';
import '../models/template.dart';
import '../models/user.dart';
import '../repositories/repositories.dart';

/// Holds the current bearer token (read synchronously by the Dio interceptor).
class TokenHolder {
  String? token;
}

final tokenHolderProvider = Provider<TokenHolder>((_) => TokenHolder());

final apiClientProvider = Provider<ApiClient>((ref) {
  final holder = ref.watch(tokenHolderProvider);
  return ApiClient(
    tokenProvider: () => holder.token,
    onUnauthorized: () => Future.microtask(() => ref.read(sessionProvider.notifier).expired()),
  );
});

final authRepoProvider = Provider((ref) => AuthRepository(ref.watch(apiClientProvider)));
final screensRepoProvider = Provider((ref) => ScreensRepository(ref.watch(apiClientProvider)));
final templatesRepoProvider = Provider((ref) => TemplatesRepository(ref.watch(apiClientProvider)));
final mediaRepoProvider = Provider((ref) => MediaRepository(ref.watch(apiClientProvider)));
final playlistsRepoProvider = Provider((ref) => PlaylistsRepository(ref.watch(apiClientProvider)));
final devicesRepoProvider = Provider((ref) => DevicesRepository(ref.watch(apiClientProvider)));
final miscRepoProvider = Provider((ref) => MiscRepository(ref.watch(apiClientProvider)));
final adminRepoProvider = Provider((ref) => AdminRepository(ref.watch(apiClientProvider)));

// ---------------------------------------------------------------------------
// Session
// ---------------------------------------------------------------------------
class SessionNotifier extends AsyncNotifier<Session?> {
  static const _tokenKey = 'api_token';

  @override
  Future<Session?> build() async {
    final token = await SecureStore.read(_tokenKey);
    if (token == null || token.isEmpty) return null;
    ref.read(tokenHolderProvider).token = token;
    try {
      final p = await ref.read(authRepoProvider).profile();
      return _fromProfile(token, p);
    } on ApiException catch (e) {
      if (e.isUnauthorized || e.statusCode == 403) {
        await SecureStore.write(_tokenKey, null);
        ref.read(tokenHolderProvider).token = null;
        return null;
      }
      rethrow;
    }
  }

  Session _fromProfile(String token, Map<String, dynamic> p) => Session(
        token: token,
        user: AppUser.fromJson(asMap(p['user'])),
        permissions: asList(p['permissions']).map((e) => e.toString()).toList(),
        limits: UserLimits.fromJson(asMap(p['limits'])),
        usage: UserUsage.fromJson(asMap(p['usage'])),
      );

  Future<void> login(String email, String password) async {
    final label = kIsWeb ? 'Web browser' : '${defaultTargetPlatform.name} app';
    final d = await ref.read(authRepoProvider).login(email, password, label);
    final token = asString(d['token']);
    ref.read(tokenHolderProvider).token = token;
    await SecureStore.write(_tokenKey, token);
    state = AsyncData(_fromProfile(token, d));
  }

  Future<void> refresh() async {
    final s = state.value;
    if (s == null) return;
    try {
      final p = await ref.read(authRepoProvider).profile();
      state = AsyncData(_fromProfile(s.token, p));
    } catch (_) {}
  }

  void updateUser(AppUser u) {
    final s = state.value;
    if (s != null) state = AsyncData(s.copyWith(user: u));
  }

  Future<void> logout() async {
    try {
      await ref.read(authRepoProvider).logout();
    } catch (_) {}
    await _clear();
  }

  Future<void> expired() async {
    if (state.value != null) await _clear();
  }

  Future<void> _clear() async {
    ref.read(tokenHolderProvider).token = null;
    await SecureStore.write(_tokenKey, null);
    state = const AsyncData(null);
  }
}

final sessionProvider = AsyncNotifierProvider<SessionNotifier, Session?>(SessionNotifier.new);

// ---------------------------------------------------------------------------
// Theme
// ---------------------------------------------------------------------------
class ThemeModeNotifier extends Notifier<ThemeMode> {
  @override
  ThemeMode build() {
    SharedPreferences.getInstance().then((p) {
      final v = p.getString('theme_mode');
      if (v != null) state = ThemeMode.values.firstWhere((m) => m.name == v, orElse: () => ThemeMode.system);
    });
    return ThemeMode.system;
  }

  Future<void> set(ThemeMode m) async {
    state = m;
    (await SharedPreferences.getInstance()).setString('theme_mode', m.name);
  }
}

final themeModeProvider = NotifierProvider<ThemeModeNotifier, ThemeMode>(ThemeModeNotifier.new);

// ---------------------------------------------------------------------------
// Data providers (invalidate after mutations)
// ---------------------------------------------------------------------------
final dashboardProvider = FutureProvider.autoDispose<Map<String, dynamic>>((ref) => ref.watch(authRepoProvider).dashboard());

class ScreenFilter {
  const ScreenFilter({this.q, this.status, this.folderId, this.favorites = false});
  final String? q, status;
  final int? folderId;
  final bool favorites;
  @override
  bool operator ==(Object other) => other is ScreenFilter && other.q == q && other.status == status && other.folderId == folderId && other.favorites == favorites;
  @override
  int get hashCode => Object.hash(q, status, folderId, favorites);
}

final screensProvider = FutureProvider.autoDispose.family<List<ScreenModel>, ScreenFilter>((ref, f) async =>
    (await ref.watch(screensRepoProvider).list(q: f.q, status: f.status, folderId: f.folderId, favorites: f.favorites)).items);
final screenFoldersProvider = FutureProvider.autoDispose<List<ScreenFolder>>((ref) => ref.watch(screensRepoProvider).folders());
final templateCategoriesProvider = FutureProvider<List<TemplateCategory>>((ref) => ref.watch(templatesRepoProvider).categories());
final templatesProvider = FutureProvider.autoDispose<List<ScreenTemplate>>((ref) => ref.watch(templatesRepoProvider).list());
final widgetTypesProvider = FutureProvider<({List<WidgetTypeInfo> types, List<String> recent})>((ref) => ref.watch(templatesRepoProvider).widgetTypes());
final playlistsProvider = FutureProvider.autoDispose<List<Playlist>>((ref) async => (await ref.watch(playlistsRepoProvider).list()).items);
final devicesProvider = FutureProvider.autoDispose<List<Device>>((ref) async => (await ref.watch(devicesRepoProvider).list()).items);
final locationsProvider = FutureProvider.autoDispose<List<LocationModel>>((ref) => ref.watch(devicesRepoProvider).locations());
final notificationsProvider = FutureProvider.autoDispose<List<AppNotification>>((ref) async => (await ref.watch(miscRepoProvider).notifications()).items);

/// Call after any content mutation so lists/dashboards refresh.
void invalidateContent(WidgetRef ref) {
  ref.invalidate(dashboardProvider);
  ref.invalidate(screensProvider);
  ref.invalidate(playlistsProvider);
  ref.invalidate(devicesProvider);
  ref.read(sessionProvider.notifier).refresh();
}
