<x-guest-layout>
    <div class="auth-card">
        <a class="brand-mark auth-brand" href="{{ route('welcome') }}">
            <span class="brand-icon"><img src="{{ asset('assets/logo.png') }}" alt="SocialPilot AI logo" width="48" height="48"></span>
            <span>SocialPilot AI</span>
        </a>
        <div class="auth-heading">
            <p class="eyebrow">Account recovery</p>
            <h1>Forgot your password?</h1>
            <p>Enter your registered email address and weâ€™ll send you a password reset link to recover your account.</p>
        </div>

        @if (session('status'))
            <div class="auth-status" role="status" style="display:flex;align-items:center;gap:10px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink:0;">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                    <polyline points="22 4 12 14.01 9 11.01"></polyline>
                </svg>
                <span>{{ session('status') }}</span>
            </div>
        @endif

        <form method="POST" action="{{ route('password.email') }}" class="auth-form" id="forgot-password-form">
            @csrf
            <div class="form-field">
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email" placeholder="name@company.com" aria-describedby="email-error">
                @error('email')<p class="field-error" id="email-error">{{ $message }}</p>@enderror
            </div>

            <button type="submit" class="button auth-submit" id="submit-reset-btn">
                <span class="btn-text">Send Password Reset Link</span>
            </button>
        </form>

        <p class="auth-switch">Remember your password? <a href="{{ route('login') }}">Back to Login</a></p>
        <p class="auth-back"><a href="{{ route('welcome') }}">â† Back to SocialPilot AI</a></p>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('forgot-password-form');
            if (form) {
                form.addEventListener('submit', () => {
                    const btn = document.getElementById('submit-reset-btn');
                    if (btn) {
                        btn.disabled = true;
                        btn.style.opacity = '0.7';
                        btn.innerHTML = '<span class="btn-text">Sending reset link...</span>';
                    }
                });
            }
        });
    </script>
</x-guest-layout>

