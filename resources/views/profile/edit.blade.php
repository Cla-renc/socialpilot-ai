<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">Account Settings</p>
                <h1>User Profile</h1>
                <p class="muted">Manage your personal details, security credentials, and account preferences.</p>
            </div>
            <div class="page-tools">
                <a href="{{ route('dashboard') }}" class="button-secondary">Back to Dashboard</a>
            </div>
        </div>
    </x-slot>

    <div class="profile-shell">
        {{-- Profile Hero Banner --}}
        <div class="profile-hero">
            <div class="profile-avatar-large" aria-hidden="true">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
            <div class="profile-hero-meta">
                <h1>{{ $user->name }}</h1>
                <p>{{ $user->email }}</p>
                <div class="profile-hero-badge">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"></polyline>
                    </svg>
                    <span>{{ $user->email_verified_at ? 'Verified Account' : 'Standard Member' }}</span>
                </div>
            </div>
        </div>

        {{-- Main Settings Grid --}}
        <div class="profile-cards-grid">
            {{-- Profile Information Card --}}
            <section class="profile-card" aria-labelledby="profile-info-heading">
                <div>
                    <div class="profile-card-header">
                        <div class="profile-card-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                <circle cx="12" cy="7" r="4"></circle>
                            </svg>
                        </div>
                        <div class="profile-card-header-text">
                            <h2 id="profile-info-heading">Personal Information</h2>
                            <p>Update your public display name and account email.</p>
                        </div>
                    </div>

                    @if (session('status') === 'profile-updated')
                        <div class="profile-status-banner" role="status">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                            <span>Your profile information has been saved successfully.</span>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('profile.update') }}" class="auth-form" id="profile-update-form">
                        @csrf
                        @method('patch')

                        <div class="form-field">
                            <label for="name">Full name</label>
                            <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required autocomplete="name" placeholder="Your name">
                            @error('name')<p class="field-error">{{ $message }}</p>@enderror
                        </div>

                        <div class="form-field">
                            <label for="email">Email address</label>
                            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="username" placeholder="you@domain.com">
                            @error('email')<p class="field-error">{{ $message }}</p>@enderror
                        </div>

                        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                            <div style="margin-top: 10px; font-size: 0.85rem; color: #fbbf24;">
                                <p>Your email address is currently unverified.</p>
                            </div>
                        @endif

                        <div style="margin-top: 8px;">
                            <button type="submit" class="button" id="save-profile-btn" style="width: 100%;">
                                <span class="btn-text">Save Changes</span>
                            </button>
                        </div>
                    </form>
                </div>
            </section>

            {{-- Password & Security Card --}}
            <section class="profile-card" aria-labelledby="password-heading">
                <div>
                    <div class="profile-card-header">
                        <div class="profile-card-icon">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                            </svg>
                        </div>
                        <div class="profile-card-header-text">
                            <h2 id="password-heading">Password & Security</h2>
                            <p>Ensure your account uses a strong, random password.</p>
                        </div>
                    </div>

                    @if (session('status') === 'password-updated')
                        <div class="profile-status-banner" role="status">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                            <span>Your password has been changed successfully.</span>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('password.update') }}" class="auth-form" id="password-update-form">
                        @csrf
                        @method('put')

                        <div class="form-field">
                            <label for="current_password">Current password</label>
                            <div class="password-input">
                                <input id="current_password" name="current_password" type="password" autocomplete="current-password" placeholder="Enter current password" required>
                                <button class="password-toggle" type="button" data-password-toggle="current_password" aria-label="Show password" aria-pressed="false">Show</button>
                            </div>
                            @if ($errors->updatePassword->has('current_password'))
                                <p class="field-error">{{ $errors->updatePassword->first('current_password') }}</p>
                            @endif
                        </div>

                        <div class="form-field">
                            <label for="new_password">New password</label>
                            <div class="password-input">
                                <input id="new_password" name="password" type="password" autocomplete="new-password" placeholder="Minimum 8 characters" required>
                                <button class="password-toggle" type="button" data-password-toggle="new_password" aria-label="Show password" aria-pressed="false">Show</button>
                            </div>
                            @if ($errors->updatePassword->has('password'))
                                <p class="field-error">{{ $errors->updatePassword->first('password') }}</p>
                            @endif
                        </div>

                        <div class="form-field">
                            <label for="password_confirmation">Confirm new password</label>
                            <div class="password-input">
                                <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" placeholder="Confirm your new password" required>
                                <button class="password-toggle" type="button" data-password-toggle="password_confirmation" aria-label="Show password" aria-pressed="false">Show</button>
                            </div>
                            @if ($errors->updatePassword->has('password_confirmation'))
                                <p class="field-error">{{ $errors->updatePassword->first('password_confirmation') }}</p>
                            @endif
                        </div>

                        <div style="margin-top: 8px;">
                            <button type="submit" class="button" id="update-password-btn" style="width: 100%;">
                                <span class="btn-text">Update Password</span>
                            </button>
                        </div>
                    </form>
                </div>
            </section>
        </div>

        {{-- Danger Zone: Account Deletion Card --}}
        <section class="profile-card card-danger" aria-labelledby="delete-account-heading">
            <div class="profile-card-header">
                <div class="profile-card-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                        <line x1="12" y1="9" x2="12" y2="13"></line>
                        <line x1="12" y1="17" x2="12.01" y2="17"></line>
                    </svg>
                </div>
                <div class="profile-card-header-text">
                    <h2 id="delete-account-heading" style="color: #f87171;">Danger Zone · Delete Account</h2>
                    <p>Permanently remove your SocialPilot AI account and all associated resources.</p>
                </div>
            </div>

            <div class="danger-warning-box">
                <strong>Warning:</strong> Deleting your account will permanently remove all created brands, connected social account credentials, generated content drafts, schedule queues, and historical analytics snapshots. This action cannot be reversed.
            </div>

            @if ($errors->userDeletion->has('password'))
                <div class="field-error" style="margin-bottom: 14px; font-weight: 600;">
                    {{ $errors->userDeletion->first('password') }}
                </div>
            @endif

            <div>
                <button type="button" class="button-danger" id="open-delete-modal-btn" onclick="document.getElementById('delete-modal').style.display='grid'">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="3 6 5 6 21 6"></polyline>
                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                    </svg>
                    <span>Delete Account</span>
                </button>
            </div>
        </section>

        {{-- Delete Account Confirmation Modal --}}
        <div id="delete-modal" class="modal-overlay" style="display: {{ $errors->userDeletion->isNotEmpty() ? 'grid' : 'none' }};">
            <div class="modal-content" role="dialog" aria-modal="true" aria-labelledby="modal-title">
                <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
                    <div style="width:36px;height:36px;border-radius:10px;background:rgba(239,68,68,0.15);display:grid;place-items:center;color:#ef4444;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path>
                            <line x1="12" y1="9" x2="12" y2="13"></line>
                            <line x1="12" y1="17" x2="12.01" y2="17"></line>
                        </svg>
                    </div>
                    <h2 id="modal-title" style="margin:0;font-size:1.2rem;color:#f87171;">Confirm Account Deletion</h2>
                </div>
                <p style="color:var(--muted);font-size:0.9rem;line-height:1.5;margin-bottom:20px;">
                    Are you absolutely sure you want to delete your account? Please enter your current account password to confirm permanent deletion.
                </p>

                <form method="POST" action="{{ route('profile.destroy') }}" class="auth-form">
                    @csrf
                    @method('delete')

                    <div class="form-field">
                        <label for="delete_password">Your Current Password</label>
                        <div class="password-input">
                            <input id="delete_password" name="password" type="password" placeholder="Enter password to confirm" required autofocus>
                            <button class="password-toggle" type="button" data-password-toggle="delete_password" aria-label="Show password" aria-pressed="false">Show</button>
                        </div>
                        @if ($errors->userDeletion->has('password'))
                            <p class="field-error">{{ $errors->userDeletion->first('password') }}</p>
                        @endif
                    </div>

                    <div class="modal-actions">
                        <button type="button" class="button-secondary" onclick="document.getElementById('delete-modal').style.display='none'">
                            Cancel
                        </button>
                        <button type="submit" class="button-danger">
                            Permanently Delete
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Loading state for forms
            const profileForm = document.getElementById('profile-update-form');
            if (profileForm) {
                profileForm.addEventListener('submit', () => {
                    const btn = document.getElementById('save-profile-btn');
                    if (btn) {
                        btn.disabled = true;
                        btn.style.opacity = '0.7';
                        btn.innerHTML = '<span class="btn-text">Saving changes...</span>';
                    }
                });
            }

            const passwordForm = document.getElementById('password-update-form');
            if (passwordForm) {
                passwordForm.addEventListener('submit', () => {
                    const btn = document.getElementById('update-password-btn');
                    if (btn) {
                        btn.disabled = true;
                        btn.style.opacity = '0.7';
                        btn.innerHTML = '<span class="btn-text">Updating password...</span>';
                    }
                });
            }
        });
    </script>
</x-app-layout>
