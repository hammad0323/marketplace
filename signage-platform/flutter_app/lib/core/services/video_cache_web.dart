// Web storage for VideoCache: the whole file in memory, played from a blob: URL.
import 'dart:js_interop';
import 'dart:typed_data';

import 'package:dio/dio.dart';
import 'package:video_player/video_player.dart';
import 'package:web/web.dart' as web;

/// Larger videos are streamed (keeps memory use reasonable on TV browsers).
const int maxCacheableBytes = 400 * 1048576;

final Dio _dio = Dio(BaseOptions(connectTimeout: const Duration(seconds: 20), receiveTimeout: const Duration(minutes: 30)));
final Map<String, String> _blobUrls = {};

Future<bool> fileExists(String path) async => false;

Future<String?> findLocal(String key, int expectedSize) async => _blobUrls[key];

Future<String?> download(String key, String url, int expectedSize, String? checksum) async {
  final r = await _dio.get<List<int>>(url, options: Options(responseType: ResponseType.bytes));
  final bytes = r.data is Uint8List ? r.data as Uint8List : Uint8List.fromList(r.data ?? const []);
  if (bytes.isEmpty || (expectedSize > 0 && bytes.length != expectedSize)) {
    throw Exception('incomplete video download (${bytes.length} of $expectedSize bytes)');
  }
  final type = r.headers.value('content-type') ?? 'video/mp4';
  final blob = web.Blob([bytes.toJS].toJS, web.BlobPropertyBag(type: type));
  final u = web.URL.createObjectURL(blob);
  _blobUrls[key] = u;
  return u;
}

VideoPlayerController controllerFor(String location) => VideoPlayerController.networkUrl(Uri.parse(location));
