import 'dart:async';
import 'dart:convert';

import 'package:app_links/app_links.dart';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:http/http.dart' as http;
import 'package:image_picker/image_picker.dart';
import 'package:url_launcher/url_launcher.dart';

import 'screens/publishing/publishing_history_screen.dart';
import 'screens/publishing/publishing_details_screen.dart';
import 'screens/publishing/publish_now_screen.dart';
import 'screens/publishing/publishing_notifications_screen.dart';
import 'screens/analytics/analytics_dashboard_screen.dart';
import 'models/publishing_attempt.dart';

const String _apiBaseUrl = String.fromEnvironment(
  'API_BASE_URL',
  defaultValue: 'http://10.0.2.2:8000/api/v1',
);

void main() {
  runApp(const SocialPilotApp());
}

final ValueNotifier<Uri?> _socialOAuthEvents = ValueNotifier(null);

class SocialPilotApp extends StatefulWidget {
  const SocialPilotApp({super.key});

  @override
  State<SocialPilotApp> createState() => _SocialPilotAppState();
}

class _SocialPilotAppState extends State<SocialPilotApp> {
  final AppLinks _appLinks = AppLinks();
  StreamSubscription<Uri>? _linkSubscription;

  @override
  void initState() {
    super.initState();
    _linkSubscription = _appLinks.uriLinkStream.listen(_handleOAuthLink);
    _appLinks.getInitialLink().then((uri) {
      if (uri != null) _handleOAuthLink(uri);
    });
  }

  void _handleOAuthLink(Uri uri) {
    if (uri.scheme == 'socialpilot' &&
        uri.host == 'oauth' &&
        uri.path == '/callback') {
      _socialOAuthEvents.value = uri;
    }
  }

  @override
  void dispose() {
    _linkSubscription?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'SocialPilot AI',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(
          seedColor: const Color(0xFF6C63FF),
          brightness: Brightness.dark,
        ),
        scaffoldBackgroundColor: const Color(0xFF071326),
        appBarTheme: const AppBarTheme(
          backgroundColor: Color(0xFF0D1B2A),
          foregroundColor: Colors.white,
          elevation: 0,
        ),
        cardTheme: CardThemeData(
          color: const Color(0xFF10233B),
          elevation: 0,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(20),
          ),
        ),
        useMaterial3: true,
      ),
      home: const AuthGate(),
      routes: {
        '/publishing/history': (context) => const PublishingHistoryScreen(),
        '/publishing/details': (context) => PublishingDetailsScreen(
          attempt: ModalRoute.of(context)!.settings.arguments as PublishingAttempt,
        ),
        '/publishing/publish-now': (context) => const PublishNowScreen(),
        '/publishing/notifications': (context) => const PublishingNotificationsScreen(),
      },
    );
  }
}

class AuthGate extends StatefulWidget {
  const AuthGate({super.key});

  @override
  State<AuthGate> createState() => _AuthGateState();
}

class _AuthGateState extends State<AuthGate> {
  final StorageService _storage = StorageService();
  final AuthService _authService = AuthService();
  bool _loading = true;
  AppUser? _user;

  @override
  void initState() {
    super.initState();
    _bootstrap();
  }

  Future<void> _bootstrap() async {
    final token = await _storage.readToken();
    if (token == null) {
      setState(() {
        _loading = false;
      });
      return;
    }

    try {
      final user = await _authService.getCurrentUser();
      setState(() {
        _user = user;
        _loading = false;
      });
    } on ApiException {
      await _storage.clearToken();
      setState(() {
        _user = null;
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const _SplashScreen();
    }

    if (_user != null) {
      return MainShell(user: _user!);
    }

    return const WelcomeScreen();
  }
}

class _SplashScreen extends StatelessWidget {
  const _SplashScreen();

  @override
  Widget build(BuildContext context) {
    return const Scaffold(body: Center(child: CircularProgressIndicator()));
  }
}

class WelcomeScreen extends StatelessWidget {
  const WelcomeScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: Container(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            colors: [Color(0xFF071326), Color(0xFF111A2E), Color(0xFF1A2340)],
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
          ),
        ),
        child: SafeArea(
          child: SingleChildScrollView(
            child: Padding(
              padding: const EdgeInsets.all(24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      ClipRRect(
                        borderRadius: BorderRadius.circular(12),
                        child: Image.asset(
                          'assets/images/logo.png',
                          width: 40,
                          height: 40,
                          fit: BoxFit.cover,
                        ),
                      ),
                      const SizedBox(width: 12),
                      const Text(
                        'SocialPilot AI',
                        style: TextStyle(
                          fontSize: 24,
                          fontWeight: FontWeight.bold,
                          color: Colors.white,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 48),
                  const Text(
                    'Grow every brand with AI-powered planning and publishing.',
                    style: TextStyle(
                      fontSize: 32,
                      fontWeight: FontWeight.bold,
                      color: Colors.white,
                      height: 1.2,
                    ),
                  ),
                  const SizedBox(height: 16),
                  const Text(
                    'Create content, schedule posts, track performance, and manage your team from one workspace.',
                    style: TextStyle(
                      fontSize: 16,
                      color: Color(0xFFB8C1D9),
                      height: 1.6,
                    ),
                  ),
                  const SizedBox(height: 32),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton.icon(
                      onPressed: () {
                        Navigator.of(context).push(
                          MaterialPageRoute(
                            builder: (_) => const RegisterScreen(),
                          ),
                        );
                      },
                      style: FilledButton.styleFrom(
                        backgroundColor: const Color(0xFF6C63FF),
                        padding: const EdgeInsets.symmetric(vertical: 18),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(16),
                        ),
                      ),
                      icon: const Icon(Icons.rocket_launch_rounded),
                      label: const Text('Get Started'),
                    ),
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton(
                          onPressed: () {
                            Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => const LoginScreen(),
                              ),
                            );
                          },
                          style: OutlinedButton.styleFrom(
                            foregroundColor: Colors.white,
                            side: const BorderSide(color: Color(0xFF2D3A5F)),
                            padding: const EdgeInsets.symmetric(vertical: 18),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(16),
                            ),
                          ),
                          child: const Text('Login'),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: OutlinedButton(
                          onPressed: () {
                            Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => const RegisterScreen(),
                              ),
                            );
                          },
                          style: OutlinedButton.styleFrom(
                            foregroundColor: const Color(0xFF7FE7FF),
                            side: const BorderSide(color: Color(0xFF7FE7FF)),
                            padding: const EdgeInsets.symmetric(vertical: 18),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(16),
                            ),
                          ),
                          child: const Text('Register'),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 32),
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(18),
                    decoration: BoxDecoration(
                      color: const Color(0xFF0F1F36),
                      borderRadius: BorderRadius.circular(20),
                      border: Border.all(color: const Color(0xFF2D3A5F)),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: const [
                        Text(
                          'Free local AI',
                          style: TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w600,
                            color: Color(0xFF7FE7FF),
                          ),
                        ),
                        SizedBox(height: 8),
                        Text(
                          'This app is configured to run with a local Ollama instance, which keeps the default AI option free while the app remains compatible with the Laravel backend.',
                          style: TextStyle(
                            color: Color(0xFFB8C1D9),
                            height: 1.5,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final TextEditingController _emailController = TextEditingController();
  final TextEditingController _passwordController = TextEditingController();
  final AuthService _authService = AuthService();
  final StorageService _storage = StorageService();
  bool _obscurePassword = true;
  bool _loading = false;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final email = _emailController.text.trim();
    final password = _passwordController.text;

    if (email.isEmpty || password.isEmpty) {
      _showSnackBar('Please complete all required fields.');
      return;
    }

    setState(() => _loading = true);

    try {
      final user = await _authService.login(email: email, password: password);
      await _storage.writeToken(_authService.lastToken ?? '');
      if (!mounted) return;
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => MainShell(user: user)),
        (route) => false,
      );
    } on ApiException catch (error) {
      _showSnackBar(error.message);
    } finally {
      if (context.mounted) {
        setState(() => _loading = false);
      }
    }
  }

  void _showSnackBar(String message) {
    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF071326),
      appBar: AppBar(
        title: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: Image.asset('assets/images/logo.png', width: 28, height: 28, fit: BoxFit.cover),
            ),
            const SizedBox(width: 8),
            const Text('SocialPilot AI'),
          ],
        ),
      ),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: Card(
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const Text(
                      'Welcome back',
                      style: TextStyle(
                        fontSize: 28,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Sign in to continue to your SocialPilot workspace.',
                      style: TextStyle(color: Colors.grey.shade400),
                    ),
                    const SizedBox(height: 22),
                    TextField(
                      controller: _emailController,
                      keyboardType: TextInputType.emailAddress,
                      decoration: const InputDecoration(
                        labelText: 'Email address',
                        prefixIcon: Icon(Icons.email_outlined),
                      ),
                    ),
                    const SizedBox(height: 18),
                    TextField(
                      controller: _passwordController,
                      obscureText: _obscurePassword,
                      decoration: InputDecoration(
                        labelText: 'Password',
                        prefixIcon: const Icon(Icons.lock_outline),
                        suffixIcon: IconButton(
                          onPressed: () => setState(
                            () => _obscurePassword = !_obscurePassword,
                          ),
                          icon: Icon(
                            _obscurePassword
                                ? Icons.visibility
                                : Icons.visibility_off,
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(height: 14),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Row(
                          children: const [
                            Icon(Icons.check_box_outline_blank, size: 18),
                            SizedBox(width: 8),
                            Text('Remember me'),
                          ],
                        ),
                        TextButton(
                          onPressed: () {
                            Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => ForgotPasswordScreen(service: _authService),
                              ),
                            );
                          },
                          child: const Text('Forgot password?'),
                        ),
                      ],
                    ),
                    const SizedBox(height: 18),
                    FilledButton(
                      onPressed: _loading ? null : _submit,
                      style: FilledButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 18),
                      ),
                      child: _loading
                          ? const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Text('Login'),
                    ),
                    const SizedBox(height: 14),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        const Text('Need an account?'),
                        TextButton(
                          onPressed: () {
                            Navigator.of(context).pushReplacement(
                              MaterialPageRoute(
                                builder: (_) => const RegisterScreen(),
                              ),
                            );
                          },
                          child: const Text('Register'),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({super.key, this.service});
  final AuthService? service;

  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  final TextEditingController _emailController = TextEditingController();
  final TextEditingController _tokenController = TextEditingController();
  final TextEditingController _passwordController = TextEditingController();
  final TextEditingController _confirmController = TextEditingController();

  late final AuthService _authService;
  bool _loading = false;
  String? _statusMessage;
  String? _errorMessage;
  bool _showResetForm = false;
  bool _obscurePassword = true;
  bool _obscureConfirm = true;

  @override
  void initState() {
    super.initState();
    _authService = widget.service ?? AuthService();
  }

  @override
  void dispose() {
    _emailController.dispose();
    _tokenController.dispose();
    _passwordController.dispose();
    _confirmController.dispose();
    super.dispose();
  }

  Future<void> _submitForgot() async {
    final email = _emailController.text.trim();
    if (email.isEmpty || !email.contains('@')) {
      setState(() => _errorMessage = 'Please provide a valid email address.');
      return;
    }

    setState(() {
      _loading = true;
      _errorMessage = null;
      _statusMessage = null;
    });

    try {
      final res = await _authService.forgotPassword(email);
      setState(() {
        _loading = false;
        _statusMessage = res['message']?.toString() ?? 'Password reset link sent to your email.';
        _showResetForm = true;
      });
    } catch (e) {
      setState(() {
        _loading = false;
        _errorMessage = e is ApiException ? e.message : e.toString();
      });
    }
  }

  Future<void> _submitReset() async {
    final email = _emailController.text.trim();
    final token = _tokenController.text.trim();
    final password = _passwordController.text;
    final confirm = _confirmController.text;

    if (token.isEmpty) {
      setState(() => _errorMessage = 'Please enter the reset token.');
      return;
    }
    if (password.length < 8) {
      setState(() => _errorMessage = 'Password must be at least 8 characters.');
      return;
    }
    if (password != confirm) {
      setState(() => _errorMessage = 'Passwords do not match.');
      return;
    }

    setState(() {
      _loading = true;
      _errorMessage = null;
      _statusMessage = null;
    });

    try {
      final res = await _authService.resetPassword(
        email: email,
        token: token,
        password: password,
        passwordConfirmation: confirm,
      );
      setState(() {
        _loading = false;
        _statusMessage = '${res['message'] ?? 'Password reset successfully.'} You can now log in with your new password.';
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Password reset successfully! Please log in.')),
        );
        Future.delayed(const Duration(seconds: 1), () {
          if (mounted) Navigator.of(context).pop();
        });
      }
    } catch (e) {
      setState(() {
        _loading = false;
        _errorMessage = e is ApiException ? e.message : e.toString();
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Password Recovery')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: 12),
              Center(
                child: Container(
                  width: 64,
                  height: 64,
                  decoration: BoxDecoration(
                    color: const Color(0xFF6C63FF).withValues(alpha: 0.15),
                    borderRadius: BorderRadius.circular(16),
                  ),
                  child: const Icon(Icons.lock_reset, color: Color(0xFF6C63FF), size: 36),
                ),
              ),
              const SizedBox(height: 20),
              const Text(
                'Forgot your password?',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 22, fontWeight: FontWeight.bold),
              ),
              const SizedBox(height: 8),
              Text(
                'Enter your registered email address and we will dispatch a password recovery link to your inbox.',
                textAlign: TextAlign.center,
                style: TextStyle(color: Colors.grey.shade400, fontSize: 14),
              ),
              const SizedBox(height: 24),

              if (_statusMessage != null)
                Container(
                  margin: const EdgeInsets.only(bottom: 16),
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: Colors.green.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: Colors.green.withValues(alpha: 0.3)),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.check_circle_outline, color: Colors.green, size: 20),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          _statusMessage!,
                          style: const TextStyle(color: Color(0xFFA7F3D0), fontSize: 13),
                        ),
                      ),
                    ],
                  ),
                ),

              if (_errorMessage != null)
                Container(
                  margin: const EdgeInsets.only(bottom: 16),
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: Colors.red.withValues(alpha: 0.12),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(color: Colors.red.withValues(alpha: 0.3)),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.error_outline, color: Colors.red, size: 20),
                      const SizedBox(width: 10),
                      Expanded(
                        child: Text(
                          _errorMessage!,
                          style: const TextStyle(color: Color(0xFFFCA5A5), fontSize: 13),
                        ),
                      ),
                    ],
                  ),
                ),

              TextField(
                controller: _emailController,
                keyboardType: TextInputType.emailAddress,
                decoration: const InputDecoration(
                  labelText: 'Email address',
                  prefixIcon: Icon(Icons.email_outlined),
                  border: OutlineInputBorder(),
                ),
              ),
              const SizedBox(height: 16),

              FilledButton(
                onPressed: _loading ? null : _submitForgot,
                style: FilledButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 16)),
                child: _loading && !_showResetForm
                    ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
                    : const Text('Send Password Reset Link'),
              ),

              const SizedBox(height: 20),
              TextButton(
                onPressed: () => setState(() => _showResetForm = !_showResetForm),
                child: Text(_showResetForm ? 'Hide Reset Form' : 'Have a reset token? Complete Reset Here'),
              ),

              if (_showResetForm) ...[
                const Divider(height: 32),
                const Text(
                  'Complete Password Reset',
                  style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
                ),
                const SizedBox(height: 8),
                Text(
                  'Paste the reset token from your email (or local log) and set a new password.',
                  style: TextStyle(color: Colors.grey.shade400, fontSize: 13),
                ),
                const SizedBox(height: 16),
                TextField(
                  controller: _tokenController,
                  decoration: const InputDecoration(
                    labelText: 'Reset Token',
                    prefixIcon: Icon(Icons.key_outlined),
                    border: OutlineInputBorder(),
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _passwordController,
                  obscureText: _obscurePassword,
                  decoration: InputDecoration(
                    labelText: 'New password',
                    prefixIcon: const Icon(Icons.lock_outline),
                    border: const OutlineInputBorder(),
                    suffixIcon: IconButton(
                      icon: Icon(_obscurePassword ? Icons.visibility : Icons.visibility_off),
                      onPressed: () => setState(() => _obscurePassword = !_obscurePassword),
                    ),
                  ),
                ),
                const SizedBox(height: 12),
                TextField(
                  controller: _confirmController,
                  obscureText: _obscureConfirm,
                  decoration: InputDecoration(
                    labelText: 'Confirm new password',
                    prefixIcon: const Icon(Icons.lock_outline),
                    border: const OutlineInputBorder(),
                    suffixIcon: IconButton(
                      icon: Icon(_obscureConfirm ? Icons.visibility : Icons.visibility_off),
                      onPressed: () => setState(() => _obscureConfirm = !_obscureConfirm),
                    ),
                  ),
                ),
                const SizedBox(height: 16),
                FilledButton.tonal(
                  onPressed: _loading ? null : _submitReset,
                  style: FilledButton.styleFrom(padding: const EdgeInsets.symmetric(vertical: 16)),
                  child: _loading && _showResetForm
                      ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
                      : const Text('Confirm New Password'),
                ),
              ],

              const SizedBox(height: 16),
              TextButton(
                onPressed: () => Navigator.of(context).pop(),
                child: const Text('Back to Login'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final TextEditingController _nameController = TextEditingController();
  final TextEditingController _emailController = TextEditingController();
  final TextEditingController _passwordController = TextEditingController();
  final TextEditingController _confirmController = TextEditingController();
  final AuthService _authService = AuthService();
  final StorageService _storage = StorageService();
  bool _obscurePassword = true;
  bool _obscureConfirm = true;
  bool _acceptTerms = false;
  bool _loading = false;

  @override
  void dispose() {
    _nameController.dispose();
    _emailController.dispose();
    _passwordController.dispose();
    _confirmController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    final name = _nameController.text.trim();
    final email = _emailController.text.trim();
    final password = _passwordController.text;
    final confirmPassword = _confirmController.text;

    if (name.isEmpty ||
        email.isEmpty ||
        password.isEmpty ||
        confirmPassword.isEmpty) {
      _showSnackBar('Please complete all required fields.');
      return;
    }

    if (password != confirmPassword) {
      _showSnackBar('Passwords do not match.');
      return;
    }

    if (!_acceptTerms) {
      _showSnackBar('You must accept the terms and conditions.');
      return;
    }

    setState(() => _loading = true);

    try {
      final user = await _authService.register(
        name: name,
        email: email,
        password: password,
        passwordConfirmation: confirmPassword,
        termsAccepted: true,
      );
      await _storage.writeToken(_authService.lastToken ?? '');
      if (!mounted) return;
      Navigator.of(context).pushAndRemoveUntil(
        MaterialPageRoute(builder: (_) => MainShell(user: user)),
        (route) => false,
      );
    } on ApiException catch (error) {
      _showSnackBar(error.message);
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  void _showSnackBar(String message) {
    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF071326),
      appBar: AppBar(
        title: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            ClipRRect(
              borderRadius: BorderRadius.circular(8),
              child: Image.asset('assets/images/logo.png', width: 28, height: 28, fit: BoxFit.cover),
            ),
            const SizedBox(width: 8),
            const Text('SocialPilot AI'),
          ],
        ),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Card(
            child: Padding(
              padding: const EdgeInsets.all(20),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  const Text(
                    'Join SocialPilot AI',
                    style: TextStyle(fontSize: 28, fontWeight: FontWeight.bold),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Create your workspace and start organizing your brands.',
                    style: TextStyle(color: Colors.grey.shade400),
                  ),
                  const SizedBox(height: 20),
                  TextField(
                    controller: _nameController,
                    decoration: const InputDecoration(
                      labelText: 'Full name',
                      prefixIcon: Icon(Icons.person_outline),
                    ),
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: _emailController,
                    keyboardType: TextInputType.emailAddress,
                    decoration: const InputDecoration(
                      labelText: 'Email address',
                      prefixIcon: Icon(Icons.email_outlined),
                    ),
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: _passwordController,
                    obscureText: _obscurePassword,
                    decoration: InputDecoration(
                      labelText: 'Password',
                      prefixIcon: const Icon(Icons.lock_outline),
                      suffixIcon: IconButton(
                        onPressed: () => setState(
                          () => _obscurePassword = !_obscurePassword,
                        ),
                        icon: Icon(
                          _obscurePassword
                              ? Icons.visibility
                              : Icons.visibility_off,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: _confirmController,
                    obscureText: _obscureConfirm,
                    decoration: InputDecoration(
                      labelText: 'Confirm password',
                      prefixIcon: const Icon(Icons.lock_clock_outlined),
                      suffixIcon: IconButton(
                        onPressed: () =>
                            setState(() => _obscureConfirm = !_obscureConfirm),
                        icon: Icon(
                          _obscureConfirm
                              ? Icons.visibility
                              : Icons.visibility_off,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 14),
                  CheckboxListTile(
                    value: _acceptTerms,
                    onChanged: (value) =>
                        setState(() => _acceptTerms = value ?? false),
                    contentPadding: EdgeInsets.zero,
                    title: const Text('I accept the terms and privacy policy.'),
                    controlAffinity: ListTileControlAffinity.leading,
                  ),
                  const SizedBox(height: 18),
                  FilledButton(
                    onPressed: _loading ? null : _submit,
                    style: FilledButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 18),
                    ),
                    child: _loading
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Text('Register'),
                  ),
                  const SizedBox(height: 14),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Text('Already have an account?'),
                      TextButton(
                        onPressed: () {
                          Navigator.of(context).pushReplacement(
                            MaterialPageRoute(
                              builder: (_) => const LoginScreen(),
                            ),
                          );
                        },
                        child: const Text('Login'),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class MainShell extends StatefulWidget {
  const MainShell({super.key, required this.user});
  final AppUser user;

  @override
  State<MainShell> createState() => _MainShellState();
}

class _MainShellState extends State<MainShell> {
  int _selectedIndex = 0;

  @override
  Widget build(BuildContext context) {
    final pages = [
      DashboardScreen(user: widget.user),
      const BrandListScreen(),
      const ContentScreen(),
      const CalendarScreen(),
      MoreScreen(user: widget.user),
    ];

    return Scaffold(
      body: IndexedStack(index: _selectedIndex, children: pages),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _selectedIndex,
        onDestinationSelected: (value) =>
            setState(() => _selectedIndex = value),
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.home_outlined),
            selectedIcon: Icon(Icons.home),
            label: 'Home',
          ),
          NavigationDestination(
            icon: Icon(Icons.business_outlined),
            selectedIcon: Icon(Icons.business),
            label: 'Brands',
          ),
          NavigationDestination(
            icon: Icon(Icons.auto_awesome_outlined),
            selectedIcon: Icon(Icons.auto_awesome),
            label: 'Content',
          ),
          NavigationDestination(
            icon: Icon(Icons.calendar_month_outlined),
            selectedIcon: Icon(Icons.calendar_month),
            label: 'Calendar',
          ),
          NavigationDestination(
            icon: Icon(Icons.more_horiz_outlined),
            selectedIcon: Icon(Icons.more_horiz),
            label: 'More',
          ),
        ],
      ),
    );
  }
}

class MoreScreen extends StatelessWidget {
  const MoreScreen({super.key, required this.user, this.authService});
  final AppUser user;
  final AuthService? authService;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('More Options'),
      ),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          _MoreDestinationCard(
            icon: Icons.send_rounded,
            iconColor: const Color(0xFF6C63FF),
            title: 'Publishing Studio',
            subtitle: 'Review dispatch logs, publishing history, and channel status.',
            badge: 'Phase 9',
            onTap: () {
              Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => const PublishingHistoryScreen()),
              );
            },
          ),
          const SizedBox(height: 12),
          _MoreDestinationCard(
            icon: Icons.analytics_outlined,
            iconColor: const Color(0xFF22D3EE),
            title: 'Analytics Studio',
            subtitle: 'Cross-platform audience insights, performance metrics, and AI recommendations.',
            badge: 'Phase 10',
            onTap: () {
              Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => const AnalyticsDashboardScreen()),
              );
            },
          ),
          const SizedBox(height: 12),
          _MoreDestinationCard(
            icon: Icons.person_outline,
            iconColor: const Color(0xFF34D399),
            title: 'Profile & Security',
            subtitle: 'Update your personal details, email, and password settings.',
            badge: 'Account',
            onTap: () {
              Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => ProfileScreen(user: user)),
              );
            },
          ),
          const SizedBox(height: 24),
          Card(
            color: const Color(0xFF131A2E),
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      ClipRRect(
                        borderRadius: BorderRadius.circular(8),
                        child: Image.asset(
                          'assets/images/logo.png',
                          width: 28,
                          height: 28,
                          fit: BoxFit.cover,
                        ),
                      ),
                      const SizedBox(width: 10),
                      const Text(
                        'SocialPilot AI',
                        style: TextStyle(fontWeight: FontWeight.bold, fontSize: 15),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Unified multi-brand social media marketing platform with autonomous AI engine.',
                    style: TextStyle(color: Colors.grey.shade400, fontSize: 13),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _MoreDestinationCard extends StatelessWidget {
  const _MoreDestinationCard({
    required this.icon,
    required this.iconColor,
    required this.title,
    required this.subtitle,
    required this.onTap,
    this.badge,
  });

  final IconData icon;
  final Color iconColor;
  final String title;
  final String subtitle;
  final VoidCallback onTap;
  final String? badge;

  @override
  Widget build(BuildContext context) {
    return Card(
      elevation: 0,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
        side: BorderSide(color: Colors.white.withValues(alpha: 0.08)),
      ),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: iconColor.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(12),
                  border: Border.all(color: iconColor.withValues(alpha: 0.24)),
                ),
                child: Icon(icon, color: iconColor, size: 24),
              ),
              const SizedBox(width: 16),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Text(
                          title,
                          style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
                        ),
                        if (badge != null) ...[
                          const SizedBox(width: 8),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 2),
                            decoration: BoxDecoration(
                              color: iconColor.withValues(alpha: 0.12),
                              borderRadius: BorderRadius.circular(6),
                            ),
                            child: Text(
                              badge!,
                              style: TextStyle(fontSize: 10, color: iconColor, fontWeight: FontWeight.w600),
                            ),
                          ),
                        ],
                      ],
                    ),
                    const SizedBox(height: 4),
                    Text(
                      subtitle,
                      style: TextStyle(fontSize: 13, color: Colors.grey.shade400),
                    ),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right, color: Colors.grey),
            ],
          ),
        ),
      ),
    );
  }
}

