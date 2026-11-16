import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:socialpilot_ai/main.dart';

void main() {
  testWidgets('welcome screen presents registration and login actions', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(const MaterialApp(home: WelcomeScreen()));

    expect(find.text('SocialPilot AI'), findsOneWidget);
    expect(find.text('Get Started'), findsOneWidget);
    expect(find.text('Login'), findsOneWidget);
    expect(find.text('Register'), findsOneWidget);
  });
}


