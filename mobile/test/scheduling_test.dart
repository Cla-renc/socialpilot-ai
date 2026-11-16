import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:socialpilot_ai/main.dart';

// ─────────────────────────────────────────────────────────────────────────────
// Phase 8 — Scheduling & Autopilot Flutter Tests
// ─────────────────────────────────────────────────────────────────────────────

void main() {
  group('Phase 8 Scheduling API Client Tests', () {
    test('createScheduledPost sends correct payload and returns post', () async {
      final requests = <http.Request>[];
      final service = _createService((req) async {
        requests.add(req);
        return http.Response(
          jsonEncode({
            'message': 'Post scheduled',
            'data': {
              'id': 101,
              'title': 'Product Teaser',
              'platform': 'instagram',
              'status': 'scheduled',
              'scheduled_at': '2026-11-20 10:00:00',
            },
          }),
          201,
        );
      });

      final result = await service.createScheduledPost(1, {
        'title': 'Product Teaser',
        'caption': 'Exciting news coming soon!',
        'platform': 'instagram',
        'scheduled_at': '2026-11-20 10:00:00',
      });

      expect(requests, hasLength(1));
      final req = requests.first;
      expect(req.method, 'POST');
      expect(req.url.path, '/api/v1/brands/1/scheduled-posts');

      final body = jsonDecode(req.body) as Map;
      expect(body['title'], 'Product Teaser');
      expect(body['platform'], 'instagram');

      expect(result['id'], 101);
      expect(result['status'], 'scheduled');
    });

    test('reschedulePost posts to reschedule endpoint', () async {
      final requests = <http.Request>[];
      final service = _createService((req) async {
        requests.add(req);
        return http.Response(
          jsonEncode({
            'message': 'Post rescheduled',
            'data': {
              'id': 101,
              'scheduled_at': '2026-11-25 15:30:00',
            },
          }),
          200,
        );
      });

      final result = await service.reschedulePost(101, '2026-11-25 15:30:00');

      expect(requests, hasLength(1));
      expect(requests.first.url.path, '/api/v1/scheduled-posts/101/reschedule');
      expect(result['scheduled_at'], '2026-11-25 15:30:00');
    });

    test('cancelScheduledPost posts reason to cancel endpoint', () async {
      final requests = <http.Request>[];
      final service = _createService((req) async {
        requests.add(req);
        return http.Response(
          jsonEncode({
            'message': 'Post cancelled',
            'data': {'id': 101, 'status': 'cancelled'},
          }),
          200,
        );
      });

      final result = await service.cancelScheduledPost(101, reason: 'Outdated');

      expect(requests, hasLength(1));
      expect(requests.first.url.path, '/api/v1/scheduled-posts/101/cancel');
      expect(result['status'], 'cancelled');
    });

    test('fetchBrandCalendar returns list of scheduled entries', () async {
      final service = _createService((req) async {
        return http.Response(
          jsonEncode({
            'brand_id': 1,
            'data': [
              {
                'id': 1,
                'title': 'Calendar Post',
                'platform': 'linkedin',
                'scheduled_at': '2026-11-20T10:00:00Z',
                'status': 'scheduled',
              }
            ],
          }),
          200,
        );
      });

      final items = await service.fetchBrandCalendar(1, startDate: '2026-11-01', endDate: '2026-11-30');
      expect(items, hasLength(1));
      expect(items.first['title'], 'Calendar Post');
    });

    test('fetchBestTimes returns platform recommendations', () async {
      final service = _createService((req) async {
        return http.Response(
          jsonEncode({
            'brand_id': 1,
            'data': [
              {
                'day_of_week': 'Tuesday',
                'suggested_time': '10:00',
                'reason': 'Peak morning engagement',
                'evidence_type': 'heuristic',
              }
            ],
          }),
          200,
        );
      });

      final recs = await service.fetchBestTimes(1, platform: 'instagram');
      expect(recs, hasLength(1));
      expect(recs.first['day_of_week'], 'Tuesday');
      expect(recs.first['suggested_time'], '10:00');
    });

    test('autopilot enable, disable, and trigger endpoints', () async {
      final requests = <http.Request>[];
      final service = _createService((req) async {
        requests.add(req);
        return http.Response(
          jsonEncode({
            'message': 'Success',
            'settings': {'is_enabled': true, 'mode': 'ai_assisted'},
          }),
          200,
        );
      });

      await service.enableAutopilot(1);
      await service.disableAutopilot(1);
      await service.triggerAutopilot(1);

      expect(requests, hasLength(3));
      expect(requests[0].url.path, '/api/v1/brands/1/autopilot/enable');
      expect(requests[1].url.path, '/api/v1/brands/1/autopilot/disable');
      expect(requests[2].url.path, '/api/v1/brands/1/autopilot/trigger');
    });
  });

  group('Phase 8 Scheduling Widget Tests', () {
    testWidgets('CalendarScreen renders scheduled posts list', (tester) async {
      final service = _createService((req) async {
        return http.Response(
          jsonEncode({
            'data': [
              {
                'id': 1,
                'title': 'Scheduled Product Launch',
                'brand_name': 'Apex Brand',
                'platform': 'instagram',
                'scheduled_for': '2026-11-20T10:00:00Z',
                'status': 'scheduled',
              }
            ]
          }),
          200,
        );
      });

      await tester.pumpWidget(MaterialApp(home: CalendarScreen(service: service)));
      await tester.pumpAndSettle();

      expect(find.text('Calendar'), findsOneWidget);
      expect(find.text('Scheduled Product Launch'), findsOneWidget);
      expect(find.text('Schedule Post'), findsOneWidget);
    });

    testWidgets('SchedulePostScreen renders form elements', (tester) async {
      final service = _createService((req) async {
        if (req.url.path == '/api/v1/brands') {
          return http.Response(
            jsonEncode({
              'data': [
                {'id': 1, 'name': 'Apex Brand', 'status': 'active'}
              ]
            }),
            200,
          );
        }
        return http.Response('{}', 200);
      });

      await tester.pumpWidget(MaterialApp(home: SchedulePostScreen(service: service)));
      await tester.pumpAndSettle();

      expect(find.text('Schedule Post'), findsOneWidget);
      expect(find.text('Post Title'), findsOneWidget);
      expect(find.text('Caption / Body'), findsOneWidget);
      expect(find.text('Confirm Schedule'), findsOneWidget);
    });

    testWidgets('AutopilotScreen renders status and control button', (tester) async {
      final service = _createService((req) async {
        if (req.url.path == '/api/v1/brands') {
          return http.Response(
            jsonEncode({
              'data': [
                {'id': 1, 'name': 'Apex Brand', 'status': 'active'}
              ]
            }),
            200,
          );
        }
        if (req.url.path == '/api/v1/brands/1/autopilot') {
          return http.Response(
            jsonEncode({
              'is_enabled': true,
              'mode': 'ai_assisted',
              'posts_per_week': 4,
            }),
            200,
          );
        }
        return http.Response('{}', 200);
      });

      await tester.pumpWidget(MaterialApp(home: AutopilotScreen(service: service)));
      await tester.pumpAndSettle();

      expect(find.text('Autopilot Agent'), findsOneWidget);
      expect(find.text('Autopilot Status'), findsOneWidget);
      expect(find.text('Run Autopilot Cycle Now'), findsOneWidget);
    });

    testWidgets('BestTimesScreen renders platform segments and recs', (tester) async {
      final service = _createService((req) async {
        if (req.url.path == '/api/v1/brands') {
          return http.Response(
            jsonEncode({
              'data': [
                {'id': 1, 'name': 'Apex Brand', 'status': 'active'}
              ]
            }),
            200,
          );
        }
        if (req.url.path.contains('/scheduling/best-times')) {
          return http.Response(
            jsonEncode({
              'data': [
                {
                  'day_of_week': 'Wednesday',
                  'suggested_time': '14:00',
                  'reason': 'Peak afternoon engagement',
                  'evidence_type': 'historical',
                }
              ]
            }),
            200,
          );
        }
        return http.Response('{}', 200);
      });

      await tester.pumpWidget(MaterialApp(home: BestTimesScreen(service: service)));
      await tester.pumpAndSettle();

      expect(find.text('AI Best Times'), findsOneWidget);
      expect(find.text('IG'), findsOneWidget);
      expect(find.text('Wednesday at 14:00'), findsOneWidget);
      expect(find.text('Peak afternoon engagement'), findsOneWidget);
    });
  });
}

AuthService _createService(Future<http.Response> Function(http.Request) handler) {
  final storage = _FakeStorageService();
  storage.writeToken('test-token');
  return AuthService(
    client: MockClient(handler),
    storage: storage,
  );
}

class _FakeStorageService extends StorageService {
  String? _token;

  @override
  Future<void> writeToken(String token) async {
    _token = token;
  }

  @override
  Future<String?> readToken() async => _token;

  @override
  Future<void> clearToken() async {
    _token = null;
  }
}


