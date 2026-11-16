import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'package:socialpilot_ai/screens/publishing/publishing_history_screen.dart';
import 'package:socialpilot_ai/screens/publishing/publishing_details_screen.dart';
import 'package:socialpilot_ai/screens/publishing/publish_now_screen.dart';
import 'package:socialpilot_ai/screens/publishing/publishing_notifications_screen.dart';
import 'package:socialpilot_ai/models/publishing_attempt.dart';

void main() {
  group('Publishing Screens', () {
    testWidgets('PublishingHistoryScreen renders AppBar', (tester) async {
      await tester.pumpWidget(
        const MaterialApp(home: PublishingHistoryScreen()),
      );
      expect(find.text('Publishing History'), findsOneWidget);
    });

    testWidgets('PublishingDetailsScreen renders for failed attempt', (tester) async {
      final attempt = PublishingAttempt(
        id: 1,
        platform: 'facebook',
        status: 'failed',
        isRetryable: true,
        attemptNumber: 1,
        createdAt: DateTime.now(),
        updatedAt: DateTime.now(),
        errorMessage: 'Token expired',
      );
      await tester.pumpWidget(
        MaterialApp(home: PublishingDetailsScreen(attempt: attempt)),
      );
      expect(find.text('Publishing Details'), findsOneWidget);
    });

    testWidgets('PublishNowScreen renders form', (tester) async {
      await tester.pumpWidget(
        const MaterialApp(home: PublishNowScreen()),
      );
      expect(find.text('Publish Now'), findsNWidgets(2));
    });

    testWidgets('PublishingNotificationsScreen renders AppBar', (tester) async {
      await tester.pumpWidget(
        const MaterialApp(home: PublishingNotificationsScreen()),
      );
      expect(find.text('Publishing Notifications'), findsOneWidget);
    });
  });
}


