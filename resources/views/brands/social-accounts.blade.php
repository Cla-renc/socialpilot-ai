<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">Social accounts</p>
                <h1>{{ $brand->name }}</h1>
            </div>
            <div class="page-tools">
                <a href="{{ route('brands.show', $brand) }}" class="button-secondary">Back to brand</a>
            </div>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="card panel" role="status">{{ session('success') }}</div>
    @endif
    @if (session('warning') || session('error'))
        <div class="card panel" role="alert">{{ session('warning') ?? session('error') }}</div>
    @endif

    <section class="content-grid">
        @foreach ($providers as $provider)
            <article class="card panel">
                <div class="line-item">
                    <h2><span class="social-provider-icon" aria-hidden="true">{{ $provider['icon'] }}</span> {{ $provider['name'] }}</h2>
                    <span class="badge badge--{{ $provider['configured'] ? 'active' : 'pending' }}">
                        {{ $provider['configured'] ? 'Ready to authorize' : 'Configuration required' }}
                    </span>
                </div>
                <p class="muted">
                    @if (! $provider['configured'])
                        Not connected. Add this provider's developer app credentials and callback URL to enable authorization.
                    @else
                        Authorization is available. The provider may still require app review and requested permissions.
                    @endif
                </p>
                @if ($provider['configured'])
                    @php
                        $providerAccounts = $accounts->where('provider', $provider['provider']);
                        $retryNeeded = $providerAccounts->contains(fn ($account) => in_array($account->status, ['connection_error', 'token_expired'], true));
                    @endphp
                    <a class="button-primary" href="{{ route('brands.social-accounts.connect', [$brand, $provider['provider']]) }}">
                        {{ $retryNeeded ? 'Retry connection' : ($providerAccounts->isNotEmpty() ? 'Connect another account' : 'Connect account') }}
                    </a>
                @endif
            </article>
        @endforeach
    </section>

    <section class="card panel" style="margin-top:1.25rem;">
        <h2>Accounts associated with {{ $brand->name }}</h2>
        @forelse ($accounts as $account)
            <article class="line-item social-account-row">
                <div>
                    <strong>
                        <span class="social-provider-icon" aria-hidden="true">{{ ['facebook' => 'f', 'instagram' => '◎', 'tiktok' => '♪', 'youtube' => '▶'][$account->provider] ?? '•' }}</span>
                        {{ $account->display_name }}
                    </strong>
                    <a class="button-secondary" href="{{ route('brands.social-accounts.show', [$brand, $account]) }}">Details</a>
                    <div class="muted">
                        {{ ucfirst($account->provider) }} · {{ $account->account_type }}
                        · ID {{ $account->provider_account_id }}
                        @if ($account->metadata['username'] ?? null)
                            · {{ '@'.$account->metadata['username'] }}
                        @endif
                        · <span class="badge badge--{{ $account->status === 'connected' ? 'active' : 'pending' }}">{{ str_replace('_', ' ', ucfirst($account->status)) }}</span>
                    </div>
                    <div class="muted">
                        Connected {{ isset($account->metadata['connected_at']) ? \Illuminate\Support\Carbon::parse($account->metadata['connected_at'])->format('M j, Y H:i') : ($account->created_at?->format('M j, Y') ?? 'date unavailable') }}
                        · Last synced {{ isset($account->metadata['last_synced_at']) ? \Illuminate\Support\Carbon::parse($account->metadata['last_synced_at'])->format('M j, Y H:i') : 'Never' }}
                        · Token expires {{ $account->token?->expires_at?->format('M j, Y H:i') ?? 'Not provided by provider' }}
                    </div>
                </div>
                <div class="page-tools">
                    @if ($account->status !== 'disconnected')
                        <form method="POST" action="{{ route('brands.social-accounts.check', [$brand, $account]) }}">
                            @csrf
                            <button class="button-secondary" type="submit">Check / refresh</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('brands.social-accounts.destroy', [$brand, $account]) }}" onsubmit="return confirm('Disconnect this account?');">
                        @csrf
                        @method('DELETE')
                        <button class="button-secondary" type="submit">Disconnect</button>
                    </form>
                </div>
            </article>
        @empty
            <p class="muted">No social accounts are connected to this brand.</p>
        @endforelse
    </section>
</x-app-layout>
