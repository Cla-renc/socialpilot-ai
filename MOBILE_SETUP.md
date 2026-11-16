# SocialPilot AI Mobile Setup

## Prerequisites

- Flutter SDK installed at `C:\src\flutter`
- VS Code with the Flutter and Dart extensions
- Either a physical Android phone with USB debugging enabled, or an Android emulator
- Laravel app running locally on port 8000

Android Studio is not required to edit or run the Flutter app from VS Code. It is one option for installing Android SDK components and managing emulators. This machine currently has `adb` and the Android emulator executable, but is missing Android command-line tools and has no configured emulator.

## Flutter installation path

Use the installed SDK directly:

```powershell
C:\src\flutter\bin\flutter --version
```

## Install dependencies

From the project root:

```powershell
cd C:\PROJECTS\SOCIALPILOT AI\mobile
C:\src\flutter\bin\flutter pub get
```

## Run from VS Code on Android

Open the `mobile` folder in VS Code, then use **Terminal → New Terminal**. Do not use plain `flutter run` unless you intend to launch the Windows desktop build. With no Android device connected, Flutter may select Windows and download Windows desktop artifacts.

### Option A: Use a physical Android phone

```powershell
# On the phone: enable Developer options, then USB debugging; connect by USB and approve the computer prompt.
& "C:\Users\Administrator\AppData\Local\Android\sdk\platform-tools\adb.exe" devices
& "C:\src\flutter\bin\flutter.bat" devices
```

If the phone appears in `flutter devices`, start Laravel in another VS Code terminal:

```powershell
cd "C:\PROJECTS\SOCIALPILOT AI"
php artisan serve --host 0.0.0.0
```

For a USB-connected phone, forward the API port through ADB. This avoids changing LAN IPs and Wi-Fi firewall rules:

```powershell
& "C:\Users\Administrator\AppData\Local\Android\sdk\platform-tools\adb.exe" reverse tcp:8000 tcp:8000
```

Then run the app using the device ID shown by `flutter devices`:

```powershell
cd "C:\PROJECTS\SOCIALPILOT AI\mobile"
& "C:\src\flutter\bin\flutter.bat" run -d <android-device-id> --dart-define=API_BASE_URL=http://127.0.0.1:8000/api/v1
```

Alternatively, if the phone and computer are on the same network and inbound port 8000 is allowed by the firewall, use the computer's current LAN IP:

```powershell
cd "C:\PROJECTS\SOCIALPILOT AI\mobile"
& "C:\src\flutter\bin\flutter.bat" run -d <android-device-id> --dart-define=API_BASE_URL=http://<computer-lan-ip>:8000/api/v1
```

### Option B: Use an Android emulator

Create and start an AVD using Android Studio's Device Manager, or install Android SDK Command-line Tools and create one with `avdmanager`. Android Studio is optional; the emulator must be running before Flutter can detect it.

Check available devices and emulators:

```powershell
& "C:\src\flutter\bin\flutter.bat" devices
& "C:\src\flutter\bin\flutter.bat" emulators
```

After an emulator appears in `flutter devices`, start Laravel in another VS Code terminal with `php artisan serve`, then run:

```powershell
cd "C:\PROJECTS\SOCIALPILOT AI\mobile"
& "C:\src\flutter\bin\flutter.bat" run -d <android-device-id>
```

The app's default emulator API URL is `http://10.0.2.2:8000/api/v1`, which maps to the computer's localhost.

If Android builds report missing SDK command-line tools or licenses, install **Android SDK Command-line Tools (latest)** through Android Studio's SDK Manager or the official Android command-line tools package, then run:

```powershell
& "C:\src\flutter\bin\flutter.bat" doctor --android-licenses
```

If you explicitly want to run the Windows desktop target, use `flutter run -d windows`; that target requires Windows Flutter artifacts.

## Local backend startup

From the Laravel project root:

```powershell
cd C:\PROJECTS\SOCIALPILOT AI
php artisan serve
php artisan storage:link
```

