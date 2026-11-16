<x-guest-layout>
    <div class="auth-card">
        <a class="brand-mark auth-brand" href="{{ route('welcome') }}">
            <span class="brand-icon"><img src="{{ asset('assets/logo.png') }}" alt="SocialPilot AI logo" width="48" height="48"></span>
            <span>SocialPilot AI</span>
        </a>
        <div class="auth-heading">
            <p class="eyebrow">Security check</p>
            <h1>Confirm your password</h1>
            <p>This is a secure area. Confirm your password before continuing.</p>
        </div>
        <form method="POST" action="{{ route('password.confirm') }}" class="auth-form">
            @csrf
            <div class="form-field">
                <label for="password">Password</label>
                <div class="password-input">
                    <input id="password" name="password" type="password" required autofocus autocomplete="current-password" aria-describedby="password-error">
                    <button class="password-toggle" type="button" data-password-toggle="password" aria-label="Show password" aria-pressed="false">Show</button>
                </div>
                @error('password')<p class="field-error" id="password-error">{{ $message }}</p>@enderror
            </div>
            <button type="submit" class="button auth-submit">Confirm</button>
        </form>
    </div>
</x-guest-layout>

