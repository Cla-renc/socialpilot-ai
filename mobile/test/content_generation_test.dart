import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:socialpilot_ai/main.dart';

// ─────────────────────────────────────────────────────────────────────────────
// Phase 6 — Content Generation Flutter Tests
// ─────────────────────────────────────────────────────────────────────────────

void main() {
  // ── AuthService API method tests ──────────────────────────────────────────

  test(
    'generateContent sends correct payload and returns draft map',
    () async {
      final requests = <http.Request>[];
      final service = _service((req) async {
        requests.add(req);
        return http.Response(
          jsonEncode({
            'message': 'Content generated',
            'data': {'id': 1, 'status': 'completed'},
            'draft': {
              'id': 42,
              'title': 'Mindset Monday',
              'hook': 'What if one habit changed everything?',
              'body': 'Consistency compounds...',
              'caption': 'Short caption here',
              'call_to_action': 'Follow for more.',
              'hashtags': ['#mindset', '#habits'],
              'quality_score': 82,
              'quality_evaluation': null,
            },
          }),
          201,
        );
      });

      final draft = await service.generateContent(
        brandId: 5,
        platform: 'linkedin',
        objective: 'brand_authority',
        category: 'educational',
        trendId: 12,
        instructions: 'Focus on discipline themes',
      );

      expect(requests, hasLength(1));
      final req = requests.first;
      expect(req.method, 'POST');
      expect(req.url.path, '/api/v1/brands/5/content/generate');
      expect(req.headers['authorization'], 'Bearer test-token');

      final body = jsonDecode(req.body) as Map;
      expect(body['platform'],     'linkedin');
      expect(body['objective'],    'brand_authority');
      expect(body['category'],     'educational');
      expect(body['trend_id'],     12);
      expect(body['instructions'], 'Focus on discipline themes');
      expect(body['sync'],         true);

      expect(draft['id'],    42);
      expect(draft['title'], 'Mindset Monday');
      expect(draft['quality_score'], 82);
    },
  );

  test(
    'fetchContentDrafts sends brand-scoped request with filters',
    () async {
      final requests = <http.Request>[];
      final service = _service((req) async {
        requests.add(req);
        return http.Response(jsonEncode({'data': <dynamic>[]}), 200);
      });

      await service.fetchContentDrafts(
        7,
        platform: 'instagram',
        search: 'morning',
        category: 'motivational',
      );

      expect(requests, hasLength(1));
      final req = requests.first;
      expect(req.url.path, '/api/v1/brands/7/content');
      expect(req.url.queryParameters['platform'],  'instagram');
      expect(req.url.queryParameters['search'],    'morning');
      expect(req.url.queryParameters['category'],  'motivational');
      expect(req.headers['authorization'], 'Bearer test-token');
    },
  );

  test(
    'fetchContentDraft returns a single draft map',
    () async {
      final service = _service((req) async {
        return http.Response(
          jsonEncode({
            'data': {
              'id': 99,
              'title': 'Single draft',
              'body': 'Body text.',
              'hook': 'Hook here',
              'platform': 'tiktok',
              'quality_score': 71,
            },
          }),
          200,
        );
      });

      final draft = await service.fetchContentDraft(99);
      expect(draft['id'],    99);
      expect(draft['title'], 'Single draft');
      expect(draft['platform'], 'tiktok');
    },
  );

  test(
    'updateContentDraft sends PUT with payload and returns updated draft',
    () async {
      final requests = <http.Request>[];
      final service = _service((req) async {
        requests.add(req);
        return http.Response(
          jsonEncode({'data': {'id': 10, 'title': 'Updated title', 'body': 'Updated body'}}),
          200,
        );
      });

      final result = await service.updateContentDraft(10, {
        'title': 'Updated title',
        'body':  'Updated body',
        'hook':  'New hook',
      });

      expect(requests.first.method, 'PUT');
      expect(requests.first.url.path, '/api/v1/content/10');
      final body = jsonDecode(requests.first.body) as Map;
      expect(body['title'], 'Updated title');
      expect(result['id'], 10);
    },
  );

  test(
    'deleteContentDraft sends DELETE request',
    () async {
      final requests = <http.Request>[];
      final service = _service((req) async {
        requests.add(req);
        return http.Response(jsonEncode({'message': 'Deleted'}), 200);
      });

      await service.deleteContentDraft(55);

      expect(requests.first.method, 'DELETE');
      expect(requests.first.url.path, '/api/v1/content/55');
    },
  );

  test(
    'improveContent sends operation and returns updated data',
    () async {
      final requests = <http.Request>[];
      final service = _service((req) async {
        requests.add(req);
        return http.Response(
          jsonEncode({
            'data': {
              'id': 20,
              'body': 'Shorter body.',
              'hook': 'Tighter hook.',
            },
          }),
          200,
        );
      });

      final result = await service.improveContent(20, operation: 'shorten');

      expect(requests.first.method, 'POST');
      expect(requests.first.url.path, '/api/v1/content/20/improve');
      final body = jsonDecode(requests.first.body) as Map;
      expect(body['operation'], 'shorten');
      expect(result['data']['body'], 'Shorter body.');
    },
  );

  test(
    'generateContentVariations sends count and returns list',
    () async {
      final requests = <http.Request>[];
      final service = _service((req) async {
        requests.add(req);
        return http.Response(
          jsonEncode({
            'data': [
              {'id': 1, 'style': 'professional', 'body': 'Professional body.'},
              {'id': 2, 'style': 'conversational', 'body': 'Conversational body.'},
            ],
          }),
          201,
        );
      });

      final variations = await service.generateContentVariations(30, 2);

      expect(requests.first.url.path, '/api/v1/content/30/variations');
      final body = jsonDecode(requests.first.body) as Map;
      expect(body['count'], 2);
      expect(variations.length, 2);
      expect(variations.first['style'], 'professional');
    },
  );

  test(
    'selectContentVariation sends POST to select endpoint',
    () async {
      final requests = <http.Request>[];
      final service = _service((req) async {
        requests.add(req);
        return http.Response(jsonEncode({'message': 'Selected'}), 200);
      });

      await service.selectContentVariation(30, 1);

      expect(requests.first.method, 'POST');
      expect(requests.first.url.path, '/api/v1/content/30/variations/1/select');
    },
  );

  // ── Widget tests ──────────────────────────────────────────────────────────

  testWidgets(
    'ContentScreen shows generate tab with platform dropdown',
    (tester) async {
      final service = _service(_brandAndDraftsHandler);

      await tester.pumpWidget(
        MaterialApp(home: ContentScreen(service: service)),
      );
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      // Should show Content Studio title
      expect(find.text('Content Studio'), findsOneWidget);

      // Should show Generate tab
      expect(find.text('Generate'), findsOneWidget);
      expect(find.text('Library'), findsOneWidget);
    },
  );

  testWidgets(
    'ContentLibraryTab shows draft list and allows navigation',
    (tester) async {
      final service = _service((req) async {
        if (req.url.path.contains('/content')) {
          return http.Response(
            jsonEncode({
              'data': [
                {
                  'id': 1,
                  'title': 'Morning Mindset Post',
                  'body': 'Start your day strong.',
                  'hook': 'What if mornings changed everything?',
                  'platform': 'linkedin',
                  'quality_score': 88,
                  'status': 'draft',
                },
              ],
            }),
            200,
          );
        }
        if (req.url.path.contains('/trends')) {
          return http.Response(jsonEncode({'data': <dynamic>[]}), 200);
        }
        // brands list
        return http.Response(
          jsonEncode({
            'data': [
              {'id': 1, 'name': 'Apex Habits', 'status': 'active'},
            ],
          }),
          200,
        );
      });

      await tester.pumpWidget(
        MaterialApp(
          home: ContentScreen(service: service),
        ),
      );
      await tester.pump();
      await tester.pump(const Duration(milliseconds: 100));

      // Switch to Library tab
      await tester.tap(find.text('Library'));
      await tester.pumpAndSettle();

      expect(find.text('Morning Mindset Post'), findsOneWidget);
    },
  );

  testWidgets(
    'ContentGenerateTab shows generation form with required fields',
    (tester) async {
      final brand = Brand(
        id: 1, name: 'Apex Habits', status: 'active',
        logoUrl: null, industry: null, niche: null,
        description: null, audience: null, region: null, language: null,
        tone: null,
      );

      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: ContentGenerateTab(
              brand: brand,
              service: _service(_brandAndDraftsHandler),
            ),
          ),
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('Platform'), findsOneWidget);
      expect(find.text('Objective'), findsOneWidget);
      expect(find.text('Generate content'), findsOneWidget);
    },
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Test helpers
// ─────────────────────────────────────────────────────────────────────────────

typedef _Handler = Future<http.Response> Function(http.Request);

AuthService _service(_Handler handler) {
  return AuthService(
    client: MockClient(handler),
    storage: _MemoryStorage(),
  );
}

Future<http.Response> _brandAndDraftsHandler(http.Request req) async {
  if (req.url.path.contains('/content') || req.url.path.contains('/trends')) {
    return http.Response(jsonEncode({'data': <dynamic>[]}), 200);
  }
  return http.Response(
    jsonEncode({
      'data': [
        {'id': 1, 'name': 'Apex Habits', 'status': 'active'},
      ],
    }),
    200,
  );
}

class _MemoryStorage implements AppStorage {
  String? token = 'test-token';

  @override Future<void> clearSelectedBrandId() async {}
  @override Future<void> clearToken() async { token = null; }
  @override Future<int?> readSelectedBrandId() async => null;
  @override Future<String?> readToken() async => token;
  @override Future<void> writeSelectedBrandId(int id) async {}
  @override Future<void> writeToken(String t) async { token = t; }
}


