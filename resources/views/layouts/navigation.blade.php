<nav class="topbar app-topbar" aria-label="Main navigation">
    <a class="brand-mark" href="{{ route('dashboard') }}">
        <span class="brand-icon">
            <img src="{{ asset('assets/logo.png') }}" alt="SocialPilot AI logo" width="36" height="36">
        </span>
        <span>SocialPilot AI</span>
    </a>

    <div class="nav-links">
        <a href="{{ route('dashboard') }}" class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">Overview</a>
        <a href="{{ route('brands.index') }}" class="{{ request()->routeIs('brands.*') && !request()->routeIs('brands.content.*') && !request()->routeIs('brands.visual-settings*') ? 'active' : '' }}">My brands</a>
        @if(request()->route('brand'))
            <a href="{{ route('brands.content.studio', request()->route('brand')) }}" class="{{ request()->routeIs('brands.content.*') ? 'active' : '' }}">Content Studio</a>
        @endif
        <a href="{{ route('media.dashboard') }}" class="{{ request()->routeIs('media.*') || request()->routeIs('brands.visual-settings*') ? 'active' : '' }}">Media Studio</a>
        <a href="{{ route('scheduling.dashboard') }}" class="{{ request()->routeIs('scheduling.*') || request()->routeIs('brands.scheduling.*') ? 'active' : '' }}">Scheduling</a>
        <a href="{{ route('publishing.dashboard') }}" class="{{ request()->routeIs('publishing.*') ? 'active' : '' }}">Publishing</a>
        <a href="{{ route('analytics.dashboard') }}" class="{{ request()->routeIs('analytics.*') ? 'active' : '' }}">Analytics</a>
    </div>

    <div class="nav-actions">
        <span class="muted nav-user-name">{{ Auth::user()->name }}</span>
        <a href="{{ route('profile.edit') }}" class="button-secondary">Profile</a>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="button">Log out</button>
        </form>
    </div>
</nav>