Then validate the API health endpoint:

```powershell
curl http://127.0.0.1:8000/api/v1/health
```

## Testing the Flutter app

Run static analysis and the widget/API-client tests:

```powershell
cd C:\PROJECTS\SOCIALPILOT AI\mobile
C:\src\flutter\bin\flutter analyze
C:\src\flutter\bin\flutter test
```

The automated tests cover brand create/update API requests, brand details and overview rendering, the confirmed delete flow, dashboard API request/auth/filter wiring, loading/error/refresh behavior, brand switching, notification read state, and dashboard layout at phone/tablet/desktop widths. They also simulate connection loss, API 5xx responses, request timeouts, expired authentication, and a successful manual retry. API requests time out after 15 seconds by default; failures do not trigger automatic retries. These are controlled HTTP-client/widget tests, not physical-device network toggles. Verify actual browser-to-phone synchronization and device-level connectivity separately with the local Laravel server and an authorized connected device.

## Social account OAuth on Android

The brand detail screen opens provider authorization in the system browser and returns through the `socialpilot://oauth/callback` app link. Each provider callback must be publicly reachable over HTTPS and match the redirect URI registered in its developer console; `127.0.0.1` cannot be reached by the provider. Configure credentials in the Laravel server `.env` as documented in [API_DOCUMENTATION.md](./API_DOCUMENTATION.md). Credentials alone do not guarantee app approval, account eligibility, or publishing permissions.

## Android build download troubleshooting

The Android Gradle build uses Flutter engine artifacts hosted at `storage.googleapis.com`. If Gradle reports `Connection reset`, retry on a stable connection. The Android Gradle configuration checks the user's local Maven cache before remote repositories, so verified artifacts downloaded into `%USERPROFILE%\.m2\repository` can be reused without adding large binaries to the project.

## Notes

- The mobile app uses secure storage for auth tokens.
- The Brands tab supports owner-scoped brand search, status filtering, create/edit/detail/delete, activation status, logo selection, and a persisted selected-brand id.
- The Dashboard tab uses the Laravel dashboard and notification endpoints for metrics, brand filtering, content/calendar previews, social account/provider states, agent activity, and notifications. Pull-to-refresh and the app-bar refresh control load a fresh snapshot; there is no continuous polling.
- API connection failures and timeouts show retry guidance. An authenticated 401 clears the stored token and offers sign-in again; requests are not retried automatically.
- Notification read state is persisted through Laravel. Notification actions only open a brand when the response contains an owned brand ID.
- Social provider/account connection, status, check/refresh, and disconnect controls are available from brand details. Provider credentials and approval are required before the actual connection can succeed.
- The same Laravel user database is used as the web app.
- Provider app review and publishing permissions are provider-controlled; the app does not infer that either has been granted.

## Phase 5 trend intelligence

The **Trend intelligence** action in the dashboard opens a brand-specific research screen backed by the authenticated Laravel API. It supports switching brands, viewing connector readiness, selecting configured sources/categories, starting asynchronous research, checking job status, filtering topics by category/source/geography/research date/relevance/momentum, opening evidence links, and saving or dismissing feedback. Manual research only returns after Laravel queues the job; refresh to load completed results. No trend topics or running jobs are seeded in the app.

Only RSS is implemented in the Python service. If the engine is unavailable or no RSS feed is configured, research surfaces the corresponding error/configuration state; News API, Reddit, YouTube, and Google Trends are not represented as connected. Trend-specific behavior and remaining verification are documented in [AI_ENGINE_DOCUMENTATION.md](./AI_ENGINE_DOCUMENTATION.md), [TREND_SOURCES.md](./TREND_SOURCES.md), and [DEVELOPMENT_PROGRESS.md](./DEVELOPMENT_PROGRESS.md).

The trend API client and widget workflow can be run with:

