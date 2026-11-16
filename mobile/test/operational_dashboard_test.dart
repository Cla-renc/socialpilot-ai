import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:socialpilot_ai/main.dart';

void main() {
  test(
    'dashboard snapshot calls authenticated, brand-filtered API endpoints',
    () async {
      final requests = <http.Request>[];
      final service = AuthService(
        client: MockClient((request) async {
          requests.add(request);
          final payload = switch (request.url.path) {
            '/api/v1/dashboard/overview' => {'data': <String, dynamic>{}},
            '/api/v1/dashboard/brands' => {'data': <dynamic>[]},
            '/api/v1/dashboard/social-accounts' => {
              'data': <dynamic>[],
              'providers': <dynamic>[],
            },
            _ => {'data': <dynamic>[]},
          };
          return http.Response(jsonEncode(payload), 200);
        }),
        storage: _MemoryStorage(),
      );

      await service.fetchDashboardSnapshot(3);

      expect(requests, hasLength(7));
      expect(
        requests.map((request) => request.url.path),
        containsAll([
          '/api/v1/dashboard/overview',
          '/api/v1/dashboard/brands',
          '/api/v1/dashboard/content-activity',
          '/api/v1/dashboard/publishing-calendar',
          '/api/v1/dashboard/social-accounts',
          '/api/v1/dashboard/agent-activity',
          '/api/v1/notifications',
        ]),
      );
      for (final request in requests) {
        expect(request.headers['authorization'], 'Bearer dashboard-test-token');
        if (!request.url.path.endsWith('/brands')) {
          expect(request.url.queryParameters['brand_id'], '3');
        }
      }
    },
  );

  test(
    'network failure shows guidance and a later request can recover',
    () async {
      var requests = 0;
      final service = AuthService(
        client: MockClient((_) async {
          requests++;
          if (requests == 1) {
            throw http.ClientException('Connection refused');
          }
          return http.Response(jsonEncode({'data': <dynamic>[]}), 200);
        }),
        storage: _MemoryStorage(),
      );

      await expectLater(
        service.fetchBrands(),
        throwsA(
          isA<ApiException>().having(
            (error) => error.message,
            'message',
            contains('internet connection'),
          ),
        ),
      );
      expect(requests, 1);

      expect(await service.fetchBrands(), isEmpty);
      expect(requests, 2);
    },
  );

  test(
    'server failures do not expose internal messages as successes',
    () async {
      final service = AuthService(
        client: MockClient(
          (_) async => http.Response(
            jsonEncode({'message': 'Database password leaked'}),
            503,
          ),
        ),
        storage: _MemoryStorage(),
      );

      await expectLater(
        service.fetchBrands(),
        throwsA(
          isA<ApiException>().having(
            (error) => error.message,
            'message',
            'SocialPilot is temporarily unavailable. Please try again.',
          ),
        ),
      );
    },
  );

  test('requests time out with a recoverable error', () async {
    final service = AuthService(
      client: MockClient((_) => Completer<http.Response>().future),
      storage: _MemoryStorage(),
      requestTimeout: const Duration(milliseconds: 10),
    );

    await expectLater(
      service.fetchBrands(),
      throwsA(
        isA<ApiException>().having(
          (error) => error.message,
          'message',
          contains('request timed out'),
        ),
      ),
    );
  });

  test('expired authentication clears the stored token', () async {
    final storage = _MemoryStorage();
    final service = AuthService(
      client: MockClient(
        (_) async =>
            http.Response(jsonEncode({'message': 'Unauthenticated.'}), 401),
      ),
      storage: storage,
    );

    await expectLater(
      service.fetchBrands(),
      throwsA(
        isA<ApiException>()
            .having((error) => error.statusCode, 'statusCode', 401)
            .having(
              (error) => error.message,
              'message',
              'Your session has expired. Please sign in again.',
            ),
      ),
    );

    expect(storage.token, isNull);
    expect(storage.clearCalls, 1);
  });

  testWidgets('dashboard displays API metrics and refreshes on demand', (
    tester,
  ) async {
    final service = _FakeDashboardService();
    await tester.pumpWidget(_dashboard(service));
    await tester.pumpAndSettle();

    expect(find.text('1'), findsWidgets);
    service.publishedPosts = 5;
    await tester.tap(find.byTooltip('Refresh dashboard'));
    await tester.pumpAndSettle();

    expect(service.requests, [null, null]);
    expect(find.text('5'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('Upcoming calendar'),
      400,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('Upcoming calendar'), findsOneWidget);
  });

  testWidgets('dashboard shows loading and API error states', (tester) async {
    final service = _FakeDashboardService()..pending = Completer();
    await tester.pumpWidget(_dashboard(service));
    await tester.pump();
    expect(find.byType(CircularProgressIndicator), findsOneWidget);

    service.pending!.completeError(ApiException('Network unavailable'));
    await tester.pumpAndSettle();
    expect(find.text('Network unavailable'), findsOneWidget);
    expect(find.text('Retry'), findsOneWidget);
    expect(find.byType(CircularProgressIndicator), findsNothing);
  });

  testWidgets('dashboard retry reloads data after reconnection', (
    tester,
  ) async {
    final service = _FakeDashboardService()
      ..nextError = ApiException(
        'Could not connect to SocialPilot. Check your internet connection and try again.',
      );
    await tester.pumpWidget(_dashboard(service));
    await tester.pumpAndSettle();

    expect(
      find.textContaining('Check your internet connection'),
      findsOneWidget,
    );
    expect(find.text('1'), findsNothing);
    expect(find.byType(CircularProgressIndicator), findsNothing);
    expect(service.requests, [null]);

    service.publishedPosts = 5;
    await tester.tap(find.text('Retry'));
    await tester.pumpAndSettle();

    expect(service.requests, [null, null]);
    expect(find.text('5'), findsOneWidget);
    expect(find.textContaining('Check your internet connection'), findsNothing);
  });

  testWidgets('expired dashboard session offers sign-in instead of retry', (
    tester,
  ) async {
    final service = _FakeDashboardService()
      ..nextError = ApiException(
        'Your session has expired. Please sign in again.',
        statusCode: 401,
      );
    await tester.pumpWidget(_dashboard(service));
    await tester.pumpAndSettle();

    expect(
      find.text('Your session has expired. Please sign in again.'),
      findsOneWidget,
    );
    expect(find.text('Sign in again'), findsOneWidget);
    expect(find.text('Retry'), findsNothing);

    await tester.tap(find.text('Sign in again'));
    await tester.pumpAndSettle();
    expect(find.text('Welcome back'), findsOneWidget);
  });

  testWidgets('notification center offers sign-in for an expired session', (
    tester,
  ) async {
    final service = _FakeDashboardService()
      ..nextNotificationError = ApiException(
        'Your session has expired. Please sign in again.',
        statusCode: 401,
      );
    await tester.pumpWidget(
      MaterialApp(
        home: NotificationCenterScreen(service: service, brandId: null),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Sign in again'), findsOneWidget);
    await tester.tap(find.text('Sign in again'));
    await tester.pumpAndSettle();
    expect(find.text('Welcome back'), findsOneWidget);
  });

  testWidgets(
    'dashboard has no layout overflow at phone, tablet, and desktop widths',
    (tester) async {
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);

      for (final width in [320.0, 600.0, 900.0]) {
        tester.view.physicalSize = Size(width, 900);
        tester.view.devicePixelRatio = 1;
        await tester.pumpWidget(_dashboard(_FakeDashboardService()));
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
      }
    },
  );

  testWidgets('selecting a brand reloads brand-filtered statistics', (
    tester,
  ) async {
    final service = _FakeDashboardService();
    await tester.pumpWidget(_dashboard(service));
    await tester.pumpAndSettle();

    await tester.tap(find.byType(DropdownButtonFormField<int?>));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Acme').last);
    await tester.pumpAndSettle();

    expect(service.requests, [null, 1]);
    expect(find.text('7'), findsOneWidget);
  });

  testWidgets('notification center persists and displays read state', (
    tester,
  ) async {
    final service = _FakeDashboardService();
    await tester.pumpWidget(_dashboard(service));
    await tester.pumpAndSettle();

    await tester.scrollUntilVisible(
      find.text('View all'),
      500,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.tap(find.text('View all'));
    await tester.pumpAndSettle();
    expect(find.text('Brand created'), findsOneWidget);
    await tester.tap(find.text('Mark read'));
    await tester.pumpAndSettle();

    expect(service.markedIds, ['notification-1']);
    expect(find.text('Read'), findsOneWidget);
  });

  testWidgets('calendar tab renders actual scheduled API records', (
    tester,
  ) async {
    final service = _FakeDashboardService();
    await tester.pumpWidget(
      MaterialApp(home: CalendarScreen(service: service)),
    );
    await tester.pumpAndSettle();

    expect(find.text('Real scheduled post'), findsOneWidget);
    expect(service.calendarRequests, [null]);
  });
}

Widget _dashboard(_FakeDashboardService service) => MaterialApp(
  home: DashboardScreen(
    user: const AppUser(id: 1, name: 'Test User', email: 'test@example.com'),
    service: service,
  ),
);

class _FakeDashboardService extends AuthService {
  _FakeDashboardService()
    : super(client: MockClient((_) async => http.Response('{}', 200)));

  final List<int?> requests = [];
  final List<int?> calendarRequests = [];
  final List<String> markedIds = [];
  Completer<Map<String, dynamic>>? pending;
  ApiException? nextError;
  ApiException? nextNotificationError;
  int publishedPosts = 1;

  @override
  Future<Map<String, dynamic>> fetchDashboardSnapshot(int? brandId) {
    requests.add(brandId);
    final error = nextError;
    if (error != null) {
      nextError = null;
      return Future.error(error);
    }
    if (pending != null) return pending!.future;
    return Future.value(_snapshot(brandId));
  }

  @override
  Future<List<Map<String, dynamic>>> fetchNotifications(int? brandId) async {
    final error = nextNotificationError;
    if (error != null) {
      nextNotificationError = null;
      throw error;
    }
    return [_notification()];
  }

  @override
  Future<List<Map<String, dynamic>>> fetchPublishingCalendar(
    int? brandId,
  ) async {
    calendarRequests.add(brandId);
    return [
      {
        'id': 1,
        'title': 'Real scheduled post',
        'brand_name': 'Acme',
        'platform': 'facebook',
        'status': 'scheduled',
        'scheduled_for': '2026-01-01T10:00:00Z',
      },
    ];
  }

  @override
  Future<Map<String, dynamic>> markNotificationRead(String id) async {
    markedIds.add(id);
    return {
      ..._notification(),
      'is_read': true,
      'read_at': '2026-01-01T00:00:00Z',
    };
  }

  Map<String, dynamic> _snapshot(int? brandId) => {
    'overview': {
      'brands': brandId == null ? 1 : 1,
      'active_brands': 1,
      'connected_accounts': 0,
      'published_posts': brandId == null ? publishedPosts : 7,
      'scheduled_posts': 1,
      'draft_posts': 0,
      'failed_posts': 0,
      'ai_generated_content': 0,
      'ai_generated_content_available': true,
    },
    'brands': [
      {'id': 1, 'name': 'Acme', 'status': 'active'},
    ],
    'content': [
      {
        'title': 'Real scheduled post',
        'brand_name': 'Acme',
        'platform': 'facebook',
        'status': 'scheduled',
      },
    ],
    'calendar': [
      {
        'id': 1,
        'title': 'Real scheduled post',
        'brand_name': 'Acme',
        'platform': 'facebook',
        'status': 'scheduled',
        'scheduled_for': '2026-01-01T10:00:00Z',
      },
    ],
    'social': {
      'data': <Map<String, dynamic>>[],
      'providers': <Map<String, dynamic>>[],
    },
    'agents': [
      {'name': 'Trend Research Agent', 'status': 'waiting_for_configuration'},
    ],
    'notifications': [_notification()],
  };

  Map<String, dynamic> _notification() => {
    'id': 'notification-1',
    'title': 'Brand created',
    'description': 'Acme was created.',
    'brand_id': 1,
    'created_at': '2026-01-01T00:00:00Z',
    'is_read': false,
  };
}

class _MemoryStorage implements AppStorage {
  String? token = 'dashboard-test-token';
  int clearCalls = 0;

  @override
  Future<String?> readToken() async => token;

  @override
  Future<void> writeToken(String token) async {}

  @override
  Future<void> clearToken() async {
    token = null;
    clearCalls++;
  }

  @override
  Future<int?> readSelectedBrandId() async => null;

  @override
  Future<void> writeSelectedBrandId(int id) async {}

  @override
  Future<void> clearSelectedBrandId() async {}
}


