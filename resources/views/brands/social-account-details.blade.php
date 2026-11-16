<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">{{ ucfirst($socialAccount->provider) }} account</p>
                <h1>{{ $socialAccount->display_name }}</h1>
            </div>
            <div class="page-tools">
                <a href="{{ route('brands.social-accounts.index', $brand) }}" class="button-secondary">All social accounts</a>
                <a href="{{ route('brands.show', $brand) }}" class="button-secondary">Brand details</a>
            </div>
        </div>
    </x-slot>

    <section class="card panel" style="max-width:800px;">
        <div class="list">
            <div class="line-item"><span class="muted">Platform</span><strong>{{ ucfirst($socialAccount->provider) }}</strong></div>
            <div class="line-item"><span class="muted">Account name</span><strong>{{ $socialAccount->display_name }}</strong></div>
            <div class="line-item"><span class="muted">Username / channel ID</span><strong>{{ $socialAccount->metadata['username'] ?? $socialAccount->provider_account_id }}</strong></div>
            <div class="line-item"><span class="muted">Brand</span><strong>{{ $brand->name }}</strong></div>
            <div class="line-item"><span class="muted">Status</span><strong>{{ str_replace('_', ' ', ucfirst($socialAccount->status)) }}</strong></div>
            <div class="line-item"><span class="muted">Connected</span><strong>{{ isset($socialAccount->metadata['connected_at']) ? \Illuminate\Support\Carbon::parse($socialAccount->metadata['connected_at'])->format('M j, Y H:i') : ($socialAccount->created_at?->format('M j, Y H:i') ?? 'Unavailable') }}</strong></div>
            <div class="line-item"><span class="muted">Last synchronized</span><strong>{{ isset($socialAccount->metadata['last_synced_at']) ? \Illuminate\Support\Carbon::parse($socialAccount->metadata['last_synced_at'])->format('M j, Y H:i') : 'Never' }}</strong></div>
            <div class="line-item"><span class="muted">Token expires</span><strong>{{ $socialAccount->token?->expires_at?->format('M j, Y H:i') ?? 'Not provided by provider' }}</strong></div>
        </div>
        @if (session('success') || session('warning'))
            <p role="{{ session('success') ? 'status' : 'alert' }}">{{ session('success') ?? session('warning') }}</p>
        @endif
        <div class="page-tools" style="margin-top:1.25rem;">
            @if ($socialAccount->status !== 'disconnected')
                <form method="POST" action="{{ route('brands.social-accounts.check', [$brand, $socialAccount]) }}">
                    @csrf
                    <button class="button-secondary" type="submit">Check / refresh</button>
                </form>
            @endif
            <form method="POST" action="{{ route('brands.social-accounts.destroy', [$brand, $socialAccount]) }}" onsubmit="return confirm('Disconnect this account?');">
                @csrf
                @method('DELETE')
                <button class="button-secondary" type="submit">Disconnect</button>
            </form>
        </div>
    </section>
</x-app-layout>
