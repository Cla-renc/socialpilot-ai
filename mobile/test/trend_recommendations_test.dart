import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:socialpilot_ai/main.dart';

class _MemoryStorage implements AppStorage {
  String? token = 'test-bearer-token';

  @override
  Future<void> clearSelectedBrandId() async {}

  @override
  Future<void> clearToken() async {
    token = null;
  }

  @override
  Future<int?> readSelectedBrandId() async => null;

  @override
  Future<String?> readToken() async => token;

  @override
  Future<void> writeSelectedBrandId(int id) async {}

  @override
  Future<void> writeToken(String token) async {
    this.token = token;
  }
}

AuthService _service(Future<http.Response> Function(http.Request) handler) {
  return AuthService(
    client: MockClient(handler),
    storage: _MemoryStorage(),
  );
}

void main() {
  test('recommendation API endpoints, query params and auth headers', () async {
    final requests = <http.Request>[];
    final service = _service((request) async {
      requests.add(request);
      if (request.method == 'POST') {
        return http.Response(jsonEncode({'data': {'status': 'saved'}}), 200);
      }
      return http.Response(jsonEncode({'data': <dynamic>[]}), 200);
    });

    await service.fetchTrendRecommendations(
      15,
      status: 'generated',
      classification: 'direct',
      platform: 'tiktok',
      format: 'short_video',
      minScore: 80,
    );
    await service.updateRecommendationStatus(15, 42, 'saved');
    await service.updateRecommendationStatus(15, 42, 'used');
    await service.submitRecommendationFeedback(15, 42, 'relevant');

    expect(requests, hasLength(4));
    // 1. Fetch query parameters
    expect(requests[0].url.path, '/api/v1/brands/15/recommendations');
    expect(requests[0].url.queryParameters['status'], 'generated');
    expect(requests[0].url.queryParameters['classification'], 'direct');
    expect(requests[0].url.queryParameters['platform'], 'tiktok');
    expect(requests[0].url.queryParameters['format'], 'short_video');
    expect(requests[0].url.queryParameters['min_score'], '80');
    expect(requests[0].headers['authorization'], 'Bearer test-bearer-token');

    // 2. Status update (saved)
    expect(requests[1].url.path, '/api/v1/brands/15/recommendations/42/status');
    expect(jsonDecode(requests[1].body)['status'], 'saved');

    // 3. Status update (used)
    expect(requests[2].url.path, '/api/v1/brands/15/recommendations/42/status');
    expect(jsonDecode(requests[2].body)['status'], 'used');

    // 4. Feedback
    expect(requests[3].url.path, '/api/v1/brands/15/recommendations/42/feedback');
    expect(jsonDecode(requests[3].body)['feedback'], 'relevant');
  });

  test('null and empty recommendation responses are safely handled without throwing', () async {
    final service = _service((request) async {
      return http.Response('{}', 200);
    });

    final results = await service.fetchTrendRecommendations(9);
    expect(results, isEmpty);
  });

  testWidgets('trend screen renders brand recommendations with hooks, angle, score, and actions', (tester) async {
    final recommendationItem = {
      'id': 101,
      'brand_id': 1,
      'score': 94,
      'relevance_classification': 'direct',
      'suggested_format': 'short_video',
      'target_platform': 'cross_platform',
      'status': 'generated',
      'topic': {
        'id': 501,
        'title': 'Autonomous Cloud Deployment Workflows 2026',
      },
      'content_package': {
        'recommended_angle': 'Why autonomous deployments will replace manual pipelines.',
        'alternative_hooks': [
          'Hook A: 3 steps to zero downtime',
          'Hook B: Stop manual pushes',
          'Hook C: The deployment shift',
        ],
        'draft_caption': 'The shift toward autonomous deployments is here.',
      },
    };

    final service = _service((request) async {
      final path = request.url.path;
      if (path == '/api/v1/brands') {
        return http.Response(jsonEncode({'data': [{'id': 1, 'name': 'DevPulse AI'}]}), 200);
      }
      if (path == '/api/v1/brands/1/recommendations') {
        return http.Response(jsonEncode({'data': [recommendationItem]}), 200);
      }
      if (path == '/api/v1/brands/1/trends') {
        return http.Response(jsonEncode({'data': <dynamic>[]}), 200);
      }
      if (path == '/api/v1/brands/1/trends/sources') {
        return http.Response(jsonEncode({'data': [{'key': 'youtube', 'name': 'YouTube', 'connection_status': 'ready'}]}), 200);
      }
      if (path == '/api/v1/brands/1/trends/status') {
        return http.Response(jsonEncode({'data': {'status': 'completed', 'result_state': 'success'}}), 200);
      }
      return http.Response('{}', 200);
    });

    await tester.pumpWidget(MaterialApp(home: TrendIntelligenceScreen(service: service)));
    await tester.pumpAndSettle();

    await tester.scrollUntilVisible(
      find.text('Brand Content Recommendations'),
      500,
      scrollable: find.byType(Scrollable).first,
    );

    // Verify Brand Recommendations header
    expect(find.text('Brand Content Recommendations'), findsOneWidget);
    expect(find.text('1 Tailored'), findsOneWidget);

    // Verify Score & Classification chip
    expect(find.text('DIRECT · 94%'), findsOneWidget);
    expect(find.text('short_video'), findsOneWidget);

    // Verify Topic title and Recommended Angle
    expect(find.text('Autonomous Cloud Deployment Workflows 2026'), findsOneWidget);
    expect(find.text('Angle: Why autonomous deployments will replace manual pipelines.'), findsOneWidget);

    // Verify action buttons
    expect(find.widgetWithText(TextButton, 'Save'), findsOneWidget);
    expect(find.widgetWithText(TextButton, 'Dismiss'), findsOneWidget);
  });

  testWidgets('tapping save on recommendation triggers status update API call', (tester) async {
    final requests = <http.Request>[];
    final recommendationItem = {
      'id': 202,
      'brand_id': 1,
      'score': 88,
      'relevance_classification': 'direct',
      'suggested_format': 'carousel',
      'status': 'generated',
      'topic': {'id': 601, 'title': 'Ultralight Pack Weight Shifts'},
      'content_package': {
        'recommended_angle': 'Cutting pack weight under 9 lbs.',
      },
    };

    final service = _service((request) async {
      requests.add(request);
      final path = request.url.path;
      if (path == '/api/v1/brands') {
        return http.Response(jsonEncode({'data': [{'id': 1, 'name': 'Summit Peak Gear'}]}), 200);
      }
      if (path == '/api/v1/brands/1/recommendations') {
        return http.Response(jsonEncode({'data': [recommendationItem]}), 200);
      }
      if (path == '/api/v1/brands/1/recommendations/202/status') {
        return http.Response(jsonEncode({'data': {'status': 'saved'}}), 200);
      }
      if (path == '/api/v1/brands/1/trends') {
        return http.Response(jsonEncode({'data': <dynamic>[]}), 200);
      }
      if (path == '/api/v1/brands/1/trends/sources') {
        return http.Response(jsonEncode({'data': []}), 200);
      }
      if (path == '/api/v1/brands/1/trends/status') {
        return http.Response(jsonEncode({'data': null}), 200);
      }
      return http.Response('{}', 200);
    });

    await tester.pumpWidget(MaterialApp(home: TrendIntelligenceScreen(service: service)));
    await tester.pumpAndSettle();

    final saveButton = find.widgetWithText(TextButton, 'Save');
    await tester.scrollUntilVisible(
      saveButton,
      500,
      scrollable: find.byType(Scrollable).first,
    );

    // Tap Save button
    await tester.tap(saveButton);
    await tester.pumpAndSettle();

    // Verify POST status API request was dispatched
    final statusRequests = requests.where((r) => r.url.path == '/api/v1/brands/1/recommendations/202/status').toList();
    expect(statusRequests, hasLength(1));
    expect(jsonDecode(statusRequests[0].body)['status'], 'saved');
  });

  testWidgets('tapping dismiss on recommendation triggers status update API call', (tester) async {
    final requests = <http.Request>[];
    final recommendationItem = {
      'id': 303,
      'brand_id': 1,
      'score': 55,
      'relevance_classification': 'adjacent',
      'suggested_format': 'short_video',
      'status': 'generated',
      'topic': {'id': 701, 'title': 'Tangential Trend'},
      'content_package': {'recommended_angle': 'Adjacent angle.'},
    };

    final service = _service((request) async {
      requests.add(request);
      final path = request.url.path;
      if (path == '/api/v1/brands') {
        return http.Response(jsonEncode({'data': [{'id': 1, 'name': 'Summit Peak Gear'}]}), 200);
      }
      if (path == '/api/v1/brands/1/recommendations') {
        return http.Response(jsonEncode({'data': [recommendationItem]}), 200);
      }
      if (path == '/api/v1/brands/1/recommendations/303/status') {
        return http.Response(jsonEncode({'data': {'status': 'dismissed'}}), 200);
      }
      if (path == '/api/v1/brands/1/trends') {
        return http.Response(jsonEncode({'data': <dynamic>[]}), 200);
      }
      if (path == '/api/v1/brands/1/trends/sources') {
        return http.Response(jsonEncode({'data': []}), 200);
      }
      if (path == '/api/v1/brands/1/trends/status') {
        return http.Response(jsonEncode({'data': null}), 200);
      }
      return http.Response('{}', 200);
    });

    await tester.pumpWidget(MaterialApp(home: TrendIntelligenceScreen(service: service)));
    await tester.pumpAndSettle();

    final dismissButton = find.widgetWithText(TextButton, 'Dismiss');
    await tester.scrollUntilVisible(
      dismissButton,
      500,
      scrollable: find.byType(Scrollable).first,
    );

    // Tap Dismiss button
    await tester.tap(dismissButton);
    await tester.pumpAndSettle();

    // Verify POST status API request was dispatched with dismissed
    final dismissRequests = requests.where((r) => r.url.path == '/api/v1/brands/1/recommendations/303/status').toList();
    expect(dismissRequests, hasLength(1));
    expect(jsonDecode(dismissRequests[0].body)['status'], 'dismissed');
  });
}
