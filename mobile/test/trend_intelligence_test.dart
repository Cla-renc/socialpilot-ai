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
      await service.fetchTrendRecommendations(12, status: 'saved', minScore: 80);
      await service.updateRecommendationStatus(12, 5, 'saved');
      await service.submitRecommendationFeedback(12, 5, 'relevant');

      expect(requests, hasLength(6));
      expect(requests[0].url.path, '/api/v1/brands/12/trends');
      expect(requests[0].url.queryParameters['category'], 'educational');
      expect(requests[0].url.queryParameters['min_relevance'], '75');
      expect(requests[0].url.queryParameters['geographic_market'], 'Kenya');
      expect(requests[0].url.queryParameters['from'], '2026-11-01');
      expect(requests[0].url.queryParameters['sort'], 'relevance');
      expect(requests[1].url.path, '/api/v1/brands/12/trends/research');
      expect(jsonDecode(requests[1].body)['days'], 7);
      expect(requests[2].url.path, '/api/v1/brands/12/trends/4/feedback');
      expect(requests[3].url.path, '/api/v1/brands/12/recommendations');
      expect(requests[3].url.queryParameters['status'], 'saved');
      expect(requests[3].url.queryParameters['min_score'], '80');
      expect(requests[4].url.path, '/api/v1/brands/12/recommendations/5/status');
      expect(jsonDecode(requests[4].body)['status'], 'saved');
      expect(requests[5].url.path, '/api/v1/brands/12/recommendations/5/feedback');
      expect(jsonDecode(requests[5].body)['feedback'], 'relevant');
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

  // =========================================================================
  // STAGE 5.6.37 — D5 RELATIVE DATE FORMATTING TESTS
  // =========================================================================

  test('formatPublishedAt produces expected relative time strings', () {
    final fixedClock = DateTime.utc(2026, 11, 26, 12, 0, 0);

    // Just now (< 45s)
    expect(
      formatPublishedAt('2026-11-26T11:59:30Z', clock: fixedClock),
      'Just now',
    );
    // Future date avoids negative relative time
    expect(
      formatPublishedAt('2026-11-26T12:05:00Z', clock: fixedClock),
      'Just now',
    );

    // Minutes ago (15 minutes)
    expect(
      formatPublishedAt('2026-11-26T11:45:00Z', clock: fixedClock),
      '15 minutes ago',
    );
    expect(
      formatPublishedAt('2026-11-26T11:59:00Z', clock: fixedClock),
      '1 minute ago',
    );

    // Hours ago (3 hours)
    expect(
      formatPublishedAt('2026-11-26T09:00:00Z', clock: fixedClock),
      '3 hours ago',
    );
    expect(
      formatPublishedAt('2026-11-26T11:00:00Z', clock: fixedClock),
      '1 hour ago',
    );

    // Yesterday (1 day ago)
    expect(
      formatPublishedAt('2026-11-25T11:00:00Z', clock: fixedClock),
      'Yesterday',
    );

    // Days ago (4 days)
    expect(
      formatPublishedAt('2026-11-22T10:00:00Z', clock: fixedClock),
      '4 days ago',
    );

    // Weeks ago
    expect(
      formatPublishedAt('2026-11-12T10:00:00Z', clock: fixedClock),
      '2 weeks ago',
    );

    // Months ago
    expect(
      formatPublishedAt('2026-09-26T10:00:00Z', clock: fixedClock),
      '2 months ago',
    );

    // Timezone offset handling (+03:00)
    // 14:00+03:00 is 11:00 UTC -> exactly 1 hour before 12:00 UTC
    expect(
      formatPublishedAt('2026-11-26T14:00:00+03:00', clock: fixedClock),
      '1 hour ago',
    );

    // Graceful null and invalid handling
    expect(formatPublishedAt(null), 'Publication date unavailable');
    expect(formatPublishedAt(''), 'Publication date unavailable');
    expect(formatPublishedAt('not-a-date'), 'not-a-date');
  });

  testWidgets('TrendDetailScreen renders relative date and tooltip with raw date', (
    tester,
  ) async {
    final now = DateTime.now().toUtc();
    final publishedIso = now.subtract(const Duration(hours: 3)).toIso8601String();
    final topicWithDate = {
      'id': 10,
      'title': 'Solar panel recycling',
      'summary': 'Summary of topic',
      'relevance_score': 80,
      'opportunity_score': 75,
      'sources': [
        {
          'source_key': 'rss',
          'source_name': 'Green Energy News',
          'title': 'Solar recycling advances',
          'url': 'https://example.test/solar',
          'published_at': publishedIso,
        },
      ],
    };

    final service = _service((_) async => http.Response('{}', 200));

    await tester.pumpWidget(
      MaterialApp(
        home: TrendDetailScreen(
          service: service,
          brandId: 1,
          topic: topicWithDate,
          onChanged: () async {},
        ),
      ),
    );
    await tester.pumpAndSettle();

    // Check that relative time is displayed instead of raw ISO string
    expect(find.text('3 hours ago'), findsOneWidget);
    expect(find.text(publishedIso), findsNothing);

    // Verify raw timestamp is preserved in Tooltip
    final tooltip = tester.widget<Tooltip>(find.byType(Tooltip).first);
    expect(tooltip.message, publishedIso);
  });

  // =========================================================================
  // STAGE 5.6.37 — D6 RESEARCH EMPTY STATES TESTS
  // =========================================================================

  testWidgets('trend screen displays no_relevant_topics empty state', (tester) async {
    final service = _service((request) async {
      final path = request.url.path;
      if (path == '/api/v1/brands') {
        return http.Response(jsonEncode({'data': [{'id': 1, 'name': 'Test brand'}]}), 200);
      }
      if (path == '/api/v1/brands/1/trends') {
        return http.Response(jsonEncode({'data': <dynamic>[]}), 200);
      }
      if (path == '/api/v1/brands/1/trends/sources') {
        return http.Response(jsonEncode({'data': [{'key': 'rss', 'name': 'RSS', 'connection_status': 'ready'}]}), 200);
      }
      if (path == '/api/v1/brands/1/trends/status') {
        return http.Response(jsonEncode({
          'data': {
            'status': 'completed',
            'result_state': 'no_relevant_topics',
            'result_meta': {'filtered_out_count': 12, 'min_relevance_score': 25},
          },
        }), 200);
      }
      return http.Response('{}', 200);
    });

    await tester.pumpWidget(MaterialApp(home: TrendIntelligenceScreen(service: service)));
    await tester.pumpAndSettle();

    await tester.scrollUntilVisible(
      find.text('No relevant topics found'),
      500,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('No relevant topics found'), findsOneWidget);
    expect(find.textContaining('no topics met your brand relevance threshold'), findsOneWidget);
  });

  testWidgets('trend screen displays all_sources_unavailable empty state with retry', (tester) async {
    final service = _service((request) async {
      final path = request.url.path;
      if (path == '/api/v1/brands') {
        return http.Response(jsonEncode({'data': [{'id': 1, 'name': 'Test brand'}]}), 200);
      }
      if (path == '/api/v1/brands/1/trends') {
        return http.Response(jsonEncode({'data': <dynamic>[]}), 200);
      }
      if (path == '/api/v1/brands/1/trends/sources') {
        return http.Response(jsonEncode({'data': [{'key': 'rss', 'name': 'RSS', 'connection_status': 'ready'}]}), 200);
      }
      if (path == '/api/v1/brands/1/trends/status') {
        return http.Response(jsonEncode({
          'data': {
            'status': 'failed',
            'result_state': 'all_sources_unavailable',
            'error_message': 'Feed timeout',
          },
        }), 200);
      }
      return http.Response('{}', 200);
    });

    await tester.pumpWidget(MaterialApp(home: TrendIntelligenceScreen(service: service)));
    await tester.pumpAndSettle();

    await tester.scrollUntilVisible(
      find.text('All research sources unavailable'),
      500,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('All research sources unavailable'), findsOneWidget);
    expect(find.textContaining('All research sources are currently unavailable'), findsOneWidget);
    expect(find.widgetWithText(OutlinedButton, 'Retry research'), findsOneWidget);
  });

  testWidgets('trend screen displays configuration_required empty state', (tester) async {
    final service = _service((request) async {
      final path = request.url.path;
      if (path == '/api/v1/brands') {
        return http.Response(jsonEncode({'data': [{'id': 1, 'name': 'Test brand'}]}), 200);
      }
      if (path == '/api/v1/brands/1/trends') {
        return http.Response(jsonEncode({'data': <dynamic>[]}), 200);
      }
      if (path == '/api/v1/brands/1/trends/sources') {
        return http.Response(jsonEncode({'data': [{'key': 'rss', 'name': 'RSS', 'connection_status': 'configuration_required'}]}), 200);
      }
      if (path == '/api/v1/brands/1/trends/status') {
        return http.Response(jsonEncode({
          'data': {
            'status': 'completed',
            'result_state': 'configuration_required',
          },
        }), 200);
      }
      return http.Response('{}', 200);
    });

    await tester.pumpWidget(MaterialApp(home: TrendIntelligenceScreen(service: service)));
    await tester.pumpAndSettle();

    await tester.scrollUntilVisible(
      find.text('Configuration required'),
      500,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('Configuration required'), findsOneWidget);
    expect(find.textContaining('Research sources need configuration before research can run'), findsOneWidget);
  });

  testWidgets('trend screen displays partial research state with no topics', (tester) async {
    final service = _service((request) async {
      final path = request.url.path;
      if (path == '/api/v1/brands') {
        return http.Response(jsonEncode({'data': [{'id': 1, 'name': 'Test brand'}]}), 200);
      }
      if (path == '/api/v1/brands/1/trends') {
        return http.Response(jsonEncode({'data': <dynamic>[]}), 200);
      }
      if (path == '/api/v1/brands/1/trends/sources') {
        return http.Response(jsonEncode({'data': [{'key': 'rss', 'name': 'RSS', 'connection_status': 'ready'}]}), 200);
      }
      if (path == '/api/v1/brands/1/trends/status') {
        return http.Response(jsonEncode({
          'data': {
            'status': 'partial',
            'result_state': 'partial',
          },
        }), 200);
      }
      return http.Response('{}', 200);
    });

    await tester.pumpWidget(MaterialApp(home: TrendIntelligenceScreen(service: service)));
    await tester.pumpAndSettle();

    await tester.scrollUntilVisible(
      find.text('Partial research: no topics found'),
      500,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('Partial research: no topics found'), findsOneWidget);
    expect(find.widgetWithText(OutlinedButton, 'Retry research'), findsOneWidget);
  });

  testWidgets('trend screen displays partial research notice when topics are available', (tester) async {
    final service = _service((request) async {
      final path = request.url.path;
      if (path == '/api/v1/brands') {
        return http.Response(jsonEncode({'data': [{'id': 1, 'name': 'Test brand'}]}), 200);
      }
      if (path == '/api/v1/brands/1/trends') {
        return http.Response(jsonEncode({'data': [_topic(4, 'Trail safety update')]}), 200);
      }
      if (path == '/api/v1/brands/1/trends/sources') {
        return http.Response(jsonEncode({'data': [{'key': 'rss', 'name': 'RSS', 'connection_status': 'ready'}]}), 200);
      }
      if (path == '/api/v1/brands/1/trends/status') {
        return http.Response(jsonEncode({
          'data': {
            'status': 'partial',
            'result_state': 'success',
          },
        }), 200);
      }
      return http.Response('{}', 200);
    });

    await tester.pumpWidget(MaterialApp(home: TrendIntelligenceScreen(service: service)));
    await tester.pumpAndSettle();

    // Latest research card shows partial notice
    expect(find.textContaining('Some sources encountered errors, but available topics are displayed below'), findsOneWidget);

    await tester.scrollUntilVisible(
      find.text('Trail safety update'),
      500,
      scrollable: find.byType(Scrollable).first,
    );
    // Topic is still rendered
    expect(find.text('Trail safety update'), findsOneWidget);
  });

  testWidgets('trend screen displays success empty state when filter excludes all topics', (tester) async {
    final service = _service((request) async {
      final path = request.url.path;
      if (path == '/api/v1/brands') {
        return http.Response(jsonEncode({'data': [{'id': 1, 'name': 'Test brand'}]}), 200);
      }
      if (path == '/api/v1/brands/1/trends') {
        return http.Response(jsonEncode({'data': <dynamic>[]}), 200);
      }
      if (path == '/api/v1/brands/1/trends/sources') {
        return http.Response(jsonEncode({'data': [{'key': 'rss', 'name': 'RSS', 'connection_status': 'ready'}]}), 200);
      }
      if (path == '/api/v1/brands/1/trends/status') {
        return http.Response(jsonEncode({
          'data': {
            'status': 'completed',
            'result_state': 'success',
          },
        }), 200);
      }
      return http.Response('{}', 200);
    });

    await tester.pumpWidget(MaterialApp(home: TrendIntelligenceScreen(service: service)));
    await tester.pumpAndSettle();

    await tester.scrollUntilVisible(
      find.text('No topics match current filters'),
      500,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('No topics match current filters'), findsOneWidget);
    expect(find.textContaining('Research completed successfully, but no topics match'), findsOneWidget);
  });

  testWidgets('displays Google Trends limitation message when reported', (
    tester,
  ) async {
    const limitationMsg =
        'Google Trends RSS reflects current search interest only; historical window 7 days cannot be queried directly via RSS.';

    final service = _service((request) async {
      final path = request.url.path;
      if (path == '/api/v1/brands') {
        return http.Response(
          jsonEncode({
            'data': [
              {'id': 1, 'name': 'Trail brand', 'status': 'active'},
            ],
          }),
          200,
        );
      }
      if (path == '/api/v1/brands/1/trends') {
        return http.Response(jsonEncode({'data': [_topic(4, 'Trail safety update')]}), 200);
      }
      if (path == '/api/v1/brands/1/trends/sources') {
        return http.Response(
          jsonEncode({
            'data': [
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
      if (path == '/api/v1/brands/1/trends/status') {
        return http.Response(
          jsonEncode({
            'data': {
              'status': 'completed',
              'result_state': 'success',
              'sources': [
                {
                  'source_key': 'google_trends',
                  'source_name': 'Google Trends',
                  'status': 'completed',
                  'candidate_count': 3,
                  'message': limitationMsg,
                },
              ],
            },
          }),
          200,
        );
      }
      return http.Response('{}', 200);
    });

    await tester.pumpWidget(MaterialApp(home: TrendIntelligenceScreen(service: service)));
    await tester.pumpAndSettle();

    // The limitation message is visible on-screen in the source controls section.
    expect(find.textContaining(limitationMsg), findsWidgets);

    // The latest research card subtitle also carries the prefixed format; scroll to it.
    await tester.scrollUntilVisible(
      find.textContaining('Google Trends: $limitationMsg'),
      500,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.textContaining('Google Trends: $limitationMsg'), findsOneWidget);
  });

  testWidgets('does not display limitation notice when source message is absent or null', (
    tester,
  ) async {
    final service = _service((request) async {
      final path = request.url.path;
      if (path == '/api/v1/brands') {
        return http.Response(
          jsonEncode({
            'data': [
              {'id': 1, 'name': 'Trail brand', 'status': 'active'},
            ],
          }),
          200,
        );
      }
      if (path == '/api/v1/brands/1/trends') {
        return http.Response(jsonEncode({'data': [_topic(4, 'Trail safety update')]}), 200);
      }
      if (path == '/api/v1/brands/1/trends/sources') {
        return http.Response(
          jsonEncode({
            'data': [
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
      if (path == '/api/v1/brands/1/trends/status') {
        return http.Response(
          jsonEncode({
            'data': {
              'status': 'completed',
              'result_state': 'success',
              'sources': [
                {
                  'source_key': 'google_trends',
                  'source_name': 'Google Trends',
                  'status': 'completed',
                  'candidate_count': 3,
                  'message': null,
                },
              ],
            },
          }),
          200,
        );
      }
      return http.Response('{}', 200);
    });

    await tester.pumpWidget(MaterialApp(home: TrendIntelligenceScreen(service: service)));
    await tester.pumpAndSettle();

    expect(find.textContaining('Google Trends RSS reflects current search interest only'), findsNothing);
  });
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


