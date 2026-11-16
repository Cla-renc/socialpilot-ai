import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:socialpilot_ai/main.dart';

// ─────────────────────────────────────────────────────────────────────────────
// Phase 7 — AI Media Generation Flutter Tests
// ─────────────────────────────────────────────────────────────────────────────

void main() {
  test('generateImage sends correct payload and returns asset response', () async {
    final requests = <http.Request>[];
    final service = _service((req) async {
      requests.add(req);
      return http.Response(
        jsonEncode({
          'message': 'Image generation job queued.',
          'job': {'id': 10, 'status': 'queued', 'job_type': 'image_generation'},
          'asset': {
            'id': 42,
            'title': 'Lion Discipline Poster',
            'asset_type': 'image',
            'generation_status': 'queued',
            'platform': 'instagram',
            'aspect_ratio': '1:1',
          },
        }),
        202,
      );
    });

    final res = await service.generateImage(
      brandId: 3,
      objective: 'promotional_poster',
      visualStyle: 'dark_cinematic',
      platform: 'instagram',
      aspectRatio: '1:1',
      title: 'Lion Discipline Poster',
      instructions: 'Dramatic lion silhouette with golden rim light',
    );

    expect(requests, hasLength(1));
    final req = requests.first;
    expect(req.method, 'POST');
    expect(req.url.path, '/api/v1/brands/3/media/images/generate');
    expect(req.headers['authorization'], 'Bearer test-token');

    final body = jsonDecode(req.body) as Map;
    expect(body['objective'], 'promotional_poster');
    expect(body['visual_style'], 'dark_cinematic');
    expect(body['platform'], 'instagram');
    expect(body['aspect_ratio'], '1:1');
    expect(body['title'], 'Lion Discipline Poster');

    expect(res['asset']['id'], 42);
    expect(res['asset']['title'], 'Lion Discipline Poster');
  });

  test('fetchMediaAssets sends brand-scoped request and decodes list', () async {
    final requests = <http.Request>[];
    final service = _service((req) async {
      requests.add(req);
      return http.Response(
        jsonEncode({
          'data': [
            {
              'id': 1,
              'title': 'Morning Discipline',
              'asset_type': 'image',
              'platform': 'instagram',
              'aspect_ratio': '1:1',
              'generation_status': 'completed',
            },
            {
              'id': 2,
              'title': 'Weekly Wisdom Reel',
              'asset_type': 'video',
              'platform': 'tiktok',
              'aspect_ratio': '9:16',
              'generation_status': 'completed',
            },
          ],
        }),
        200,
      );
    });

    final assets = await service.fetchMediaAssets(2, assetType: 'image');

    expect(requests, hasLength(1));
    final req = requests.first;
    expect(req.method, 'GET');
    expect(req.url.path, '/api/v1/brands/2/media');
    expect(req.url.queryParameters['asset_type'], 'image');

    expect(assets, hasLength(2));
    expect(assets[0]['title'], 'Morning Discipline');
    expect(assets[1]['asset_type'], 'video');
  });

  test('generateStoryboard sends correct request and returns scene list', () async {
    final requests = <http.Request>[];
    final service = _service((req) async {
      requests.add(req);
      return http.Response(
        jsonEncode({
          'message': 'Storyboard created.',
          'storyboard': {
            'id': 15,
            'title': 'Discipline Breakdown',
            'platform': 'instagram',
            'aspect_ratio': '9:16',
            'scenes': [
              {
                'scene_number': 1,
                'description': 'Opening hero silhouette',
                'visual_suggestion': 'Macro lens, rim lighting',
                'duration_seconds': 5,
                'transition': 'fade_in',
              },
            ],
          },
        }),
        201,
      );
    });

    final result = await service.generateStoryboard(
      brandId: 1,
      videoIdea: 'A deep dive into morning habits',
      platform: 'instagram',
      aspectRatio: '9:16',
      numScenes: 4,
    );

    expect(requests, hasLength(1));
    final req = requests.first;
    expect(req.method, 'POST');
    expect(req.url.path, '/api/v1/brands/1/media/storyboards/generate');

    final body = jsonDecode(req.body) as Map;
    expect(body['video_idea'], 'A deep dive into morning habits');
    expect(body['num_scenes'], 4);

    expect(result['storyboard']['id'], 15);
  });

  test('getVisualSettings and updateVisualSettings handle brand styling', () async {
    final requests = <http.Request>[];
    final service = _service((req) async {
      requests.add(req);
      if (req.method == 'GET') {
        return http.Response(
          jsonEncode({
            'data': {
              'id': 1,
              'brand_id': 5,
              'visual_identity': 'dark_cinematic',
              'primary_color': '#0d1b2a',
              'accent_color': '#e0a96d',
            },
          }),
          200,
        );
      }
      return http.Response(
        jsonEncode({
          'data': {
            'id': 1,
            'brand_id': 5,
            'visual_identity': 'minimalist',
            'primary_color': '#ffffff',
          },
        }),
        200,
      );
    });

    final settings = await service.getVisualSettings(5);
    expect(settings['visual_identity'], 'dark_cinematic');

    final updated = await service.updateVisualSettings(5, {
      'visual_identity': 'minimalist',
      'primary_color': '#ffffff',
    });
    expect(updated['visual_identity'], 'minimalist');
    expect(requests, hasLength(2));
    expect(requests.last.method, 'PUT');
  });

  testWidgets('MediaStudioScreen renders all 3 tabs and brand selector', (tester) async {
    final service = _service((req) async {
      if (req.url.path.contains('/brands') && !req.url.path.contains('/media')) {
        return http.Response(
          jsonEncode({
            'data': [
              {'id': 1, 'name': 'Bruce Lee Discipline Diaries', 'status': 'active'},
            ],
          }),
          200,
        );
      }
      return http.Response(jsonEncode({'data': <dynamic>[]}), 200);
    });

    await tester.pumpWidget(
      MaterialApp(
        home: MediaStudioScreen(service: service, initialBrandId: 1),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('AI Media Studio'), findsOneWidget);
    expect(find.text('Generator'), findsOneWidget);
    expect(find.text('Library'), findsOneWidget);
    expect(find.text('Video & Story'), findsOneWidget);
    expect(find.text('Generate Brand Media'), findsOneWidget);
    expect(find.byType(FilledButton), findsWidgets);
  });

  test('composeGraphic sends correct payload and returns completed asset', () async {
    final requests = <http.Request>[];
    final service = _service((req) async {
      requests.add(req);
      return http.Response(
        jsonEncode({
          'message': 'Graphic composed successfully.',
          'asset': {
            'id': 77,
            'title': 'Discipline Quote Card',
            'asset_type': 'image',
            'generation_status': 'completed',
            'platform': 'instagram',
            'aspect_ratio': '1:1',
            'file_format': 'svg',
            'provider': 'internal_composer',
          },
        }),
        201,
      );
    });

    final result = await service.composeGraphic(
      brandId: 1,
      template: 'quote_card',
      quote: 'Discipline is the bridge between goals and accomplishment.',
      title: 'Discipline Quote Card',
      platform: 'instagram',
      aspectRatio: '1:1',
    );

    expect(requests, hasLength(1));
    final req = requests.first;
    expect(req.method, 'POST');
    expect(req.url.path, '/api/v1/brands/1/media/compose');
    expect(req.headers['authorization'], 'Bearer test-token');

    final body = jsonDecode(req.body) as Map;
    expect(body['template'], 'quote_card');
    expect(body['quote'], 'Discipline is the bridge between goals and accomplishment.');
    expect(body['title'], 'Discipline Quote Card');
    expect(body['platform'], 'instagram');
    expect(body['aspect_ratio'], '1:1');

    expect(result['asset']['id'], 77);
    expect(result['asset']['generation_status'], 'completed');
    expect(result['asset']['provider'], 'internal_composer');
    expect(result['asset']['file_format'], 'svg');
  });

  testWidgets('MediaStudioScreen switches to Video & Story tab successfully', (tester) async {
    final service = _service((req) async {
      if (req.url.path.contains('/brands') && !req.url.path.contains('/media')) {
        return http.Response(
          jsonEncode({
            'data': [
              {'id': 1, 'name': 'Yaa Clarence', 'status': 'active'},
            ],
          }),
          200,
        );
      }
      return http.Response(jsonEncode({'data': <dynamic>[]}), 200);
    });

    await tester.pumpWidget(
      MaterialApp(
        home: MediaStudioScreen(service: service, initialBrandId: 1),
      ),
    );
    await tester.pumpAndSettle();

    // Tap Video & Story tab
    await tester.tap(find.text('Video & Story'));
    await tester.pumpAndSettle();

    expect(find.text('Video Storyboard Director'), findsOneWidget);
    expect(find.text('Generate Storyboard'), findsOneWidget);
  });
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

class _MemoryStorage implements AppStorage {
  String? token = 'test-token';

  @override Future<void> clearSelectedBrandId() async {}
  @override Future<void> clearToken() async { token = null; }
  @override Future<int?> readSelectedBrandId() async => null;
  @override Future<String?> readToken() async => token;
  @override Future<void> writeSelectedBrandId(int id) async {}
  @override Future<void> writeToken(String t) async { token = t; }
}


