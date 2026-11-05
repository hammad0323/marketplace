import 'package:dio/dio.dart';

import '../constants/app_config.dart';

/// Error thrown for any failed API call. [message] is user-friendly.
class ApiException implements Exception {
  ApiException(this.message, {this.statusCode, this.code, this.errors});
  final String message;
  final int? statusCode;
  final String? code;
  final Map<String, dynamic>? errors;

  bool get isNetwork => statusCode == null;
  bool get isUnauthorized => statusCode == 401;
  bool get isLimit => code != null && code!.startsWith('LIMIT_');

  @override
  String toString() => message;
}

/// Parsed `{status, message, data, meta}` envelope.
class ApiResponse<T> {
  ApiResponse(this.data, this.message, this.meta, this.raw);
  final T data;
  final String message;
  final Map<String, dynamic>? meta;
  final Map<String, dynamic> raw;
}

/// Thin Dio wrapper used by all repositories.
class ApiClient {
  ApiClient({String? baseUrl, this.tokenProvider, this.onUnauthorized})
      : dio = Dio(BaseOptions(
          baseUrl: '${(baseUrl ?? AppConfig.apiBaseUrl).replaceAll(RegExp(r'/+$'), '')}/api/',
          connectTimeout: AppConfig.connectTimeout,
          receiveTimeout: AppConfig.receiveTimeout,
          sendTimeout: const Duration(minutes: 10),
          responseType: ResponseType.json,
          validateStatus: (_) => true,
        )) {
    dio.interceptors.add(InterceptorsWrapper(onRequest: (options, handler) async {
      final t = tokenProvider?.call();
      if (t != null && t.isNotEmpty) options.headers['Authorization'] = 'Bearer $t';
      handler.next(options);
    }));
  }

  final Dio dio;
  final String? Function()? tokenProvider;
  final void Function()? onUnauthorized;

  Future<ApiResponse<dynamic>> get(String path, {Map<String, dynamic>? query}) =>
      _send(() => dio.get(path, queryParameters: _clean(query)));

  Future<ApiResponse<dynamic>> post(String path, [Map<String, dynamic>? body]) =>
      _send(() => dio.post(path, data: body ?? <String, dynamic>{}));

  /// Multipart upload of one file (bytes on web, streamed from disk on mobile).
  Future<ApiResponse<dynamic>> upload(
    String path, {
    required MultipartFile file,
    Map<String, dynamic>? fields,
    void Function(double progress)? onProgress,
  }) {
    final form = FormData.fromMap({
      ...?fields?.map((k, v) => MapEntry(k, v?.toString() ?? '')),
      'file': file,
    });
    return _send(() => dio.post(path, data: form, onSendProgress: (sent, total) {
          if (total > 0) onProgress?.call(sent / total);
        }));
  }

  Map<String, dynamic>? _clean(Map<String, dynamic>? q) {
    if (q == null) return null;
    final out = <String, dynamic>{};
    q.forEach((k, v) {
      if (v == null) return;
      out[k] = v is bool ? (v ? '1' : '0') : v.toString();
    });
    return out;
  }

  Future<ApiResponse<dynamic>> _send(Future<Response<dynamic>> Function() call) async {
    Response<dynamic> r;
    try {
      r = await call();
    } on DioException catch (e) {
      throw ApiException(_networkMessage(e));
    }
    final body = r.data;
    if (body is! Map<String, dynamic>) {
      throw ApiException('Unexpected response from server (${r.statusCode})', statusCode: r.statusCode);
    }
    if (body['status'] == true) {
      return ApiResponse(body['data'], (body['message'] ?? '').toString(),
          body['meta'] is Map<String, dynamic> ? body['meta'] as Map<String, dynamic> : null, body);
    }
    if (r.statusCode == 401) onUnauthorized?.call();
    throw ApiException(
      (body['message'] ?? 'Request failed').toString(),
      statusCode: r.statusCode,
      code: body['code']?.toString(),
      errors: body['errors'] is Map<String, dynamic> ? body['errors'] as Map<String, dynamic> : null,
    );
  }

  String _networkMessage(DioException e) {
    switch (e.type) {
      case DioExceptionType.connectionTimeout:
      case DioExceptionType.receiveTimeout:
      case DioExceptionType.sendTimeout:
        return 'The server is taking too long to respond. Check your connection and try again.';
      case DioExceptionType.connectionError:
        return 'Cannot reach the server. Check your internet connection.';
      default:
        return 'Network error. Please try again.';
    }
  }
}
