<x-guest-layout>
    <div class="auth-card">
        <a class="brand-mark auth-brand" href="{{ route('welcome') }}">
            <span class="brand-icon"><img src="{{ asset('assets/logo.png') }}" alt="SocialPilot AI logo" width="48" height="48"></span>
            <span>SocialPilot AI</span>
        </a>
        <div class="auth-heading">
            <p class="eyebrow">Welcome back</p>
            <h1>Log in to your account</h1>
            <p>Pick up where you left off with your brands and content.</p>
        </div>

        @if (session('status'))
            <div class="auth-status" role="status">{{ session('status') }}</div>
        @endif

        <form method="POST" action="{{ route('login') }}" class="auth-form">
            @csrf
            <div class="form-field">
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username" aria-describedby="email-error">
                @error('email')<p class="field-error" id="email-error">{{ $message }}</p>@enderror
            </div>

            <div class="form-field">
                <div class="field-label-row">
                    <label for="password">Password</label>
                    <a href="{{ route('password.request') }}">Forgot password?</a>
                </div>
                <div class="password-input">
                    <input id="password" name="password" type="password" required autocomplete="current-password" aria-describedby="password-error">
                    <button class="password-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false">Show</button>
                </div>
                @error('password')<p class="field-error" id="password-error">{{ $message }}</p>@enderror
            </div>

            <label class="remember-field" for="remember">
                <input id="remember" name="remember" type="checkbox" value="1" @checked(old('remember'))>
                <span>Remember me</span>
            </label>

            <button type="submit" class="button auth-submit">Log in</button>
        </form>

        <p class="auth-switch">New to SocialPilot AI? <a href="{{ route('register') }}">Create an account</a></p>
        <p class="auth-back"><a href="{{ route('welcome') }}">â† Back to SocialPilot AI</a></p>
    </div>
</x-guest-layout>

