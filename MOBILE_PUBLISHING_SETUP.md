# Mobile Publishing Setup & Integration Guide (Flutter)

## 1. Overview
The SocialPilot AI mobile application utilizes the exact same REST API as the Laravel web application to ensure complete feature parity and unified ownership constraints.

## 2. Available Screens
- `PublishNowScreen`: Direct mobile post publishing with brand selection, social account picker, and real-time validation.
- `PublishingHistoryScreen`: Pull-to-refresh listing of publishing history with platform filters and status indicators.
- `PublishingDetailsScreen`: Detailed inspection of individual attempts, error diagnostics, external URL launcher, and retry dispatch.
- `PublishingNotificationsScreen`: Real-time notification feed of success and failure alerts.

## 3. Configuration & Running
1. Set the API URL in `mobile/lib/services/api_service.dart` or via environment variables:
   ```bash
   flutter run --dart-define=API_BASE_URL=http://your-server-ip:8000/api/v1
   ```
2. Running mobile unit and widget tests:
   ```bash
   cd mobile
   flutter test test/publishing_test.dart
   flutter test
   ```
3. Static code analysis:
   ```bash
   cd mobile
   flutter analyze
   ```