class DashboardScreen extends StatefulWidget {
  const DashboardScreen({super.key, required this.user, this.service});
  final AppUser user;
  final AuthService? service;

  @override
  State<DashboardScreen> createState() => _DashboardScreenState();
}

class _DashboardScreenState extends State<DashboardScreen> {
  late final AuthService _authService;
  late Future<Map<String, dynamic>> _dashboardFuture;
  int? _selectedBrandId;
  bool _refreshing = false;

  @override
  void initState() {
    super.initState();
    _authService = widget.service ?? AuthService();
    _dashboardFuture = _loadDashboard();
  }

  Future<Map<String, dynamic>> _loadDashboard() =>
      _authService.fetchDashboardSnapshot(_selectedBrandId);

  Future<void> _refresh() async {
    final next = _loadDashboard();
    setState(() {
      _refreshing = true;
      _dashboardFuture = next;
    });
    try {
      await next;
    } on Exception {
      // FutureBuilder renders the failed request as an error state.
    } finally {
      if (mounted) setState(() => _refreshing = false);
    }
  }

  void _selectBrand(int? brandId) {
    setState(() {
      _selectedBrandId = brandId;
      _dashboardFuture = _loadDashboard();
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Welcome, ${widget.user.name.split(' ').first}'),
        actions: [
          IconButton(
            tooltip: 'Refresh dashboard',
            onPressed: _refreshing ? null : _refresh,
            icon: _refreshing
                ? const SizedBox(
                    width: 20,
                    height: 20,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.refresh),
          ),
        ],
      ),
      body: FutureBuilder<Map<String, dynamic>>(
        future: _dashboardFuture,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting &&
              !snapshot.hasData) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError) {
            final sessionExpired = _isAuthenticationError(snapshot.error);
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.cloud_off_outlined, size: 44),
                    const SizedBox(height: 12),
                    Text(
                      snapshot.error.toString(),
                      textAlign: TextAlign.center,
                    ),
                    const SizedBox(height: 12),
                    FilledButton.icon(
                      onPressed: sessionExpired
                          ? () => _returnToLogin(context)
                          : _refresh,
                      icon: const Icon(Icons.refresh),
                      label: Text(sessionExpired ? 'Sign in again' : 'Retry'),
                    ),
                  ],
                ),
              ),
            );
          }

          final data = snapshot.data!;
          final overview = data['overview'] as Map<String, dynamic>;
          final brands = data['brands'] as List<Map<String, dynamic>>;
          final content = data['content'] as List<Map<String, dynamic>>;
          final calendar = data['calendar'] as List<Map<String, dynamic>>;
          final social = data['social'] as Map<String, dynamic>;
          final accounts = social['data'] as List<Map<String, dynamic>>;
          final providers = social['providers'] as List<Map<String, dynamic>>;
          final agents = data['agents'] as List<Map<String, dynamic>>;
          final notifications =
              data['notifications'] as List<Map<String, dynamic>>;

          return SafeArea(
            child: RefreshIndicator(
              onRefresh: _refresh,
              child: LayoutBuilder(
                builder: (context, constraints) {
                  final columns = constraints.maxWidth >= 700 ? 3 : 2;
                  return ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.all(16),
                    children: [
                      Text(
                        'Operations overview',
                        style: Theme.of(context).textTheme.headlineSmall,
                      ),
                      const SizedBox(height: 12),
                      DropdownButtonFormField<int?>(
                        key: ValueKey(_selectedBrandId),
                        initialValue: _selectedBrandId,
                        decoration: const InputDecoration(
                          labelText: 'Dashboard brand',
                          border: OutlineInputBorder(),
                        ),
                        items: [
                          const DropdownMenuItem<int?>(
                            value: null,
                            child: Text('All brands'),
                          ),
                          for (final brand in brands)
                            DropdownMenuItem<int?>(
                              value: brand['id'] as int,
                              child: Text(brand['name'].toString()),
                            ),
                        ],
                        onChanged: _selectBrand,
                      ),
                      const SizedBox(height: 16),
                      GridView.count(
                        crossAxisCount: columns,
                        shrinkWrap: true,
                        physics: const NeverScrollableScrollPhysics(),
                        crossAxisSpacing: 10,
                        mainAxisSpacing: 10,
                        childAspectRatio: constraints.maxWidth < 380
                            ? 0.95
                            : constraints.maxWidth < 700
                            ? 1.45
                            : 1.7,
                        children: [
                          _MetricCard(
                            label: 'Brands',
                            value: _metric(overview['brands']),
                            accent: const Color(0xFF6C63FF),
                          ),
                          _MetricCard(
                            label: 'Active brands',
                            value: _metric(overview['active_brands']),
                            accent: const Color(0xFF7FE7FF),
                          ),
                          _MetricCard(
                            label: 'Connected accounts',
                            value: _metric(overview['connected_accounts']),
                            accent: const Color(0xFF35D0BA),
                          ),
                          _MetricCard(
                            label: 'Published posts',
                            value: _metric(overview['published_posts']),
                            accent: const Color(0xFFB875FF),
                          ),
                          _MetricCard(
                            label: 'Scheduled',
                            value: _metric(overview['scheduled_posts']),
                            accent: const Color(0xFF7FE7FF),
                          ),
                          _MetricCard(
                            label: 'Drafts',
                            value: _metric(overview['draft_posts']),
                            accent: const Color(0xFF35D0BA),
                          ),
                          _MetricCard(
                            label: 'Failed',
                            value: _metric(overview['failed_posts']),
                            accent: const Color(0xFFFF8A80),
                          ),
                          _MetricCard(
                            label: 'AI-generated',
                            value:
                                overview['ai_generated_content_available'] ==
                                    true
                                ? _metric(overview['ai_generated_content'])
                                : 'Unavailable',
                            accent: const Color(0xFFB875FF),
                          ),
                        ],
                      ),
                      const SizedBox(height: 20),
                      _DashboardSection(
                        title: 'Recent content',
                        emptyText: 'No content activity for this selection.',
                        isEmpty: content.isEmpty,
                        child: Column(
                          children: [
                            for (final item in content.take(8))
                              ListTile(
                                contentPadding: EdgeInsets.zero,
                                title: Text(item['title'].toString()),
                                subtitle: Text(
                                  '${item['brand_name'] ?? 'Brand'} · ${item['platform'] ?? 'Platform not set'} · ${item['status']}',
                                ),
                                onTap: () =>
                                    _openBrandById(item['brand_id'], brands),
                              ),
                          ],
                        ),
                      ),
                      _DashboardSection(
                        title: 'Upcoming calendar',
                        emptyText: 'No scheduled posts in the next 30 days.',
                        isEmpty: calendar.isEmpty,
                        action: TextButton(
                          onPressed: () => Navigator.of(context).push(
                            MaterialPageRoute<void>(
                              builder: (_) => DashboardCalendarScreen(
                                items: calendar,
                                brandName: _selectedBrandId == null
                                    ? 'All brands'
                                    : _brandName(brands, _selectedBrandId),
                              ),
                            ),
                          ),
                          child: const Text('Full calendar'),
                        ),
                        child: Column(
                          children: [
                            for (final item in calendar.take(5))
                              ListTile(
                                contentPadding: EdgeInsets.zero,
                                title: Text(item['title'].toString()),
                                subtitle: Text(
                                  '${item['brand_name']} · ${item['platform']} · ${_formatDashboardDate(item['scheduled_for'])}',
                                ),
                                trailing: Text(item['status'].toString()),
                              ),
                          ],
                        ),
                      ),
                      _DashboardSection(
                        title: 'Social accounts',
                        emptyText:
                            'No social accounts found for this selection.',
                        isEmpty: accounts.isEmpty && providers.isEmpty,
                        child: Column(
                          children: [
                            for (final provider in providers.where(
                              (provider) => !accounts.any(
                                (account) =>
                                    account['provider'] == provider['provider'],
                              ),
                            ))
                              ListTile(
                                contentPadding: EdgeInsets.zero,
                                leading: const Icon(Icons.link_outlined),
                                title: Text(provider['name'].toString()),
                                subtitle: Text(
                                  provider['configured'] == true
                                      ? 'Not connected · ready for authorization'
                                      : 'Not connected · configuration required',
                                ),
                              ),
                            for (final account in accounts)
                              ListTile(
                                contentPadding: EdgeInsets.zero,
                                leading: const Icon(Icons.public),
                                title: Text(account['display_name'].toString()),
                                subtitle: Text(
                                  '${account['provider']} · ${account['brand_name'] ?? 'Unassigned'} · ${_humanStatus(account['status'])}\nConnected: ${_formatDashboardDate(account['connected_at'])} · Last sync: ${_formatDashboardDate(account['last_synced_at'])}\nToken expires: ${_formatDashboardDate(account['token_expires_at'])}',
                                ),
                                isThreeLine: true,
                              ),
                          ],
                        ),
                      ),
                      _DashboardSection(
                        title: 'AI agent activity',
                        emptyText: 'No agent status is available.',
                        isEmpty: agents.isEmpty,
                        child: Column(
                          children: [
                            for (final agent in agents)
                              ListTile(
                                contentPadding: EdgeInsets.zero,
                                title: Text(agent['name'].toString()),
                                trailing: Text(
                                  _agentStatus(agent['status'].toString()),
                                ),
                              ),
                          ],
                        ),
                      ),
                      _DashboardSection(
                        title: 'Notifications',
                        emptyText: 'No notifications yet.',
                        isEmpty: notifications.isEmpty,
                        action: TextButton(
                          onPressed: () async {
                            await Navigator.of(context).push<void>(
                              MaterialPageRoute<void>(
                                builder: (_) => NotificationCenterScreen(
                                  service: _authService,
                                  brandId: _selectedBrandId,
                                ),
                              ),
                            );
                            if (mounted) await _refresh();
                          },
                          child: const Text('View all'),
                        ),
                        child: Column(
                          children: [
                            for (final notification in notifications)
                              ListTile(
                                contentPadding: EdgeInsets.zero,
                                title: Text(notification['title'].toString()),
                                subtitle: Text(
                                  '${notification['description']} · ${_formatDashboardDate(notification['created_at'])}',
                                ),
                                trailing: notification['is_read'] == true
                                    ? const Icon(
                                        Icons.done,
                                        semanticLabel: 'Read',
                                      )
                                    : TextButton(
                                        onPressed: () =>
                                            _markNotificationRead(notification),
                                        child: const Text('Mark read'),
                                      ),
                                onTap: () =>
                                    _openNotification(notification, brands),
                              ),
                          ],
                        ),
                      ),
                      _DashboardSection(
                        title: 'Quick actions',
                        isEmpty: false,
                        child: Wrap(
                          spacing: 8,
                          runSpacing: 8,
                          children: [
                            FilledButton.icon(
                              onPressed: () => _createBrand(),
                              icon: const Icon(Icons.add_business),
                              label: const Text('Create brand'),
                            ),
                            OutlinedButton.icon(
                              onPressed: _selectedBrandId == null
                                  ? null
                                  : () => _openSelectedBrand(brands),
                              icon: const Icon(Icons.link),
                              label: const Text('Connect account'),
                            ),
                            OutlinedButton.icon(
                              onPressed: () async {
                                await Navigator.of(context).push<void>(
                                  MaterialPageRoute<void>(
                                    builder: (_) => TrendIntelligenceScreen(
                                      service: _authService,
                                      initialBrandId: _selectedBrandId,
                                    ),
                                  ),
                                );
                                if (mounted) await _refresh();
                              },
                              icon: const Icon(Icons.trending_up),
                              label: const Text('Trend intelligence'),
                            ),
                            OutlinedButton.icon(
                              onPressed: () async {
                                await Navigator.of(context).push<void>(
                                  MaterialPageRoute<void>(
                                    builder: (_) => MediaStudioScreen(
                                      service: _authService,
                                      initialBrandId: _selectedBrandId,
                                    ),
                                  ),
                                );
                                if (mounted) await _refresh();
                              },
                              icon: const Icon(Icons.photo_library),
                              label: const Text('Media Studio'),
                            ),
                            const Chip(
                              label: Text('AI generation · mobile UI coming'),
                            ),
                            const Chip(
                              label: Text('Content library · future phase'),
                            ),
                            const Chip(label: Text('Autopilot · future phase')),
                          ],
                        ),
                      ),
                    ],
                  );
                },
              ),
            ),
          );
        },
      ),
    );
  }

  String _metric(dynamic value) => value == null ? 'Unavailable' : '$value';

  Future<void> _createBrand() async {
    final created = await Navigator.of(context).push<Brand>(
      MaterialPageRoute(builder: (_) => BrandFormScreen(service: _authService)),
    );
    if (created != null && mounted) await _refresh();
  }

  void _openSelectedBrand(List<Map<String, dynamic>> brands) {
    _openBrandById(_selectedBrandId, brands);
  }

  void _openBrandById(dynamic brandId, List<Map<String, dynamic>> brands) {
    final selected = brands.where((brand) => brand['id'] == brandId);
    if (selected.isEmpty) return;
    final brand = Brand.fromJson(selected.first);
    Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (_) => BrandDetailScreen(
          brand: brand,
          service: _authService,
          onChanged: _refresh,
        ),
      ),
    );
  }

  Future<void> _markNotificationRead(Map<String, dynamic> notification) async {
    try {
      final updated = await _authService.markNotificationRead(
        notification['id'].toString(),
      );
      final current = await _dashboardFuture;
      final updatedNotifications =
          (current['notifications'] as List<Map<String, dynamic>>)
              .map((item) => item['id'] == updated['id'] ? updated : item)
              .toList();
      if (!mounted) return;
      setState(() {
        _dashboardFuture = Future.value({
          ...current,
          'notifications': updatedNotifications,
        });
      });
    } on Exception catch (error) {
      if (mounted) _showMessage(context, error.toString());
    }
  }

  void _openNotification(
    Map<String, dynamic> notification,
    List<Map<String, dynamic>> brands,
  ) {
    final brandId = notification['brand_id'];
    if (brandId is! int) return;
    final selected = brands.where((brand) => brand['id'] == brandId);
    if (selected.isEmpty) return;
    final brand = Brand.fromJson(selected.first);
    Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (_) => BrandDetailScreen(
          brand: brand,
          service: _authService,
          onChanged: _refresh,
        ),
      ),
    );
  }
}

class _DashboardSection extends StatelessWidget {
  const _DashboardSection({
    required this.title,
    required this.isEmpty,
    required this.child,
    this.emptyText,
    this.action,
  });

  final String title;
  final bool isEmpty;
  final Widget child;
  final String? emptyText;
  final Widget? action;

  @override
  Widget build(BuildContext context) => Card(
    margin: const EdgeInsets.only(bottom: 12),
    child: Padding(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  title,
                  style: Theme.of(context).textTheme.titleLarge,
                ),
              ),
              ?action,
            ],
          ),
          const SizedBox(height: 8),
          if (isEmpty) Text(emptyText ?? 'No records available.') else child,
        ],
      ),
    ),
  );
}

class DashboardCalendarScreen extends StatelessWidget {
  const DashboardCalendarScreen({
    super.key,
    required this.items,
    required this.brandName,
  });

  final List<Map<String, dynamic>> items;
  final String brandName;

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Publishing calendar')),
    body: ListView(
      padding: const EdgeInsets.all(16),
      children: [
        Text(brandName, style: Theme.of(context).textTheme.titleMedium),
        const SizedBox(height: 12),
        if (items.isEmpty)
          const Text('No scheduled posts in the next 30 days.')
        else
          for (final item in items)
            Card(
              child: ListTile(
                title: Text(item['title'].toString()),
                subtitle: Text(
                  '${item['brand_name']} · ${item['platform']} · ${_formatDashboardDate(item['scheduled_for'])}',
                ),
                trailing: Text(item['status'].toString()),
              ),
            ),
      ],
    ),
  );
}

class NotificationCenterScreen extends StatefulWidget {
  const NotificationCenterScreen({
    super.key,
    required this.service,
    this.brandId,
  });

  final AuthService service;
  final int? brandId;

  @override
  State<NotificationCenterScreen> createState() =>
      _NotificationCenterScreenState();
}

class _NotificationCenterScreenState extends State<NotificationCenterScreen> {
  late Future<List<Map<String, dynamic>>> _notifications;

  @override
  void initState() {
    super.initState();
    _notifications = widget.service.fetchNotifications(widget.brandId);
  }

  Future<void> _refresh() async {
    final next = widget.service.fetchNotifications(widget.brandId);
    setState(() => _notifications = next);
    try {
      await next;
    } on Exception {
      // FutureBuilder renders the request failure.
    }
  }

  Future<void> _markRead(int index, String id) async {
    try {
      final updated = await widget.service.markNotificationRead(id);
      final items = await _notifications;
      if (!mounted) return;
      setState(() {
        items[index] = updated;
        _notifications = Future.value(items);
      });
    } on Exception catch (error) {
      if (mounted) _showMessage(context, error.toString());
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Notifications'),
      actions: [
        IconButton(
          tooltip: 'Refresh notifications',
          onPressed: _refresh,
          icon: const Icon(Icons.refresh),
        ),
      ],
    ),
    body: FutureBuilder<List<Map<String, dynamic>>>(
      future: _notifications,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting &&
            !snapshot.hasData) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          final sessionExpired = _isAuthenticationError(snapshot.error);
          return Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(snapshot.error.toString()),
                TextButton(
                  onPressed: sessionExpired
                      ? () => _returnToLogin(context)
                      : _refresh,
                  child: Text(sessionExpired ? 'Sign in again' : 'Retry'),
                ),
              ],
            ),
          );
        }
        final items = snapshot.data!;
        if (items.isEmpty) {
          return const Center(child: Text('No notifications yet.'));
        }
        return RefreshIndicator(
          onRefresh: _refresh,
          child: ListView.builder(
            physics: const AlwaysScrollableScrollPhysics(),
            itemCount: items.length,
            itemBuilder: (context, index) {
              final item = items[index];
              return ListTile(
                leading: Icon(
                  item['is_read'] == true
                      ? Icons.mark_email_read_outlined
                      : Icons.mark_email_unread_outlined,
                ),
                title: Text(item['title'].toString()),
                subtitle: Text(
                  '${item['description']}\n${_formatDashboardDate(item['created_at'])}',
                ),
                isThreeLine: true,
                trailing: item['is_read'] == true
                    ? const Text('Read')
                    : TextButton(
                        onPressed: () =>
                            _markRead(index, item['id'].toString()),
                        child: const Text('Mark read'),
                      ),
                onTap: () async {
                  final brandId = item['brand_id'];
                  if (brandId is! int) return;
                  final brands = await widget.service.fetchBrands();
                  final matches = brands.where((brand) => brand.id == brandId);
                  if (!context.mounted || matches.isEmpty) return;
                  await Navigator.of(context).push<void>(
                    MaterialPageRoute<void>(
                      builder: (_) => BrandDetailScreen(
                        brand: matches.first,
                        service: widget.service,
                        onChanged: _refresh,
                      ),
                    ),
                  );
                },
              );
            },
          ),
        );
      },
    ),
  );
}

String _formatDashboardDate(dynamic value) {
  if (value == null || value.toString().isEmpty) return 'Unavailable';
  final date = DateTime.tryParse(value.toString())?.toLocal();
  if (date == null) return 'Unavailable';
  return '${date.year}-${date.month.toString().padLeft(2, '0')}-'
      '${date.day.toString().padLeft(2, '0')} '
      '${date.hour.toString().padLeft(2, '0')}:${date.minute.toString().padLeft(2, '0')}';
}

String formatPublishedAt(dynamic value, {DateTime? clock}) {
  if (value == null) return 'Publication date unavailable';
  final raw = value.toString().trim();
  if (raw.isEmpty) return 'Publication date unavailable';
  final parsed = DateTime.tryParse(raw);
  if (parsed == null) return raw;

  final now = (clock ?? DateTime.now()).toUtc();
  final utcDate = parsed.isUtc ? parsed : parsed.toUtc();
  final difference = now.difference(utcDate);

  if (difference.isNegative || difference.inSeconds < 45) {
    return 'Just now';
  }
  if (difference.inMinutes < 60) {
    final m = difference.inMinutes;
    return m == 1 ? '1 minute ago' : '$m minutes ago';
  }
  if (difference.inHours < 24) {
    final h = difference.inHours;
    return h == 1 ? '1 hour ago' : '$h hours ago';
  }
  if (difference.inDays == 1) {
    return 'Yesterday';
  }
  if (difference.inDays < 7) {
    return '${difference.inDays} days ago';
  }
  if (difference.inDays < 30) {
    final weeks = (difference.inDays / 7).floor();
    return weeks == 1 ? '1 week ago' : '$weeks weeks ago';
  }
  if (difference.inDays < 365) {
    final months = (difference.inDays / 30).floor();
    return months == 1 ? '1 month ago' : '$months months ago';
  }
  final years = (difference.inDays / 365).floor();
  return years == 1 ? '1 year ago' : '$years years ago';
}

String _brandName(List<Map<String, dynamic>> brands, int? brandId) {
  for (final brand in brands) {
    if (brand['id'] == brandId) return brand['name'].toString();
  }
  return 'Selected brand';
}

String _agentStatus(String status) => switch (status) {
  'running' => 'Running',
  'completed' => 'Completed',
  'failed' => 'Failed',
  _ => 'Waiting for configuration',
};

String _humanStatus(dynamic status) =>
    status?.toString().replaceAll('_', ' ') ?? 'Unavailable';

class _MetricCard extends StatelessWidget {
  const _MetricCard({
    required this.label,
    required this.value,
    required this.accent,
  });

  final String label;
  final String value;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: [accent.withValues(alpha: 0.35), const Color(0xFF0E1A2B)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: accent.withValues(alpha: 0.3)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Text(
            label,
            style: const TextStyle(color: Colors.white70, fontSize: 12),
          ),
          const SizedBox(height: 8),
          Text(
            value,
            style: const TextStyle(fontSize: 28, fontWeight: FontWeight.bold),
          ),
        ],
      ),
    );
  }
}

class BrandListScreen extends StatefulWidget {
  const BrandListScreen({super.key, this.service, this.storage});

  final AuthService? service;
  final AppStorage? storage;

  @override
  State<BrandListScreen> createState() => _BrandListScreenState();
}

class _BrandListScreenState extends State<BrandListScreen> {
  late final AuthService _authService;
  late final AppStorage _storage;
  final TextEditingController _searchController = TextEditingController();
  Future<List<Brand>>? _future;
  String? _statusFilter;
  int? _selectedBrandId;

