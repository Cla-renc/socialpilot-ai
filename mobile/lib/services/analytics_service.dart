import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;

const String _analyticsApiBaseUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'http://10.0.2.2:8000/api/v1',
);

class AnalyticsService {
  AnalyticsService({http.Client? client})
      : _client = client ?? http.Client();

  final http.Client _client;

  static const FlutterSecureStorage _storage = FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
    iOptions: IOSOptions(accessibility: KeychainAccessibility.first_unlock),
  );

  static const String _tokenKey = 'socialpilot_api_token';

  Future<String?> _getToken() => _storage.read(key: _tokenKey);

  Map<String, String> _headers(String token) => {
        'Authorization': 'Bearer $token',
        'Accept': 'application/json',
        'Content-Type': 'application/json',
      };

  Future<Map<String, dynamic>> _get(String path) async {
    final token = await _getToken();
    if (token == null) throw Exception('Not authenticated');
    final response = await _client.get(
      Uri.parse('$_analyticsApiBaseUrl$path'),
      headers: _headers(token),
    );
    return _handle(response);
  }

  Future<Map<String, dynamic>> _post(String path) async {
    final token = await _getToken();
    if (token == null) throw Exception('Not authenticated');
    final response = await _client.post(
      Uri.parse('$_analyticsApiBaseUrl$path'),
      headers: _headers(token),
    );
    return _handle(response);
  }

  Future<Map<String, dynamic>> getOverview({String period = '30d'}) =>
      _get('/analytics/overview?period=$period');

  Future<Map<String, dynamic>> getBrandAnalytics(int brandId,
          {String period = '30d'}) =>
      _get('/analytics/brands/$brandId?period=$period');

  Future<Map<String, dynamic>> getPlatformAnalytics(
      {String? platform, String period = '30d'}) =>
      _get('/analytics/platforms?period=$period${platform != null ? '&platform=$platform' : ''}');

  Future<Map<String, dynamic>> getTopPerforming({String? platform}) =>
      _get('/analytics/top-performing${platform != null ? '?platform=$platform' : ''}');

  Future<Map<String, dynamic>> getRecommendations({String status = 'pending'}) =>
      _get('/analytics/recommendations?status=$status');

  Future<Map<String, dynamic>> acceptRecommendation(int id) =>
      _post('/analytics/recommendations/$id/accept');

  Future<Map<String, dynamic>> dismissRecommendation(int id) =>
      _post('/analytics/recommendations/$id/dismiss');

  Future<Map<String, dynamic>> getReports() => _get('/analytics/reports');

  Future<Map<String, dynamic>> getCollectionStatus() =>
      _get('/analytics/collection-status');

  Map<String, dynamic> _handle(http.Response response) {
    if (response.statusCode >= 200 && response.statusCode < 300) {
      if (response.body.isEmpty) return {};
      final decoded = jsonDecode(response.body);
      if (decoded is Map<String, dynamic>) return decoded;
      return {'data': decoded};
    }
    throw Exception('Request failed: ${response.statusCode}');
  }
}
