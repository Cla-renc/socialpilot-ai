<x-guest-layout>
    <div class="auth-card">
        <a class="brand-mark auth-brand" href="{{ route('welcome') }}">
            <span class="brand-icon"><img src="{{ asset('assets/logo.png') }}" alt="SocialPilot AI logo" width="48" height="48"></span>
            <span>SocialPilot AI</span>
        </a>
        <div class="auth-heading">
            <p class="eyebrow">Account recovery</p>
            <h1>Reset your password</h1>
            <p>Please enter your email and choose a new, strong password for your account.</p>
        </div>

        <form method="POST" action="{{ route('password.store') }}" class="auth-form" id="reset-password-form">
            @csrf
            <input type="hidden" name="token" value="{{ $request->route('token') }}">

            <div class="form-field">
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username" placeholder="name@company.com" aria-describedby="email-error">
                @error('email')<p class="field-error" id="email-error">{{ $message }}</p>@enderror
            </div>

            <div class="form-field">
                <label for="password">New password</label>
                <div class="password-input">
                    <input id="password" name="password" type="password" required autocomplete="new-password" placeholder="Minimum 8 characters" aria-describedby="password-error">
                    <button class="password-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false">Show</button>
                </div>
                @error('password')<p class="field-error" id="password-error">{{ $message }}</p>@enderror
            </div>

            <div class="form-field">
                <label for="password_confirmation">Confirm new password</label>
                <div class="password-input">
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" placeholder="Confirm your new password" aria-describedby="password-confirmation-error">
                    <button class="password-toggle" type="button" data-password-toggle="password_confirmation" aria-label="Show confirmation password" aria-pressed="false">Show</button>
                </div>
                @error('password_confirmation')<p class="field-error" id="password-confirmation-error">{{ $message }}</p>@enderror
            </div>

            <button type="submit" class="button auth-submit" id="reset-submit-btn">
                <span class="btn-text">Reset Password</span>
            </button>
        </form>

        <p class="auth-switch"><a href="{{ route('login') }}">â† Back to Login</a></p>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('reset-password-form');
            if (form) {
                form.addEventListener('submit', () => {
                    const btn = document.getElementById('reset-submit-btn');
                    if (btn) {
                        btn.disabled = true;
                        btn.style.opacity = '0.7';
                        btn.innerHTML = '<span class="btn-text">Resetting password...</span>';
                    }
                });
            }
        });
    </script>
</x-guest-layout>