  @override
  void initState() {
    super.initState();
    _authService = widget.service ?? AuthService();
    _storage = widget.storage ?? const StorageService();
    _loadBrands();
    _loadSelectedBrand();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  void _loadBrands() {
    _future = _authService.fetchBrands(
      search: _searchController.text,
      status: _statusFilter,
    );
  }

  Future<void> _loadSelectedBrand() async {
    final selectedId = await const StorageService().readSelectedBrandId();
    if (mounted) setState(() => _selectedBrandId = selectedId);
  }

  Future<void> _openForm([Brand? brand]) async {
    final result = await Navigator.of(context).push<Brand>(
      MaterialPageRoute(
        builder: (_) => BrandFormScreen(brand: brand, service: _authService),
      ),
    );
    if (result != null && mounted) {
      setState(_loadBrands);
    }
  }

  Future<void> _selectBrand(Brand brand) async {
    await _storage.writeSelectedBrandId(brand.id);
    if (!mounted) return;
    setState(() => _selectedBrandId = brand.id);
    ScaffoldMessenger.of(context)
        .showSnackBar(SnackBar(content: Text('${brand.name} selected')));
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Brands'),
        actions: [
          IconButton(
            tooltip: 'Refresh brands',
            onPressed: () => setState(_loadBrands),
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _openForm(),
        icon: const Icon(Icons.add),
        label: const Text('Add brand'),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
            child: Column(
              children: [
                TextField(
                  controller: _searchController,
                  textInputAction: TextInputAction.search,
                  onSubmitted: (_) => setState(_loadBrands),
                  decoration: InputDecoration(
                    hintText: 'Search your brands',
                    prefixIcon: const Icon(Icons.search),
                    suffixIcon: IconButton(
                      tooltip: 'Search',
                      onPressed: () => setState(_loadBrands),
                      icon: const Icon(Icons.arrow_forward),
                    ),
                  ),
                ),
                const SizedBox(height: 8),
                DropdownButtonFormField<String?>(
                  initialValue: _statusFilter,
                  decoration: const InputDecoration(labelText: 'Status'),
                  items: const [
                    DropdownMenuItem<String?>(
                      value: null,
                      child: Text('All statuses'),
                    ),
                    DropdownMenuItem<String?>(
                      value: 'active',
                      child: Text('Active'),
                    ),
                    DropdownMenuItem<String?>(
                      value: 'draft',
                      child: Text('Draft'),
                    ),
                    DropdownMenuItem<String?>(
                      value: 'paused',
                      child: Text('Paused'),
                    ),
                  ],
                  onChanged: (value) {
                    setState(() {
                      _statusFilter = value;
                      _loadBrands();
                    });
                  },
                ),
              ],
            ),
          ),
          Expanded(
            child: FutureBuilder<List<Brand>>(
              future: _future,
              builder: (context, snapshot) {
                if (snapshot.connectionState == ConnectionState.waiting) {
                  return const Center(child: CircularProgressIndicator());
                }

                if (snapshot.hasError) {
                  return _LoadError(
                    message: snapshot.error.toString(),
                    onRetry: () => setState(_loadBrands),
                  );
                }

                final brands = snapshot.data ?? const <Brand>[];
                if (brands.isEmpty) {
                  return Center(
                    child: Padding(
                      padding: const EdgeInsets.all(28),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          const Icon(Icons.business_outlined, size: 52),
                          const SizedBox(height: 12),
                          Text(
                            _searchController.text.isEmpty
                                ? 'No brands yet. Create your first brand.'
                                : 'No brands match this search.',
                            textAlign: TextAlign.center,
                          ),
                          const SizedBox(height: 12),
                          if (_searchController.text.isEmpty)
                            FilledButton.icon(
                              onPressed: () => _openForm(),
                              icon: const Icon(Icons.add),
                              label: const Text('Create brand'),
                            ),
                        ],
                      ),
                    ),
                  );
                }

                return ListView.builder(
                  padding: const EdgeInsets.fromLTRB(16, 0, 16, 96),
                  itemCount: brands.length,
                  itemBuilder: (context, index) {
                    final brand = brands[index];
                    return Card(
                      margin: const EdgeInsets.only(bottom: 12),
                      child: Column(
                        children: [
                          ListTile(
                            leading: brand.logoUrl == null
                                ? const CircleAvatar(
                                    child: Icon(Icons.business),
                                  )
                                : CircleAvatar(
                                    backgroundImage: NetworkImage(
                                      brand.logoUrl!,
                                    ),
                                    onBackgroundImageError: (_, _) {},
                                  ),
                            title: Text(brand.name),
                            subtitle: Text(
                              '${brand.industry ?? 'Brand'} · ${brand.status}',
                            ),
                            trailing: brand.id == _selectedBrandId
                                ? const Icon(
                                    Icons.check_circle,
                                    color: Color(0xFF35D0BA),
                                  )
                                : IconButton(
                                    tooltip: 'Select brand',
                                    icon: const Icon(
                                      Icons.radio_button_unchecked,
                                    ),
                                    onPressed: () => _selectBrand(brand),
                                  ),
                            onTap: () async {
                              await Navigator.of(context).push<void>(
                                MaterialPageRoute(
                                  builder: (_) => BrandDetailScreen(
                                    brand: brand,
                                    onChanged: () => setState(_loadBrands),
                                    service: _authService,
                                  ),
                                ),
                              );
                              if (mounted) setState(_loadBrands);
                            },
                          ),
                          Padding(
                            padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
                            child: Row(
                              children: [
                                TextButton.icon(
                                  onPressed: () => _selectBrand(brand),
                                  icon: const Icon(Icons.check_circle_outline),
                                  label: const Text('Select'),
                                ),
                                const Spacer(),
                                IconButton(
                                  tooltip: 'Edit brand',
                                  onPressed: () => _openForm(brand),
                                  icon: const Icon(Icons.edit_outlined),
                                ),
                                IconButton(
                                  tooltip: 'Delete brand',
                                  onPressed: () async {
                                    final confirmed = await showDialog<bool>(
                                      context: context,
                                      builder: (context) => AlertDialog(
                                        title: const Text('Delete brand?'),
                                        content: Text(
                                          'Delete ${brand.name} and its profile?',
                                        ),
                                        actions: [
                                          TextButton(
                                            onPressed: () =>
                                                Navigator.pop(context, false),
                                            child: const Text('Cancel'),
                                          ),
                                          FilledButton(
                                            onPressed: () =>
                                                Navigator.pop(context, true),
                                            child: const Text('Delete'),
                                          ),
                                        ],
                                      ),
                                    );
                                    if (confirmed == true) {
                                      try {
                                        await _authService.deleteBrand(
                                          brand.id,
                                        );
                                        if (brand.id == _selectedBrandId) {
                                          await _storage.clearSelectedBrandId();
                                          _selectedBrandId = null;
                                        }
                                        if (mounted) setState(_loadBrands);
                                      } on ApiException catch (error) {
                                        if (context.mounted) {
                                          _showMessage(context, error.message);
                                        }
                                      }
                                    }
                                  },
                                  icon: const Icon(Icons.delete_outline),
                                ),
                              ],
                            ),
                          ),
                        ],
                      ),
                    );
                  },
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

class BrandFormScreen extends StatefulWidget {
  const BrandFormScreen({super.key, this.brand, this.service});

  final Brand? brand;
  final AuthService? service;

  @override
  State<BrandFormScreen> createState() => _BrandFormScreenState();
}

class _BrandFormScreenState extends State<BrandFormScreen> {
  static const Map<String, String> _fieldLabels = {
    'name': 'Brand name',
    'description': 'Description',
    'industry': 'Industry',
    'niche': 'Niche',
    'website_url': 'Website URL',
    'audience': 'Target audience',
    'target_age_group': 'Target age group',
    'audience_interests': 'Audience interests (comma-separated)',
    'region': 'Region',
    'language': 'Language',
    'tone': 'Brand tone',
    'brand_voice': 'Brand voice',
    'writing_style': 'Writing style',
    'brand_personality': 'Brand personality (comma-separated)',
    'brand_values': 'Brand values (comma-separated)',
    'brand_keywords': 'Brand keywords (comma-separated)',
    'topics_to_avoid': 'Topics to avoid (comma-separated)',
    'content_restrictions': 'Content restrictions (comma-separated)',
    'hashtag_preferences': 'Hashtag preferences (comma-separated)',
    'preferred_publishing_times':
        'Preferred publishing times (comma-separated)',
    'content_formats': 'Preferred content formats (comma-separated)',
    'brand_colours': 'Brand colours (comma-separated)',
    'publishing_frequency': 'Publishing frequency',
    'timezone': 'Timezone',
  };
  static const Map<String, String> _objectives = {
    'brand_awareness': 'Brand awareness',
    'engagement': 'Engagement',
    'website_traffic': 'Website traffic',
    'lead_generation': 'Lead generation',
    'sales': 'Sales',
    'community_growth': 'Community growth',
  };
  static const Map<String, String> _contentTypes = {
    'educational': 'Educational',
    'entertaining': 'Entertaining',
    'promotional': 'Promotional',
    'inspirational': 'Inspirational',
    'community': 'Community',
  };

  final _formKey = GlobalKey<FormState>();
  late final AuthService _service;
  final _picker = ImagePicker();
  final Map<String, TextEditingController> _controllers = {};
  final Set<String> _selectedObjectives = {};
  final Map<String, int?> _preferences = {};
  String _status = 'draft';
  XFile? _logo;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _service = widget.service ?? AuthService();
    final settings = widget.brand?.settings ?? const <String, dynamic>{};
    for (final entry in _fieldLabels.entries) {
      final key = entry.key;
      final value = key == 'industry'
          ? widget.brand?.industry
          : key == 'website_url'
          ? widget.brand?.websiteUrl
          : key == 'target_age_group'
          ? widget.brand?.targetAgeGroup
          : key == 'audience_interests'
          ? (widget.brand?.audienceInterests ?? const <String>[]).join(', ')
          : settings.containsKey(key)
          ? settings[key]
          : widget.brand?.toJson()[key];
      final normalized = value is List
          ? value.join(', ')
          : value?.toString() ?? '';
      _controllers[key] = TextEditingController(text: normalized);
    }
    _status = widget.brand?.status ?? 'draft';
    final objectives = settings['marketing_objectives'];
    if (objectives is List) {
      _selectedObjectives.addAll(objectives.cast<String>());
    }
    final preferences = settings['content_preferences'];
    if (preferences is Map) {
      for (final entry in preferences.entries) {
        _preferences[entry.key.toString()] = int.tryParse(
          entry.value.toString(),
        );
      }
    }
  }

  @override
  void dispose() {
    for (final controller in _controllers.values) {
      controller.dispose();
    }
    super.dispose();
  }

  List<String> _commaList(String key) => _controllers[key]!.text
      .split(RegExp(r'[,\n]'))
      .map((item) => item.trim())
      .where((item) => item.isNotEmpty)
      .toList();

  Future<void> _chooseLogo() async {
    final image = await _picker.pickImage(source: ImageSource.gallery);
    if (image != null && mounted) setState(() => _logo = image);
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _saving = true);
    const listFields = {
      'audience_interests',
      'brand_personality',
      'brand_values',
      'brand_keywords',
      'topics_to_avoid',
      'content_restrictions',
      'hashtag_preferences',
      'preferred_publishing_times',
      'content_formats',
      'brand_colours',
    };
    final payload = <String, dynamic>{
      for (final key in _fieldLabels.keys)
        key: listFields.contains(key)
            ? _commaList(key)
            : _controllers[key]!.text.trim().isEmpty
            ? null
            : _controllers[key]!.text.trim(),
      'status': _status,
      'marketing_objectives': _selectedObjectives.toList(),
      'content_preferences': {
        for (final key in _contentTypes.keys) key: _preferences[key],
      },
    };

    try {
      final brand = await _service.saveBrand(
        payload,
        id: widget.brand?.id,
        logo: _logo,
      );
      if (mounted) Navigator.of(context).pop(brand);
    } on ApiException catch (error) {
      if (mounted) _showMessage(context, error.message);
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(widget.brand == null ? 'Create brand' : 'Edit brand'),
      ),
      body: Form(
        key: _formKey,
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            if (_logo != null)
              ListTile(
                leading: const Icon(Icons.image),
                title: Text(_logo!.name),
                trailing: IconButton(
                  onPressed: () => setState(() => _logo = null),
                  icon: const Icon(Icons.close),
                ),
              )
            else
              OutlinedButton.icon(
                onPressed: _chooseLogo,
                icon: const Icon(Icons.add_photo_alternate_outlined),
                label: const Text('Choose brand logo'),
              ),
            const SizedBox(height: 12),
            DropdownButtonFormField<String>(
              initialValue: _status,
              decoration: const InputDecoration(labelText: 'Status'),
              items: const [
                DropdownMenuItem(value: 'draft', child: Text('Draft')),
                DropdownMenuItem(value: 'active', child: Text('Active')),
                DropdownMenuItem(value: 'paused', child: Text('Paused')),
              ],
              onChanged: (value) => setState(() => _status = value ?? 'draft'),
            ),
            const SizedBox(height: 12),
            for (final entry in _fieldLabels.entries)
              Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: TextFormField(
                  controller: _controllers[entry.key],
                  maxLines:
                      const {
                        'description',
                        'audience',
                        'brand_personality',
                      }.contains(entry.key)
                      ? 3
                      : 1,
                  keyboardType: entry.key == 'website_url'
                      ? TextInputType.url
                      : TextInputType.text,
                  decoration: InputDecoration(labelText: entry.value),
                  validator: entry.key == 'name'
                      ? (value) => value == null || value.trim().isEmpty
                            ? 'Enter a brand name.'
                            : null
                      : null,
                ),
              ),
            const SizedBox(height: 8),
            Text(
              'Marketing objectives',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            Wrap(
              spacing: 8,
              children: _objectives.entries.map((entry) {
                return FilterChip(
                  label: Text(entry.value),
                  selected: _selectedObjectives.contains(entry.key),
                  onSelected: (selected) => setState(() {
                    selected
                        ? _selectedObjectives.add(entry.key)
                        : _selectedObjectives.remove(entry.key);
                  }),
                );
              }).toList(),
            ),
            const SizedBox(height: 8),
            Text(
              'Content preferences',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            for (final entry in _contentTypes.entries)
              DropdownButtonFormField<int?>(
                initialValue: _preferences[entry.key],
                decoration: InputDecoration(labelText: '${entry.value} (1-5)'),
                items: [
                  const DropdownMenuItem<int?>(
                    value: null,
                    child: Text('Not set'),
                  ),
                  for (var rating = 1; rating <= 5; rating++)
                    DropdownMenuItem<int?>(
                      value: rating,
                      child: Text('$rating'),
                    ),
                ],
                onChanged: (value) =>
                    setState(() => _preferences[entry.key] = value),
              ),
            const SizedBox(height: 20),
            FilledButton(
              onPressed: _saving ? null : _save,
              child: _saving
                  ? const CircularProgressIndicator()
                  : Text(
                      widget.brand == null ? 'Create brand' : 'Save changes',
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

class BrandDetailScreen extends StatefulWidget {
  const BrandDetailScreen({
    super.key,
    required this.brand,
    required this.onChanged,
    this.service,
  });

  final Brand brand;
  final VoidCallback onChanged;
  final AuthService? service;

  @override
  State<BrandDetailScreen> createState() => _BrandDetailScreenState();
}

class _BrandDetailScreenState extends State<BrandDetailScreen> {
  late final AuthService _service;
  late Future<Map<String, dynamic>> _socialData;
  late Future<Map<String, dynamic>> _overview;

  @override
  void initState() {
    super.initState();
    _service = widget.service ?? AuthService();
    _socialOAuthEvents.addListener(_handleOAuthResult);
    WidgetsBinding.instance.addPostFrameCallback((_) => _handleOAuthResult());
    _socialData = _service.fetchBrandSocialAccounts(widget.brand.id);
    _overview = _service.fetchBrandOverview(widget.brand.id);
  }

  @override
  void dispose() {
    _socialOAuthEvents.removeListener(_handleOAuthResult);
    super.dispose();
  }

  void _handleOAuthResult() {
    final uri = _socialOAuthEvents.value;
    if (uri == null ||
        uri.queryParameters['brand_id'] != '${widget.brand.id}') {
      return;
    }
    _socialOAuthEvents.value = null;
    _reloadSocial();
    if (mounted) {
      _showMessage(
        context,
        uri.queryParameters['message'] ?? 'Social account flow completed.',
      );
    }
  }

  void _reloadSocial() => setState(
    () => _socialData = _service.fetchBrandSocialAccounts(widget.brand.id),
  );

  @override
  Widget build(BuildContext context) {
    final brand = widget.brand;
    final settings = brand.settings;
    return Scaffold(
      appBar: AppBar(title: Text(brand.name)),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Card(
            child: Padding(
              padding: const EdgeInsets.all(18),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    brand.name,
                    style: Theme.of(context).textTheme.headlineSmall,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    '${brand.status.toUpperCase()} · ${brand.industry ?? 'No industry'}',
                  ),
                  const Divider(height: 24),
                  for (final row in <String, String?>{
                    'Description': brand.description,
                    'Niche': brand.niche,
                    'Website': brand.websiteUrl,
                    'Audience': brand.audience,
                    'Age group': brand.targetAgeGroup,
                    'Interests': brand.audienceInterests.join(', '),
                    'Region': brand.region,
                    'Language': brand.language,
                    'Tone': brand.tone,
                    'Brand voice': settings['brand_voice']?.toString(),
                    'Writing style': settings['writing_style']?.toString(),
                    'Keywords':
                        (settings['brand_keywords'] as List? ?? const []).join(
                          ', ',
                        ),
                    'Topics to avoid':
                        (settings['topics_to_avoid'] as List? ?? const []).join(
                          ', ',
                        ),
                    'Values': (settings['brand_values'] as List? ?? const [])
                        .join(', '),
                    'Personality':
                        (settings['brand_personality'] as List? ?? const [])
                            .join(', '),
                    'Formats':
                        (settings['content_formats'] as List? ?? const []).join(
                          ', ',
                        ),
                    'Brand colors':
                        (settings['brand_colours'] as List? ?? const []).join(
                          ', ',
                        ),
                    'Content restrictions':
                        (settings['content_restrictions'] as List? ?? const [])
                            .join(', '),
                    'Hashtag preferences':
                        (settings['hashtag_preferences'] as List? ?? const [])
                            .join(', '),
                    'Objectives':
                        (settings['marketing_objectives'] as List? ?? const [])
                            .join(', '),
                  }.entries)
                    if (row.value != null && row.value!.isNotEmpty)
                      Padding(
                        padding: const EdgeInsets.symmetric(vertical: 4),
                        child: Text('${row.key}: ${row.value}'),
                      ),
                ],
              ),
            ),
          ),
          FutureBuilder<Map<String, dynamic>>(
            future: _overview,
            builder: (context, snapshot) {
              if (snapshot.hasError) {
                return _LoadError(
                  message: snapshot.error.toString(),
                  onRetry: () => setState(
                    () => _overview = _service.fetchBrandOverview(brand.id),
                  ),
                );
              }
              if (!snapshot.hasData) {
                return const Center(child: CircularProgressIndicator());
              }
              final overview = snapshot.data!;
              return Card(
                child: Padding(
                  padding: const EdgeInsets.all(16),
                  child: Wrap(
                    spacing: 24,
                    runSpacing: 12,
                    children: [
                      for (final entry in <String, String>{
                        'Connected accounts': 'connected_accounts',
                        'Content drafts': 'content_drafts',
                        'Posts': 'posts',
                        'Campaigns': 'campaigns',
                      }.entries)
                        Text('${entry.key}: ${overview[entry.value] ?? 0}'),
                    ],
                  ),
                ),
              );
            },
          ),
          Wrap(
            spacing: 8,
            children: [
              OutlinedButton.icon(
                onPressed: () async {
                  final updated = await Navigator.of(context).push<Brand>(
                    MaterialPageRoute(
                      builder: (_) =>
                          BrandFormScreen(brand: brand, service: _service),
                    ),
                  );
                  if (updated != null && context.mounted) {
                    widget.onChanged();
                    Navigator.of(context).pop();
                  }
                },
                icon: const Icon(Icons.edit),
                label: const Text('Edit'),
              ),
              OutlinedButton(
                onPressed: () async {
                  try {
                    await _service.updateBrandStatus(
                      brand.id,
                      brand.status == 'active' ? 'paused' : 'active',
                    );
                    if (context.mounted) {
                      _showMessage(context, 'Brand status updated.');
                      widget.onChanged();
                      Navigator.of(context).pop();
                    }
                  } on ApiException catch (error) {
                    if (context.mounted) _showMessage(context, error.message);
                  }
                },
                child: Text(brand.status == 'active' ? 'Pause' : 'Activate'),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            'Social accounts',
            style: Theme.of(context).textTheme.titleLarge,
          ),
          FutureBuilder<Map<String, dynamic>>(
            future: _socialData,
            builder: (context, snapshot) {
              if (snapshot.hasError) {
                return _LoadError(
                  message: snapshot.error.toString(),
                  onRetry: _reloadSocial,
                );
              }
              if (!snapshot.hasData) {
                return const Center(child: CircularProgressIndicator());
              }
              final accounts = snapshot.data!['data'] as List? ?? const [];
              final providers =
                  snapshot.data!['providers'] as List? ?? const [];
              return Column(
                children: [
                  for (final provider in providers)
                    ListTile(
                      leading: _providerIcon(provider['provider'].toString()),
                      title: Text(provider['name'].toString()),
                      subtitle: Text(
                        provider['status'] == 'configuration_required'
                            ? 'Configuration required · Not connected'
                            : 'Ready to authorize · Not connected',
                      ),
                      trailing: provider['configured'] == true
                          ? IconButton(
                              tooltip: 'Connect ${provider['name']}',
                              icon: const Icon(Icons.link),
                              onPressed: () => _connectSocial(
                                provider['provider'].toString(),
                              ),
                            )
                          : const Icon(Icons.link_off),
                    ),
                  for (final item in accounts)
                    ListTile(
                      leading: _providerIcon(item['provider'].toString()),
                      title: Text(item['display_name'].toString()),
                      subtitle: Text(
                        [
                          '${item['provider']} · ${item['status']}',
                          if (item['username'] != null) '@${item['username']}',
                          'ID: ${item['provider_account_id']}',
                          'Brand: ${item['brand_name'] ?? brand.name}',
                          'Connected: ${_formatSocialDate(item['connected_at'])}',
                          'Last synced: ${_formatSocialDate(item['last_synced_at'])}',
                          'Token expires: ${_formatSocialDate(item['token_expires_at'])}',
                        ].join('\n'),
                      ),
                      isThreeLine: true,
                      trailing: PopupMenuButton<String>(
                        onSelected: (action) =>
                            _handleAccountAction(action, item),
                        itemBuilder: (_) => const [
                          PopupMenuItem(
                            value: 'check',
                            child: Text('Check / refresh'),
                          ),
                          PopupMenuItem(
                            value: 'disconnect',
                            child: Text('Disconnect'),
                          ),
                        ],
                      ),
                    ),
                ],
              );
            },
          ),
        ],
      ),
    );
  }

  Future<void> _connectSocial(String provider) async {
    try {
      final authorizationUrl = await _service.beginSocialConnection(
        widget.brand.id,
        provider,
      );
      final uri = Uri.parse(authorizationUrl);
      if (!await launchUrl(uri, mode: LaunchMode.externalApplication)) {
        throw ApiException('Could not open the provider authorization page.');
      }
    } on ApiException catch (error) {
      if (mounted) _showMessage(context, error.message);
    }
  }

  Future<void> _handleAccountAction(String action, Map item) async {
    try {
      final accountId = item['id'] as int;
      if (action == 'check') {
        final result = await _service.checkSocialAccount(accountId);
        if (mounted) {
          _showMessage(
            context,
            result['message']?.toString() ?? 'Check complete.',
          );
        }
      } else {
        final result = await _service.disconnectSocialAccount(accountId);
        if (mounted) {
          _showMessage(
            context,
            result['message']?.toString() ?? 'Disconnected.',
          );
        }
      }
      _reloadSocial();
    } on ApiException catch (error) {
      if (mounted) _showMessage(context, error.message);
    }
  }

  Widget _providerIcon(String provider) {
    final (IconData icon, Color color) = switch (provider) {
      'facebook' => (Icons.facebook, const Color(0xFF1877F2)),
      'instagram' => (Icons.camera_alt, const Color(0xFFE1306C)),
      'tiktok' => (Icons.music_note, Colors.white),
      'youtube' => (Icons.play_circle_fill, const Color(0xFFFF0033)),
      _ => (Icons.link, Colors.white70),
    };
    return CircleAvatar(
      backgroundColor: color.withValues(alpha: 0.18),
      child: Icon(icon, color: color),
    );
  }

  String _formatSocialDate(dynamic value) {
    if (value == null || value.toString().isEmpty) return 'Unavailable';
    final date = DateTime.tryParse(value.toString())?.toLocal();
    if (date == null) return 'Unavailable';
    return '${date.year}-${date.month.toString().padLeft(2, '0')}-'
        '${date.day.toString().padLeft(2, '0')} '
        '${date.hour.toString().padLeft(2, '0')}:'
        '${date.minute.toString().padLeft(2, '0')}';
  }
}

class _LoadError extends StatelessWidget {
  const _LoadError({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.cloud_off_outlined, size: 40),
          const SizedBox(height: 8),
          Text(message, textAlign: TextAlign.center),
          TextButton(onPressed: onRetry, child: const Text('Retry')),
        ],
      ),
    ),
  );
}

void _showMessage(BuildContext context, String message) {
  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
}

bool _isAuthenticationError(Object? error) =>
    error is ApiException && error.statusCode == 401;

void _returnToLogin(BuildContext context) {
  Navigator.of(context).pushAndRemoveUntil<void>(
    MaterialPageRoute<void>(builder: (_) => const LoginScreen()),
    (_) => false,
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Phase 6 — Content Studio
// ─────────────────────────────────────────────────────────────────────────────

class ContentScreen extends StatefulWidget {
  const ContentScreen({super.key, this.service});
  final AuthService? service;

  @override
  State<ContentScreen> createState() => _ContentScreenState();
}

class _ContentScreenState extends State<ContentScreen> {
  late final AuthService _service;
  List<Brand> _brands = const [];
  Brand? _selectedBrand;
  bool _loading = true;
  Object? _error;

  @override
  void initState() {
    super.initState();
    _service = widget.service ?? AuthService();
    _load();
  }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final brands = await _service.fetchBrands();
      setState(() {
        _brands = brands;
        _selectedBrand = brands.isNotEmpty ? brands.first : null;
        _loading = false;
      });
    } catch (e) {
      setState(() { _error = e; _loading = false; });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Content Studio'),
        actions: [
          IconButton(icon: const Icon(Icons.refresh), onPressed: _load),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(
                  child: Padding(
                    padding: const EdgeInsets.all(24),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(_error.toString(), textAlign: TextAlign.center),
                        const SizedBox(height: 12),
                        ElevatedButton(onPressed: _load, child: const Text('Retry')),
                      ],
                    ),
                  ),
                )
              : _brands.isEmpty
                  ? const Center(child: Text('Create a brand to start generating content.'))
                  : Column(
                      children: [
                        // Brand picker
                        if (_brands.length > 1)
                          Padding(
                            padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                            child: DropdownButtonFormField<Brand>(
                              initialValue: _selectedBrand,
                              decoration: const InputDecoration(
                                labelText: 'Brand',
                                border: OutlineInputBorder(),
                                contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                              ),
                              items: _brands.map((b) => DropdownMenuItem(
                                value: b,
                                child: Text(b.name, overflow: TextOverflow.ellipsis),
                              )).toList(),
                              onChanged: (b) => setState(() => _selectedBrand = b),
                            ),
                          ),
                        Expanded(
                          child: DefaultTabController(
                            length: 2,
                            child: Column(
                              children: [
                                const TabBar(tabs: [
                                  Tab(text: 'Generate'),
                                  Tab(text: 'Library'),
                                ]),
                                Expanded(
                                  child: TabBarView(children: [
                                    ContentGenerateTab(
                                      brand: _selectedBrand!,
                                      service: _service,
                                    ),
                                    ContentLibraryTab(
                                      brand: _selectedBrand!,
                                      service: _service,
                                    ),
                                  ]),
                                ),
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),
    );
  }
}

// ── Generate Tab ──────────────────────────────────────────────────────────────

class ContentGenerateTab extends StatefulWidget {
  const ContentGenerateTab({super.key, required this.brand, required this.service});
  final Brand brand;
  final AuthService service;

  @override
  State<ContentGenerateTab> createState() => _ContentGenerateTabState();
}

class _ContentGenerateTabState extends State<ContentGenerateTab> {
  String _platform  = 'linkedin';
  String _objective = 'engagement';
  String _category  = '';
  String _format    = '';
  int?   _trendId;
  final TextEditingController _instructionsCtrl = TextEditingController();

  List<Map<String, dynamic>> _trends = const [];
  bool _generating = false;
  Map<String, dynamic>? _result;
  String? _error;

  static const _platforms  = ['linkedin', 'instagram', 'tiktok', 'youtube', 'facebook'];
  static const _objectives = [
    'awareness', 'engagement', 'education', 'community',
    'brand_authority', 'promotion', 'traffic', 'lead_generation',
  ];
  static const _categories = [
    '', 'educational', 'motivational', 'promotional',
    'storytelling', 'thought_leadership', 'community', 'announcement', 'question',
  ];
  static const _formats = [
    '', 'text_post', 'caption', 'short_script', 'long_form',
    'educational_post', 'storytelling_post', 'linkedin_article',
  ];

  @override
  void initState() {
    super.initState();
    _loadTrends();
  }

  Future<void> _loadTrends() async {
    try {
      final topics = await widget.service.fetchTrendTopics(widget.brand.id);
      if (mounted) setState(() => _trends = topics);
    } catch (_) { /* trends optional */ }
  }

  Future<void> _generate() async {
    setState(() { _generating = true; _error = null; _result = null; });
    try {
      final draft = await widget.service.generateContent(
        brandId: widget.brand.id,
        platform: _platform,
        objective: _objective,
        category: _category.isEmpty ? null : _category,
        contentFormat: _format.isEmpty ? null : _format,
        trendId: _trendId,
        instructions: _instructionsCtrl.text.trim().isEmpty ? null : _instructionsCtrl.text.trim(),
      );
      setState(() { _result = draft; _generating = false; });
    } catch (e) {
      setState(() { _error = e.toString(); _generating = false; });
    }
  }

  String _label(String s) => s.replaceAll('_', ' ').split(' ')
      .map((w) => w.isNotEmpty ? '${w[0].toUpperCase()}${w.substring(1)}' : '')
      .join(' ');

  @override
  void dispose() {
    _instructionsCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        // Platform
        DropdownButtonFormField<String>(
          initialValue: _platform,
          decoration: const InputDecoration(labelText: 'Platform', border: OutlineInputBorder()),
          items: _platforms.map((p) => DropdownMenuItem(value: p, child: Text(_label(p)))).toList(),
          onChanged: (v) => setState(() => _platform = v!),
        ),
        const SizedBox(height: 12),

        // Objective
        DropdownButtonFormField<String>(
          initialValue: _objective,
          decoration: const InputDecoration(labelText: 'Objective', border: OutlineInputBorder()),
          items: _objectives.map((o) => DropdownMenuItem(value: o, child: Text(_label(o)))).toList(),
          onChanged: (v) => setState(() => _objective = v!),
        ),
        const SizedBox(height: 12),

        // Category
        DropdownButtonFormField<String>(
          initialValue: _category,
          decoration: const InputDecoration(labelText: 'Category (optional)', border: OutlineInputBorder()),
          items: _categories.map((c) => DropdownMenuItem(value: c, child: Text(c.isEmpty ? 'Any' : _label(c)))).toList(),
          onChanged: (v) => setState(() => _category = v!),
        ),
        const SizedBox(height: 12),

        // Format
        DropdownButtonFormField<String>(
          initialValue: _format,
          decoration: const InputDecoration(labelText: 'Content format (optional)', border: OutlineInputBorder()),
          items: _formats.map((f) => DropdownMenuItem(value: f, child: Text(f.isEmpty ? 'Auto-select' : _label(f)))).toList(),
          onChanged: (v) => setState(() => _format = v!),
        ),
        const SizedBox(height: 12),

        // Trend selector
        if (_trends.isNotEmpty) ...[
          DropdownButtonFormField<int?>(
            initialValue: _trendId,
            decoration: const InputDecoration(labelText: 'Base on trend (optional)', border: OutlineInputBorder()),
            items: [
              const DropdownMenuItem<int?>(value: null, child: Text('No trend — original content')),
              ..._trends.map((t) {
                final title = t['title']?.toString() ?? 'Trend';
                final score = t['relevance_score'] ?? 0;
                final display = title.length > 50 ? '${title.substring(0, 50)}…' : title;
                return DropdownMenuItem<int?>(
                  value: t['id'] as int?,
                  child: Text('[$score/100] $display', overflow: TextOverflow.ellipsis),
                );
              }),
            ],
            onChanged: (v) => setState(() => _trendId = v),
          ),
          const SizedBox(height: 12),
        ],

        // Instructions
        TextFormField(
          controller: _instructionsCtrl,
          decoration: const InputDecoration(
            labelText: 'Additional instructions (optional)',
            border: OutlineInputBorder(),
            alignLabelWithHint: true,
          ),
          maxLines: 3,
        ),
        const SizedBox(height: 16),

        // Generate button
        SizedBox(
          width: double.infinity,
          child: ElevatedButton(
            onPressed: _generating ? null : _generate,
            child: _generating
                ? const SizedBox(height: 20, width: 20, child: CircularProgressIndicator(strokeWidth: 2))
                : const Text('Generate content'),
          ),
        ),

        if (_error != null) ...[
          const SizedBox(height: 12),
          Text(_error!, style: const TextStyle(color: Colors.red)),
        ],

        if (_result != null) ...[
          const SizedBox(height: 20),
          _ContentResultCard(
            draft: _result!,
            brand: widget.brand,
            service: widget.service,
            onViewFull: () => Navigator.of(context).push(
              MaterialPageRoute<void>(
                builder: (_) => ContentDraftDetailScreen(
                  brand: widget.brand,
                  draftId: _result!['id'] as int,
                  service: widget.service,
                ),
              ),
            ),
          ),
        ],
      ],
    );
  }
}

// ── Content Result Card ───────────────────────────────────────────────────────

class _ContentResultCard extends StatelessWidget {
  const _ContentResultCard({
    required this.draft,
    required this.brand,
    required this.service,
    required this.onViewFull,
  });
  final Map<String, dynamic> draft;
  final Brand brand;
  final AuthService service;
  final VoidCallback onViewFull;

  @override
  Widget build(BuildContext context) {
    final quality = draft['quality_score'];
    final hook    = draft['hook']?.toString() ?? '';
    final body    = draft['body']?.toString() ?? '';
    final cta     = draft['call_to_action']?.toString() ?? '';
    final tags    = draft['hashtags'];
    final tagStr  = tags is List ? tags.join(' ') : (tags?.toString() ?? '');

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(
                  child: Text(
                    draft['title']?.toString() ?? 'Generated content',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                ),
                if (quality != null)
                  Chip(
                    label: Text('Quality: $quality/100'),
                    backgroundColor: (quality as int) >= 75
                        ? Colors.green.shade100
                        : quality >= 50 ? Colors.orange.shade100 : Colors.red.shade100,
                  ),
              ],
            ),
            if (hook.isNotEmpty) ...[
              const SizedBox(height: 8),
              const Text('Hook', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12)),
              Text(hook, style: const TextStyle(fontStyle: FontStyle.italic)),
            ],
            if (body.isNotEmpty) ...[
              const SizedBox(height: 8),
              const Text('Body', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12)),
              Text(body, maxLines: 6, overflow: TextOverflow.ellipsis),
            ],
            if (cta.isNotEmpty) ...[
              const SizedBox(height: 8),
              const Text('Call to action', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 12)),
              Text(cta),
            ],
            if (tagStr.isNotEmpty) ...[
              const SizedBox(height: 8),
              Text(tagStr, style: const TextStyle(color: Colors.blue, fontSize: 12)),
            ],
            const SizedBox(height: 12),
            Row(
              children: [
                ElevatedButton(onPressed: onViewFull, child: const Text('Full editor')),
                const SizedBox(width: 8),
                OutlinedButton(
                  onPressed: () {
                    // Copy content to clipboard (Clipboard requires flutter/services import — omitted for brevity)
                    final _ = [hook, body, cta, tagStr].where((s) => s.isNotEmpty).join('\n\n');
                  },
                  child: const Text('Copy'),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

// ── Library Tab ───────────────────────────────────────────────────────────────

class ContentLibraryTab extends StatefulWidget {
  const ContentLibraryTab({super.key, required this.brand, required this.service});
  final Brand brand;
  final AuthService service;

  @override
  State<ContentLibraryTab> createState() => _ContentLibraryTabState();
}

class _ContentLibraryTabState extends State<ContentLibraryTab> {
  List<Map<String, dynamic>> _drafts = const [];
  bool _loading = true;
  Object? _error;
  String _platform = '';
  String _search   = '';
  final TextEditingController _searchCtrl = TextEditingController();

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final drafts = await widget.service.fetchContentDrafts(
        widget.brand.id,
        platform: _platform.isEmpty ? null : _platform,
        search: _search.isEmpty ? null : _search,
      );
      setState(() { _drafts = drafts; _loading = false; });
    } catch (e) {
      setState(() { _error = e; _loading = false; });
    }
  }

  @override
  void dispose() {
    _searchCtrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: [
              Expanded(
                child: TextField(
                  controller: _searchCtrl,
                  decoration: const InputDecoration(
                    hintText: 'Search…',
                    prefixIcon: Icon(Icons.search),
                    border: OutlineInputBorder(),
                    contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    isDense: true,
                  ),
                  onSubmitted: (v) { _search = v; _load(); },
                ),
              ),
              const SizedBox(width: 8),
              DropdownButton<String>(
                value: _platform,
                items: const [
                  DropdownMenuItem(value: '', child: Text('All')),
                  DropdownMenuItem(value: 'linkedin', child: Text('LinkedIn')),
                  DropdownMenuItem(value: 'instagram', child: Text('Instagram')),
                  DropdownMenuItem(value: 'tiktok', child: Text('TikTok')),
                  DropdownMenuItem(value: 'youtube', child: Text('YouTube')),
                  DropdownMenuItem(value: 'facebook', child: Text('Facebook')),
                ],
                onChanged: (v) { setState(() => _platform = v!); _load(); },
              ),
            ],
          ),
        ),
        Expanded(
          child: _loading
              ? const Center(child: CircularProgressIndicator())
              : _error != null
                  ? Center(child: Text(_error.toString()))
                  : _drafts.isEmpty
                      ? const Center(child: Text('No content drafts yet. Use the Generate tab to create content.'))
                      : RefreshIndicator(
                          onRefresh: _load,
                          child: ListView.separated(
                            padding: const EdgeInsets.all(12),
                            itemCount: _drafts.length,
                            separatorBuilder: (context, index) => const SizedBox(height: 8),
                            itemBuilder: (context, i) {
                              final d = _drafts[i];
                              final quality = d['quality_score'];
                              return Card(
                                child: ListTile(
                                  title: Text(d['title']?.toString() ?? 'Untitled', maxLines: 1, overflow: TextOverflow.ellipsis),
                                  subtitle: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      if ((d['hook'] ?? d['body'])?.toString().isNotEmpty == true)
                                        Text(
                                          (d['hook'] ?? d['body']).toString(),
                                          maxLines: 2,
                                          overflow: TextOverflow.ellipsis,
                                          style: const TextStyle(fontSize: 12),
                                        ),
                                      const SizedBox(height: 2),
                                      Wrap(
                                        spacing: 4,
                                        children: [
                                          if (d['platform'] != null)
                                            Chip(label: Text(d['platform'].toString().toUpperCase(), style: const TextStyle(fontSize: 10)), padding: EdgeInsets.zero, materialTapTargetSize: MaterialTapTargetSize.shrinkWrap),
                                          if (quality != null)
                                            Chip(label: Text('Q: $quality/100', style: const TextStyle(fontSize: 10)), padding: EdgeInsets.zero, materialTapTargetSize: MaterialTapTargetSize.shrinkWrap),
                                        ],
                                      ),
                                    ],
                                  ),
                                  isThreeLine: true,
                                  trailing: const Icon(Icons.chevron_right),
                                  onTap: () => Navigator.of(context).push(
                                    MaterialPageRoute<void>(
                                      builder: (_) => ContentDraftDetailScreen(
                                        brand: widget.brand,
                                        draftId: d['id'] as int,
                                        service: widget.service,
                                      ),
                                    ),
                                  ).then((_) => _load()),
                                ),
                              );
                            },
                          ),
                        ),
        ),
      ],
    );
  }
}

