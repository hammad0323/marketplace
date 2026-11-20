import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:material_ui/material_ui.dart';
import 'package:printhub_app/api/api.dart';
import 'package:printhub_app/screens/customer/home.dart';
import 'package:printhub_app/state/session.dart';
import 'package:printhub_app/util/format.dart';
import 'package:shared_preferences/shared_preferences.dart';

http.Response json(Object body, [int code = 200]) => http.Response(jsonEncode(body), code, headers: {'content-type': 'application/json'});

void main() {
  group('Api client', () {
    test('sends bearer + customer token headers and unwraps data', () async {
      late http.BaseRequest seen;
      final api = Api(
        baseUrl: 'https://example.test/api/v1',
        client: MockClient((r) async {
          seen = r;
          return json({'status': true, 'message': 'OK', 'data': {'value': 42}});
        }),
      )
        ..ownerToken = 'abc'
        ..customerToken = 'f' * 64;
      final d = await api.get('/owner/dashboard', {'x': 1});
      expect(d['value'], 42);
      expect(seen.url.toString(), 'https://example.test/api/v1/owner/dashboard?x=1');
      expect(seen.headers['Authorization'], 'Bearer abc');
      expect(seen.headers['X-Customer-Token'], 'f' * 64);
      expect(seen.headers['X-Client'], 'app');
      expect(api.lastMessage, 'OK');
    });

    test('throws ApiException with server message, code and errors', () async {
      final api = Api(baseUrl: 'https://example.test/api/v1', client: MockClient((r) async => json({'status': false, 'message': 'Subscription expired', 'data': {'errors': ['a', 'b']}}, 402)));
      try {
        await api.post('/owner/services', {});
        fail('should throw');
      } on ApiException catch (e) {
        expect(e.message, 'Subscription expired');
        expect(e.subscriptionExpired, isTrue);
        expect(e.errors, ['a', 'b']);
      }
    });

    test('maps network failures to a friendly message', () async {
      final api = Api(baseUrl: 'https://example.test/api/v1', client: MockClient((r) async => throw http.ClientException('down')));
      expect(() => api.get('/platform'), throwsA(isA<ApiException>().having((e) => e.message, 'message', contains('internet'))));
    });
  });

  group('helpers', () {
    test('parseRanges keeps order, removes duplicates and clamps', () {
      expect(parseRanges('1-3, 5, 2, 9-7, 40', 10), [1, 2, 3, 5, 9, 8, 7]);
      expect(parseRanges('abc', 5), isEmpty);
    });
    test('shopCodeFrom reads shop URLs and typed codes', () {
      expect(shopCodeFrom('https://printhub.pk/shop/PH-8GEJ9B'), 'PH-8GEJ9B');
      expect(shopCodeFrom('ph8gej9b'), 'PH-8GEJ9B');
      expect(shopCodeFrom('hello world'), isNull);
    });
    test('money formats rupees', () {
      expect(money(1500), 'Rs. 1,500');
      expect(money(null), '—');
    });
  });

  testWidgets('home screen shows scan and shop ID entry', (tester) async {
    SharedPreferences.setMockInitialValues({});
    FlutterSecureStorage.setMockInitialValues({});
    final session = Session(api: Api(baseUrl: 'https://example.test/api/v1', client: MockClient((r) async => json({'status': true, 'message': '', 'data': {}}))));
    await session.init();
    await tester.pumpWidget(SessionScope(session: session, child: const MaterialApp(home: HomeScreen())));
    expect(find.text('Scan shop QR code'), findsOneWidget);
    expect(find.text('Or enter shop ID'), findsOneWidget);
    expect(find.text('Shop owner'), findsOneWidget);
  });
}
