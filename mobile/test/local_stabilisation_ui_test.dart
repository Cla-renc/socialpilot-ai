import 'dart:convert';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:socialpilot_ai/main.dart';

void main() {
  const testUser = AppUser(id: 1, name: 'Alice Founder', email: 'alice@socialpilot.test');

  group('Phase 10+ Local Stabilisation & UX Tests', () {
    testWidgets('MainShell displays 5 bottom navigation destinations including More', (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: MainShell(user: testUser),
        ),
      );
      await tester.pump(const Duration(milliseconds: 100));

      // Verify 5 destinations exist: Home, Brands, Content, Calendar, More
      expect(find.text('Home'), findsOneWidget);
      expect(find.text('Brands'), findsOneWidget);
      expect(find.text('Content'), findsOneWidget);
      expect(find.text('Calendar'), findsOneWidget);
      expect(find.text('More'), findsOneWidget);

      // Tapping More switches to MoreScreen
      await tester.tap(find.text('More'));
      await tester.pump(const Duration(milliseconds: 100));

      expect(find.text('Publishing Studio'), findsOneWidget);
      expect(find.text('Analytics Studio'), findsOneWidget);
      expect(find.text('Profile & Security'), findsOneWidget);
    });

    testWidgets('ForgotPasswordScreen renders input field and dispatches recovery request', (tester) async {
      final mockClient = MockClient((request) async {
        if (request.url.path.contains('/auth/forgot-password')) {
          final body = jsonDecode(request.body) as Map<String, dynamic>;
          if (body['email'] == 'alice@socialpilot.test') {
            return http.Response(jsonEncode({
              'status': 'success',
              'message': 'We have emailed your password reset link.',
            }), 200, headers: {'content-type': 'application/json'});
          }
        }
        return http.Response('{"message": "Not found"}', 404);
      });

      final authService = AuthService(client: mockClient);

      await tester.pumpWidget(
        MaterialApp(
          home: ForgotPasswordScreen(service: authService),
        ),
      );

      expect(find.text('Forgot your password?'), findsOneWidget);
      expect(find.byType(TextField), findsOneWidget);

      await tester.enterText(find.byType(TextField), 'alice@socialpilot.test');
      await tester.tap(find.text('Send Password Reset Link'));
      await tester.pump(const Duration(milliseconds: 100));

      expect(find.text('We have emailed your password reset link.'), findsOneWidget);
      expect(find.text('Complete Password Reset'), findsOneWidget);
    });

    testWidgets('ProfileScreen renders user details, Edit Profile, and Change Password options', (tester) async {
      final mockClient = MockClient((request) async {
        if (request.url.path.contains('/me')) {
          return http.Response(jsonEncode({
            'user': {
              'id': 1,
              'name': 'Alice Founder',
              'email': 'alice@socialpilot.test',
            }
          }), 200, headers: {'content-type': 'application/json'});
        }
        return http.Response('{}', 200);
      });

      final authService = AuthService(client: mockClient);

      await tester.pumpWidget(
        MaterialApp(
          home: ProfileScreen(user: testUser, service: authService),
        ),
      );

      await tester.pump(const Duration(milliseconds: 100));

      expect(find.text('Alice Founder'), findsOneWidget);
      expect(find.text('alice@socialpilot.test'), findsOneWidget);
      expect(find.text('Edit Profile'), findsOneWidget);
      expect(find.text('Change Password'), findsOneWidget);
      expect(find.text('Log Out'), findsOneWidget);

      // Open Edit Profile dialog
      await tester.tap(find.text('Edit Profile'));
      await tester.pump(const Duration(milliseconds: 100));

      expect(find.text('Save Changes'), findsOneWidget);
      await tester.tap(find.text('Cancel'));
      await tester.pump(const Duration(milliseconds: 100));

      // Open Change Password dialog
      await tester.tap(find.text('Change Password'));
      await tester.pump(const Duration(milliseconds: 100));

      expect(find.text('Update Password'), findsOneWidget);
      await tester.tap(find.text('Cancel'));
      await tester.pump(const Duration(milliseconds: 100));
    });

    testWidgets('MoreScreen navigates correctly to ProfileScreen', (tester) async {
      await tester.pumpWidget(
        MaterialApp(
          home: MoreScreen(user: testUser),
        ),
      );

      expect(find.text('Profile & Security'), findsOneWidget);
      await tester.tap(find.text('Profile & Security'));
      await tester.pumpAndSettle();

      expect(find.text('Account Management'), findsOneWidget);
    });
  });
}