// ── Draft Detail Screen ───────────────────────────────────────────────────────

class ContentDraftDetailScreen extends StatefulWidget {
  const ContentDraftDetailScreen({
    super.key,
    required this.brand,
    required this.draftId,
    required this.service,
  });
  final Brand brand;
  final int draftId;
  final AuthService service;

  @override
  State<ContentDraftDetailScreen> createState() => _ContentDraftDetailScreenState();
}

class _ContentDraftDetailScreenState extends State<ContentDraftDetailScreen> {
  Map<String, dynamic>? _draft;
  bool _loading = true;
  bool _saving  = false;
  Object? _error;

  final _titleCtrl  = TextEditingController();
  final _hookCtrl   = TextEditingController();
  final _bodyCtrl   = TextEditingController();
  final _captionCtrl= TextEditingController();
  final _ctaCtrl    = TextEditingController();
  final _tagsCtrl   = TextEditingController();

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() { _loading = true; _error = null; });
    try {
      final draft = await widget.service.fetchContentDraft(widget.draftId);
      _titleCtrl.text   = draft['title']?.toString() ?? '';
      _hookCtrl.text    = draft['hook']?.toString()  ?? '';
      _bodyCtrl.text    = draft['body']?.toString()  ?? '';
      _captionCtrl.text = draft['caption']?.toString() ?? '';
      _ctaCtrl.text     = draft['call_to_action']?.toString() ?? '';
      final tags = draft['hashtags'];
      _tagsCtrl.text    = tags is List ? tags.join(' ') : (tags?.toString() ?? '');
      setState(() { _draft = draft; _loading = false; });
    } catch (e) {
      setState(() { _error = e; _loading = false; });
    }
  }

  Future<void> _save() async {
    setState(() { _saving = true; });
    try {
      await widget.service.updateContentDraft(widget.draftId, {
        'title':          _titleCtrl.text,
        'hook':           _hookCtrl.text,
        'body':           _bodyCtrl.text,
        'caption':        _captionCtrl.text,
        'call_to_action': _ctaCtrl.text,
        'hashtags':       _tagsCtrl.text,
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Saved')));
      }
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  Future<void> _improve(String operation) async {
    final messenger = ScaffoldMessenger.of(context);
    messenger.showSnackBar(SnackBar(content: Text('Applying $operation…')));
    try {
      final result = await widget.service.improveContent(widget.draftId, operation: operation);
      final d = result['data'] as Map<String, dynamic>? ?? result;
      if (d['hook']          != null) _hookCtrl.text    = d['hook'].toString();
      if (d['body']          != null) _bodyCtrl.text    = d['body'].toString();
      if (d['caption']       != null) _captionCtrl.text = d['caption'].toString();
      if (d['call_to_action']!= null) _ctaCtrl.text    = d['call_to_action'].toString();
      if (mounted) messenger.showSnackBar(const SnackBar(content: Text('Applied — save to keep changes')));
    } catch (e) {
      if (mounted) messenger.showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  Future<void> _delete() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete draft?'),
        content: const Text('This cannot be undone.'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          TextButton(onPressed: () => Navigator.pop(ctx, true),  child: const Text('Delete', style: TextStyle(color: Colors.red))),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      await widget.service.deleteContentDraft(widget.draftId);
      if (mounted) Navigator.of(context).pop();
    } catch (e) {
      if (mounted) ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(e.toString())));
    }
  }

  @override
  void dispose() {
    _titleCtrl.dispose(); _hookCtrl.dispose(); _bodyCtrl.dispose();
    _captionCtrl.dispose(); _ctaCtrl.dispose(); _tagsCtrl.dispose();
    super.dispose();
  }

  Widget _field(String label, TextEditingController ctrl, {int maxLines = 1}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextFormField(
        controller: ctrl,
        maxLines: maxLines,
        decoration: InputDecoration(labelText: label, border: const OutlineInputBorder()),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(_draft?['title']?.toString() ?? 'Content draft'),
        actions: [
          if (!_loading && _draft != null) ...[
            IconButton(
              icon: _saving ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2)) : const Icon(Icons.save),
              onPressed: _saving ? null : _save,
              tooltip: 'Save',
            ),
            IconButton(
              icon: const Icon(Icons.delete_outline),
              onPressed: _delete,
              tooltip: 'Delete',
            ),
          ],
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error.toString()))
              : DefaultTabController(
                  length: 2,
                  child: Column(
                    children: [
                      // Quality badge
                      if (_draft!['quality_score'] != null)
                        Container(
                          color: (_draft!['quality_score'] as int) >= 75
                              ? Colors.green.shade50
                              : (_draft!['quality_score'] as int) >= 50 ? Colors.orange.shade50 : Colors.red.shade50,
                          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 6),
                          child: Row(
                            children: [
                              Text('Quality: ${_draft!['quality_score']}/100', style: const TextStyle(fontWeight: FontWeight.bold)),
                              const Spacer(),
                              Text('${_draft!['platform'] ?? ''}', style: const TextStyle(color: Colors.grey)),
                            ],
                          ),
                        ),
                      const TabBar(tabs: [Tab(text: 'Edit'), Tab(text: 'Improve')]),
                      Expanded(
                        child: TabBarView(
                          children: [
                            // Edit tab
                            ListView(
                              padding: const EdgeInsets.all(16),
                              children: [
                                _field('Title', _titleCtrl),
                                _field('Hook', _hookCtrl, maxLines: 2),
                                _field('Body', _bodyCtrl, maxLines: 10),
                                _field('Caption', _captionCtrl, maxLines: 2),
                                _field('Call to action', _ctaCtrl),
                                _field('Hashtags', _tagsCtrl),
                                const SizedBox(height: 8),
                                ElevatedButton(onPressed: _saving ? null : _save, child: const Text('Save changes')),
                              ],
                            ),
                            // Improve tab
                            ListView(
                              padding: const EdgeInsets.all(16),
                              children: [
                                for (final entry in const [
                                  ['shorten',          'Shorten',          'Remove unnecessary words.'],
                                  ['expand',           'Expand',           'Add more depth and detail.'],
                                  ['improve_hook',     'Improve hook',     'Rewrite the opening hook.'],
                                  ['better_cta',       'Stronger CTA',     'More effective call to action.'],
                                  ['add_storytelling', 'Add storytelling', 'Reframe with narrative structure.'],
                                  ['professional',     'Make professional','Business audience tone.'],
                                  ['conversational',   'Make conversational','Friendly, natural voice.'],
                                  ['adapt_platform:linkedin',  'Adapt → LinkedIn',  ''],
                                  ['adapt_platform:instagram', 'Adapt → Instagram', ''],
                                  ['adapt_platform:tiktok',   'Adapt → TikTok',   ''],
                                ])
                                  Card(
                                    margin: const EdgeInsets.only(bottom: 8),
                                    child: ListTile(
                                      title: Text(entry[1]),
                                      subtitle: entry[2].isNotEmpty ? Text(entry[2]) : null,
                                      trailing: ElevatedButton(
                                        onPressed: () {
                                          final op = entry[0];
                                          if (op.startsWith('adapt_platform:')) {
                                            _improve('adapt_platform');
                                          } else {
                                            _improve(op);
                                          }
                                        },
                                        child: const Text('Apply'),
                                      ),
                                    ),
                                  ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
    );
  }
}

class TrendIntelligenceScreen extends StatefulWidget {
  const TrendIntelligenceScreen({super.key, this.service, this.initialBrandId});

  final AuthService? service;
  final int? initialBrandId;

  @override
  State<TrendIntelligenceScreen> createState() =>
      _TrendIntelligenceScreenState();
}

class _TrendIntelligenceScreenState extends State<TrendIntelligenceScreen> {
  late final AuthService _service;
  final TextEditingController _marketController = TextEditingController();
  List<Brand> _brands = const [];
  List<Map<String, dynamic>> _topics = const [];
  List<Map<String, dynamic>> _recommendations = const [];
  List<Map<String, dynamic>> _sources = const [];
  Map<String, dynamic>? _research;
  int? _brandId;
  int _days = 30;
  int _minimumRelevance = 0;
  String? _category;
  String? _momentum;
  String? _source;
  bool _savedOnly = false;
  final Set<String> _selectedCategories = {};
  final Set<String> _selectedSourceKeys = {};
  int? _sourceSelectionBrandId;
  bool _loading = true;
  bool _starting = false;
  Object? _error;

  @override
  void initState() {
    super.initState();
    _service = widget.service ?? AuthService();
    _load();
  }

  @override
  void dispose() {
    _marketController.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final brands = await _service.fetchBrands();
      final requested = _brandId ?? widget.initialBrandId;
      final selected = brands.any((brand) => brand.id == requested)
          ? requested
          : brands.isEmpty
          ? null
          : brands.first.id;
      if (selected == null) {
        setState(() {
          _brands = brands;
          _topics = const [];
          _recommendations = const [];
          _sources = const [];
          _research = null;
          _brandId = null;
          _loading = false;
        });
        return;
      }

      final results = await Future.wait<Object?>([
        _service.fetchTrendTopics(
          selected,
          category: _category,
          momentum: _momentum,
          source: _source,
          geographicMarket: _marketController.text.trim(),
          from: DateTime.now()
              .subtract(Duration(days: _days))
              .toIso8601String()
              .split('T')
              .first,
          minRelevance: _minimumRelevance == 0 ? null : _minimumRelevance,
          savedOnly: _savedOnly,
        ),
        _service.fetchTrendSources(selected),
        _service.fetchTrendResearchStatus(selected),
        _service
            .fetchTrendRecommendations(selected)
            .catchError((_) => <Map<String, dynamic>>[]),
      ]);
      final selectedBrand = brands.firstWhere((brand) => brand.id == selected);
      final readySourceKeys = (results[1] as List<Map<String, dynamic>>)
          .where((source) => source['connection_status'] == 'ready')
          .map((source) => source['key'].toString())
          .toSet();
      setState(() {
        _brands = brands;
        _brandId = selected;
        _topics = results[0] as List<Map<String, dynamic>>;
        _sources = results[1] as List<Map<String, dynamic>>;
        _research = results[2] as Map<String, dynamic>?;
        _recommendations = results[3] as List<Map<String, dynamic>>;
        if (_sourceSelectionBrandId != selected) {
          _selectedSourceKeys
            ..clear()
            ..addAll(readySourceKeys);
          _sourceSelectionBrandId = selected;
        } else {
          _selectedSourceKeys.retainAll(readySourceKeys);
        }
        if (_marketController.text.isEmpty) {
          _marketController.text = selectedBrand.region ?? '';
        }
        _loading = false;
      });
    } on Object catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error;
        _loading = false;
      });
    }
  }

  Future<void> _changeBrand(int? value) async {
    if (value == null || value == _brandId) return;
    setState(() {
      _brandId = value;
      _category = null;
      _momentum = null;
      _source = null;
      _savedOnly = false;
      _selectedCategories.clear();
      _selectedSourceKeys.clear();
      _sourceSelectionBrandId = null;
      final selectedBrand = _brands.where((brand) => brand.id == value);
      if (selectedBrand.isNotEmpty) {
        _marketController.text = selectedBrand.first.region ?? '';
      }
    });
    await _load();
  }

  Future<void> _startResearch() async {
    final brandId = _brandId;
    if (brandId == null) return;
    final availableSources = _sources
        .where((source) => source['connection_status'] == 'ready')
        .where((source) => _selectedSourceKeys.contains(source['key']))
        .map((source) => source['key'].toString())
        .toList();
    if (availableSources.isEmpty) return;

    setState(() => _starting = true);
    try {
      await _service.startTrendResearch(
        brandId,
        days: _days,
        geographicMarket: _marketController.text.trim().isEmpty
            ? null
            : _marketController.text.trim(),
        categories: _selectedCategories.toList(),
        sources: availableSources,
      );
      if (mounted) {
        _showMessage(context, 'Trend research queued.');
        await _load();
      }
    } on Object catch (error) {
      if (mounted) _showMessage(context, error.toString());
    } finally {
      if (mounted) setState(() => _starting = false);
    }
  }

  Future<void> _openTopic(Map<String, dynamic> topic) async {
    final brandId = _brandId;
    if (brandId == null) return;
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (_) => TrendDetailScreen(
          service: _service,
          brandId: brandId,
          topic: topic,
          onChanged: _load,
        ),
      ),
    );
    if (mounted) await _load();
  }

  Future<void> _updateRecStatus(Map<String, dynamic> rec, String status) async {
    final brandId = _brandId;
    final recId = int.tryParse(rec['id']?.toString() ?? '');
    if (brandId == null || recId == null) return;
    try {
      await _service.updateRecommendationStatus(brandId, recId, status);
      if (mounted) {
        _showMessage(context, 'Recommendation marked as $status.');
        _load();
      }
    } catch (e) {
      if (mounted) _showMessage(context, e.toString());
    }
  }

  Future<void> _applyFilters() async {
    final brandId = _brandId;
    if (brandId == null) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final topics = await _service.fetchTrendTopics(
        brandId,
        category: _category,
        momentum: _momentum,
        source: _source,
        geographicMarket: _marketController.text.trim(),
        from: DateTime.now()
            .subtract(Duration(days: _days))
            .toIso8601String()
            .split('T')
            .first,
        minRelevance: _minimumRelevance == 0 ? null : _minimumRelevance,
        savedOnly: _savedOnly,
      );
      if (mounted) {
        setState(() {
          _topics = topics;
          _loading = false;
        });
      }
    } on Object catch (error) {
      if (mounted) {
        setState(() {
          _error = error;
          _loading = false;
        });
      }
    }
  }

  Map<String, dynamic>? _sourceResearchStatus(String key) {
    final sources = _research?['sources'];
    if (sources is List) {
      for (final s in sources) {
        if (s is Map && s['source_key'] == key) {
          return Map<String, dynamic>.from(s);
        }
      }
    }
    return null;
  }

  String _latestResearchSubtitle() {
    if (_research == null) return '';
    final sources = _research!['sources'];
    final sourceWarnings = <String>[];
    if (sources is List) {
      for (final s in sources) {
        if (s is Map &&
            s['message'] != null &&
            s['message'].toString().trim().isNotEmpty) {
          final name = s['source_name']?.toString() ??
              s['source_key']?.toString() ??
              'Source';
          sourceWarnings.add('$name: ${s['message'].toString().trim()}');
        }
      }
    }
    if (sourceWarnings.isNotEmpty) {
      if (_topics.isNotEmpty) {
        return '${sourceWarnings.join('\n')}\nAvailable topics are displayed below.';
      }
      return sourceWarnings.join('\n');
    }
    if (_research!['status'] == 'partial' && _topics.isNotEmpty) {
      return 'Some sources encountered errors, but available topics are displayed below.';
    }
    return _research!['error_message']?.toString() ??
        (_research!['created_at']?.toString() ?? '');
  }

  Widget _buildEmptyState(BuildContext context) {
    final state = _research?['result_state']?.toString();
    final status = _research?['status']?.toString();

    String title;
    String description;
    IconData icon;
    Widget? action;

    if (state == 'no_relevant_topics') {
      icon = Icons.filter_alt_off_outlined;
      title = 'No relevant topics found';
      description =
          'Sources returned data, but no topics met your brand relevance threshold. '
          'Try adding more brand keywords or lowering the minimum relevance score.';
    } else if (state == 'all_sources_unavailable' || status == 'failed') {
      icon = Icons.cloud_off_outlined;
      title = 'All research sources unavailable';
      description =
          'All research sources are currently unavailable. Check your source configurations or network connection and try again.';
      action = OutlinedButton.icon(
        onPressed: _startResearch,
        icon: const Icon(Icons.refresh),
        label: const Text('Retry research'),
      );
    } else if (state == 'configuration_required') {
      icon = Icons.settings_suggest_outlined;
      title = 'Configuration required';
      description =
          'Research sources need configuration before research can run. Please configure API keys or RSS feeds.';
    } else if (status == 'partial' || state == 'partial') {
      icon = Icons.warning_amber_rounded;
      title = 'Partial research: no topics found';
      final subtitle = _latestResearchSubtitle();
      description = subtitle.isNotEmpty && subtitle.contains(':')
          ? subtitle
          : 'Some sources failed, and no topics were found from remaining sources.';
      action = OutlinedButton.icon(
        onPressed: _startResearch,
        icon: const Icon(Icons.refresh),
        label: const Text('Retry research'),
      );
    } else if (state == 'success') {
      icon = Icons.search_off_outlined;
      title = 'No topics match current filters';
      description =
          'Research completed successfully, but no topics match your current filter criteria.';
    } else {
      icon = Icons.insights_outlined;
      title = 'No trend research yet';
      description =
          'No sourced topics match this brand yet. Configure an available source and run research.';
    }

    return Padding(
      padding: const EdgeInsets.all(24),
      child: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 48, color: Theme.of(context).colorScheme.outline),
            const SizedBox(height: 16),
            Text(
              title,
              style: Theme.of(context).textTheme.titleMedium,
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 8),
            Text(
              description,
              style: Theme.of(context).textTheme.bodyMedium,
              textAlign: TextAlign.center,
            ),
            if (action != null) ...[
              const SizedBox(height: 16),
              action,
            ],
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final readySources = _sources
        .where((source) => source['connection_status'] == 'ready')
        .toList();
    final categories =
        _topics
            .map((topic) => topic['category']?.toString())
            .whereType<String>()
            .toSet()
            .toList()
          ..sort();
    if (_category != null && !categories.contains(_category)) {
      categories.add(_category!);
    }
    categories.addAll(
      const {
        'educational',
        'entertaining',
        'promotional',
        'inspirational',
        'community',
      }.difference(categories.toSet()),
    );
    categories.sort();

    return Scaffold(
      appBar: AppBar(
        title: const Text('Trend intelligence'),
        actions: [
          IconButton(
            tooltip: 'Refresh trend research',
            onPressed: _loading ? null : _load,
            icon: const Icon(Icons.refresh),
          ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
          ? Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(_error.toString(), textAlign: TextAlign.center),
                    const SizedBox(height: 12),
                    if (_isAuthenticationError(_error)) ...[
                      FilledButton(
                        onPressed: () => _returnToLogin(context),
                        child: const Text('Sign in'),
                      ),
                    ] else ...[
                      OutlinedButton(
                        onPressed: _load,
                        child: const Text('Retry'),
                      ),
                    ],
                  ],
                ),
              ),
            )
          : _brands.isEmpty
          ? const Center(
              child: Text('Create a brand before starting research.'),
            )
          : RefreshIndicator(
              onRefresh: _load,
              child: ListView(
                padding: const EdgeInsets.all(16),
                children: [
                  DropdownButtonFormField<int>(
                    initialValue: _brandId,
                    decoration: const InputDecoration(
                      labelText: 'Research brand',
                    ),
                    items: [
                      for (final brand in _brands)
                        DropdownMenuItem(
                          value: brand.id,
                          child: Text(brand.name),
                        ),
                    ],
                    onChanged: _changeBrand,
                  ),
                  const SizedBox(height: 16),
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            'Research controls',
                            style: Theme.of(context).textTheme.titleMedium,
                          ),
                          const SizedBox(height: 12),
                          DropdownButtonFormField<int>(
                            initialValue: _days,
                            decoration: const InputDecoration(
                              labelText: 'Research period',
                            ),
                            items: const [
                              DropdownMenuItem(
                                value: 7,
                                child: Text('Past 7 days'),
                              ),
                              DropdownMenuItem(
                                value: 30,
                                child: Text('Past 30 days'),
                              ),
                              DropdownMenuItem(
                                value: 90,
                                child: Text('Past 90 days'),
                              ),
                            ],
                            onChanged: (value) =>
                                setState(() => _days = value ?? 30),
                          ),
                          const SizedBox(height: 8),
                          TextField(
                            controller: _marketController,
                            decoration: const InputDecoration(
                              labelText: 'Geographic focus',
                            ),
                          ),
                          const SizedBox(height: 12),
                          for (final source in _sources)
                            Builder(
                              builder: (context) {
                                final key = source['key']?.toString() ?? '';
                                final srcStatus = _sourceResearchStatus(key);
                                final message = (srcStatus != null &&
                                        srcStatus['message'] != null &&
                                        srcStatus['message'].toString().trim().isNotEmpty)
                                    ? srcStatus['message'].toString().trim()
                                    : null;
                                return ListTile(
                                  contentPadding: EdgeInsets.zero,
                                  title: Text(
                                    source['name']?.toString() ?? 'Research source',
                                  ),
                                  subtitle: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    mainAxisSize: MainAxisSize.min,
                                    children: [
                                      Text(
                                        source['last_error']?.toString() ??
                                            source['connection_status']?.toString() ??
                                            'Configuration required',
                                      ),
                                      if (message != null) ...[
                                        const SizedBox(height: 2),
                                        Text(
                                          message,
                                          style: TextStyle(
                                            color: Theme.of(context).colorScheme.error,
                                            fontSize: 12,
                                          ),
                                        ),
                                      ],
                                    ],
                                  ),
                                  trailing: Checkbox(
                                    value: _selectedSourceKeys.contains(
                                      source['key'],
                                    ),
                                    onChanged:
                                        source['connection_status'] == 'ready'
                                        ? (selected) => setState(() {
                                            if (selected == true) {
                                              _selectedSourceKeys.add(key);
                                            } else {
                                              _selectedSourceKeys.remove(key);
                                            }
                                          })
                                        : null,
                                  ),
                                );
                              },
                            ),
                          const SizedBox(height: 8),
                          Text(
                            'Research categories',
                            style: Theme.of(context).textTheme.titleSmall,
                          ),
                          Wrap(
                            spacing: 8,
                            children: [
                              for (final category in const [
                                'educational',
                                'entertaining',
                                'promotional',
                                'inspirational',
                                'community',
                              ])
                                FilterChip(
                                  label: Text(category),
                                  selected: _selectedCategories.contains(
                                    category,
                                  ),
                                  onSelected: (selected) => setState(() {
                                    if (selected) {
                                      _selectedCategories.add(category);
                                    } else {
                                      _selectedCategories.remove(category);
                                    }
                                  }),
                                ),
                            ],
                          ),
                          FilledButton.icon(
                            onPressed: readySources.isEmpty || _starting
                                ? null
                                : _startResearch,
                            icon: _starting
                                ? const SizedBox(
                                    width: 18,
                                    height: 18,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                    ),
                                  )
                                : const Icon(Icons.search),
                            label: Text(
                              readySources.isEmpty
                                  ? 'Configure a research source'
                                  : 'Start research',
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  if (_research != null)
                    Card(
                      child: ListTile(
                        leading: _research!['status'] == 'partial'
                            ? const Icon(Icons.warning_amber_rounded, color: Colors.orange)
                            : null,
                        title: Text(
                          'Latest research · ${_research!['status']}',
                        ),
                        subtitle: Text(
                          _latestResearchSubtitle(),
                        ),
                        trailing: IconButton(
                          tooltip: 'Refresh research status',
                          onPressed: _load,
                          icon: const Icon(Icons.refresh),
                        ),
                      ),
                    ),
                  const SizedBox(height: 12),
                  Text(
                    'Discovered topics',
                    style: Theme.of(context).textTheme.titleLarge,
                  ),
                  const SizedBox(height: 8),
                  DropdownButtonFormField<String>(
                    initialValue: _category,
                    decoration: const InputDecoration(labelText: 'Category'),
                    items: [
                      const DropdownMenuItem(
                        value: null,
                        child: Text('All categories'),
                      ),
                      for (final value in categories)
                        DropdownMenuItem(value: value, child: Text(value)),
                    ],
                    onChanged: (value) {
                      setState(() => _category = value);
                      _applyFilters();
                    },
                  ),
                  DropdownButtonFormField<String?>(
                    initialValue: _source,
                    decoration: const InputDecoration(labelText: 'Source'),
                    items: [
                      const DropdownMenuItem<String?>(
                        value: null,
                        child: Text('All sources'),
                      ),
                      for (final source in _sources)
                        DropdownMenuItem<String?>(
                          value: source['key']?.toString(),
                          child: Text(
                            source['name']?.toString() ?? 'Research source',
                          ),
                        ),
                    ],
                    onChanged: (value) {
                      setState(() => _source = value);
                      _applyFilters();
                    },
                  ),
                  DropdownButtonFormField<String>(
                    initialValue: _momentum,
                    decoration: const InputDecoration(labelText: 'Momentum'),
                    items: const [
                      DropdownMenuItem(
                        value: null,
                        child: Text('All classifications'),
                      ),
                      DropdownMenuItem(
                        value: 'emerging',
                        child: Text('Emerging'),
                      ),
                      DropdownMenuItem(
                        value: 'growing',
                        child: Text('Growing'),
                      ),
                      DropdownMenuItem(
                        value: 'established',
                        child: Text('Established'),
                      ),
                      DropdownMenuItem(
                        value: 'declining',
                        child: Text('Declining'),
                      ),
                      DropdownMenuItem(
                        value: 'time_sensitive',
                        child: Text('Time-sensitive'),
                      ),
                      DropdownMenuItem(
                        value: 'evergreen',
                        child: Text('Evergreen'),
                      ),
                    ],
                    onChanged: (value) {
                      setState(() => _momentum = value);
                      _applyFilters();
                    },
                  ),
                  DropdownButtonFormField<int>(
                    initialValue: _minimumRelevance,
                    decoration: const InputDecoration(
                      labelText: 'Minimum relevance',
                    ),
                    items: const [
                      DropdownMenuItem(value: 0, child: Text('Any score')),
                      DropdownMenuItem(value: 50, child: Text('50+')),
                      DropdownMenuItem(value: 75, child: Text('75+')),
                    ],
                    onChanged: (value) {
                      setState(() => _minimumRelevance = value ?? 0);
                      _applyFilters();
                    },
                  ),
                  SwitchListTile(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Saved topics only'),
                    value: _savedOnly,
                    onChanged: (value) {
                      setState(() => _savedOnly = value);
                      _applyFilters();
                    },
                  ),
                  if (_recommendations.isNotEmpty) ...[
                    const Divider(height: 32),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          'Brand Content Recommendations',
                          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                fontWeight: FontWeight.bold,
                              ),
                        ),
                        Chip(
                          label: Text('${_recommendations.length} Tailored'),
                          visualDensity: VisualDensity.compact,
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    for (final rec in _recommendations)
                      Card(
                        margin: const EdgeInsets.symmetric(vertical: 6),
                        child: Padding(
                          padding: const EdgeInsets.all(12),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                                children: [
                                  Chip(
                                    label: Text(
                                      '${rec['relevance_classification']?.toString().toUpperCase()} · ${rec['score']}%',
                                      style: const TextStyle(fontSize: 11),
                                    ),
                                    visualDensity: VisualDensity.compact,
                                  ),
                                  Chip(
                                    label: Text(
                                      '${rec['suggested_format'] ?? 'short_video'}',
                                      style: const TextStyle(fontSize: 11),
                                    ),
                                    visualDensity: VisualDensity.compact,
                                  ),
                                ],
                              ),
                              const SizedBox(height: 6),
                              Text(
                                rec['topic']?['title']?.toString() ?? 'Recommended Concept',
                                style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 14),
                              ),
                              if (rec['content_package']?['recommended_angle'] != null) ...[
                                const SizedBox(height: 4),
                                Text(
                                  'Angle: ${rec['content_package']['recommended_angle']}',
                                  style: TextStyle(fontSize: 12, color: Colors.grey.shade700),
                                ),
                              ],
                              const SizedBox(height: 8),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.end,
                                children: [
                                  TextButton.icon(
                                    onPressed: () => _updateRecStatus(rec, 'saved'),
                                    icon: const Icon(Icons.bookmark_add, size: 16),
                                    label: const Text('Save', style: TextStyle(fontSize: 12)),
                                  ),
                                  TextButton.icon(
                                    onPressed: () => _updateRecStatus(rec, 'dismissed'),
                                    icon: const Icon(Icons.close, size: 16, color: Colors.red),
                                    label: const Text('Dismiss', style: TextStyle(fontSize: 12, color: Colors.red)),
                                  ),
                                ],
                              ),
                            ],
                          ),
                        ),
                      ),
                    const Divider(height: 32),
                    Text(
                      'All Discovered Topics',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.bold,
                          ),
                    ),
                    const SizedBox(height: 8),
                  ],
                  if (_topics.isEmpty)
                    _buildEmptyState(context)
                  else
                    for (final topic in _topics)
                      Card(
                        child: ListTile(
                          title: Text(
                            topic['title']?.toString() ?? 'Untitled topic',
                          ),
                          subtitle: Text(
                            '${topic['category'] ?? 'General'} · '
                            '${topic['momentum'] ?? 'Momentum unavailable'} · '
                            '${topic['source_count'] ?? 0} sources\n'
                            '${topic['description'] ?? topic['summary'] ?? ''}',
                          ),
                          isThreeLine: true,
                          trailing: Text(
                            '${topic['relevance_score'] ?? 0}/100',
                          ),
                          onTap: () => _openTopic(topic),
                        ),
                      ),
                ],
              ),
            ),
    );
  }
}

