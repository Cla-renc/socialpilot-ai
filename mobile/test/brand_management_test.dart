import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:socialpilot_ai/main.dart';

class _TestStorage implements AppStorage {
  String? token = 'test-token';
  int? selectedBrandId;

  @override
  Future<void> clearSelectedBrandId() async => selectedBrandId = null;

  @override
  Future<void> clearToken() async => token = null;

  @override
  Future<int?> readSelectedBrandId() async => selectedBrandId;

  @override
  Future<String?> readToken() async => token;

  @override
  Future<void> writeSelectedBrandId(int id) async => selectedBrandId = id;

  @override
  Future<void> writeToken(String token) async => this.token = token;
}

void main() {
  const brand = Brand(
    id: 41,
    name: 'Northstar',
    status: 'active',
    industry: 'Design',
    settings: {'brand_voice': 'Helpful and direct'},
  );

  test('brand create and edit use the shared API', () async {
    final requests = <http.Request>[];
    final service = AuthService(
      storage: _TestStorage(),
      client: MockClient((request) async {
        requests.add(request);
        final payload = jsonDecode(request.body) as Map<String, dynamic>;
        return http.Response(
          jsonEncode({
            'data': {
              'id': request.method == 'POST' ? 42 : 41,
              'name': payload['name'],
              'status': 'active',
            },
          }),
          request.method == 'POST' ? 201 : 200,
        );
      }),
    );

    final created = await service.saveBrand({'name': 'New brand'});
    final updated = await service.saveBrand({
      'name': 'Northstar Studio',
    }, id: brand.id);

    expect(created.id, 42);
    expect(updated.name, 'Northstar Studio');
    expect(requests.map((request) => request.method), ['POST', 'PUT']);
    expect(requests.map((request) => request.url.path), [
      '/api/v1/brands',
      '/api/v1/brands/41',
    ]);
  });

  testWidgets('brand details display API overview and account data', (
    tester,
  ) async {
    final service = AuthService(
      storage: _TestStorage(),
      client: MockClient((request) async {
        if (request.url.path.endsWith('/overview')) {
          return http.Response(
            jsonEncode({
              'data': {
                'connected_accounts': 1,
                'content_drafts': 2,
                'posts': 3,
                'campaigns': 4,
              },
            }),
            200,
          );
        }
        return http.Response(
          jsonEncode({
            'data': [
              {
                'id': 15,
                'provider': 'youtube',
                'display_name': 'Northstar Channel',
                'status': 'connected',
                'provider_account_id': 'channel-15',
                'brand_name': 'Northstar',
              },
            ],
            'providers': [],
          }),
          200,
        );
      }),
    );

    await tester.pumpWidget(
      MaterialApp(
        home: BrandDetailScreen(
          brand: brand,
          onChanged: () {},
          service: service,
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Northstar'), findsWidgets);
    expect(find.text('Content drafts: 2'), findsOneWidget);
    expect(find.text('Northstar Channel'), findsOneWidget);
    expect(find.textContaining('channel-15'), findsOneWidget);
  });

  testWidgets('brand list confirms and sends delete through the API', (
    tester,
  ) async {
    var deleteSent = false;
    final service = AuthService(
      storage: _TestStorage(),
      client: MockClient((request) async {
        if (request.method == 'DELETE') {
          expect(request.url.path, '/api/v1/brands/41');
          deleteSent = true;
          return http.Response('{"message":"deleted"}', 200);
        }
        return http.Response(
          jsonEncode({
            'data': deleteSent
                ? []
                : [
                    {
                      'id': 41,
                      'name': 'Northstar',
                      'status': 'active',
                      'industry': 'Design',
                    },
                  ],
          }),
          200,
        );
      }),
    );

    await tester.pumpWidget(
      MaterialApp(
        home: BrandListScreen(service: service, storage: _TestStorage()),
      ),
    );
    await tester.pumpAndSettle();
    await tester.tap(find.byTooltip('Delete brand'));
    await tester.pumpAndSettle();
    expect(find.text('Delete brand?'), findsOneWidget);
    await tester.tap(find.widgetWithText(FilledButton, 'Delete'));
    await tester.pumpAndSettle();

    expect(deleteSent, isTrue);
    expect(
      find.text('No brands yet. Create your first brand.'),
      findsOneWidget,
    );
  });
}


