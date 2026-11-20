# PrintHub mobile app (Flutter)

One app with two modes:

- **Customers:** scan a shop QR code (or type the shop ID), see the shop page and its Wi-Fi details, upload PDF, photo or DOCX files, crop, rotate or flip photos, pick, rotate and reorder PDF pages, choose options, see the live price and a print-ready preview, send the order, track it, upload payment receipts, and use the CV maker (templates, live preview, PDF, print at a shop).
- **Shop owners:** sign in (accounts come from the platform admin), forced password change on first sign-in, dashboard, orders with every workflow action, QR code (design, PNG, poster PDF, full screen, share, regenerate), Wi-Fi sharing and Wi-Fi QR, services, prices with a tester, shop and order settings, reports, subscription status, notifications.

```bash
flutter pub get
flutter run --dart-define=API_BASE=https://yourdomain.com
flutter analyze && flutter test
```

Folder layout: `lib/api` (REST client), `lib/state` (session: owner token in secure storage, anonymous customer token), `lib/screens/owner`, `lib/screens/customer`, `lib/widgets`, `lib/services/notifier.dart` (polling + local notifications), `lib/router.dart` (routes mirror the web URLs for App Links).

Build, signing, App Links and push notes: see `../docs/DEPLOYMENT.md#flutter-app`.
