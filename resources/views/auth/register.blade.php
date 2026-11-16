<x-guest-layout>
    <div class="auth-card">
        <a class="brand-mark auth-brand" href="{{ route('welcome') }}">
            <span class="brand-icon"><img src="{{ asset('assets/logo.png') }}" alt="SocialPilot AI logo" width="48" height="48"></span>
            <span>SocialPilot AI</span>
        </a>
        <div class="auth-heading">
            <p class="eyebrow">Create your workspace</p>
            <h1>Create an account</h1>
            <p>Start organizing your brands, content, and social workflow.</p>
        </div>

        <form method="POST" action="{{ route('register') }}" class="auth-form">
            @csrf
            <div class="form-field">
                <label for="name">Full name</label>
                <input id="name" name="name" type="text" value="{{ old('name') }}" required autofocus autocomplete="name" aria-describedby="name-error">
                @error('name')<p class="field-error" id="name-error">{{ $message }}</p>@enderror
            </div>

            <div class="form-field">
                <label for="email">Email address</label>
                <input id="email" name="email" type="email" value="{{ old('email') }}" required autocomplete="email" aria-describedby="email-error">
                @error('email')<p class="field-error" id="email-error">{{ $message }}</p>@enderror
            </div>

            <div class="form-field">
                <label for="password">Password</label>
                <div class="password-input">
                    <input id="password" name="password" type="password" required autocomplete="new-password" aria-describedby="password-hint password-error">
                    <button class="password-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false">Show</button>
                </div>
                <p class="field-hint" id="password-hint">Use a secure password with at least 8 characters.</p>
                @error('password')<p class="field-error" id="password-error">{{ $message }}</p>@enderror
            </div>

            <div class="form-field">
                <label for="password_confirmation">Confirm password</label>
                <div class="password-input">
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" aria-describedby="password-confirmation-error">
                    <button class="password-toggle" type="button" data-password-toggle="password_confirmation" aria-label="Show confirmation password" aria-pressed="false">Show</button>
                </div>
                @error('password_confirmation')<p class="field-error" id="password-confirmation-error">{{ $message }}</p>@enderror
            </div>

            <div class="terms-field">
                <input id="terms" name="terms" type="checkbox" value="1" @checked(old('terms')) required aria-describedby="terms-error">
                <label for="terms">I agree to the <a href="{{ route('welcome') }}#terms">current terms</a> and acknowledge the <a href="{{ route('welcome') }}#privacy">privacy notice</a>.</label>
            </div>
            @error('terms')<p class="field-error" id="terms-error">{{ $message }}</p>@enderror

            <button type="submit" class="button auth-submit">Create account</button>
        </form>

        <p class="auth-switch">Already have an account? <a href="{{ route('login') }}">Log in</a></p>
        <p class="auth-back"><a href="{{ route('welcome') }}">â† Back to SocialPilot AI</a></p>
    </div>
</x-guest-layout>

