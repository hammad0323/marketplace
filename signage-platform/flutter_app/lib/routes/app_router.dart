import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../features/admin/admin_screens.dart';
import '../features/admin/admin_shell.dart';
import '../features/admin/admin_users.dart';
import '../features/authentication/login_screen.dart';
import '../features/dashboard/home_screen.dart';
import '../features/dashboard/main_shell.dart';
import '../features/devices/devices_screens.dart';
import '../features/editor/editor_screen.dart';
import '../features/media_library/media_library_screen.dart';
import '../features/notifications/notifications_screen.dart';
import '../features/playlists/playlist_editor_screen.dart';
import '../features/playlists/playlist_preview_screen.dart';
import '../features/playlists/playlists_screen.dart';
import '../features/profile/profile_screens.dart';
import '../features/scheduling/schedules_overview_screen.dart';
import '../features/screens/screen_preview_screen.dart';
import '../features/screens/screens_list_screen.dart';
import '../features/templates/template_library_screen.dart';
import '../providers/providers.dart';

/// Bridges Riverpod session changes into GoRouter.refreshListenable.
class _RouterRefresh extends ChangeNotifier {
  void ping() => notifyListeners();
}

final routerProvider = Provider<GoRouter>((ref) {
  final refresh = _RouterRefresh();
  ref.listen(sessionProvider, (_, _) => refresh.ping());
  ref.onDispose(refresh.dispose);

  return GoRouter(
    initialLocation: '/home',
    refreshListenable: refresh,
    redirect: (context, state) {
      final session = ref.read(sessionProvider);
      if (session.isLoading && !session.hasValue) return state.matchedLocation == '/splash' ? null : '/splash';
      final s = session.value;
      final loc = state.matchedLocation;
      final public = loc == '/login' || loc == '/forgot';
      if (s == null) return public ? null : '/login';
      if (public || loc == '/splash') return s.user.isAdmin ? '/admin' : '/home';
      if (loc.startsWith('/admin') && !s.user.isAdmin) return '/home';
      return null;
    },
    routes: [
      GoRoute(path: '/splash', builder: (_, _) => const SplashScreen()),
      GoRoute(path: '/login', builder: (_, _) => const LoginScreen()),
      GoRoute(path: '/forgot', builder: (_, _) => const ForgotPasswordScreen()),

      // ---------------- User experience (bottom navigation) ----------------
      StatefulShellRoute.indexedStack(
        builder: (_, _, shell) => MainShell(shell: shell),
        branches: [
          StatefulShellBranch(routes: [GoRoute(path: '/home', builder: (_, _) => const HomeScreen())]),
          StatefulShellBranch(routes: [GoRoute(path: '/screens', builder: (_, _) => const ScreensListScreen())]),
          StatefulShellBranch(routes: [GoRoute(path: '/playlists', builder: (_, _) => const PlaylistsScreen())]),
          StatefulShellBranch(routes: [GoRoute(path: '/devices', builder: (_, _) => const DevicesScreen())]),
          StatefulShellBranch(routes: [GoRoute(path: '/profile', builder: (_, _) => const ProfileScreen())]),
        ],
      ),
      GoRoute(path: '/screens/new', builder: (_, s) => TemplateLibraryScreen(startBlank: s.uri.queryParameters['blank'] == '1')),
      GoRoute(path: '/editor/:id', builder: (_, s) => EditorScreen(key: ValueKey('ed${s.pathParameters['id']}'), screenId: int.parse(s.pathParameters['id']!))),
      GoRoute(path: '/preview/screen/:id', builder: (_, s) => ScreenPreviewScreen(key: ValueKey('sp${s.pathParameters['id']}'), screenId: int.parse(s.pathParameters['id']!))),
      GoRoute(
        path: '/playlists/new',
        builder: (_, s) => PlaylistEditorScreen(
          initialScreenIds: (s.uri.queryParameters['screens'] ?? '').split(',').where((e) => e.isNotEmpty).map(int.parse).toList(),
        ),
      ),
      GoRoute(path: '/playlists/:id/edit', builder: (_, s) => PlaylistEditorScreen(key: ValueKey('pe${s.pathParameters['id']}'), playlistId: int.parse(s.pathParameters['id']!))),
      GoRoute(path: '/playlists/:id/preview', builder: (_, s) => PlaylistPreviewScreen(key: ValueKey('pp${s.pathParameters['id']}'), playlistId: int.parse(s.pathParameters['id']!))),
      GoRoute(path: '/schedules', builder: (_, _) => const SchedulesOverviewScreen()),
      GoRoute(path: '/devices/add', builder: (_, _) => const AddDeviceScreen()),
      GoRoute(path: '/devices/:id', builder: (_, s) => DeviceDetailScreen(key: ValueKey('dd${s.pathParameters['id']}'), deviceId: int.parse(s.pathParameters['id']!))),
      GoRoute(path: '/media', builder: (_, _) => const MediaLibraryScreen()),
      GoRoute(path: '/notifications', builder: (_, _) => const NotificationsScreen()),
      GoRoute(path: '/profile/edit', builder: (_, _) => const EditProfileScreen()),
      GoRoute(path: '/profile/password', builder: (_, _) => const ChangePasswordScreen()),
      GoRoute(path: '/emergency', builder: (_, _) => const EmergencyScreen()),
      GoRoute(path: '/locations', builder: (_, _) => const LocationsScreen()),
      GoRoute(path: '/reports', builder: (_, _) => const ReportsScreen()),

      // ---------------- Super admin ----------------
      StatefulShellRoute.indexedStack(
        builder: (_, _, shell) => AdminShell(shell: shell),
        branches: [
          StatefulShellBranch(routes: [GoRoute(path: '/admin', builder: (_, _) => const AdminDashboardScreen())]),
          StatefulShellBranch(routes: [GoRoute(path: '/admin/users', builder: (_, _) => const AdminUsersScreen())]),
          StatefulShellBranch(routes: [GoRoute(path: '/admin/plans', builder: (_, _) => const AdminPlansScreen())]),
          StatefulShellBranch(routes: [GoRoute(path: '/admin/content', builder: (_, _) => const AdminContentScreen())]),
          StatefulShellBranch(routes: [GoRoute(path: '/admin/more', builder: (_, _) => const AdminMoreScreen())]),
        ],
      ),
      GoRoute(path: '/admin/users/new', builder: (_, _) => const AdminUserFormScreen()),
      GoRoute(path: '/admin/users/:id', builder: (_, s) => AdminUserDetailScreen(key: ValueKey('ud${s.pathParameters['id']}'), userId: int.parse(s.pathParameters['id']!))),
      GoRoute(path: '/admin/users/:id/edit', builder: (_, s) => AdminUserFormScreen(key: ValueKey('uf${s.pathParameters['id']}'), userId: int.parse(s.pathParameters['id']!))),
      GoRoute(path: '/admin/devices', builder: (_, _) => const AdminDevicesScreen()),
      GoRoute(path: '/admin/activity', builder: (_, _) => const AdminActivityScreen()),
      GoRoute(path: '/admin/settings', builder: (_, _) => const AdminSettingsScreen()),
      GoRoute(path: '/admin/widgets', builder: (_, _) => const AdminWidgetTypesScreen()),
      GoRoute(path: '/admin/media', builder: (_, _) => const AdminMediaScreen()),
    ],
  );
});
