<x-app-layout>
    <x-slot name="header">
        <div class="page-header">
            <div>
                <p class="eyebrow">Operations overview</p>
                <h1>Dashboard</h1>
                <p class="muted">{{ $selectedBrand ? $selectedBrand->name : 'All brands' }}</p>
            </div>
            <div class="page-tools">
                <a href="{{ route('brands.create') }}" class="button-secondary">New brand</a>
                <a href="{{ route('brands.index') }}" class="button">Manage brands</a>
            </div>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="card panel" role="status" style="margin-bottom:1rem;">{{ session('success') }}</div>
    @endif

    <section class="card panel" style="margin-bottom:1.25rem;">
        <form method="GET" action="{{ route('dashboard') }}" style="display:flex;align-items:end;gap:12px;flex-wrap:wrap;">
            <label style="min-width:min(100%, 280px);">
                <span class="label">Dashboard brand</span>
                <select name="brand_id" aria-label="Filter dashboard by brand">
                    <option value="">All brands</option>
                    @foreach ($brands as $brand)
                        <option value="{{ $brand['id'] }}" @selected($selectedBrand?->id === $brand['id'])>{{ $brand['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <button type="submit" class="button">Apply filter</button>
            <button type="submit" class="button-secondary" formnovalidate>Refresh dashboard</button>
        </form>
    </section>

    <section class="dashboard-grid" aria-label="Operational statistics">
        @foreach ([
            'Total brands' => $metrics['brands'],
            'Active brands' => $metrics['active_brands'],
            'Connected accounts' => $metrics['connected_accounts'],
            'Published posts' => $metrics['published_posts'],
            'Scheduled posts' => $metrics['scheduled_posts'],
            'Draft posts' => $metrics['draft_posts'],
            'Failed posts' => $metrics['failed_posts'],
            'AI-generated content' => $metrics['ai_generated_content'],
            'Engagements' => $metrics['engagements'],
        ] as $label => $value)
            <article class="card stat-card">
                <span class="label">{{ $label }}</span>
                <div class="value">{{ is_null($value) ? 'Unavailable' : number_format($value) }}</div>
                @if ($label === 'AI-generated content' && ! $metrics['ai_generated_content_available'])
                    <div class="meta">Some legacy records have unknown generation source</div>
                @elseif ($label === 'Engagements' && ! $metrics['engagement_available'])
                    <div class="meta">No analytics records available</div>
                @endif
            </article>
        @endforeach
    </section>

    <section class="content-grid" style="margin-top:1.25rem;">
        <article class="card panel">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
                <h2>Recent content activity</h2>
                <span class="muted">{{ count($activity) }} items</span>
            </div>
            @forelse ($activity as $item)
                <div class="line-item">
                    <div>
                        <strong>{{ $item['title'] }}</strong>
                        <p class="muted" style="margin:.35rem 0;">{{ $item['preview'] }}</p>
                        <span class="muted">{{ $item['brand_name'] }} · {{ $item['platform'] ?? 'Platform not set' }} · {{ $item['format'] }}</span>
                    </div>
                    <div style="text-align:right;">
                        <span class="badge badge--pending">{{ \Illuminate\Support\Str::headline($item['status']) }}</span>
                        @if ($item['scheduled_for'])
                            <div class="muted" style="margin-top:.4rem;">{{ \Illuminate\Support\Carbon::parse($item['scheduled_for'])->format('M j, g:i A') }}</div>
                        @endif
                        <a href="{{ $item['action_url'] }}" class="button-secondary" style="margin-top:.45rem;">View brand</a>
                    </div>
                </div>
            @empty
                <p class="muted">No content activity for this selection yet.</p>
            @endforelse
        </article>

        <article class="card panel">
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;">
                <h2>Upcoming publishing</h2>
                <a href="{{ route('dashboard.calendar', request()->only('brand_id')) }}" class="button-secondary">Full calendar</a>
            </div>
            @forelse ($calendar as $item)
                <div class="line-item">
                    <div>
                        <strong>{{ $item['title'] }}</strong>
                        <div class="muted">{{ $item['brand_name'] }} · {{ ucfirst($item['platform']) }}</div>
                    </div>
                    <div style="text-align:right;">
                        <span class="badge badge--pending">{{ \Illuminate\Support\Str::headline($item['status']) }}</span>
                        <div class="muted">{{ \Illuminate\Support\Carbon::parse($item['scheduled_for'])->format('M j, g:i A') }}</div>
                    </div>
                </div>
            @empty
                <p class="muted">No scheduled posts in the next 14 days.</p>
            @endforelse
        </article>
    </section>

    <section class="content-grid" style="margin-top:1.25rem;">
        <article class="card panel">
            <h2>Social accounts</h2>
            @forelse ($accounts as $account)
                <div class="line-item">
                    <div>
                        <strong>{{ $account['display_name'] }}</strong>
                        <div class="muted">{{ ucfirst($account['provider']) }} · {{ $account['brand_name'] ?? 'No brand assigned' }}</div>
                        <div class="muted">Connected {{ $account['connected_at'] ? \Illuminate\Support\Carbon::parse($account['connected_at'])->format('M j, Y') : 'date unavailable' }} · Last sync {{ $account['last_synced_at'] ? \Illuminate\Support\Carbon::parse($account['last_synced_at'])->diffForHumans() : 'unavailable' }}</div>
                        @if ($account['token_expires_at'])
                            <div class="muted">Token expires {{ \Illuminate\Support\Carbon::parse($account['token_expires_at'])->diffForHumans() }}</div>
                        @endif
                    </div>
                    <span class="badge {{ $account['status'] === 'connected' ? 'badge--active' : 'badge--pending' }}">{{ \Illuminate\Support\Str::headline($account['status']) }}</span>
                </div>
            @empty
                <p class="muted">No social accounts are associated with this selection.</p>
            @endforelse
            @foreach ($unconnectedProviders as $provider)
                <div class="line-item">
                    <div>
                        <strong>{{ $provider['name'] }}</strong>
                        <div class="muted">No account connected</div>
                    </div>
                    <span class="badge badge--pending">{{ $provider['configured'] ? 'Not connected' : 'Configuration required' }}</span>
                </div>
            @endforeach
            @if ($selectedBrand)
                <a href="{{ route('brands.social-accounts.index', $selectedBrand) }}" class="button-secondary">Manage connections</a>
            @else
                <a href="{{ route('brands.index') }}" class="button-secondary">Choose a brand to connect</a>
            @endif
        </article>

        <article class="card panel">
            <h2>AI agent activity</h2>
            @foreach ($agents as $agent)
                <div class="line-item">
                    <span>{{ $agent['name'] }}</span>
                    <span class="badge badge--pending">{{ \Illuminate\Support\Str::headline($agent['status']) }}</span>
                </div>
            @endforeach
            <p class="muted" style="margin-top:1rem;">Only persisted agent execution records are shown. Unimplemented agents remain waiting for configuration.</p>
        </article>
    </section>

    <section class="content-grid" style="margin-top:1.25rem;">
        <article class="card panel">
            <h2>Notifications</h2>
            @forelse ($notifications as $notification)
                @php($data = $notification->data ?? [])
                <div class="line-item" style="{{ $notification->read_at ? 'opacity:.72;' : '' }}">
                    <div>
                        <strong>{{ $data['title'] ?? 'Notification' }}</strong>
                        <div>{{ $data['description'] ?? '' }}</div>
                        <div class="muted">{{ $notification->created_at->diffForHumans() }} · {{ $notification->read_at ? 'Read' : 'Unread' }}</div>
                    </div>
                    <div style="display:flex;gap:.5rem;align-items:center;">
                        @if (! empty($data['action_url']) && str_starts_with($data['action_url'], '/') && ! str_starts_with($data['action_url'], '//'))
                            <a class="button-secondary" href="{{ $data['action_url'] }}">Open</a>
                        @endif
                        @if (! $notification->read_at)
                            <form method="POST" action="{{ route('notifications.read', $notification->id) }}">
                                @csrf
                                <button class="button-secondary" type="submit">Mark read</button>
                            </form>
                        @endif
                    </div>
                </div>
            @empty
                <p class="muted">No notifications yet.</p>
            @endforelse
        </article>

        <article class="card panel">
            <h2>Quick actions</h2>
            <div style="display:flex;gap:.6rem;flex-wrap:wrap;">
                <a href="{{ route('brands.create') }}" class="button">Create a brand</a>
                @if ($selectedBrand)
                    <a href="{{ route('brands.social-accounts.index', $selectedBrand) }}" class="button-secondary">Connect a social account</a>
                @else
                    <a href="{{ route('brands.index') }}" class="button-secondary">Choose brand to connect</a>
                @endif
                <a href="{{ route('dashboard.calendar', request()->only('brand_id')) }}" class="button-secondary">Publishing calendar</a>
                @if ($selectedBrand)
                    <a href="{{ route('brands.trends.index', $selectedBrand) }}" class="button-secondary">Trend intelligence</a>
                @else
                    <a href="{{ route('brands.index') }}" class="button-secondary">Select a brand for trend research</a>
                @endif
                <a href="{{ route('media.dashboard') }}" class="button-secondary">Media Studio</a>
                <span class="button-secondary" aria-disabled="true">Autopilot · future phase</span>
                <span class="button-secondary" aria-disabled="true">Web content generation · unavailable</span>
            </div>
        </article>
    </section>
</x-app-layout>
