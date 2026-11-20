import 'dart:convert';
import 'dart:typed_data';

import 'package:http/http.dart' as http;

import '../config.dart';

/// Error carrying the server's message (`{"status": false, "message": ...}`).
class ApiException implements Exception {
  ApiException(this.message, {this.code = 0, this.errors = const []});
  final String message;
  final int code;
  final List<String> errors;
  bool get unauthorized => code == 401;
  bool get subscriptionExpired => code == 402;
  @override
  String toString() => message;
}

/// Thin client for the PrintHub REST API. Every response uses the envelope
/// `{"status": bool, "message": string, "data": object}`; `data` is returned on success.
class Api {
  Api({http.Client? client, this.baseUrl}) : _http = client ?? http.Client();

  final http.Client _http;
  final String? baseUrl;
  String? ownerToken;
  String? customerToken;

  String get _base => baseUrl ?? AppConfig.apiBase;

  Map<String, String> _headers({bool json = false}) => {
        'Accept': 'application/json',
        'X-Client': 'app',
        if (json) 'Content-Type': 'application/json',
        if (ownerToken != null) 'Authorization': 'Bearer $ownerToken',
        'X-Customer-Token': ?customerToken,
      };

  Uri uri(String path, [Map<String, dynamic>? query]) {
    final u = Uri.parse('$_base$path');
    if (query == null || query.isEmpty) return u;
    return u.replace(queryParameters: {...u.queryParameters, ...query.map((k, v) => MapEntry(k, '$v'))});
  }

  Map<String, String> get authHeaders => _headers();

  Future<Map<String, dynamic>> get(String path, [Map<String, dynamic>? query]) async =>
      _decode(await _send(() => _http.get(uri(path, query), headers: _headers())));

  Future<Map<String, dynamic>> post(String path, [Map<String, dynamic>? body]) async =>
      _decode(await _send(() => _http.post(uri(path), headers: _headers(json: true), body: jsonEncode(body ?? {}))));

  Future<Map<String, dynamic>> delete(String path) async => _decode(await _send(() => _http.delete(uri(path), headers: _headers())));

  /// Message of the last successful call (e.g. "Order submitted successfully").
  String lastMessage = '';

  Future<Map<String, dynamic>> upload(String path, {required String field, required Uint8List bytes, required String filename, Map<String, String> fields = const {}}) async {
    final req = http.MultipartRequest('POST', uri(path))
      ..headers.addAll(_headers())
      ..fields.addAll(fields)
      ..files.add(http.MultipartFile.fromBytes(field, bytes, filename: filename));
    final streamed = await _send(() => _http.send(req));
    return _decode(await http.Response.fromStream(streamed as http.StreamedResponse));
  }

  /// Binary download (QR PNG, posters, PDFs) with the same authentication.
  Future<Uint8List> bytes(String path, [Map<String, dynamic>? query]) async {
    final r = await _send(() => _http.get(uri(path, query), headers: _headers())) as http.Response;
    if (r.statusCode != 200) {
      _decode(r);
    }
    return r.bodyBytes;
  }

  /// Downloads any absolute URL (signed file links).
  Future<Uint8List> url(String url) async {
    final r = await _send(() => _http.get(Uri.parse(url))) as http.Response;
    if (r.statusCode != 200) throw ApiException('The file is no longer available.', code: r.statusCode);
    return r.bodyBytes;
  }

  Future<http.BaseResponse> _send(Future<http.BaseResponse> Function() fn) async {
    try {
      return await fn().timeout(const Duration(seconds: 90));
    } catch (e) {
      if (e is ApiException) rethrow;
      throw ApiException('No internet connection. Please check your network and try again.');
    }
  }

  Map<String, dynamic> _decode(http.BaseResponse res) {
    final r = res as http.Response;
    Map<String, dynamic> body;
    try {
      body = jsonDecode(utf8.decode(r.bodyBytes)) as Map<String, dynamic>;
    } catch (_) {
      throw ApiException(r.statusCode == 413 ? 'This file is too large for the server.' : 'Unexpected server response (${r.statusCode}).', code: r.statusCode);
    }
    final data = body['data'];
    if (body['status'] != true) {
      final errors = data is Map && data['errors'] is List ? (data['errors'] as List).map((e) => '$e').toList() : <String>[];
      throw ApiException('${body['message'] ?? 'Request failed'}', code: r.statusCode, errors: errors);
    }
    lastMessage = '${body['message'] ?? ''}';
    return data is Map<String, dynamic> ? data : <String, dynamic>{};
  }
}
