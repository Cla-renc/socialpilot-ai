<x-guest-layout>
    <div class="auth-card">
        <a class="brand-mark auth-brand" href="{{ route('welcome') }}">
            <span class="brand-icon"><img src="{{ asset('assets/logo.png') }}" alt="SocialPilot AI logo" width="48" height="48"></span>
            <span>SocialPilot AI</span>
        </a>
        <div class="auth-heading">
            <p class="eyebrow">One more step</p>
            <h1>Verify your email</h1>
            <p>Thanks for signing up. If verification is enabled, use the link sent to your email address. You can request another below.</p>
        </div>

        @if (session('status') == 'verification-link-sent')
            <div class="auth-status" role="status">A new verification link has been sent to your email address.</div>
        @endif

        <div class="auth-form">
            <form method="POST" action="{{ route('verification.send') }}">
                @csrf
                <button type="submit" class="button auth-submit">Resend verification email</button>
            </form>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="button-secondary auth-submit">Log out</button>
            </form>
        </div>
    </div>
</x-guest-layout>

