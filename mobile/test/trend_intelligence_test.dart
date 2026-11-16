import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:socialpilot_ai/main.dart';

void main() {
  test(
    'trend API requests are authenticated, brand-filtered, and actionable',
    () async {
      final requests = <http.Request>[];
      final service = _service((request) async {
        requests.add(request);
        if (request.method == 'POST') {
          return http.Response(
            jsonEncode({
              'data': {'id': 8, 'status': 'queued'},
            }),
            202,
          );
        }
        return http.Response(jsonEncode({'data': <dynamic>[]}), 200);
      });

      await service.fetchTrendTopics(
        12,
        category: 'educational',
        minRelevance: 75,
        geographicMarket: 'Kenya',
        from: '2026-11-01',
        sort: 'relevance',
      );
      await service.startTrendResearch(12, days: 7, sources: ['rss']);
      await service.submitTrendFeedback(12, 4, feedback: 'saved');

      expect(requests, hasLength(3));
      expect(requests[0].url.path, '/api/v1/brands/12/trends');
      expect(requests[0].url.queryParameters['category'], 'educational');
      expect(requests[0].url.queryParameters['min_relevance'], '75');
      expect(requests[0].url.queryParameters['geographic_market'], 'Kenya');
      expect(requests[0].url.queryParameters['from'], '2026-11-01');
      expect(requests[0].url.queryParameters['sort'], 'relevance');
      expect(requests[1].url.path, '/api/v1/brands/12/trends/research');
      expect(jsonDecode(requests[1].body)['days'], 7);
      expect(requests[2].url.path, '/api/v1/brands/12/trends/4/feedback');
      for (final request in requests) {
        expect(request.headers['authorization'], 'Bearer trend-test-token');
      }
    },
  );

  testWidgets('trend screen loads brand evidence and switches brands', (
    tester,
  ) async {
    final service = _service(_response);
    await tester.pumpWidget(
      MaterialApp(home: TrendIntelligenceScreen(service: service)),
    );
    await tester.pumpAndSettle();

    expect(find.text('Configured RSS feeds'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('Trail safety update'),
      500,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('Trail safety update'), findsOneWidget);
    await tester.drag(find.byType(Scrollable).first, const Offset(0, 1000));
    await tester.pumpAndSettle();
    expect(find.text('Configured RSS feeds'), findsOneWidget);
    final brandDropdown = find.byType(DropdownButtonFormField<int>).first;
    await tester.ensureVisible(brandDropdown);
    await tester.tap(brandDropdown);
    await tester.pumpAndSettle();
    await tester.tap(find.text('Second brand').last);
    await tester.pumpAndSettle();

    await tester.scrollUntilVisible(
      find.text('Outdoor equipment launch'),
      500,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('Outdoor equipment launch'), findsOneWidget);
    expect(find.text('Trail safety update'), findsNothing);
  });

  testWidgets('mobile starts research and persists saved-topic feedback', (
    tester,
  ) async {
    final requests = <http.Request>[];
    final service = _service((request) async {
      requests.add(request);
      return _response(request);
    });
    await tester.pumpWidget(
      MaterialApp(home: TrendIntelligenceScreen(service: service)),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Start research'));
    await tester.pumpAndSettle();
    expect(
      requests.any(
        (request) =>
            request.method == 'POST' &&
            request.url.path.endsWith('/trends/research'),
      ),
      isTrue,
    );

    await tester.scrollUntilVisible(
      find.text('Trail safety update'),
      500,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.text('Trail safety update'));
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(OutlinedButton, 'Save'));
    await tester.pumpAndSettle();
    expect(
      requests.any(
        (request) =>
            request.method == 'POST' &&
            request.url.path.endsWith('/trends/4/feedback') &&
            jsonDecode(request.body)['feedback'] == 'saved',
      ),
      isTrue,
    );
  });

  testWidgets('trend screen offers retry on API failure', (tester) async {
    var failed = false;
    final service = _service((request) async {
      if (!failed && request.url.path.endsWith('/api/v1/brands')) {
        failed = true;
        return http.Response(
          jsonEncode({'message': 'Database details should not leak'}),
          503,
        );
      }
      return _response(request);
    });
    await tester.pumpWidget(
      MaterialApp(home: TrendIntelligenceScreen(service: service)),
    );
    await tester.pumpAndSettle();

    expect(
      find.text('SocialPilot is temporarily unavailable. Please try again.'),
      findsOneWidget,
    );
    await tester.tap(find.text('Retry'));
    await tester.pumpAndSettle();
    await tester.scrollUntilVisible(
      find.text('Trail safety update'),
      500,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('Trail safety update'), findsOneWidget);
  });

  testWidgets(
    'trend screen displays multi-source provider status and topic details',
    (tester) async {
      final service = _service((request) async {
        if (request.url.path == '/api/v1/brands/1/trends/sources') {
          return http.Response(
            jsonEncode({
              'data': [
                {
                  'key': 'rss',
                  'name': 'Configured RSS feeds',
                  'connection_status': 'ready',
                },
                {
                  'key': 'news_api',
                  'name': 'News API',
                  'connection_status': 'configuration_required',
                },
                {
                  'key': 'reddit',
                  'name': 'Reddit API',
                  'connection_status': 'configuration_required',
                },
                {
                  'key': 'youtube',
                  'name': 'YouTube Data API',
                  'connection_status': 'configuration_required',
                },
                {
                  'key': 'google_trends',
                  'name': 'Google Trends',
                  'connection_status': 'ready',
                },
              ],
            }),
            200,
          );
        }
        return _response(request);
      });

      await tester.pumpWidget(
        MaterialApp(home: TrendIntelligenceScreen(service: service)),
      );
      await tester.pumpAndSettle();

      // Verified multi-source chips and readiness
      expect(find.text('Configured RSS feeds'), findsOneWidget);
      expect(find.text('Google Trends'), findsOneWidget);
      expect(find.text('News API'), findsOneWidget);
      expect(find.text('Reddit API'), findsOneWidget);
      expect(find.text('YouTube Data API'), findsOneWidget);
    },
  );
}

AuthService _service(Future<http.Response> Function(http.Request) handler) {
  return AuthService(client: MockClient(handler), storage: _MemoryStorage());
}

Future<http.Response> _response(http.Request request) async {
  final path = request.url.path;
  final data = switch (path) {
    '/api/v1/brands' => [
      {'id': 1, 'name': 'Trail brand', 'status': 'active', 'region': 'Kenya'},
      {'id': 2, 'name': 'Second brand', 'status': 'active'},
    ],
    '/api/v1/brands/1/trends' => [_topic(4, 'Trail safety update')],
    '/api/v1/brands/2/trends' => [_topic(5, 'Outdoor equipment launch')],
    '/api/v1/brands/1/trends/sources' => [
      {
        'key': 'rss',
        'name': 'Configured RSS feeds',
        'connection_status': 'ready',
      },
    ],
    '/api/v1/brands/2/trends/sources' => <dynamic>[],
    '/api/v1/brands/1/trends/status' ||
    '/api/v1/brands/2/trends/status' => null,
    '/api/v1/brands/1/trends/research' => {'id': 3, 'status': 'queued'},
    _ => <dynamic>[],
  };
  if (request.method == 'POST' && path.endsWith('/feedback')) {
    return http.Response(
      jsonEncode({
        'data': {'feedback_type': jsonDecode(request.body)['feedback']},
      }),
      200,
    );
  }
  return http.Response(
    jsonEncode({'data': data}),
    path.endsWith('/research') ? 202 : 200,
  );
}

Map<String, dynamic> _topic(int id, String title) => {
  'id': id,
  'brand_id': id == 4 ? 1 : 2,
  'title': title,
  'description': 'A source-backed description.',
  'summary': 'A short source-backed summary.',
  'category': 'educational',
  'relevance_score': 82,
  'relevance_reason': 'Matches saved brand keywords.',
  'opportunity_score': 71,
  'momentum': null,
  'source_count': 1,
  'sources': [
    {
      'source_key': 'rss',
      'source_name': 'Example News',
      'title': title,
      'url': 'https://example.test/story',
      'evidence': 'Observed source excerpt.',
    },
  ],
};

class _MemoryStorage implements AppStorage {
  String? token = 'trend-test-token';

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