```powershell
cd "C:\PROJECTS\SOCIALPILOT AI\mobile"
C:\src\flutter\bin\flutter.bat test test\trend_intelligence_test.dart
C:\src\flutter\bin\flutter.bat analyze
```

## Phase 6 AI content generation

The mobile bottom navigation **Content** tab opens the full **Content Studio** screen:
- **Generate tab**: Brand selector, platform dropdown (LinkedIn, Instagram, TikTok, YouTube, Facebook), objective dropdown (awareness, engagement, education, etc.), category, format, optional trend selector (populated from the brand's researched trends), additional instructions field, and generation action.
- **Library tab**: Search bar, platform filter dropdown, paginated draft cards with platform badges and quality score chips.
- **Draft detail & editor**: Full field editing (title, hook, body, caption, call-to-action, hashtags), save button, delete confirmation dialog, and targeted **Improve** actions (`shorten`, `expand`, `improve_hook`, `better_cta`, `add_storytelling`, `professional`, `conversational`, platform adaptation).

Run the Phase 6 content generation mobile tests:

```powershell
cd "C:\PROJECTS\SOCIALPILOT AI\mobile"
C:\src\flutter\bin\flutter.bat test test\content_generation_test.dart
C:\src\flutter\bin\flutter.bat analyze
```

## Phase 7 AI media studio

The mobile app includes the **AI Media Studio** (`MediaStudioScreen`), accessible via the **Media Studio** quick action on the dashboard:
- **Generator tab**: Brand selector, title, objective selector (`social_post`, `promotional_poster`, `quote_card`, `product_ad`, `educational`, `thumbnail`), visual style selector (`dark_cinematic`, `minimalist`, `corporate`, `commercial`, `inspirational`, `editorial`), target platform, aspect ratio (`1:1`, `9:16`, `16:9`, `4:5`, `1.91:1`), instructions prompt input, and asynchronous generation with polling progress and instant result cards.
- **Library tab**: Media asset grid with asset-type filter chips (`All`, `Image`, `Video`, `Storyboard`, `Uploaded`), format & platform tags, tap-to-inspect detail dialogs, pull-to-refresh, and asset deletion.
- **Video & Story tab**: Video Storyboard Director that generates scene-by-scene scripts (camera angles, voiceover, kinetic text, durations, transitions) and displays provider readiness statuses for Runway, Stability Video, and Pika.
- **Brand Visual Settings sheet**: Palette editor (primary/accent hex colors, primary font, visual identity archetypes, visual prompt directives).

Run the Phase 7 media generation mobile tests:

```powershell
cd "C:\PROJECTS\SOCIALPILOT AI\mobile"
C:\src\flutter\bin\flutter.bat test test\media_generation_test.dart
C:\src\flutter\bin\flutter.bat analyze
```

## Phase 8 AI scheduling and autopilot studio

The mobile bottom navigation **Calendar** tab features the comprehensive **Scheduling Hub**:
- **Calendar list & detail view**: Real-time listing of scheduled posts synced with the Laravel database, platform badges, timestamps, and status chips.
- **Schedule post wizard** (`SchedulePostScreen`): Quick post scheduling directly from mobile. Brand selector, post title, caption/body, platform picker (Instagram, Facebook, TikTok, YouTube, LinkedIn), date/time pickers, and approval requirements toggle.
- **Autopilot Agent screen** (`AutopilotScreen`): Autopilot status switch (Enable/Disable), current operating mode indicator (Manual, AI-Assisted, Automatic), posts-per-week targets, and instant "Run Autopilot Cycle Now" trigger action.
- **AI Best Times screen** (`BestTimesScreen`): Segmented platform selector with data-driven peak engagement recommendations (day of week, recommended time, evidence classification tag, and strategic reason).

Run the Phase 8 scheduling mobile tests:

```powershell
cd "C:\PROJECTS\SOCIALPILOT AI\mobile"
C:\src\flutter\bin\flutter.bat test test\scheduling_test.dart
C:\src\flutter\bin\flutter.bat analyze
```