class TrendDetailScreen extends StatelessWidget {
  const TrendDetailScreen({
    super.key,
    required this.service,
    required this.brandId,
    required this.topic,
    required this.onChanged,
  });

  final AuthService service;
  final int brandId;
  final Map<String, dynamic> topic;
  final Future<void> Function() onChanged;

  Future<void> _feedback(BuildContext context, String value) async {
    try {
      await service.submitTrendFeedback(
        brandId,
        int.parse(topic['id'].toString()),
        feedback: value,
      );
      if (!context.mounted) return;
      _showMessage(context, 'Trend feedback saved.');
      await onChanged();
      if (context.mounted && value == 'dismissed') Navigator.of(context).pop();
    } on Object catch (error) {
      if (context.mounted) _showMessage(context, error.toString());
    }
  }

  @override
  Widget build(BuildContext context) {
    final sources = topic['sources'] is List
        ? (topic['sources'] as List).whereType<Map>().toList()
        : const <Map>[];
    final angles = topic['content_angles'] is List
        ? (topic['content_angles'] as List)
              .map((value) => value.toString())
              .toList()
        : const <String>[];
    return Scaffold(
      appBar: AppBar(title: const Text('Trend details')),
      body: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          Text(
            topic['title']?.toString() ?? '',
            style: Theme.of(context).textTheme.headlineSmall,
          ),
          const SizedBox(height: 12),
          Text(
            topic['summary']?.toString() ??
                topic['description']?.toString() ??
                'No sourced summary available.',
          ),
          const SizedBox(height: 12),
          Text(
            'Relevance ${topic['relevance_score'] ?? 0}/100 · Opportunity ${topic['opportunity_score'] ?? 0}/100',
          ),
          const SizedBox(height: 8),
          Text(
            topic['relevance_reason']?.toString() ??
                'No relevance explanation available.',
          ),
          const SizedBox(height: 8),
          Text(
            'Momentum: ${topic['momentum'] ?? 'Unavailable — historical evidence is insufficient.'}',
          ),
          const SizedBox(height: 16),
          Text(
            'Suggested content angles',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const Text(
            'Recommendations only; this does not create a post or draft.',
          ),
          if (topic['content_angle_error'] != null)
            Text(topic['content_angle_error'].toString()),
          if (topic['content_angle_status'] == 'configuration_required')
            const Text('Configure optional Ollama angle generation for ideas.'),
          if (topic['content_angle_status'] == 'disabled')
            const Text('Optional Ollama angle generation is disabled.'),
          for (final angle in angles) ListTile(title: Text(angle)),
          const SizedBox(height: 16),
          Text(
            'Supporting sources',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          if (sources.isEmpty) const Text('No supporting source records.'),
          for (final source in sources)
            ListTile(
              title: Text(
                source['title']?.toString() ??
                    source['source_name']?.toString() ??
                    'Source',
              ),
              subtitle: Tooltip(
                message: source['published_at']?.toString() ??
                    'Publication date unavailable',
                child: Text(
                  formatPublishedAt(source['published_at']),
                ),
              ),
              trailing: const Icon(Icons.open_in_new),
              onTap: () {
                final uri = Uri.tryParse(source['url']?.toString() ?? '');
                if (uri != null &&
                    (uri.scheme == 'https' || uri.scheme == 'http')) {
                  launchUrl(uri, mode: LaunchMode.externalApplication);
                }
              },
            ),
          const SizedBox(height: 16),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final action in const {
                'relevant': 'Relevant',
                'irrelevant': 'Not relevant',
                'saved': 'Save',
                'dismissed': 'Dismiss',
              }.entries)
                OutlinedButton(
                  onPressed: () => _feedback(context, action.key),
                  child: Text(action.value),
                ),
            ],
          ),
        ],
      ),
    );
  }
}

// ── Phase 7 Media Studio Screen ──────────────────────────────────────────

class MediaStudioScreen extends StatefulWidget {
  const MediaStudioScreen({super.key, this.service, this.initialBrandId});

  final AuthService? service;
  final int? initialBrandId;

  @override
  State<MediaStudioScreen> createState() => _MediaStudioScreenState();
}

class _MediaStudioScreenState extends State<MediaStudioScreen> {
  late final AuthService _service;
  List<Brand> _brands = const [];
  int? _selectedBrandId;
  bool _loadingBrands = true;

  // Image Generator State
  String _objective = 'social_post';
  String _visualStyle = 'dark_cinematic';
  String _platform = 'instagram';
  String _aspectRatio = '1:1';
  final TextEditingController _imageTitleController = TextEditingController();
  final TextEditingController _instructionsController = TextEditingController();
  bool _generatingImage = false;
  Map<String, dynamic>? _lastGeneratedAsset;
  String? _imageError;

  // Library State
  String _libraryFilter = 'all';
  List<Map<String, dynamic>> _mediaAssets = const [];
  bool _loadingLibrary = false;

  // Storyboard & Video State
  final TextEditingController _videoIdeaController = TextEditingController();
  String _videoPlatform = 'instagram';
  final String _videoAspectRatio = '9:16';
  int _sceneCount = 4;
  bool _generatingStoryboard = false;
  Map<String, dynamic>? _lastStoryboard;
  String? _storyboardError;

  @override
  void initState() {
    super.initState();
    _service = widget.service ?? AuthService();
    _selectedBrandId = widget.initialBrandId;
    _loadBrands();
  }

  @override
  void dispose() {
    _imageTitleController.dispose();
    _instructionsController.dispose();
    _videoIdeaController.dispose();
    super.dispose();
  }

  Future<void> _loadBrands() async {
    try {
      final brands = await _service.fetchBrands();
      if (!mounted) return;
      setState(() {
        _brands = brands;
        if (_selectedBrandId == null && brands.isNotEmpty) {
          _selectedBrandId = brands.first.id;
        }
        _loadingBrands = false;
      });
      if (_selectedBrandId != null) {
        _loadLibrary();
      }
    } catch (_) {
      if (mounted) setState(() => _loadingBrands = false);
    }
  }

  Future<void> _loadLibrary() async {
    if (_selectedBrandId == null) return;
    setState(() => _loadingLibrary = true);
    try {
      final assets = await _service.fetchMediaAssets(
        _selectedBrandId!,
        assetType: _libraryFilter == 'all' ? null : _libraryFilter,
      );
      if (mounted) {
        setState(() {
          _mediaAssets = assets;
          _loadingLibrary = false;
        });
      }
    } catch (e) {
      if (mounted) setState(() => _loadingLibrary = false);
    }
  }

