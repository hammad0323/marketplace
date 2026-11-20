import 'package:go_router/go_router.dart';

import 'screens/customer/cv.dart';
import 'screens/customer/home.dart';
import 'screens/customer/orders.dart';
import 'screens/customer/print_flow.dart';
import 'screens/customer/shop.dart';
import 'screens/owner/auth.dart';
import 'screens/owner/more.dart';
import 'screens/owner/orders.dart';
import 'screens/owner/pricing.dart';
import 'screens/owner/shell.dart';
import 'state/session.dart';

/// Routes mirror the web URLs (/shop/{code}, /track/{number}, /cv/{token}) so App Links / Universal Links
/// open the same screen in the app that the browser would show.
GoRouter buildRouter(Session session) => GoRouter(
      refreshListenable: session,
      initialLocation: session.isOwner ? '/owner' : '/',
      redirect: (context, state) {
        final p = state.uri.path;
        if (!p.startsWith('/owner')) return null;
        const open = ['/owner/login', '/owner/forgot', '/owner/reset'];
        if (!session.isOwner) return open.contains(p) ? null : '/owner/login';
        if (session.mustChangePassword && p != '/owner/password') return '/owner/password';
        if (open.contains(p)) return '/owner';
        return null;
      },
      routes: [
        GoRoute(path: '/', builder: (c, s) => const HomeScreen()),
        GoRoute(path: '/shop/:code', builder: (c, s) => ShopScreen(code: s.pathParameters['code']!.toUpperCase())),
        GoRoute(
          path: '/shop/:code/print',
          builder: (c, s) => PrintFlowScreen(code: s.pathParameters['code']!.toUpperCase(), categoryId: int.tryParse(s.uri.queryParameters['category'] ?? ''), cvToken: s.uri.queryParameters['cv']),
        ),
        GoRoute(path: '/track', builder: (c, s) => const TrackLookupScreen()),
        GoRoute(path: '/track/:number', builder: (c, s) => TrackScreen(number: s.pathParameters['number']!, token: s.uri.queryParameters['t'])),
        GoRoute(path: '/orders', builder: (c, s) => const MyOrdersScreen()),
        GoRoute(path: '/cv', builder: (c, s) => CvListScreen(shop: s.uri.queryParameters['shop'])),
        GoRoute(path: '/cv/:token', builder: (c, s) => CvEditorScreen(token: s.pathParameters['token']!)),
        GoRoute(path: '/owner/login', builder: (c, s) => const OwnerLoginScreen()),
        GoRoute(path: '/owner/forgot', builder: (c, s) => const ForgotPasswordScreen()),
        GoRoute(path: '/owner/reset', builder: (c, s) => ResetPasswordScreen(token: s.uri.queryParameters['token'] ?? '')),
        GoRoute(path: '/owner/password', builder: (c, s) => const ChangePasswordScreen()),
        GoRoute(path: '/owner', builder: (c, s) => OwnerShell(tab: int.tryParse(s.uri.queryParameters['tab'] ?? '') ?? 0)),
        GoRoute(path: '/owner/orders/:id', builder: (c, s) => OwnerOrderScreen(id: int.parse(s.pathParameters['id']!))),
        GoRoute(path: '/owner/wifi', builder: (c, s) => const WifiScreen()),
        GoRoute(path: '/owner/services', builder: (c, s) => const ServicesScreen()),
        GoRoute(path: '/owner/pricing', builder: (c, s) => const PricingScreen()),
        GoRoute(path: '/owner/shop', builder: (c, s) => const ShopSettingsScreen()),
        GoRoute(path: '/owner/profile', builder: (c, s) => const ProfileScreen()),
        GoRoute(path: '/owner/reports', builder: (c, s) => const ReportsScreen()),
        GoRoute(path: '/owner/subscription', builder: (c, s) => const SubscriptionScreen()),
        GoRoute(path: '/owner/notifications', builder: (c, s) => const OwnerNotificationsScreen()),
      ],
    );
