<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'SocialPilot AI') }}</title>
        <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('assets/logo-32.png') }}">
        <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
        <link rel="apple-touch-icon" sizes="192x192" href="{{ asset('assets/logo-192.png') }}">
        <meta name="application-name" content="SocialPilot AI">
        <meta name="theme-color" content="#6366f1">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="{{ asset('assets/socialpilot.css') }}">
    </head>
    <body>
        <div class="app-shell">
            @include('layouts.navigation')

            @isset($header)
                <header class="page-header">
                    {{ $header }}
                </header>
            @endisset

            <main>
                {{ $slot }}
            </main>
        </div>
        <script src="{{ asset('assets/socialpilot.js') }}" defer></script>
    </body>
</html>