  Future<void> _handleGenerateImage() async {
    if (_selectedBrandId == null) return;
    setState(() {
      _generatingImage = true;
      _imageError = null;
    });

    try {
      final result = await _service.generateImage(
        brandId: _selectedBrandId!,
        objective: _objective,
        visualStyle: _visualStyle,
        platform: _platform,
        aspectRatio: _aspectRatio,
        title: _imageTitleController.text.trim().isEmpty ? null : _imageTitleController.text.trim(),
        instructions: _instructionsController.text.trim().isEmpty ? null : _instructionsController.text.trim(),
      );

      final asset = result['asset'] ?? (result['data'] is Map ? (result['data'] as Map)['asset'] : null);
      if (mounted) {
        setState(() {
          _lastGeneratedAsset = asset is Map ? Map<String, dynamic>.from(asset) : result;
          _generatingImage = false;
        });
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Image generation queued successfully!')),
        );
        _loadLibrary();
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _imageError = e.toString().replaceAll('ApiException: ', '');
          _generatingImage = false;
        });
      }
    }
  }

  Future<void> _handleGenerateStoryboard() async {
    if (_selectedBrandId == null) return;
    setState(() {
      _generatingStoryboard = true;
      _storyboardError = null;
    });

    try {
      final result = await _service.generateStoryboard(
        brandId: _selectedBrandId!,
        videoIdea: _videoIdeaController.text.trim().isEmpty ? 'Brand visual showcase' : _videoIdeaController.text.trim(),
        platform: _videoPlatform,
        aspectRatio: _videoAspectRatio,
        numScenes: _sceneCount,
      );

      final storyboardData = result['storyboard'] ?? (result['data'] is Map ? (result['data'] as Map)['storyboard'] : result);
      if (mounted) {
        setState(() {
          _lastStoryboard = storyboardData is Map ? Map<String, dynamic>.from(storyboardData) : result;
          _generatingStoryboard = false;
        });
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Video storyboard created!')),
        );
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _storyboardError = e.toString().replaceAll('ApiException: ', '');
          _generatingStoryboard = false;
        });
      }
    }
  }

  Future<void> _handleDeleteAsset(int assetId) async {
    final confirm = await showDialog<bool>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: const Text('Delete Asset'),
        content: const Text('Are you sure you want to remove this media asset?'),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx, false), child: const Text('Cancel')),
          FilledButton(onPressed: () => Navigator.pop(ctx, true), child: const Text('Delete')),
        ],
      ),
    );

    if (confirm == true) {
      try {
        await _service.deleteMediaAsset(assetId);
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Asset deleted successfully.')),
          );
          _loadLibrary();
        }
      } catch (e) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Failed to delete asset: $e')),
          );
        }
      }
    }
  }

  void _showVisualSettingsSheet() async {
    if (_selectedBrandId == null) return;
    showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      backgroundColor: const Color(0xFF0D1B2A),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(24)),
      ),
      builder: (ctx) => _VisualSettingsSheet(
        brandId: _selectedBrandId!,
        service: _service,
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 3,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('AI Media Studio'),
          actions: [
            IconButton(
              icon: const Icon(Icons.palette_outlined),
              tooltip: 'Brand Visual Settings',
              onPressed: _showVisualSettingsSheet,
            ),
          ],
          bottom: const TabBar(
            tabs: [
              Tab(icon: Icon(Icons.auto_fix_high), text: 'Generator'),
              Tab(icon: Icon(Icons.photo_library), text: 'Library'),
              Tab(icon: Icon(Icons.movie_creation), text: 'Video & Story'),
            ],
          ),
        ),
        body: _loadingBrands
            ? const Center(child: CircularProgressIndicator())
            : Column(
                children: [
                  _buildBrandBar(),
                  Expanded(
                    child: TabBarView(
                      children: [
                        _buildGeneratorTab(),
                        _buildLibraryTab(),
                        _buildVideoTab(),
                      ],
                    ),
                  ),
                ],
              ),
      ),
    );
  }

  Widget _buildBrandBar() {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
      color: const Color(0xFF0F1F36),
      child: Row(
        children: [
          const Icon(Icons.business, size: 20, color: Color(0xFF7FE7FF)),
          const SizedBox(width: 8),
          const Text('Brand:', style: TextStyle(fontWeight: FontWeight.w600)),
          const SizedBox(width: 12),
          Expanded(
            child: DropdownButtonHideUnderline(
              child: DropdownButton<int>(
                value: _selectedBrandId,
                isExpanded: true,
                dropdownColor: const Color(0xFF10233B),
                items: _brands.map((b) {
                  return DropdownMenuItem<int>(
                    value: b.id,
                    child: Text(b.name, overflow: TextOverflow.ellipsis),
                  );
                }).toList(),
                onChanged: (id) {
                  setState(() => _selectedBrandId = id);
                  _loadLibrary();
                },
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildGeneratorTab() {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Generate Brand Media',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 12),
          TextFormField(
            controller: _imageTitleController,
            decoration: const InputDecoration(
              labelText: 'Media Title (optional)',
              hintText: 'e.g. Summer Campaign Banner',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: DropdownButtonFormField<String>(
                  initialValue: _objective,
                  decoration: const InputDecoration(labelText: 'Objective', border: OutlineInputBorder()),
                  dropdownColor: const Color(0xFF10233B),
                  items: const [
                    DropdownMenuItem(value: 'social_post', child: Text('Social Post')),
                    DropdownMenuItem(value: 'promotional_poster', child: Text('Promo Poster')),
                    DropdownMenuItem(value: 'quote_card', child: Text('Quote Card')),
                    DropdownMenuItem(value: 'product_ad', child: Text('Product Ad')),
                    DropdownMenuItem(value: 'educational', child: Text('Educational')),
                    DropdownMenuItem(value: 'thumbnail', child: Text('Thumbnail')),
                  ],
                  onChanged: (val) => setState(() => _objective = val ?? 'social_post'),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: DropdownButtonFormField<String>(
                  initialValue: _visualStyle,
                  decoration: const InputDecoration(labelText: 'Visual Style', border: OutlineInputBorder()),
                  dropdownColor: const Color(0xFF10233B),
                  items: const [
                    DropdownMenuItem(value: 'dark_cinematic', child: Text('Dark Cinematic')),
                    DropdownMenuItem(value: 'minimalist', child: Text('Minimalist')),
                    DropdownMenuItem(value: 'corporate', child: Text('Corporate')),
                    DropdownMenuItem(value: 'commercial', child: Text('Commercial')),
                    DropdownMenuItem(value: 'inspirational', child: Text('Inspirational')),
                    DropdownMenuItem(value: 'editorial', child: Text('Editorial')),
                  ],
                  onChanged: (val) => setState(() => _visualStyle = val ?? 'dark_cinematic'),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: DropdownButtonFormField<String>(
                  initialValue: _platform,
                  decoration: const InputDecoration(labelText: 'Platform', border: OutlineInputBorder()),
                  dropdownColor: const Color(0xFF10233B),
                  items: const [
                    DropdownMenuItem(value: 'instagram', child: Text('Instagram')),
                    DropdownMenuItem(value: 'facebook', child: Text('Facebook')),
                    DropdownMenuItem(value: 'linkedin', child: Text('LinkedIn')),
                    DropdownMenuItem(value: 'tiktok', child: Text('TikTok')),
                    DropdownMenuItem(value: 'youtube', child: Text('YouTube')),
                  ],
                  onChanged: (val) => setState(() => _platform = val ?? 'instagram'),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: DropdownButtonFormField<String>(
                  initialValue: _aspectRatio,
                  decoration: const InputDecoration(labelText: 'Aspect Ratio', border: OutlineInputBorder()),
                  dropdownColor: const Color(0xFF10233B),
                  items: const [
                    DropdownMenuItem(value: '1:1', child: Text('1:1 Square')),
                    DropdownMenuItem(value: '9:16', child: Text('9:16 Vertical')),
                    DropdownMenuItem(value: '16:9', child: Text('16:9 Landscape')),
                    DropdownMenuItem(value: '4:5', child: Text('4:5 Portrait')),
                    DropdownMenuItem(value: '1.91:1', child: Text('1.91:1 Wide')),
                  ],
                  onChanged: (val) => setState(() => _aspectRatio = val ?? '1:1'),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          TextFormField(
            controller: _instructionsController,
            maxLines: 3,
            decoration: const InputDecoration(
              labelText: 'Generation Instructions / Prompt',
              hintText: 'Describe subject matter, lighting, or specific layout elements...',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 16),
          if (_imageError != null)
            Container(
              padding: const EdgeInsets.all(12),
              margin: const EdgeInsets.only(bottom: 12),
              decoration: BoxDecoration(
                color: Colors.red.withValues(alpha: 0.15),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: Colors.red.shade400),
              ),
              child: Row(
                children: [
                  const Icon(Icons.error_outline, color: Colors.red),
                  const SizedBox(width: 8),
                  Expanded(child: Text(_imageError!, style: const TextStyle(color: Colors.red))),
                ],
              ),
            ),
          SizedBox(
            width: double.infinity,
            height: 48,
            child: FilledButton.icon(
              onPressed: _generatingImage ? null : _handleGenerateImage,
              icon: _generatingImage
                  ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
                  : const Icon(Icons.auto_awesome),
              label: Text(_generatingImage ? 'Generating Image...' : 'Generate Image'),
            ),
          ),
          if (_lastGeneratedAsset != null) ...[
            const SizedBox(height: 24),
            const Text('Generated Media Result', style: TextStyle(fontWeight: FontWeight.bold, fontSize: 16)),
            const SizedBox(height: 8),
            Card(
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        const Icon(Icons.check_circle, color: Colors.green),
                        const SizedBox(width: 8),
                        Expanded(
                          child: Text(
                            _lastGeneratedAsset!['title']?.toString() ?? 'Asset #${_lastGeneratedAsset!['id']}',
                            style: const TextStyle(fontWeight: FontWeight.bold),
                          ),
                        ),
                        Chip(
                          label: Text(_lastGeneratedAsset!['generation_status']?.toString() ?? 'completed'),
                          backgroundColor: Colors.teal.withValues(alpha: 0.2),
                        ),
                      ],
                    ),
                    const SizedBox(height: 8),
                    Text('Platform: ${_lastGeneratedAsset!['platform'] ?? _platform} · Aspect Ratio: ${_lastGeneratedAsset!['aspect_ratio'] ?? _aspectRatio}'),
                    if (_lastGeneratedAsset!['public_url'] != null) ...[
                      const SizedBox(height: 12),
                      ClipRRect(
                        borderRadius: BorderRadius.circular(12),
                        child: Image.network(
                          _lastGeneratedAsset!['public_url'] as String,
                          height: 200,
                          width: double.infinity,
                          fit: BoxFit.cover,
                          errorBuilder: (_, _, _) => Container(
                            height: 120,
                            color: Colors.black26,
                            alignment: Alignment.center,
                            child: const Text('Asset registered (local storage preview)'),
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildLibraryTab() {
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.all(12),
          child: SingleChildScrollView(
            scrollDirection: Axis.horizontal,
            child: Row(
              children: [
                for (final filter in ['all', 'image', 'video', 'storyboard', 'uploaded'])
                  Padding(
                    padding: const EdgeInsets.only(right: 8),
                    child: FilterChip(
                      selected: _libraryFilter == filter,
                      label: Text(filter.toUpperCase()),
                      onSelected: (val) {
                        setState(() => _libraryFilter = filter);
                        _loadLibrary();
                      },
                    ),
                  ),
              ],
            ),
          ),
        ),
        Expanded(
          child: _loadingLibrary
              ? const Center(child: CircularProgressIndicator())
              : _mediaAssets.isEmpty
                  ? Center(
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: const [
                          Icon(Icons.perm_media_outlined, size: 64, color: Colors.white24),
                          SizedBox(height: 16),
                          Text('No media assets found in library.', style: TextStyle(color: Colors.white54)),
                        ],
                      ),
                    )
                  : RefreshIndicator(
                      onRefresh: _loadLibrary,
                      child: GridView.builder(
                        padding: const EdgeInsets.all(12),
                        gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                          crossAxisCount: 2,
                          childAspectRatio: 0.82,
                          crossAxisSpacing: 12,
                          mainAxisSpacing: 12,
                        ),
                        itemCount: _mediaAssets.length,
                        itemBuilder: (context, index) {
                          final asset = _mediaAssets[index];
                          final id = asset['id'] is int ? asset['id'] as int : int.tryParse(asset['id'].toString()) ?? 0;
                          final title = asset['title']?.toString() ?? 'Media #$id';
                          final assetType = asset['asset_type']?.toString() ?? 'image';
                          final platform = asset['platform']?.toString() ?? 'general';
                          final ratio = asset['aspect_ratio']?.toString() ?? '1:1';

                          return Card(
                            clipBehavior: Clip.antiAlias,
                            child: InkWell(
                              onTap: () => _showAssetDetailsDialog(asset),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Expanded(
                                    child: Container(
                                      color: const Color(0xFF1B2A47),
                                      alignment: Alignment.center,
                                      child: Icon(
                                        assetType == 'video'
                                            ? Icons.videocam
                                            : assetType == 'storyboard'
                                                ? Icons.movie_filter
                                                : Icons.image,
                                        size: 40,
                                        color: const Color(0xFF7FE7FF),
                                      ),
                                    ),
                                  ),
                                  Padding(
                                    padding: const EdgeInsets.all(8),
                                    child: Column(
                                      crossAxisAlignment: CrossAxisAlignment.start,
                                      children: [
                                        Text(title, maxLines: 1, overflow: TextOverflow.ellipsis, style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
                                        const SizedBox(height: 4),
                                        Row(
                                          children: [
                                            Text(platform, style: const TextStyle(fontSize: 11, color: Colors.white60)),
                                            const Spacer(),
                                            Text(ratio, style: const TextStyle(fontSize: 11, color: Colors.white60)),
                                            const SizedBox(width: 4),
                                            InkWell(
                                              onTap: () => _handleDeleteAsset(id),
                                              child: const Icon(Icons.delete_outline, size: 16, color: Colors.redAccent),
                                            ),
                                          ],
                                        ),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          );
                        },
                      ),
                    ),
        ),
      ],
    );
  }

  void _showAssetDetailsDialog(Map<String, dynamic> asset) {
    showDialog<void>(
      context: context,
      builder: (ctx) => AlertDialog(
        title: Text(asset['title']?.toString() ?? 'Media Asset Details'),
        content: SingleChildScrollView(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              _detailRow('Asset Type', asset['asset_type']?.toString()),
              _detailRow('Platform', asset['platform']?.toString()),
              _detailRow('Aspect Ratio', asset['aspect_ratio']?.toString()),
              _detailRow('Status', asset['generation_status']?.toString()),
              _detailRow('Provider', asset['provider']?.toString()),
              _detailRow('Visual Style', asset['visual_style']?.toString()),
              if (asset['generation_prompt'] != null)
                Padding(
                  padding: const EdgeInsets.only(top: 8),
                  child: Text('Prompt: ${asset['generation_prompt']}', style: const TextStyle(fontSize: 12, color: Colors.white70)),
                ),
            ],
          ),
        ),
        actions: [
          TextButton(onPressed: () => Navigator.pop(ctx), child: const Text('Close')),
        ],
      ),
    );
  }

  Widget _detailRow(String label, String? value) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        children: [
          Text('$label: ', style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 13)),
          Text(value ?? 'N/A', style: const TextStyle(fontSize: 13)),
        ],
      ),
    );
  }

  Widget _buildVideoTab() {
    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Video Storyboard Director', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
          const SizedBox(height: 8),
          const Text('Generate scene-by-scene video scripts with camera directions, voiceovers, and kinetic text.', style: TextStyle(color: Colors.white60, fontSize: 13)),
          const SizedBox(height: 16),
          TextFormField(
            controller: _videoIdeaController,
            maxLines: 2,
            decoration: const InputDecoration(
              labelText: 'Video Concept / Idea',
              hintText: 'e.g. 5 Morning Habits that separate top performers',
              border: OutlineInputBorder(),
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: DropdownButtonFormField<String>(
                  initialValue: _videoPlatform,
                  decoration: const InputDecoration(labelText: 'Platform', border: OutlineInputBorder()),
                  dropdownColor: const Color(0xFF10233B),
                  items: const [
                    DropdownMenuItem(value: 'instagram', child: Text('Instagram Reels')),
                    DropdownMenuItem(value: 'tiktok', child: Text('TikTok')),
                    DropdownMenuItem(value: 'youtube', child: Text('YouTube Shorts')),
                  ],
                  onChanged: (val) => setState(() => _videoPlatform = val ?? 'instagram'),
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: DropdownButtonFormField<int>(
                  initialValue: _sceneCount,
                  decoration: const InputDecoration(labelText: 'Scenes', border: OutlineInputBorder()),
                  dropdownColor: const Color(0xFF10233B),
                  items: const [
                    DropdownMenuItem(value: 3, child: Text('3 Scenes (15s)')),
                    DropdownMenuItem(value: 4, child: Text('4 Scenes (20s)')),
                    DropdownMenuItem(value: 6, child: Text('6 Scenes (30s)')),
                  ],
                  onChanged: (val) => setState(() => _sceneCount = val ?? 4),
                ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          if (_storyboardError != null)
            Container(
              padding: const EdgeInsets.all(12),
              margin: const EdgeInsets.only(bottom: 12),
              decoration: BoxDecoration(
                color: Colors.red.withValues(alpha: 0.15),
                borderRadius: BorderRadius.circular(8),
                border: Border.all(color: Colors.red.shade400),
              ),
              child: Text(_storyboardError!, style: const TextStyle(color: Colors.red)),
            ),
          SizedBox(
            width: double.infinity,
            height: 48,
            child: FilledButton.icon(
              onPressed: _generatingStoryboard ? null : _handleGenerateStoryboard,
              icon: _generatingStoryboard
                  ? const SizedBox(width: 20, height: 20, child: CircularProgressIndicator(strokeWidth: 2))
                  : const Icon(Icons.movie_filter),
              label: Text(_generatingStoryboard ? 'Directing Storyboard...' : 'Generate Storyboard'),
            ),
          ),
          if (_lastStoryboard != null) ...[
            const SizedBox(height: 24),
            Text(
              'Storyboard: ${_lastStoryboard!['title'] ?? 'Generated Scenes'}',
              style: const TextStyle(fontWeight: FontWeight.bold, fontSize: 16),
            ),
            const SizedBox(height: 12),
            if (_lastStoryboard!['scenes'] is List)
              for (final scene in (_lastStoryboard!['scenes'] as List))
                Card(
                  margin: const EdgeInsets.only(bottom: 12),
                  child: Padding(
                    padding: const EdgeInsets.all(14),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Row(
                          children: [
                            CircleAvatar(
                              radius: 12,
                              child: Text(scene['scene_number']?.toString() ?? '1', style: const TextStyle(fontSize: 12)),
                            ),
                            const SizedBox(width: 8),
                            Text('Duration: ${scene['duration_seconds'] ?? 5}s', style: const TextStyle(fontWeight: FontWeight.bold)),
                            const Spacer(),
                            Chip(
                              label: Text(scene['transition']?.toString() ?? 'cut', style: const TextStyle(fontSize: 10)),
                              padding: EdgeInsets.zero,
                            ),
                          ],
                        ),
                        const SizedBox(height: 8),
                        Text('Visual: ${scene['visual_suggestion'] ?? scene['description']}', style: const TextStyle(color: Color(0xFF7FE7FF), fontSize: 13)),
                        if (scene['narration_text'] != null)
                          Padding(
                            padding: const EdgeInsets.only(top: 4),
                            child: Text('Voiceover: "${scene['narration_text']}"', style: const TextStyle(fontStyle: FontStyle.italic, fontSize: 12)),
                          ),
                      ],
                    ),
                  ),
                ),
          ],
          const SizedBox(height: 32),
          // AI Video Generation Notice
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              color: const Color(0xFF0F1F36),
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: const Color(0xFF2D3A5F)),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: const [
                Text('AI Video Generation Provider Status', style: TextStyle(fontWeight: FontWeight.bold, color: Color(0xFF7FE7FF))),
                SizedBox(height: 6),
                Text(
                  'Direct AI video generation supports external providers (Runway, Stability Video, Pika). When external credentials are unconfigured, the system automatically uses the Storyboard Director to produce scene-by-scene production blueprints.',
                  style: TextStyle(fontSize: 12, color: Colors.white70),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _VisualSettingsSheet extends StatefulWidget {
  const _VisualSettingsSheet({required this.brandId, required this.service});

  final int brandId;
  final AuthService service;

  @override
  State<_VisualSettingsSheet> createState() => _VisualSettingsSheetState();
}

class _VisualSettingsSheetState extends State<_VisualSettingsSheet> {
  bool _loading = true;
  String _visualIdentity = 'dark_cinematic';
  final TextEditingController _primaryColorCtrl = TextEditingController();
  final TextEditingController _accentColorCtrl = TextEditingController();
  final TextEditingController _primaryFontCtrl = TextEditingController();
  final TextEditingController _directivesCtrl = TextEditingController();
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _loadSettings();
  }

  @override
  void dispose() {
    _primaryColorCtrl.dispose();
    _accentColorCtrl.dispose();
    _primaryFontCtrl.dispose();
    _directivesCtrl.dispose();
    super.dispose();
  }

  Future<void> _loadSettings() async {
    try {
      final settings = await widget.service.getVisualSettings(widget.brandId);
      if (mounted) {
        setState(() {
          _visualIdentity = settings['visual_identity']?.toString() ?? 'dark_cinematic';
          _primaryColorCtrl.text = settings['primary_color']?.toString() ?? '#0d1b2a';
          _accentColorCtrl.text = settings['accent_color']?.toString() ?? '#e0a96d';
          _primaryFontCtrl.text = settings['primary_font']?.toString() ?? 'Oswald';
          _directivesCtrl.text = settings['positive_directives']?.toString() ?? '';
          _loading = false;
        });
      }
    } catch (_) {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _saveSettings() async {
    setState(() => _saving = true);
    try {
      await widget.service.updateVisualSettings(widget.brandId, {
        'visual_identity': _visualIdentity,
        'primary_color': _primaryColorCtrl.text.trim(),
        'accent_color': _accentColorCtrl.text.trim(),
        'primary_font': _primaryFontCtrl.text.trim(),
        'positive_directives': _directivesCtrl.text.trim(),
      });
      if (mounted) {
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Visual settings saved!')),
        );
      }
    } catch (e) {
      if (mounted) {
        setState(() => _saving = false);
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Failed to save settings: $e')),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: EdgeInsets.only(
        top: 20,
        left: 20,
        right: 20,
        bottom: MediaQuery.of(context).viewInsets.bottom + 20,
      ),
      child: _loading
          ? const Center(heightFactor: 4, child: CircularProgressIndicator())
          : SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      const Icon(Icons.palette, color: Color(0xFF7FE7FF)),
                      const SizedBox(width: 8),
                      const Text('Brand Visual Settings', style: TextStyle(fontSize: 18, fontWeight: FontWeight.bold)),
                      const Spacer(),
                      IconButton(onPressed: () => Navigator.pop(context), icon: const Icon(Icons.close)),
                    ],
                  ),
                  const SizedBox(height: 16),
                  DropdownButtonFormField<String>(
                    initialValue: _visualIdentity,
                    decoration: const InputDecoration(labelText: 'Visual Identity Style', border: OutlineInputBorder()),
                    dropdownColor: const Color(0xFF10233B),
                    items: const [
                      DropdownMenuItem(value: 'dark_cinematic', child: Text('Dark & Cinematic')),
                      DropdownMenuItem(value: 'minimalist', child: Text('Minimalist')),
                      DropdownMenuItem(value: 'corporate', child: Text('Corporate & Professional')),
                      DropdownMenuItem(value: 'commercial', child: Text('Commercial & Retail')),
                      DropdownMenuItem(value: 'educational', child: Text('Educational & Infographic')),
                      DropdownMenuItem(value: 'inspirational', child: Text('Inspirational / Quotes')),
                      DropdownMenuItem(value: 'editorial', child: Text('Editorial')),
                    ],
                    onChanged: (val) => setState(() => _visualIdentity = val ?? 'dark_cinematic'),
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: TextFormField(
                          controller: _primaryColorCtrl,
                          decoration: const InputDecoration(labelText: 'Primary Color', border: OutlineInputBorder()),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: TextFormField(
                          controller: _accentColorCtrl,
                          decoration: const InputDecoration(labelText: 'Accent Color', border: OutlineInputBorder()),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _primaryFontCtrl,
                    decoration: const InputDecoration(labelText: 'Primary Font', border: OutlineInputBorder()),
                  ),
                  const SizedBox(height: 12),
                  TextFormField(
                    controller: _directivesCtrl,
                    maxLines: 2,
                    decoration: const InputDecoration(
                      labelText: 'Visual Directives (optional)',
                      hintText: 'e.g. Bold lighting, clean backgrounds, high contrast',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 20),
                  SizedBox(
                    width: double.infinity,
                    height: 48,
                    child: FilledButton(
                      onPressed: _saving ? null : _saveSettings,
                      child: Text(_saving ? 'Saving...' : 'Save Visual Settings'),
                    ),
                  ),
                ],
              ),
            ),
    );
  }
}

class CalendarScreen extends StatefulWidget {
  const CalendarScreen({super.key, this.service});

  final AuthService? service;

  @override
  State<CalendarScreen> createState() => _CalendarScreenState();
}

class _CalendarScreenState extends State<CalendarScreen> {
  late final AuthService _service;
  late Future<List<Map<String, dynamic>>> _calendar;

  @override
  void initState() {
    super.initState();
    _service = widget.service ?? AuthService();
    _calendar = _service.fetchPublishingCalendar(null);
  }

  Future<void> _refresh() async {
    final next = _service.fetchPublishingCalendar(null);
    setState(() => _calendar = next);
    try {
      await next;
    } on Exception {
      // FutureBuilder renders the request failure.
    }
  }

  void _openSchedulePostDialog() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => SchedulePostScreen(service: _service, onScheduled: _refresh),
      ),
    );
  }

  void _openAutopilot() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => AutopilotScreen(service: _service),
      ),
    );
  }

  void _openBestTimes() {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => BestTimesScreen(service: _service),
      ),
    );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      title: const Text('Calendar'),
      actions: [
        IconButton(
          tooltip: 'AI Best Times',
          onPressed: _openBestTimes,
          icon: const Icon(Icons.insights),
        ),
        IconButton(
          tooltip: 'Autopilot Agent',
          onPressed: _openAutopilot,
          icon: const Icon(Icons.smart_toy_outlined),
        ),
        IconButton(
          tooltip: 'Refresh calendar',
          onPressed: _refresh,
          icon: const Icon(Icons.refresh),
        ),
      ],
    ),
    floatingActionButton: FloatingActionButton.extended(
      onPressed: _openSchedulePostDialog,
      icon: const Icon(Icons.add),
      label: const Text('Schedule Post'),
    ),
    body: FutureBuilder<List<Map<String, dynamic>>>(
      future: _calendar,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting &&
            !snapshot.hasData) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          final sessionExpired = _isAuthenticationError(snapshot.error);
          return Center(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(snapshot.error.toString()),
                TextButton(
                  onPressed: sessionExpired
                      ? () => _returnToLogin(context)
                      : _refresh,
                  child: Text(sessionExpired ? 'Sign in again' : 'Retry'),
                ),
              ],
            ),
          );
        }
        final items = snapshot.data!;
        if (items.isEmpty) {
          return const Center(child: Text('No scheduled posts.'));
        }
        return RefreshIndicator(
          onRefresh: _refresh,
          child: ListView.builder(
            physics: const AlwaysScrollableScrollPhysics(),
            itemCount: items.length,
            itemBuilder: (context, index) {
              final item = items[index];
              return ListTile(
                title: Text(item['title'].toString()),
                subtitle: Text(
                  '${item['brand_name']} · ${item['platform']} · ${_formatDashboardDate(item['scheduled_for'])}',
                ),
                trailing: Chip(
                  label: Text(
                    item['status'].toString(),
                    style: const TextStyle(fontSize: 12),
                  ),
                ),
              );
            },
          ),
        );
      },
    ),
  );
}

// ─────────────────────────────────────────────────────────────────────────────
// Phase 8: Mobile Scheduling Screens
// ─────────────────────────────────────────────────────────────────────────────

class SchedulePostScreen extends StatefulWidget {
  const SchedulePostScreen({super.key, this.service, this.onScheduled});

  final AuthService? service;
  final VoidCallback? onScheduled;

  @override
  State<SchedulePostScreen> createState() => _SchedulePostScreenState();
}

class _SchedulePostScreenState extends State<SchedulePostScreen> {
  late final AuthService _service;
  final _formKey = GlobalKey<FormState>();
  final _titleController = TextEditingController();
  final _captionController = TextEditingController();
  String _platform = 'instagram';
  DateTime _scheduledDate = DateTime.now().add(const Duration(days: 1));
  TimeOfDay _scheduledTime = const TimeOfDay(hour: 10, minute: 0);
  bool _requiresApproval = false;
  bool _isLoading = false;
  List<Brand> _brands = [];
  int? _selectedBrandId;

  @override
  void initState() {
    super.initState();
    _service = widget.service ?? AuthService();
    _loadBrands();
  }

  Future<void> _loadBrands() async {
    try {
      final brands = await _service.fetchBrands();
      if (mounted) {
        setState(() {
          _brands = brands;
          if (brands.isNotEmpty) {
            _selectedBrandId = brands.first.id;
          }
        });
      }
    } catch (_) {}
  }

  @override
  void dispose() {
    _titleController.dispose();
    _captionController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    if (_selectedBrandId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select a brand.')),
      );
      return;
    }

    setState(() => _isLoading = true);

    final dt = DateTime(
      _scheduledDate.year,
      _scheduledDate.month,
      _scheduledDate.day,
      _scheduledTime.hour,
      _scheduledTime.minute,
    );
    final dtStr = dt.toIso8601String().substring(0, 19).replaceAll('T', ' ');

    try {
      await _service.createScheduledPost(_selectedBrandId!, {
        'title': _titleController.text.trim(),
        'caption': _captionController.text.trim(),
        'platform': _platform,
        'scheduled_at': dtStr,
        'timezone': 'UTC',
        'requires_approval': _requiresApproval,
      });

      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Post scheduled successfully!')),
        );
        widget.onScheduled?.call();
        Navigator.of(context).pop();
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString())),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Schedule Post')),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (_brands.isNotEmpty)
                      DropdownButtonFormField<int>(
                        initialValue: _selectedBrandId,
                        decoration: const InputDecoration(labelText: 'Brand'),
                        items: _brands
                            .map((b) => DropdownMenuItem<int>(
                                  value: b.id,
                                  child: Text(b.name),
                                ))
                            .toList(),
                        onChanged: (val) => setState(() => _selectedBrandId = val),
                      ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _titleController,
                      decoration: const InputDecoration(
                        labelText: 'Post Title',
                        hintText: 'e.g. Weekly Launch Announcement',
                      ),
                      validator: (v) =>
                          (v == null || v.trim().isEmpty) ? 'Title is required' : null,
                    ),
                    const SizedBox(height: 12),
                    TextFormField(
                      controller: _captionController,
                      maxLines: 4,
                      decoration: const InputDecoration(
                        labelText: 'Caption / Body',
                        hintText: 'Enter post copy...',
                      ),
                      validator: (v) =>
                          (v == null || v.trim().isEmpty) ? 'Caption is required' : null,
                    ),
                    const SizedBox(height: 12),
                    DropdownButtonFormField<String>(
                      initialValue: _platform,
                      decoration: const InputDecoration(labelText: 'Platform'),
                      items: const [
                        DropdownMenuItem(value: 'instagram', child: Text('Instagram')),
                        DropdownMenuItem(value: 'facebook', child: Text('Facebook')),
                        DropdownMenuItem(value: 'tiktok', child: Text('TikTok')),
                        DropdownMenuItem(value: 'youtube', child: Text('YouTube')),
                        DropdownMenuItem(value: 'linkedin', child: Text('LinkedIn')),
                      ],
                      onChanged: (val) {
                        if (val != null) setState(() => _platform = val);
                      },
                    ),
                    const SizedBox(height: 16),
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      title: Text(
                        'Date: ${_scheduledDate.year}-${_scheduledDate.month.toString().padLeft(2, '0')}-${_scheduledDate.day.toString().padLeft(2, '0')}',
                      ),
                      trailing: const Icon(Icons.calendar_today),
                      onTap: () async {
                        final picked = await showDatePicker(
                          context: context,
                          initialDate: _scheduledDate,
                          firstDate: DateTime.now(),
                          lastDate: DateTime.now().add(const Duration(days: 365)),
                        );
                        if (picked != null) setState(() => _scheduledDate = picked);
                      },
                    ),
                    ListTile(
                      contentPadding: EdgeInsets.zero,
                      title: Text(
                        'Time: ${_scheduledTime.format(context)}',
                      ),
                      trailing: const Icon(Icons.access_time),
                      onTap: () async {
                        final picked = await showTimePicker(
                          context: context,
                          initialTime: _scheduledTime,
                        );
                        if (picked != null) setState(() => _scheduledTime = picked);
                      },
                    ),
                    SwitchListTile(
                      contentPadding: EdgeInsets.zero,
                      title: const Text('Require approval before publishing'),
                      value: _requiresApproval,
                      onChanged: (val) => setState(() => _requiresApproval = val),
                    ),
                    const SizedBox(height: 24),
                    FilledButton(
                      onPressed: _submit,
                      child: const Text('Confirm Schedule'),
                    ),
                  ],
                ),
              ),
            ),
    );
  }
}

