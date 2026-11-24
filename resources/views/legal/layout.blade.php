<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Compliance' }} — {{ config('app.name', 'SocialPilot AI') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/logo-32.png') }}">
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="apple-touch-icon" sizes="192x192" href="{{ asset('assets/logo-192.png') }}">
    <meta name="application-name" content="SocialPilot AI">
    <meta name="theme-color" content="#6366f1">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/socialpilot.css') }}">
    <style>
        .legal-shell {
            max-width: 900px;
            margin: 2rem auto;
            padding: 2.5rem 2rem;
            background: var(--surface, #ffffff);
            border: 1px solid var(--stroke, #e5e7eb);
            border-radius: 12px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
            line-height: 1.7;
            color: var(--text, #1f2937);
        }
        .legal-shell h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--text-dark, #111827);
        }
        .legal-shell h2 {
            font-size: 1.35rem;
            font-weight: 600;
            margin-top: 2rem;
            margin-bottom: 0.75rem;
            padding-bottom: 0.35rem;
            border-bottom: 1px solid var(--stroke, #e5e7eb);
            color: var(--text-dark, #111827);
        }
        .legal-shell h3 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-top: 1.25rem;
            margin-bottom: 0.5rem;
        }
        .legal-shell p, .legal-shell ul, .legal-shell ol {
            margin-bottom: 1rem;
        }
        .legal-shell ul, .legal-shell ol {
            padding-left: 1.5rem;
        }
        .legal-shell li {
            margin-bottom: 0.35rem;
        }
        .legal-shell table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5rem 0;
            font-size: 0.92rem;
        }
        .legal-shell th, .legal-shell td {
            border: 1px solid var(--stroke, #e5e7eb);
            padding: 0.65rem 0.85rem;
            text-align: left;
        }
        .legal-shell th {
            background: var(--surface-alt, #f9fafb);
            font-weight: 600;
        }
        .legal-badge-notice {
            display: inline-block;
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fcd34d;
            padding: 0.4rem 0.85rem;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }
        .placeholder-tag {
            background: #fee2e2;
            color: #991b1b;
            padding: 0.15rem 0.4rem;
            border-radius: 4px;
            font-family: monospace;
            font-size: 0.88rem;
        }
        .legal-nav-strip {
            display: flex;
            gap: 1.25rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--stroke, #e5e7eb);
            flex-wrap: wrap;
            font-size: 0.95rem;
        }
        .legal-nav-strip a {
            color: var(--primary, #4f46e5);
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="marketing-shell">
        <header class="topbar marketing-topbar" aria-label="Site header">
            <a class="brand-mark" href="{{ route('welcome') }}" aria-label="SocialPilot AI home">
                <span class="brand-icon"><img src="{{ asset('assets/logo.png') }}" alt="SocialPilot AI logo" width="36" height="36"></span>
                <span>SocialPilot AI</span>
            </a>
            <nav class="marketing-navigation" id="marketing-navigation" aria-label="Main navigation">
                <div class="nav-links">
                    <a href="{{ route('welcome') }}">Home</a>
                    <a href="{{ route('terms') }}">Terms</a>
                    <a href="{{ route('privacy') }}">Privacy</a>
                    <a href="{{ route('data-deletion') }}">Data Deletion</a>
                </div>
                <div class="nav-actions">
                    @auth
                        <a href="{{ route('dashboard') }}" class="button-secondary">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="button-secondary">Log in</a>
                        <a href="{{ route('register') }}" class="button">Create account</a>
                    @endauth
                </div>
            </nav>
        </header>

        <main id="main-content" class="legal-shell">
            <div class="legal-badge-notice" role="note">
                DRAFT FOR REVIEW — SPECIFIC BUSINESS DETAILS PENDING FINAL CONFIRMATION
            </div>

            @hasSection('content')
                @yield('content')
            @else
                {{ $slot ?? '' }}
            @endif

            <nav class="legal-nav-strip" aria-label="Compliance documents navigation">
                <strong>Compliance Documents:</strong>
                <a href="{{ route('privacy') }}">Privacy Policy</a>
                <a href="{{ route('terms') }}">Terms of Service</a>
                <a href="{{ route('data-deletion') }}">User Data Deletion</a>
                <a href="{{ route('welcome') }}">Back to Home</a>
            </nav>
        </main>

        <footer class="marketing-footer">
            <a class="brand-mark" href="{{ route('welcome') }}"><span class="brand-icon"><img src="{{ asset('assets/logo.png') }}" alt="SocialPilot AI logo" width="36" height="36"></span><span>SocialPilot AI</span></a>
            <span>Social planning, made more manageable.</span>
            <div style="display:flex;gap:1rem;flex-wrap:wrap;">
                <a href="{{ route('terms') }}">Terms of Service</a>
                <a href="{{ route('privacy') }}">Privacy Policy</a>
                <a href="{{ route('data-deletion') }}">User Data Deletion</a>
            </div>
        </footer>
    </div>
    <script src="{{ asset('assets/socialpilot.js') }}" defer></script>
</body>
</html>
