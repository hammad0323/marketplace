// Integration test for offline display mode, against a running API:
//   SIGNAGE_API=http://127.0.0.1:8080 flutter test test/integration/offline_display_test.dart
// (skipped when SIGNAGE_API is not set)
import 'dart:io';

import 'package:crypto/crypto.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:path_provider_platform_interface/path_provider_platform_interface.dart';
import 'package:plugin_platform_interface/plugin_platform_interface.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:signage_app/core/utils/json.dart';
import 'package:signage_app/features/display/code_display_service.dart';

class _FakePaths extends Fake with MockPlatformInterfaceMixin implements PathProviderPlatform {
  _FakePaths(this.dir);
  final String dir;
  @override
  Future<String?> getApplicationSupportPath() async => dir;
}

void main() {
  final api = Platform.environment['SIGNAGE_API'];
  late Directory tmp;

  setUpAll(() async {
    tmp = await Directory.systemTemp.createTemp('display_offline_');
    PathProviderPlatform.instance = _FakePaths(tmp.path);
    SharedPreferences.setMockInitialValues({});
  });
  tearDownAll(() => tmp.delete(recursive: true));

  test('downloads once, then plays offline with no network (even after a restart)', () async {
    // 1) Online: first start downloads content + every media file.
    final s1 = CodeDisplayService('PROMO1', baseUrl: api);
    await s1.start();
    expect(s1.doc, isNotNull, reason: s1.errorMessage);
    expect(s1.savedForOffline, isTrue, reason: 'all media stored on the device');
    expect(s1.savedFiles, greaterThan(0));
    // Files on disk match the server checksums.
    for (final m in asList(s1.doc!['media']).map(asMap)) {
      final f = File(s1.localPaths[asInt(m['media_id'])]!);
      expect(sha256.convert(await f.readAsBytes()).toString(), asString(m['checksum_sha256']));
    }

    // 2) Turn offline mode on.
    expect(await s1.setOfflineMode(true), isNull);
    expect(s1.offlineMode, isTrue);
    s1.dispose();

    // 3) "Reboot" with the server unreachable: must play from the device without any request.
    final s2 = CodeDisplayService('PROMO1', baseUrl: 'http://127.0.0.1:9');
    await s2.start();
    expect(s2.offlineMode, isTrue);
    expect(s2.doc, isNotNull);
    expect(s2.savedForOffline, isTrue);
    expect(s2.lastCheck, isNull, reason: 'offline mode makes no network request at all');
    expect(s2.media.image(s2.localPaths.keys.first), isNotNull);

    // 4) Offline mode off but no internet: keeps playing saved content.
    await s2.setOfflineMode(false);
    expect(s2.online, isFalse);
    expect(s2.doc, isNotNull);
    s2.dispose();

    // 5) Turning offline mode ON without internet and without saved content fails clearly.
    final s3 = CodeDisplayService('STORE1', baseUrl: 'http://127.0.0.1:9');
    await s3.start();
    expect(await s3.setOfflineMode(true), contains('No internet'));
    s3.dispose();
  }, skip: api == null ? 'set SIGNAGE_API to run' : false, timeout: const Timeout(Duration(minutes: 2)));
}