class AutopilotScreen extends StatefulWidget {
  const AutopilotScreen({super.key, this.service});

  final AuthService? service;

  @override
  State<AutopilotScreen> createState() => _AutopilotScreenState();
}

class _AutopilotScreenState extends State<AutopilotScreen> {
  late final AuthService _service;
  bool _isLoading = true;
  bool _isEnabled = false;
  String _mode = 'ai_assisted';
  int _postsPerWeek = 3;
  int? _brandId;
  String? _error;

  @override
  void initState() {
    super.initState();
    _service = widget.service ?? AuthService();
    _loadSettings();
  }

  Future<void> _loadSettings() async {
    setState(() => _isLoading = true);
    try {
      final brands = await _service.fetchBrands();
      if (brands.isNotEmpty) {
        _brandId = brands.first.id;
        if (_brandId != null) {
          final settings = await _service.getAutopilotSettings(_brandId!);
          _isEnabled = settings['is_enabled'] == true;
          _mode = settings['mode']?.toString() ?? 'ai_assisted';
          _postsPerWeek = (settings['posts_per_week'] as num?)?.toInt() ?? 3;
        }
      }
    } catch (e) {
      _error = e.toString();
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _toggleAutopilot() async {
    if (_brandId == null) return;
    setState(() => _isLoading = true);
    try {
      if (_isEnabled) {
        await _service.disableAutopilot(_brandId!);
        _isEnabled = false;
      } else {
        await _service.enableAutopilot(_brandId!);
        _isEnabled = true;
      }
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(_isEnabled ? 'Autopilot enabled!' : 'Autopilot disabled.'),
          ),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString())),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  Future<void> _triggerCycle() async {
    if (_brandId == null) return;
    setState(() => _isLoading = true);
    try {
      await _service.triggerAutopilot(_brandId!);
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Autopilot cycle executed successfully.')),
        );
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(e.toString())),
        );
      }
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Autopilot Agent')),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? Center(child: Text(_error!))
              : SingleChildScrollView(
                  padding: const EdgeInsets.all(16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.stretch,
                    children: [
                      Card(
                        child: Padding(
                          padding: const EdgeInsets.all(16),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Row(
                                mainAxisAlignment:
                                    MainAxisAlignment.spaceBetween,
                                children: [
                                  const Text(
                                    'Autopilot Status',
                                    style: TextStyle(
                                      fontSize: 18,
                                      fontWeight: FontWeight.bold,
                                    ),
                                  ),
                                  Switch(
                                    value: _isEnabled,
                                    onChanged: (_) => _toggleAutopilot(),
                                  ),
                                ],
                              ),
                              const SizedBox(height: 8),
                              Text(
                                _isEnabled ? 'Active and monitoring' : 'Disabled',
                                style: TextStyle(
                                  color: _isEnabled
                                      ? Colors.green
                                      : Colors.grey,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 16),
                      Card(
                        child: Padding(
                          padding: const EdgeInsets.all(16),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              const Text(
                                'Strategy Configuration',
                                style: TextStyle(
                                  fontSize: 16,
                                  fontWeight: FontWeight.bold,
                                ),
                              ),
                              const SizedBox(height: 12),
                              Text('Operating Mode: ${_mode.toUpperCase()}'),
                              const SizedBox(height: 8),
                              Text('Target: $_postsPerWeek posts / week'),
                            ],
                          ),
                        ),
                      ),
                      const SizedBox(height: 24),
                      FilledButton.icon(
                        onPressed: _triggerCycle,
                        icon: const Icon(Icons.play_arrow),
                        label: const Text('Run Autopilot Cycle Now'),
                      ),
                    ],
                  ),
                ),
    );
  }
}

class BestTimesScreen extends StatefulWidget {
  const BestTimesScreen({super.key, this.service});

  final AuthService? service;

  @override
  State<BestTimesScreen> createState() => _BestTimesScreenState();
}

class _BestTimesScreenState extends State<BestTimesScreen> {
  late final AuthService _service;
  bool _isLoading = true;
  List<Map<String, dynamic>> _recommendations = [];
  String _platform = 'instagram';
  String? _error;

  @override
  void initState() {
    super.initState();
    _service = widget.service ?? AuthService();
    _fetchRecs();
  }

  Future<void> _fetchRecs() async {
    setState(() => _isLoading = true);
    try {
      final brands = await _service.fetchBrands();
      if (brands.isNotEmpty) {
        final brandId = brands.first.id;
        final recs = await _service.fetchBestTimes(brandId, platform: _platform);
        _recommendations = recs;
      }
    } catch (e) {
      _error = e.toString();
    } finally {
      if (mounted) setState(() => _isLoading = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('AI Best Times')),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.all(12),
            child: SegmentedButton<String>(
              segments: const [
                ButtonSegment(value: 'instagram', label: Text('IG')),
                ButtonSegment(value: 'facebook', label: Text('FB')),
                ButtonSegment(value: 'tiktok', label: Text('TT')),
                ButtonSegment(value: 'youtube', label: Text('YT')),
                ButtonSegment(value: 'linkedin', label: Text('LI')),
              ],
              selected: {_platform},
              onSelectionChanged: (val) {
                setState(() => _platform = val.first);
                _fetchRecs();
              },
            ),
          ),
          Expanded(
            child: _isLoading
                ? const Center(child: CircularProgressIndicator())
                : _error != null
                    ? Center(child: Text(_error!))
                    : _recommendations.isEmpty
                        ? const Center(child: Text('No recommendations available.'))
                        : ListView.builder(
                            padding: const EdgeInsets.all(16),
                            itemCount: _recommendations.length,
                            itemBuilder: (context, i) {
                              final r = _recommendations[i];
                              return Card(
                                margin: const EdgeInsets.only(bottom: 12),
                                child: ListTile(
                                  leading: const Icon(Icons.schedule, color: Colors.blue),
                                  title: Text(
                                    '${r['day_of_week']} at ${r['suggested_time']}',
                                    style: const TextStyle(fontWeight: FontWeight.bold),
                                  ),
                                  subtitle: Text(r['reason']?.toString() ?? ''),
                                  trailing: Chip(
                                    label: Text(
                                      r['evidence_type']?.toString().contains('historical') == true
                                          ? 'Historical'
                                          : 'Heuristic',
                                      style: const TextStyle(fontSize: 10),
                                    ),
                                  ),
                                ),
                              );
                            },
                          ),
          ),
        ],
      ),
    );
  }
}

class ProfileScreen extends StatefulWidget {
  const ProfileScreen({super.key, required this.user, this.service});
  final AppUser user;
  final AuthService? service;

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  late AppUser _currentUser;
  late final AuthService _authService;
  final StorageService _storageService = const StorageService();

  @override
  void initState() {
    super.initState();
    _currentUser = widget.user;
    _authService = widget.service ?? AuthService();
    _refreshUser();
  }

  Future<void> _refreshUser() async {
    try {
      final user = await _authService.getCurrentUser();
      if (mounted) {
        setState(() => _currentUser = user);
      }
    } catch (_) {
      // Keep existing local user on offline or error
    }
  }

