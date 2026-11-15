// Integration test for the video cache, against a running API with a published video screen:
//   SIGNAGE_API=http://127.0.0.1:8080 SIGNAGE_VIDEO_CODE=<display code> flutter test test/integration/video_cache_test.dart
// (skipped when the variables are not set)
import 'dart:io';

import 'package:crypto/crypto.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:path_provider_platform_interface/path_provider_platform_interface.dart';
import 'package:plugin_platform_interface/plugin_platform_interface.dart';
import 'package:signage_app/core/services/video_cache.dart';
import 'package:signage_app/core/utils/json.dart';

class _FakePaths extends Fake with MockPlatformInterfaceMixin implements PathProviderPlatform {
  _FakePaths(this.dir);
  final String dir;
  @override
  Future<String?> getApplicationSupportPath() async => dir;
}

void main() {
  final api = Platform.environment['SIGNAGE_API'];
  final code = Platform.environment['SIGNAGE_VIDEO_CODE'];
  late Directory tmp;

  setUpAll(() async {
    tmp = await Directory.systemTemp.createTemp('video_cache_');
    PathProviderPlatform.instance = _FakePaths(tmp.path);
  });
  tearDownAll(() => tmp.delete(recursive: true));

  test('downloads a video once, verifies it, then always plays the local file', () async {
    final r = await Dio().get('$api/api/display/content.php', queryParameters: {'code': code});
    final media = asList(asMap(asMap(r.data)['data'])['doc']['media']).map(asMap).firstWhere((m) => m['file_type'] == 'video');
    final ref = VideoRef(mediaId: asInt(media['media_id']), url: asString(media['url']), size: asInt(media['file_size']), checksum: asString(media['checksum_sha256']));

    // 1) Nothing cached yet → stream now, download in the background.
    expect(await VideoCache.instance.local(ref), isNull);
    final (first, firstLocal) = await VideoCache.instance.open(ref);
    expect(firstLocal, isFalse);
    await first.dispose();

    // 2) The download completes, with the exact size and SHA-256 of the server file.
    final path = await VideoCache.instance.whenCached(ref);
    expect(path, isNotNull);
    final bytes = await File(path!).readAsBytes();
    expect(bytes.length, ref.size);
    expect(sha256.convert(bytes).toString(), ref.checksum);

    // 3) Every later open (other screens, next loop, after a restart) uses the local file,
    //    even when the signed URL has changed — and even with no URL/network at all.
    final (second, secondLocal) = await VideoCache.instance.open(ref);
    expect(secondLocal, isTrue);
    expect(second.dataSourceType.name, 'file');
    await second.dispose();
    final offline = VideoRef(mediaId: ref.mediaId, url: 'http://127.0.0.1:9/expired-link', size: ref.size, checksum: ref.checksum);
    final (third, thirdLocal) = await VideoCache.instance.open(offline);
    expect(thirdLocal, isTrue);
    await third.dispose();

    // 4) A damaged/partial file is detected and thrown away instead of being played.
    await File(path).writeAsBytes(bytes.sublist(0, bytes.length ~/ 2));
    expect(await VideoCache.instance.local(ref), isNull);
  }, skip: api == null || code == null ? 'set SIGNAGE_API and SIGNAGE_VIDEO_CODE' : false, timeout: const Timeout(Duration(minutes: 2)));
}
