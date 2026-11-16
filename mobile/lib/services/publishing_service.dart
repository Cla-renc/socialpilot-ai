import 'dart:async';
import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;

import '../models/publishing_attempt.dart';

const String _publishingApiBaseUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'http://10.0.2.2:8000/api/v1',
);

class PublishingService {
  PublishingService({
    http.Client? client,
    this.requestTimeout = const Duration(seconds: 15),
  })  : _client = client ?? http.Client();

  final http.Client _client;
  final Duration requestTimeout;

  static const FlutterSecureStorage _storage = FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
    iOptions: IOSOptions(accessibility: KeychainAccessibility.first_unlock),
  );

  // ── HTTP helpers ──────────────────────────────────────────────────────────

  Future<Map<String, dynamic>> _request(
    String method,
    String path, {
    Map<String, dynamic>? body,
  }) async {
    final token = await _storage.read(key: 'socialpilot_api_token');
    final uri = Uri.parse('$_publishingApiBaseUrl$path');
    final headers = <String, String>{'Accept': 'application/json'};
    if (token != null && token.isNotEmpty) {
      headers['Authorization'] = 'Bearer $token';
    }

    late final http.Response response;
    try {
      if (method == 'GET') {
        response = await _client.get(uri, headers: headers).timeout(requestTimeout);
      } else if (method == 'POST') {
        response = await _client
            .post(
              uri,
              headers: {...headers, 'Content-Type': 'application/json'},
              body: jsonEncode(body ?? {}),
            )
            .timeout(requestTimeout);
      } else if (method == 'PUT') {
        response = await _client
            .put(
              uri,
              headers: {...headers, 'Content-Type': 'application/json'},
              body: jsonEncode(body ?? {}),
            )
            .timeout(requestTimeout);
      } else if (method == 'PATCH') {
        response = await _client
            .patch(
              uri,
              headers: {...headers, 'Content-Type': 'application/json'},
              body: jsonEncode(body ?? {}),
            )
            .timeout(requestTimeout);
      } else if (method == 'DELETE') {
        response = await _client.delete(uri, headers: headers).timeout(requestTimeout);
      } else {
        throw UnsupportedError('Unsupported method: $method');
      }
    } on TimeoutException {
      throw _ApiException(
        'The request timed out. Check your connection and try again.',
      );
    } on http.ClientException {
      throw _ApiException(
        'Could not connect to SocialPilot. Check your internet connection and try again.',
      );
    }

    return _handleResponse(response);
  }

  Map<String, dynamic> _handleResponse(http.Response response) {
    if (response.statusCode == 401) {
      throw _ApiException(
        'Your session has expired. Please sign in again.',
        statusCode: response.statusCode,
      );
    }
    if (response.statusCode >= 500) {
      throw _ApiException(
        'SocialPilot is temporarily unavailable. Please try again.',
        statusCode: response.statusCode,
      );
    }
    return _decodeResponse(response.statusCode, response.body);
  }

  Map<String, dynamic> _decodeResponse(int statusCode, String body) {
    final dynamic decoded;
    try {
      decoded = body.isEmpty ? <String, dynamic>{} : jsonDecode(body);
    } on FormatException {
      throw _ApiException(
        'The server returned an invalid response. Please try again.',
      );
    }
    if (statusCode >= 200 && statusCode < 300) {
      if (decoded is Map<String, dynamic>) return decoded;
      if (decoded is Map) return Map<String, dynamic>.from(decoded);
      return {'data': decoded};
    }
    if (decoded is Map && decoded['errors'] is Map) {
      final errors = decoded['errors'] as Map;
      for (final error in errors.values) {
        if (error is List && error.isNotEmpty) {
          throw _ApiException(error.first.toString());
        }
      }
    }
    if (decoded is Map && decoded['error'] is Map) {
      throw _ApiException(
        (decoded['error'] as Map)['message']?.toString() ??
            'Request failed with status $statusCode.',
      );
    }
    if (decoded is Map && decoded.containsKey('message')) {
      throw _ApiException(decoded['message'].toString());
    }
    throw _ApiException('Request failed with status $statusCode.');
  }

  // ── Publishing Attempts ───────────────────────────────────────────────────

  Future<Map<String, dynamic>> getAttempts({
    int? brandId,
    String? platform,
    String? status,
    int page = 1,
  }) async {
    final query = <String, String>{'page': '$page'};
    if (brandId != null) query['brand_id'] = '$brandId';
    if (platform != null && platform.isNotEmpty) query['platform'] = platform;
    if (status != null && status.isNotEmpty) query['status'] = status;
    return _request(
      'GET',
      '/publishing/attempts?${Uri(queryParameters: query).query}',
    );
  }

  Future<PublishingAttempt> getAttempt(int id) async {
    final response = await _request('GET', '/publishing/attempts/$id');
    final data = response['data'];
    if (data is! Map) {
      throw _ApiException('Invalid attempt response.');
    }
    return PublishingAttempt.fromJson(Map<String, dynamic>.from(data));
  }

  Future<void> retryAttempt(int id) async {
    await _request('POST', '/publishing/attempts/$id/retry');
  }

  Future<void> cancelAttempt(int id) async {
    await _request('POST', '/publishing/attempts/$id/cancel');
  }

  Future<Map<String, dynamic>> getHistory({
    int? brandId,
    String? platform,
    int page = 1,
  }) async {
    final query = <String, String>{'page': '$page'};
    if (brandId != null) query['brand_id'] = '$brandId';
    if (platform != null && platform.isNotEmpty) query['platform'] = platform;
    return _request(
      'GET',
      '/publishing/history?${Uri(queryParameters: query).query}',
    );
  }

  Future<Map<String, dynamic>> getFailed({
    int? brandId,
    int page = 1,
  }) async {
    final query = <String, String>{'page': '$page'};
    if (brandId != null) query['brand_id'] = '$brandId';
    return _request(
      'GET',
      '/publishing/failed?${Uri(queryParameters: query).query}',
    );
  }

  Future<Map<String, dynamic>> getStatistics({int? brandId}) async {
    final query = <String, String>{};
    if (brandId != null) query['brand_id'] = '$brandId';
    final qs = query.isEmpty ? '' : '?${Uri(queryParameters: query).query}';
    return _request('GET', '/publishing/statistics$qs');
  }

  Future<PublishingAttempt> publishNow({
    required int socialAccountId,
    required String content,
    required int brandId,
    List<String> mediaUrls = const [],
  }) async {
    final response = await _request(
      'POST',
      '/publishing/publish-now',
      body: {
        'social_account_id': socialAccountId,
        'content': content,
        'brand_id': brandId,
        'media_urls': mediaUrls,
      },
    );
    final data = response['data'];
    if (data is! Map) {
      throw _ApiException('Invalid publish-now response.');
    }
    return PublishingAttempt.fromJson(Map<String, dynamic>.from(data));
  }

  // ── Notifications ─────────────────────────────────────────────────────────

  Future<Map<String, dynamic>> getNotifications({
    bool unreadOnly = false,
    int page = 1,
  }) async {
    final query = <String, String>{'page': '$page'};
    if (unreadOnly) query['unread_only'] = '1';
    return _request(
      'GET',
      '/publishing/notifications?${Uri(queryParameters: query).query}',
    );
  }

  Future<int> getUnreadCount() async {
    try {
      final response =
          await _request('GET', '/publishing/notifications/unread-count');
      return (response['count'] as int?) ?? 0;
    } catch (_) {
      return 0;
    }
  }

  Future<void> markNotificationRead(int id) async {
    await _request('POST', '/publishing/notifications/$id/read');
  }
}

class _ApiException implements Exception {
  _ApiException(this.message, {this.statusCode});

  final String message;
  final int? statusCode;

  @override
  String toString() => message;
}