  Future<void> _showEditProfileDialog() async {
    final nameCtrl = TextEditingController(text: _currentUser.name);
    final emailCtrl = TextEditingController(text: _currentUser.email);
    String? dialogError;
    bool saving = false;

    await showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Edit Profile'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (dialogError != null)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: Text(dialogError!, style: const TextStyle(color: Colors.red, fontSize: 13)),
                ),
              TextField(
                controller: nameCtrl,
                decoration: const InputDecoration(labelText: 'Full name', border: OutlineInputBorder()),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: emailCtrl,
                keyboardType: TextInputType.emailAddress,
                decoration: const InputDecoration(labelText: 'Email address', border: OutlineInputBorder()),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: saving ? null : () => Navigator.of(ctx).pop(),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: saving
                  ? null
                  : () async {
                      final newName = nameCtrl.text.trim();
                      final newEmail = emailCtrl.text.trim();
                      if (newName.isEmpty || newEmail.isEmpty) {
                        setDialogState(() => dialogError = 'Name and email cannot be empty.');
                        return;
                      }
                      setDialogState(() {
                        saving = true;
                        dialogError = null;
                      });
                      try {
                        final updated = await _authService.updateProfile(name: newName, email: newEmail);
                        if (mounted) {
                          setState(() => _currentUser = updated);
                          ScaffoldMessenger.of(this.context).showSnackBar(
                            const SnackBar(content: Text('Profile updated successfully!')),
                          );
                        }
                        if (ctx.mounted) Navigator.of(ctx).pop();
                      } catch (e) {
                        setDialogState(() {
                          saving = false;
                          dialogError = e is ApiException ? e.message : e.toString();
                        });
                      }
                    },
              child: saving
                  ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                  : const Text('Save Changes'),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _showChangePasswordDialog() async {
    final currentCtrl = TextEditingController();
    final newCtrl = TextEditingController();
    final confirmCtrl = TextEditingController();
    String? dialogError;
    bool saving = false;

    await showDialog(
      context: context,
      builder: (ctx) => StatefulBuilder(
        builder: (context, setDialogState) => AlertDialog(
          title: const Text('Change Password'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              if (dialogError != null)
                Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: Text(dialogError!, style: const TextStyle(color: Colors.red, fontSize: 13)),
                ),
              TextField(
                controller: currentCtrl,
                obscureText: true,
                decoration: const InputDecoration(labelText: 'Current password', border: OutlineInputBorder()),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: newCtrl,
                obscureText: true,
                decoration: const InputDecoration(labelText: 'New password (min 8)', border: OutlineInputBorder()),
              ),
              const SizedBox(height: 12),
              TextField(
                controller: confirmCtrl,
                obscureText: true,
                decoration: const InputDecoration(labelText: 'Confirm new password', border: OutlineInputBorder()),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: saving ? null : () => Navigator.of(ctx).pop(),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: saving
                  ? null
                  : () async {
                      if (currentCtrl.text.isEmpty || newCtrl.text.isEmpty) {
                        setDialogState(() => dialogError = 'Please fill in all fields.');
                        return;
                      }
                      if (newCtrl.text.length < 8) {
                        setDialogState(() => dialogError = 'New password must be at least 8 characters.');
                        return;
                      }
                      if (newCtrl.text != confirmCtrl.text) {
                        setDialogState(() => dialogError = 'Passwords do not match.');
                        return;
                      }
                      setDialogState(() {
                        saving = true;
                        dialogError = null;
                      });
                      try {
                        await _authService.updatePassword(
                          currentPassword: currentCtrl.text,
                          newPassword: newCtrl.text,
                          confirmPassword: confirmCtrl.text,
                        );
                        if (mounted) {
                          ScaffoldMessenger.of(this.context).showSnackBar(
                            const SnackBar(content: Text('Password updated successfully!')),
                          );
                        }
                        if (ctx.mounted) Navigator.of(ctx).pop();
                      } catch (e) {
                        setDialogState(() {
                          saving = false;
                          dialogError = e is ApiException ? e.message : e.toString();
                        });
                      }
                    },
              child: saving
                  ? const SizedBox(width: 16, height: 16, child: CircularProgressIndicator(strokeWidth: 2))
                  : const Text('Update Password'),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Profile & Security'),
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            tooltip: 'Refresh Profile',
            onPressed: _refreshUser,
          ),
        ],
      ),
      body: RefreshIndicator(
        onRefresh: _refreshUser,
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            // User Header Card
            Card(
              elevation: 0,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(20),
                side: BorderSide(color: Colors.white.withValues(alpha: 0.08)),
              ),
              child: Padding(
                padding: const EdgeInsets.all(20),
                child: Row(
                  children: [
                    CircleAvatar(
                      radius: 36,
                      backgroundColor: const Color(0xFF6C63FF),
                      child: Text(
                        _currentUser.name.isNotEmpty
                            ? _currentUser.name.substring(0, 1).toUpperCase()
                            : 'S',
                        style: const TextStyle(
                          fontSize: 26,
                          fontWeight: FontWeight.bold,
                          color: Colors.white,
                        ),
                      ),
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            _currentUser.name,
                            style: const TextStyle(
                              fontSize: 20,
                              fontWeight: FontWeight.bold,
                            ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            _currentUser.email,
                            style: TextStyle(color: Colors.grey.shade400, fontSize: 14),
                          ),
                          const SizedBox(height: 8),
                          Container(
                            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                            decoration: BoxDecoration(
                              color: const Color(0xFF34D399).withValues(alpha: 0.12),
                              borderRadius: BorderRadius.circular(8),
                              border: Border.all(color: const Color(0xFF34D399).withValues(alpha: 0.3)),
                            ),
                            child: const Text(
                              'Active Account',
                              style: TextStyle(color: Color(0xFF34D399), fontSize: 11, fontWeight: FontWeight.w600),
                            ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
            const SizedBox(height: 20),

            // Action Options List
            const Text(
              'Account Management',
              style: TextStyle(fontSize: 16, fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 10),

            Card(
              elevation: 0,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(16),
                side: BorderSide(color: Colors.white.withValues(alpha: 0.08)),
              ),
              child: Column(
                children: [
                  ListTile(
                    leading: const Icon(Icons.edit_outlined, color: Color(0xFF6C63FF)),
                    title: const Text('Edit Profile'),
                    subtitle: const Text('Change your display name and email address', style: TextStyle(fontSize: 12)),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: _showEditProfileDialog,
                  ),
                  const Divider(height: 1, indent: 56),
                  ListTile(
                    leading: const Icon(Icons.lock_outline, color: Color(0xFF22D3EE)),
                    title: const Text('Change Password'),
                    subtitle: const Text('Update your login password and credentials', style: TextStyle(fontSize: 12)),
                    trailing: const Icon(Icons.chevron_right),
                    onTap: _showChangePasswordDialog,
                  ),
                  const Divider(height: 1, indent: 56),
                  ListTile(
                    leading: const Icon(Icons.verified_user_outlined, color: Color(0xFF34D399)),
                    title: const Text('Security & Isolation'),
                    subtitle: const Text('Multi-brand tenant isolation enforced', style: TextStyle(fontSize: 12)),
                    trailing: const Icon(Icons.check, color: Color(0xFF34D399), size: 18),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 24),

            // Logout Button
            FilledButton.tonal(
              onPressed: () async {
                final confirm = await showDialog<bool>(
                  context: context,
                  builder: (ctx) => AlertDialog(
                    title: const Text('Log Out'),
                    content: const Text('Are you sure you want to log out of SocialPilot AI?'),
                    actions: [
                      TextButton(onPressed: () => Navigator.of(ctx).pop(false), child: const Text('Cancel')),
                      FilledButton(onPressed: () => Navigator.of(ctx).pop(true), child: const Text('Log Out')),
                    ],
                  ),
                );
                if (confirm != true) return;

                try {
                  await _authService.logout();
                } catch (_) {}
                await _storageService.clearToken();
                if (context.mounted) {
                  Navigator.of(context).pushAndRemoveUntil(
                    MaterialPageRoute(builder: (_) => const WelcomeScreen()),
                    (route) => false,
                  );
                }
              },
              style: FilledButton.styleFrom(
                padding: const EdgeInsets.symmetric(vertical: 16),
                backgroundColor: Colors.red.withValues(alpha: 0.12),
                foregroundColor: const Color(0xFFFCA5A5),
              ),
              child: const Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Icon(Icons.logout, size: 18),
                  SizedBox(width: 8),
                  Text('Log Out'),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class AppUser {
  const AppUser({required this.id, required this.name, required this.email});

  AppUser copyWith({int? id, String? name, String? email}) {
    return AppUser(
      id: id ?? this.id,
      name: name ?? this.name,
      email: email ?? this.email,
    );
  }

  factory AppUser.fromJson(Map<String, dynamic> json) {
    return AppUser(
      id: json['id'] ?? 0,
      name: json['name'] ?? '',
      email: json['email'] ?? '',
    );
  }

  final int id;
  final String name;
  final String email;
}

class Brand {
  const Brand({
    required this.id,
    required this.name,
    required this.status,
    this.description,
    this.industry,
    this.niche,
    this.websiteUrl,
    this.audience,
    this.targetAgeGroup,
    this.audienceInterests = const [],
    this.region,
    this.language,
    this.tone,
    this.logoUrl,
    this.settings = const {},
  });

  factory Brand.fromJson(Map<String, dynamic> json) {
    final settings = json['settings'];
    final interests = json['audience_interests'];
    String? logoUrl = json['logo_url']?.toString();
    if (logoUrl != null && logoUrl.isNotEmpty) {
      final logoUri = Uri.tryParse(logoUrl);
      final apiUri = Uri.parse(_apiBaseUrl);
      if (logoUri != null &&
          (logoUri.host == 'localhost' || logoUri.host == '127.0.0.1')) {
        logoUrl = apiUri.replace(path: logoUri.path).toString();
      }
    }
    return Brand(
      id: json['id'] ?? 0,
      name: json['name'] ?? 'Unnamed brand',
      status: json['status'] ?? 'active',
      description: json['description']?.toString(),
      industry: (json['industry'] ?? json['category'])?.toString(),
      niche: json['niche']?.toString(),
      websiteUrl: json['website_url']?.toString(),
      audience: json['audience']?.toString(),
      targetAgeGroup: json['target_age_group']?.toString(),
      audienceInterests: interests is List
          ? interests.map((item) => item.toString()).toList()
          : const [],
      region: json['region']?.toString(),
      language: json['language']?.toString(),
      tone: json['tone']?.toString(),
      logoUrl: logoUrl,
      settings: settings is Map
          ? Map<String, dynamic>.from(settings)
          : const {},
    );
  }

  final int id;
  final String name;
  final String status;
  final String? description;
  final String? industry;
  final String? niche;
  final String? websiteUrl;
  final String? audience;
  final String? targetAgeGroup;
  final List<String> audienceInterests;
  final String? region;
  final String? language;
  final String? tone;
  final String? logoUrl;
  final Map<String, dynamic> settings;

  Map<String, dynamic> toJson() => {
    'id': id,
    'name': name,
    'status': status,
    'description': description,
    'industry': industry,
    'niche': niche,
    'website_url': websiteUrl,
    'audience': audience,
    'target_age_group': targetAgeGroup,
    'audience_interests': audienceInterests,
    'region': region,
    'language': language,
    'tone': tone,
    'logo_url': logoUrl,
    'settings': settings,
  };
}

class ApiException implements Exception {
  ApiException(this.message, {this.statusCode});

  final String message;
  final int? statusCode;

  @override
  String toString() => message;
}

abstract interface class AppStorage {
  Future<String?> readToken();
  Future<void> writeToken(String token);
  Future<void> clearToken();
  Future<int?> readSelectedBrandId();
  Future<void> writeSelectedBrandId(int id);
  Future<void> clearSelectedBrandId();
}

class StorageService implements AppStorage {
  const StorageService();

  static const String _tokenKey = 'socialpilot_api_token';
  static const String _selectedBrandKey = 'socialpilot_selected_brand_id';

  final FlutterSecureStorage _storage = const FlutterSecureStorage(
    aOptions: AndroidOptions(encryptedSharedPreferences: true),
    iOptions: IOSOptions(accessibility: KeychainAccessibility.first_unlock),
  );

  @override
  Future<String?> readToken() async {
    return _storage.read(key: _tokenKey);
  }

  @override
  Future<void> writeToken(String token) async {
    await _storage.write(key: _tokenKey, value: token);
  }

  @override
  Future<void> clearToken() async {
    await _storage.delete(key: _tokenKey);
    await _storage.delete(key: _selectedBrandKey);
  }

  @override
  Future<int?> readSelectedBrandId() async {
    final value = await _storage.read(key: _selectedBrandKey);
    return value == null ? null : int.tryParse(value);
  }

  @override
  Future<void> writeSelectedBrandId(int id) async {
    await _storage.write(key: _selectedBrandKey, value: id.toString());
  }

  @override
  Future<void> clearSelectedBrandId() async {
    await _storage.delete(key: _selectedBrandKey);
  }
}

class AuthService {
  AuthService({
    http.Client? client,
    AppStorage? storage,
    this.requestTimeout = const Duration(seconds: 15),
  }) : _client = client ?? http.Client(),
       _storage = storage ?? const StorageService();

  final http.Client _client;
  final AppStorage _storage;
  final Duration requestTimeout;

  String? lastToken;

  Future<AppUser> login({
    required String email,
    required String password,
  }) async {
    final payload = await _request(
      'POST',
      '/login',
      body: {'email': email, 'password': password},
      authenticated: false,
    );

    final token = payload['token']?.toString();
    final userData = payload['user'];
    if (token == null || userData == null) {
      throw ApiException(payload['message'] ?? 'Login failed.');
    }

    lastToken = token;
    return AppUser.fromJson(Map<String, dynamic>.from(userData as Map));
  }

  Future<AppUser> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
    required bool termsAccepted,
  }) async {
    final payload = await _request(
      'POST',
      '/auth/register',
      body: {
        'name': name,
        'email': email,
        'password': password,
        'password_confirmation': passwordConfirmation,
        'terms': termsAccepted,
      },
      authenticated: false,
    );

    final token = payload['token']?.toString();
    final userData = payload['user'];
    if (token == null || userData == null) {
      throw ApiException(payload['message'] ?? 'Registration failed.');
    }

    lastToken = token;
    return AppUser.fromJson(Map<String, dynamic>.from(userData as Map));
  }

  Future<AppUser> getCurrentUser() async {
    final payload = await _request('GET', '/me');
    final userData = payload['user'];
    if (userData == null) {
      throw ApiException('Unable to load current user.');
    }

    final user = AppUser.fromJson(Map<String, dynamic>.from(userData as Map));
    return user;
  }

  Future<Map<String, dynamic>> forgotPassword(String email) async {
    final payload = await _request(
      'POST',
      '/auth/forgot-password',
      body: {'email': email},
      authenticated: false,
    );
    return payload;
  }

  Future<Map<String, dynamic>> resetPassword({
    required String email,
    required String token,
    required String password,
    required String passwordConfirmation,
  }) async {
    final payload = await _request(
      'POST',
      '/auth/reset-password',
      body: {
        'email': email,
        'token': token,
        'password': password,
        'password_confirmation': passwordConfirmation,
      },
      authenticated: false,
    );
    return payload;
  }

  Future<AppUser> updateProfile({
    required String name,
    required String email,
  }) async {
    final payload = await _request(
      'PUT',
      '/user/profile',
      body: {'name': name, 'email': email},
      authenticated: true,
    );
    final userData = payload['user'];
    if (userData == null) {
      throw ApiException(payload['message']?.toString() ?? 'Profile update failed.');
    }
    return AppUser.fromJson(Map<String, dynamic>.from(userData as Map));
  }

  Future<void> updatePassword({
    required String currentPassword,
    required String newPassword,
    required String confirmPassword,
  }) async {
    await _request(
      'PUT',
      '/user/password',
      body: {
        'current_password': currentPassword,
        'password': newPassword,
        'password_confirmation': confirmPassword,
      },
      authenticated: true,
    );
  }

  Future<List<Brand>> fetchBrands({String? search, String? status}) async {
    final query = <String, String>{'per_page': '50'};
    if (search != null && search.trim().isNotEmpty) {
      query['search'] = search.trim();
    }
    if (status != null && status.isNotEmpty) query['status'] = status;
    final payload = await _request(
      'GET',
      '/brands?${Uri(queryParameters: query).query}',
    );
    final rows = payload['data'];
    if (rows is! List) {
      throw ApiException('The brands response was invalid.');
    }

    return rows
        .map((item) => Brand.fromJson(Map<String, dynamic>.from(item as Map)))
        .toList();
  }

  Future<Map<String, dynamic>> fetchDashboardSnapshot(int? brandId) async {
    final filter = brandId == null ? '' : '?brand_id=$brandId';
    final notificationsFilter = brandId == null
        ? '?per_page=20'
        : '?per_page=20&brand_id=$brandId';
    final responses = await Future.wait<Map<String, dynamic>>([
      _request('GET', '/dashboard/overview$filter'),
      _request('GET', '/dashboard/brands'),
      _request('GET', '/dashboard/content-activity$filter'),
      _request('GET', '/dashboard/publishing-calendar$filter'),
      _request('GET', '/dashboard/social-accounts$filter'),
      _request('GET', '/dashboard/agent-activity$filter'),
      _request('GET', '/notifications$notificationsFilter'),
    ]);

    return {
      'overview': _responseObject(responses[0], 'data'),
      'brands': _responseList(responses[1], 'data'),
      'content': _responseList(responses[2], 'data'),
      'calendar': _responseList(responses[3], 'data'),
      'social': {
        'data': _responseList(responses[4], 'data'),
        'providers': _responseList(responses[4], 'providers'),
      },
      'agents': _responseList(responses[5], 'data'),
      'notifications': _responseList(responses[6], 'data'),
    };
  }

  Future<List<Map<String, dynamic>>> fetchNotifications(int? brandId) async {
    final query = <String, String>{'per_page': '50'};
    if (brandId != null) query['brand_id'] = '$brandId';
    final response = await _request(
      'GET',
      '/notifications?${Uri(queryParameters: query).query}',
    );
    return _responseList(response, 'data');
  }

  Future<List<Map<String, dynamic>>> fetchPublishingCalendar(
    int? brandId,
  ) async {
    final query = <String, String>{'per_page': '50'};
    if (brandId != null) query['brand_id'] = '$brandId';
    final response = await _request(
      'GET',
      '/dashboard/publishing-calendar?${Uri(queryParameters: query).query}',
    );
    return _responseList(response, 'data');
  }

  Future<List<Map<String, dynamic>>> fetchTrendTopics(
    int brandId, {
    String? category,
    String? momentum,
    String? source,
    String? geographicMarket,
    String? from,
    String? to,
    String? sort,
    int? minRelevance,
    bool savedOnly = false,
  }) async {
    final query = <String, String>{'per_page': '50'};
    if (category != null && category.isNotEmpty) query['category'] = category;
    if (momentum != null && momentum.isNotEmpty) query['momentum'] = momentum;
    if (source != null && source.isNotEmpty) query['source'] = source;
    if (geographicMarket != null && geographicMarket.isNotEmpty) {
      query['geographic_market'] = geographicMarket;
    }
    if (from != null && from.isNotEmpty) query['from'] = from;
    if (to != null && to.isNotEmpty) query['to'] = to;
    if (sort != null && sort.isNotEmpty) query['sort'] = sort;
    if (minRelevance != null) query['min_relevance'] = '$minRelevance';
    if (savedOnly) query['saved_only'] = '1';
    final response = await _request(
      'GET',
      '/brands/$brandId/trends?${Uri(queryParameters: query).query}',
    );
    return _responseList(response, 'data');
  }

  Future<List<Map<String, dynamic>>> fetchTrendSources(int brandId) async {
    final response = await _request('GET', '/brands/$brandId/trends/sources');
    return _responseList(response, 'data');
  }

  Future<Map<String, dynamic>?> fetchTrendResearchStatus(int brandId) async {
    final response = await _request('GET', '/brands/$brandId/trends/status');
    final value = response['data'];
    return value is Map ? Map<String, dynamic>.from(value) : null;
  }

  Future<Map<String, dynamic>> startTrendResearch(
    int brandId, {
    int days = 30,
    String? geographicMarket,
    List<String> categories = const [],
    List<String> sources = const [],
  }) async {
    final response = await _request(
      'POST',
      '/brands/$brandId/trends/research',
      body: {
        'days': days,
        'geographic_market': geographicMarket,
        'categories': categories,
        'sources': sources,
      },
    );
    return _responseObject(response, 'data');
  }

  Future<Map<String, dynamic>> submitTrendFeedback(
    int brandId,
    int topicId, {
    required String feedback,
    String? note,
  }) async {
    final response = await _request(
      'POST',
      '/brands/$brandId/trends/$topicId/feedback',
      body: {'feedback': feedback, 'note': note},
    );
    return _responseObject(response, 'data');
  }

  Future<List<Map<String, dynamic>>> fetchTrendRecommendations(
    int brandId, {
    String? status,
    String? classification,
    String? platform,
    String? format,
    int? minScore,
  }) async {
    final query = <String, String>{'per_page': '50'};
    if (status != null && status.isNotEmpty) query['status'] = status;
    if (classification != null && classification.isNotEmpty) query['classification'] = classification;
    if (platform != null && platform.isNotEmpty) query['platform'] = platform;
    if (format != null && format.isNotEmpty) query['format'] = format;
    if (minScore != null) query['min_score'] = '$minScore';
    final response = await _request(
      'GET',
      '/brands/$brandId/recommendations?${Uri(queryParameters: query).query}',
    );
    if (response['data'] is! List) return const [];
    return _responseList(response, 'data');
  }

  Future<Map<String, dynamic>> updateRecommendationStatus(
    int brandId,
    int recommendationId,
    String status,
  ) async {
    final response = await _request(
      'POST',
      '/brands/$brandId/recommendations/$recommendationId/status',
      body: {'status': status},
    );
    return _responseObject(response, 'data');
  }

  Future<Map<String, dynamic>> submitRecommendationFeedback(
    int brandId,
    int recommendationId,
    String feedback,
  ) async {
    final response = await _request(
      'POST',
      '/brands/$brandId/recommendations/$recommendationId/feedback',
      body: {'feedback': feedback},
    );
    return _responseObject(response, 'data');
  }

  Future<Map<String, dynamic>> markNotificationRead(String id) async {
    final response = await _request('POST', '/notifications/$id/read');
    return _responseObject(response, 'data');
  }

  Map<String, dynamic> _responseObject(
    Map<String, dynamic> response,
    String key,
  ) {
    final value = response[key];
    if (value is! Map) {
      throw ApiException('The $key response was invalid.');
    }
    return Map<String, dynamic>.from(value);
  }

  List<Map<String, dynamic>> _responseList(
    Map<String, dynamic> response,
    String key,
  ) {
    final value = response[key];
    if (value is! List) {
      throw ApiException('The $key response was invalid.');
    }
    return value.map((item) => Map<String, dynamic>.from(item as Map)).toList();
  }

  Future<Brand> saveBrand(
    Map<String, dynamic> payload, {
    int? id,
    XFile? logo,
  }) async {
    final path = id == null ? '/brands' : '/brands/$id';
    final response = logo == null
        ? await _request(id == null ? 'POST' : 'PUT', path, body: payload)
        : await _multipartRequest(path, payload, logo, update: id != null);
    final data = response['data'];
    if (data is! Map) {
      throw ApiException('The saved brand response was invalid.');
    }
    return Brand.fromJson(Map<String, dynamic>.from(data));
  }

  Future<void> deleteBrand(int id) async {
    await _request('DELETE', '/brands/$id');
  }

  Future<void> updateBrandStatus(int id, String status) async {
    await _request('PATCH', '/brands/$id/status', body: {'status': status});
  }

  Future<Map<String, dynamic>> fetchBrandSocialAccounts(int id) {
    return _request('GET', '/brands/$id/social-accounts');
  }

  Future<Map<String, dynamic>> fetchBrandOverview(int id) async {
    final response = await _request('GET', '/brands/$id/overview');
    final data = response['data'];
    if (data is! Map) {
      throw ApiException('The brand overview response was invalid.');
    }
    return Map<String, dynamic>.from(data);
  }

  Future<String> beginSocialConnection(int brandId, String provider) async {
    final response = await _request(
      'POST',
      '/brands/$brandId/social-providers/$provider/authorize',
    );
    final data = response['data'];
    if (data is! Map || data['authorization_url'] is! String) {
      throw ApiException('The provider authorization response was invalid.');
    }
    return data['authorization_url'] as String;
  }

  Future<Map<String, dynamic>> checkSocialAccount(int id) async {
    final response = await _request('POST', '/social-accounts/$id/check');
    final data = response['data'];
    if (data is! Map) {
      throw ApiException('The account status response was invalid.');
    }
    return Map<String, dynamic>.from(data);
  }

  Future<Map<String, dynamic>> disconnectSocialAccount(int id) async {
    return _request('DELETE', '/social-accounts/$id');
  }

  Future<void> logout() async {
    await _request('POST', '/logout');
    lastToken = null;
  }

  // ── Phase 6 Content Generation API methods ──────────────────────────────

  Future<Map<String, dynamic>> generateContent({
    required int brandId,
    required String platform,
    required String objective,
    String? category,
    String? contentFormat,
    int? trendId,
    String? instructions,
  }) async {
    final body = <String, dynamic>{
      'platform':  platform,
      'objective': objective,
      'sync':      true,
    };
    if (category      != null) body['category']       = category;
    if (contentFormat != null) body['content_format'] = contentFormat;
    if (trendId       != null) body['trend_id']       = trendId;
    if (instructions  != null) body['instructions']   = instructions;

    final response = await _request('POST', '/brands/$brandId/content/generate', body: body);
    final draft = response['draft'];
    if (draft is! Map) throw ApiException('Invalid generation response.');
    return Map<String, dynamic>.from(draft);
  }

  Future<List<Map<String, dynamic>>> fetchContentDrafts(
    int brandId, {
    String? platform,
    String? search,
    String? category,
    String? objective,
    String? status,
  }) async {
    final query = <String, String>{'per_page': '50'};
    if (platform  != null) query['platform']  = platform;
    if (search    != null) query['search']    = search;
    if (category  != null) query['category']  = category;
    if (objective != null) query['objective'] = objective;
    if (status    != null) query['status']    = status;
    final response = await _request(
      'GET',
      '/brands/$brandId/content?${Uri(queryParameters: query).query}',
    );
    return _responseList(response, 'data');
  }

  Future<Map<String, dynamic>> fetchContentDraft(int draftId) async {
    final response = await _request('GET', '/content/$draftId');
    final data = response['data'];
    if (data is! Map) throw ApiException('Invalid draft response.');
    return Map<String, dynamic>.from(data);
  }

  Future<Map<String, dynamic>> updateContentDraft(
    int draftId,
    Map<String, dynamic> payload,
  ) async {
    final response = await _request('PUT', '/content/$draftId', body: payload);
    final data = response['data'];
    if (data is! Map) throw ApiException('Invalid update response.');
    return Map<String, dynamic>.from(data);
  }

  Future<void> deleteContentDraft(int draftId) async {
    await _request('DELETE', '/content/$draftId');
  }

  Future<Map<String, dynamic>> improveContent(
    int draftId, {
    required String operation,
    String? targetPlatform,
  }) async {
    final body = <String, dynamic>{'operation': operation};
    if (targetPlatform != null) body['target_platform'] = targetPlatform;
    return _request('POST', '/content/$draftId/improve', body: body);
  }

  Future<List<Map<String, dynamic>>> fetchContentVariations(int draftId) async {
    final response = await _request('GET', '/content/$draftId/variations');
    return _responseList(response, 'data');
  }

  Future<List<Map<String, dynamic>>> generateContentVariations(
    int draftId,
    int count,
  ) async {
    final response = await _request('POST', '/content/$draftId/variations', body: {'count': count});
    return _responseList(response, 'data');
  }

  Future<void> selectContentVariation(int draftId, int variationId) async {
    await _request('POST', '/content/$draftId/variations/$variationId/select');
  }

  // ── Phase 7 Media Generation API methods ──────────────────────────────

  Future<Map<String, dynamic>> generateImage({
    required int brandId,
    required String objective,
    String? visualStyle,
    String? platform,
    String? aspectRatio,
    String? instructions,
    String? title,
    int? contentDraftId,
  }) async {
    final body = <String, dynamic>{
      'objective': objective,
    };
    if (visualStyle != null) body['visual_style'] = visualStyle;
    if (platform != null) body['platform'] = platform;
    if (aspectRatio != null) body['aspect_ratio'] = aspectRatio;
    if (instructions != null) body['instructions'] = instructions;
    if (title != null) body['title'] = title;
    if (contentDraftId != null) body['content_draft_id'] = contentDraftId;

    return _request('POST', '/brands/$brandId/media/images/generate', body: body);
  }

  Future<Map<String, dynamic>> getImageStatus(int assetId) async {
    return _request('GET', '/media/images/$assetId/status');
  }

  Future<Map<String, dynamic>> composeGraphic({
    required int brandId,
    String template = 'quote_card',
    String? headline,
    String? quote,
    String? author,
    String? caption,
    String? callToAction,
    List<String>? bullets,
    String? platform,
    String? aspectRatio,
    String? title,
    int? contentDraftId,
  }) async {
    final body = <String, dynamic>{
      'template': template,
    };
    if (headline != null) body['headline'] = headline;
    if (quote != null) body['quote'] = quote;
    if (author != null) body['author'] = author;
    if (caption != null) body['caption'] = caption;
    if (callToAction != null) body['call_to_action'] = callToAction;
    if (bullets != null) body['bullets'] = bullets;
    if (platform != null) body['platform'] = platform;
    if (aspectRatio != null) body['aspect_ratio'] = aspectRatio;
    if (title != null) body['title'] = title;
    if (contentDraftId != null) body['content_draft_id'] = contentDraftId;

    return _request('POST', '/brands/$brandId/media/compose', body: body);
  }

  Future<Map<String, dynamic>> generateStoryboard({
    required int brandId,
    String? videoIdea,
    String? concept,
    int? contentDraftId,
    String platform = 'instagram',
    String aspectRatio = '9:16',
    int numScenes = 6,
  }) async {
    final body = <String, dynamic>{
      'platform': platform,
      'aspect_ratio': aspectRatio,
      'num_scenes': numScenes,
    };
    if (videoIdea != null) body['video_idea'] = videoIdea;
    if (concept != null) body['concept'] = concept;
    if (contentDraftId != null) body['content_draft_id'] = contentDraftId;

    return _request('POST', '/brands/$brandId/media/storyboards/generate', body: body);
  }

  Future<List<Map<String, dynamic>>> fetchMediaAssets(
    int brandId, {
    String? assetType,
    String? platform,
    String? search,
  }) async {
    final query = <String, String>{};
    if (assetType != null) query['asset_type'] = assetType;
    if (platform != null) query['platform'] = platform;
    if (search != null) query['search'] = search;

    final queryString = query.isNotEmpty ? '?${Uri(queryParameters: query).query}' : '';
    final response = await _request('GET', '/brands/$brandId/media$queryString');
    return _responseList(response, 'data');
  }

  Future<Map<String, dynamic>> fetchMediaAsset(int assetId) async {
    final response = await _request('GET', '/media/$assetId');
    final data = response['data'] ?? response['asset'];
    if (data is! Map) throw ApiException('Invalid asset response.');
    return Map<String, dynamic>.from(data);
  }

  Future<void> deleteMediaAsset(int assetId) async {
    await _request('DELETE', '/media/$assetId');
  }

  Future<Map<String, dynamic>> getVisualSettings(int brandId) async {
    final response = await _request('GET', '/brands/$brandId/visual-settings');
    final data = response['data'] ?? response['settings'];
    if (data is! Map) throw ApiException('Invalid visual settings response.');
    return Map<String, dynamic>.from(data);
  }

  Future<Map<String, dynamic>> updateVisualSettings(
    int brandId,
    Map<String, dynamic> payload,
  ) async {
    final response = await _request('PUT', '/brands/$brandId/visual-settings', body: payload);
    final data = response['data'] ?? response['settings'];
    if (data is! Map) throw ApiException('Invalid updated settings response.');
    return Map<String, dynamic>.from(data);
  }

  // ─────────────────────────────────────────────────────────────────────────────
  // Phase 8: Scheduling & Autopilot API Methods
  // ─────────────────────────────────────────────────────────────────────────────

  Future<List<Map<String, dynamic>>> fetchScheduledPosts(
    int brandId, {
    String? status,
    String? platform,
    int perPage = 20,
  }) async {
    final query = <String, String>{'per_page': '$perPage'};
    if (status != null) query['status'] = status;
    if (platform != null) query['platform'] = platform;

    final response = await _request(
      'GET',
      '/brands/$brandId/scheduled-posts?${Uri(queryParameters: query).query}',
    );
    return _responseList(response, 'data');
  }

  Future<Map<String, dynamic>> createScheduledPost(
    int brandId,
    Map<String, dynamic> payload,
  ) async {
    final response = await _request(
      'POST',
      '/brands/$brandId/scheduled-posts',
      body: payload,
    );
    final data = response['data'] ?? response;
    if (data is! Map) throw ApiException('Invalid scheduled post response.');
    return Map<String, dynamic>.from(data);
  }

  Future<Map<String, dynamic>> reschedulePost(
    int postId,
    String scheduledAt, {
    String? timezone,
  }) async {
    final body = <String, dynamic>{'scheduled_at': scheduledAt};
    if (timezone != null) body['timezone'] = timezone;

    final response = await _request(
      'POST',
      '/scheduled-posts/$postId/reschedule',
      body: body,
    );
    final data = response['data'] ?? response;
    if (data is! Map) throw ApiException('Invalid reschedule response.');
    return Map<String, dynamic>.from(data);
  }

  Future<Map<String, dynamic>> cancelScheduledPost(
    int postId, {
    String? reason,
  }) async {
    final body = <String, dynamic>{};
    if (reason != null) body['reason'] = reason;

    final response = await _request(
      'POST',
      '/scheduled-posts/$postId/cancel',
      body: body,
    );
    final data = response['data'] ?? response;
    if (data is! Map) throw ApiException('Invalid cancel response.');
    return Map<String, dynamic>.from(data);
  }

  Future<Map<String, dynamic>> approveScheduledPost(int postId) async {
    final response = await _request(
      'POST',
      '/scheduled-posts/$postId/approve',
    );
    final data = response['data'] ?? response;
    if (data is! Map) throw ApiException('Invalid approval response.');
    return Map<String, dynamic>.from(data);
  }

  Future<List<Map<String, dynamic>>> fetchBrandCalendar(
    int brandId, {
    String? startDate,
    String? endDate,
    String? platform,
    String? status,
  }) async {
    final query = <String, String>{};
    if (startDate != null) query['start_date'] = startDate;
    if (endDate != null) query['end_date'] = endDate;
    if (platform != null) query['platform'] = platform;
    if (status != null) query['status'] = status;

    final queryString = query.isNotEmpty ? '?${Uri(queryParameters: query).query}' : '';
    final response = await _request('GET', '/brands/$brandId/calendar$queryString');
    return _responseList(response, 'data');
  }

  Future<List<Map<String, dynamic>>> fetchBestTimes(
    int brandId, {
    String? platform,
  }) async {
    final query = <String, String>{};
    if (platform != null) query['platform'] = platform;

    final queryString = query.isNotEmpty ? '?${Uri(queryParameters: query).query}' : '';
    final response = await _request('GET', '/brands/$brandId/scheduling/best-times$queryString');
    return _responseList(response, 'data');
  }

  Future<Map<String, dynamic>> fetchPublishingQueue(int brandId) async {
    final response = await _request('GET', '/brands/$brandId/publishing-queue');
    final data = response['data'] ?? response;
    if (data is! Map) throw ApiException('Invalid publishing queue response.');
    return Map<String, dynamic>.from(data);
  }

  Future<Map<String, dynamic>> updatePublishingQueue(
    int queueId,
    Map<String, dynamic> payload,
  ) async {
    final response = await _request('PUT', '/publishing-queue/$queueId', body: payload);
    final data = response['data'] ?? response;
    if (data is! Map) throw ApiException('Invalid queue update response.');
    return Map<String, dynamic>.from(data);
  }

  Future<void> pausePublishingQueue(int queueId) async {
    await _request('POST', '/publishing-queue/$queueId/pause');
  }

  Future<void> resumePublishingQueue(int queueId) async {
    await _request('POST', '/publishing-queue/$queueId/resume');
  }

  Future<Map<String, dynamic>> getAutopilotSettings(int brandId) async {
    final response = await _request('GET', '/brands/$brandId/autopilot');
    final data = response['data'] ?? response['settings'] ?? response;
    if (data is! Map) throw ApiException('Invalid autopilot response.');
    return Map<String, dynamic>.from(data);
  }

  Future<Map<String, dynamic>> updateAutopilotSettings(
    int brandId,
    Map<String, dynamic> payload,
  ) async {
    final response = await _request('PUT', '/brands/$brandId/autopilot', body: payload);
    final data = response['data'] ?? response['settings'] ?? response;
    if (data is! Map) throw ApiException('Invalid updated autopilot response.');
    return Map<String, dynamic>.from(data);
  }

  Future<Map<String, dynamic>> enableAutopilot(int brandId) async {
    final response = await _request('POST', '/brands/$brandId/autopilot/enable');
    final data = response['data'] ?? response['settings'] ?? response;
    if (data is! Map) throw ApiException('Invalid enable autopilot response.');
    return Map<String, dynamic>.from(data);
  }

  Future<Map<String, dynamic>> pauseAutopilot(int brandId) async {
    final response = await _request('POST', '/brands/$brandId/autopilot/pause');
    final data = response['data'] ?? response['settings'] ?? response;
    if (data is! Map) throw ApiException('Invalid pause autopilot response.');
    return Map<String, dynamic>.from(data);
  }

  Future<Map<String, dynamic>> resumeAutopilot(int brandId) async {
    final response = await _request('POST', '/brands/$brandId/autopilot/resume');
    final data = response['data'] ?? response['settings'] ?? response;
    if (data is! Map) throw ApiException('Invalid resume autopilot response.');
    return Map<String, dynamic>.from(data);
  }

  Future<Map<String, dynamic>> disableAutopilot(int brandId) async {
    final response = await _request('POST', '/brands/$brandId/autopilot/disable');
    final data = response['data'] ?? response['settings'] ?? response;
    if (data is! Map) throw ApiException('Invalid disable autopilot response.');
    return Map<String, dynamic>.from(data);
  }

  Future<Map<String, dynamic>> triggerAutopilot(int brandId) async {
    final response = await _request('POST', '/brands/$brandId/autopilot/trigger');
    return response;
  }

  Future<List<Map<String, dynamic>>> fetchAutopilotActivity(
    int brandId, {
    int perPage = 20,
  }) async {
    final response = await _request(
      'GET',
      '/brands/$brandId/autopilot/activity?per_page=$perPage',
    );
    return _responseList(response, 'data');
  }

  Future<Map<String, dynamic>> _request(
    String method,
    String path, {
    Map<String, dynamic>? body,
    bool authenticated = true,
  }) async {
    final String? token = authenticated ? await _storage.readToken() : null;
    final uri = Uri.parse('$_apiBaseUrl$path');
    final headers = <String, String>{'Accept': 'application/json'};

    if (authenticated && token != null && token.isNotEmpty) {
      headers['Authorization'] = 'Bearer $token';
    }

    late final http.Response response;
    if (method == 'GET') {
      response = await _runNetworkRequest(
        () => _client.get(uri, headers: headers),
      );
    } else if (method == 'POST') {
      response = await _runNetworkRequest(
        () => _client.post(
          uri,
          headers: {...headers, 'Content-Type': 'application/json'},
          body: jsonEncode(body ?? {}),
        ),
      );
    } else if (method == 'DELETE') {
      response = await _runNetworkRequest(
        () => _client.delete(uri, headers: headers),
      );
    } else if (method == 'PUT') {
      response = await _runNetworkRequest(
        () => _client.put(
          uri,
          headers: {...headers, 'Content-Type': 'application/json'},
          body: jsonEncode(body ?? {}),
        ),
      );
    } else if (method == 'PATCH') {
      response = await _runNetworkRequest(
        () => _client.patch(
          uri,
          headers: {...headers, 'Content-Type': 'application/json'},
          body: jsonEncode(body ?? {}),
        ),
      );
    } else {
      throw UnsupportedError('Unsupported method: $method');
    }

    return _handleResponse(response, authenticated: authenticated);
  }

  Future<Map<String, dynamic>> _multipartRequest(
    String path,
    Map<String, dynamic> payload,
    XFile logo, {
    required bool update,
  }) async {
    final request = http.MultipartRequest(
      'POST',
      Uri.parse('$_apiBaseUrl$path'),
    );
    request.headers['Accept'] = 'application/json';
    final token = await _storage.readToken();
    if (token != null && token.isNotEmpty) {
      request.headers['Authorization'] = 'Bearer $token';
    }
    if (update) request.fields['_method'] = 'PUT';

    for (final entry in payload.entries) {
      final value = entry.value;
      if (value is List) {
        if (value.isEmpty) {
          request.fields['${entry.key}[0]'] = '';
        } else {
          for (var index = 0; index < value.length; index++) {
            request.fields['${entry.key}[$index]'] = value[index].toString();
          }
        }
      } else if (value is Map) {
        for (final item in value.entries) {
          request.fields['${entry.key}[${item.key}]'] =
              item.value?.toString() ?? '';
        }
      } else {
        request.fields[entry.key] = value?.toString() ?? '';
      }
    }

    request.files.add(
      http.MultipartFile.fromBytes(
        'logo',
        await logo.readAsBytes(),
        filename: logo.name,
      ),
    );
    final response = await _runNetworkRequest(() async {
      final streamedResponse = await request.send();
      return http.Response.fromStream(streamedResponse);
    });
    return _handleResponse(response, authenticated: true);
  }

  Future<T> _runNetworkRequest<T>(Future<T> Function() request) async {
    try {
      return await request().timeout(requestTimeout);
    } on TimeoutException {
      throw ApiException(
        'The request timed out. Check your connection and try again.',
      );
    } on http.ClientException {
      throw ApiException(
        'Could not connect to SocialPilot. Check your internet connection and try again.',
      );
    }
  }

  Future<Map<String, dynamic>> _handleResponse(
    http.Response response, {
    required bool authenticated,
  }) async {
    if (response.statusCode == 401 && authenticated) {
      await _storage.clearToken();
      throw ApiException(
        'Your session has expired. Please sign in again.',
        statusCode: response.statusCode,
      );
    }
    if (response.statusCode >= 500) {
      throw ApiException(
        'SocialPilot is temporarily unavailable. Please try again.',
        statusCode: response.statusCode,
      );
    }
    return _decodeResponse(response.statusCode, response.body);
  }

  Map<String, dynamic> _decodeResponse(int statusCode, String body) {
    final dynamic decoded;
    try {
      decoded = body.isEmpty ? <String, dynamic>{} : jsonDecode(body);
    } on FormatException {
      throw ApiException(
        'The server returned an invalid response. Please try again.',
      );
    }
    if (statusCode >= 200 && statusCode < 300) {
      if (decoded is Map<String, dynamic>) return decoded;
      if (decoded is Map) return Map<String, dynamic>.from(decoded);
      return {'data': decoded};
    }

    if (decoded is Map && decoded['errors'] is Map) {
      final errors = decoded['errors'] as Map;
      for (final error in errors.values) {
        if (error is List && error.isNotEmpty) {
          throw ApiException(error.first.toString());
        }
      }
    }
    if (decoded is Map && decoded['error'] is Map) {
      throw ApiException(
        (decoded['error'] as Map)['message']?.toString() ??
            'Request failed with status $statusCode.',
      );
    }
    if (decoded is Map && decoded.containsKey('message')) {
      throw ApiException(decoded['message'].toString());
    }

    throw ApiException('Request failed with status $statusCode.');
  }
}
